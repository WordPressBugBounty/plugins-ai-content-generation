<?php

namespace WPWand\Automation;

use WPWand\Generation\ErrorFormatter;
use WPWand\Generation\JobRunner;
use WPWand\Generation\UsageLimits;

/**
 * Phase 3 — scheduled, recurring post automation.
 *
 * A lightweight WP-Cron tick (every 5 minutes) walks the saved {@see Schedules}; any schedule
 * whose next_run has passed is run: it picks the titles for this run (from a fixed list, or by
 * asking the AI for ideas about a subject), enqueues them on the existing step-based generation
 * queue ({@see JobRunner}) tagged with the target post status, and advances next_run. The queue
 * is drained unattended by the engine's WP-Cron drainer, and JobRunner auto-creates the WP post
 * when a job carries an auto_status — so finished posts appear as drafts/published with no UI open.
 */
final class AutomationRunner
{
    public const TICK_HOOK = 'wpwand_automation_tick';
    private const SCHEDULE  = 'wpwand_5min';
    private const LOCK      = 'wpwand_automation_lock';
    private const LOCK_TTL  = 290;

    /** Why the last run produced no posts (model error / limit), for the "Run now" feedback. */
    private string $lastError = '';

    public function last_error(): string
    {
        return $this->lastError;
    }

    public function register(): void
    {
        add_filter('cron_schedules', [$this, 'add_schedule']);
        add_action(self::TICK_HOOK, [$this, 'tick']);
        add_action('init', [$this, 'ensure_scheduled']);
    }

    /** @param array<string, array{interval:int, display:string}> $schedules */
    public function add_schedule(array $schedules): array
    {
        if (!isset($schedules[self::SCHEDULE])) {
            $schedules[self::SCHEDULE] = [
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display'  => __('Every 5 minutes (WP Wand)', 'wp-wand-pro'),
            ];
        }
        return $schedules;
    }

    public function ensure_scheduled(): void
    {
        if (!wp_next_scheduled(self::TICK_HOOK)) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, self::SCHEDULE, self::TICK_HOOK);
        }
    }

    /** Run every due schedule. Serialized with a transient lock against overlapping cron runs. */
    public function tick(): void
    {
        if (get_transient(self::LOCK)) {
            return;
        }
        set_transient(self::LOCK, 1, self::LOCK_TTL);

        try {
            $now = time();
            foreach (Schedules::all() as $schedule) {
                if (empty($schedule['enabled'])) {
                    continue;
                }
                if ((int) ($schedule['next_run'] ?? 0) > $now) {
                    continue;
                }
                $this->run_schedule($schedule);
            }
        } finally {
            delete_transient(self::LOCK);
        }
    }

    /**
     * Generate this run's posts for one schedule and advance its cadence.
     *
     * @param array<string, mixed> $schedule
     * @return int number of posts queued
     */
    public function run_schedule(array $schedule): int
    {
        $id     = (string) $schedule['id'];
        $count  = max(1, (int) $schedule['count']);

        $patch = [
            'last_run' => time(),
            'next_run' => Schedules::next_run_from_now((string) $schedule['frequency']),
            'runs'     => (int) ($schedule['runs'] ?? 0) + 1,
        ];

        $this->lastError = '';

        // Respect the monthly automation cap (2× the bulk cap; unlimited for agency). If the cap is
        // already used up, just wait for the next run (the counter resets monthly) — do NOT treat
        // this as the topic list being exhausted, so the schedule stays enabled.
        $remaining = UsageLimits::automation_remaining();
        if ($remaining <= 0) {
            $this->lastError = __('Monthly automation limit reached. Upgrade to a higher plan to generate more.', 'wp-wand-pro');
            Schedules::update($id, $patch);
            return 0;
        }
        $count  = min($count, $remaining);

        $titles = $this->titles_for($schedule, $count);

        if (empty($titles)) {
            // If the AI title generation failed (prompt mode), surface the real model error instead
            // of the generic "list finished" message.
            if ($this->lastError === '' && ($schedule['mode'] ?? 'list') === 'list' && empty($schedule['loop'])) {
                // List exhausted with looping off → stop the schedule rather than spin idle.
                $patch['enabled']  = false;
                $patch['next_run'] = 0;
            }
            Schedules::update($id, $patch);
            return 0;
        }

        // Advance the list cursor (list mode only); merge in any wrap done by titles_for().
        if (($schedule['mode'] ?? 'list') === 'list') {
            $patch['cursor'] = $this->advance_cursor($schedule, count($titles));
        }

        // Single normalize point for the target length: empty string / null / 0 all mean
        // "auto — let the AI decide" (JobRunner treats word_count <= 0 as auto).
        $word_count = max(0, (int) ($schedule['word_count'] ?? 0));

        $settings = [
            'tone'        => (string) $schedule['tone'],
            'language'    => (string) $schedule['language'],
            'word_count'  => $word_count,
            'toc_include' => (bool) $schedule['toc_include'],
            'faq_include' => (bool) $schedule['faq_include'],
            // Markers that make JobRunner auto-create the WP post on completion.
            'auto_status' => (string) $schedule['post_status'],
            'auto_author' => (int) $schedule['author'],
            'schedule_id' => $id,
        ];

        (new JobRunner())->enqueue($titles, $settings);
        UsageLimits::consume_automation(count($titles)); // count against the monthly automation cap
        $this->ensure_drainer();

        Schedules::update($id, $patch);

        return count($titles);
    }

    /**
     * The titles to generate this run.
     *  - list mode:   the next `count` topics starting at the cursor (wrapping if loop is on).
     *  - prompt mode: ask the AI for `count` post-title ideas about the subject.
     *
     * @param array<string, mixed> $schedule
     * @return string[]
     */
    private function titles_for(array $schedule, int $count): array
    {
        if (($schedule['mode'] ?? 'list') === 'prompt') {
            return $this->ai_titles((string) $schedule['subject'], $count, $schedule);
        }

        $topics = array_values(array_filter((array) ($schedule['topics'] ?? [])));
        $total  = count($topics);
        if ($total === 0) {
            return [];
        }

        $cursor = (int) ($schedule['cursor'] ?? 0);
        $loop   = !empty($schedule['loop']);
        $out    = [];

        for ($i = 0; $i < $count; $i++) {
            $idx = $cursor + $i;
            if ($idx >= $total) {
                if (!$loop) {
                    break;
                }
                $idx %= $total;
            }
            $out[] = $topics[$idx];
        }

        return $out;
    }

    /** New cursor after consuming $consumed titles (wraps for looping list schedules). */
    private function advance_cursor(array $schedule, int $consumed): int
    {
        $total = count(array_values(array_filter((array) ($schedule['topics'] ?? []))));
        if ($total === 0) {
            return 0;
        }
        $cursor = (int) ($schedule['cursor'] ?? 0) + $consumed;
        return !empty($schedule['loop']) ? $cursor % $total : min($cursor, $total);
    }

    /**
     * Ask the AI for blog-post title ideas about a subject.
     *
     * @return string[]
     */
    private function ai_titles(string $subject, int $count, array $schedule): array
    {
        if ($subject === '' || !class_exists('WPWand\Generation\Generator')) {
            return [];
        }

        $tone   = (string) $schedule['tone'];
        $prompt = "Suggest exactly {$count} specific, engaging blog post titles about \"{$subject}\". "
            . 'Return ONLY a numbered list of the titles — no intro, no descriptions, no quotation marks.'
            . ($tone !== '' ? " Tone: {$tone}." : '');

        $res = \WPWand\Generation\Generator::generate($prompt, 1, ['max_tokens' => 400]);
        if (is_object($res) && isset($res->error)) {
            // Bubble up the real provider message (e.g. model rate-limited/down) for the UI.
            $this->lastError = ErrorFormatter::humanize($res->error, __('Could not generate titles.', 'wp-wand-pro'));
            return [];
        }
        if (!is_object($res) || !isset($res->choices[0])) {
            $this->lastError = __('The AI returned no title ideas. Please try again.', 'wp-wand-pro');
            return [];
        }
        $choice = $res->choices[0];
        $text   = isset($choice->message->content) ? (string) $choice->message->content
            : (isset($choice->text) ? (string) $choice->text : '');

        $titles = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = preg_replace('/^\s*(\d+[\.\)]|[-*•])\s*/', '', trim($line));
            $line = trim((string) $line, " \t\"'*#");
            if ($line !== '' && mb_strlen($line) <= 160) {
                $titles[] = $line;
            }
        }

        return array_slice($titles, 0, $count);
    }

    /** Make sure the engine's WP-Cron drainer is scheduled so queued jobs process unattended. */
    private function ensure_drainer(): void
    {
        if (!wp_next_scheduled('wpwand_gen_cron_tick')) {
            wp_schedule_event(time() + 30, 'wpwand_minutely', 'wpwand_gen_cron_tick');
        }
    }
}
