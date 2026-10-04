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
                'display'  => __('Every 5 minutes (WP Wand)', 'ai-content-generation'),
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
            $this->lastError = UsageLimits::limit_reached_message('automation');
            Schedules::update($id, $patch);
            return 0;
        }
        // Every tier counts RUNS, so the remaining allowance is never a post budget. The run's post
        // volume is the schedule's own count, capped per tier by Schedules::max_count() — 5 on
        // free, 10 / 20 on Solo / Growth, no ceiling on Agency. Pro used to clamp to its remaining
        // post allowance here (until 2026-09-13).
        $count = Schedules::clamp_count($count);

        $titles = $this->titles_for($schedule, $count);

        if (empty($titles)) {
            // If the AI title generation failed (prompt mode), surface the real model error instead
            // of the generic "list finished" message.
            if ($this->lastError === '' && self::list_finished($schedule)) {
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
            // The schedule has had a Keywords field since 2026-08-31 and this array never carried
            // it, so the field saved, showed itself back, and reached no prompt at all.
            'keyword'     => (string) ($schedule['keyword'] ?? ''),
            'language'    => (string) $schedule['language'],
            'word_count'  => $word_count,
            'toc_include' => (bool) $schedule['toc_include'],
            'faq_include' => (bool) $schedule['faq_include'],
            // Markers that make JobRunner auto-create the WP post on completion.
            'auto_status' => (string) $schedule['post_status'],
            'auto_author' => (int) $schedule['author'],
            'schedule_id' => $id,
            // The house style. It reached the Write with AI button and nothing else, so a
            // scheduled post was written to no rules at all — no ceiling on exclamation marks, no
            // named list of words to avoid, no "never cite a study you cannot name", and none of
            // the About your business text the user had already typed into Settings. A schedule
            // writes unattended, which is the argument for holding it to the rules, not against.
            //
            // `HouseStyle::rules()` drops the business and audience blocks itself when they are
            // blank, so an install that has typed neither still gets the rules and nothing empty
            // is quoted into the prompt.
            'style_rules' => \WPWand\Generation\HouseStyle::rules(\WPWand\Generation\HouseStyle::owner_context()),
        ];

        // enqueue() drops any title whose post row failed to insert, so bill and report on what it
        // actually queued. Counting $titles here charged the user for posts that were never created.
        $queued = (new JobRunner())->enqueue($titles, $settings);
        if ($queued === []) {
            $this->lastError = __('Could not add these posts to the queue. Try again.', 'ai-content-generation');
            return 0;
        }

        UsageLimits::consume_automation_run(); // one unit per run, every tier
        $this->ensure_drainer();

        Schedules::update($id, $patch);

        return count($queued);
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

    /**
     * Has this schedule run out of topics — cursor past the last one, with looping off?
     *
     * ONE function, two callers: run_schedule() stops the schedule with it, and AutomationController
     * puts the same answer in the row. Working it out twice is how a row ends up claiming a schedule
     * will run tomorrow when the runner has already decided it never will.
     *
     * Prompt-mode schedules invent their titles every run, so they are never finished; a looping list
     * starts over instead of ending. An empty list counts as finished — there is nothing left to write.
     *
     * @param array<string, mixed> $schedule
     */
    public static function list_finished(array $schedule): bool
    {
        if (($schedule['mode'] ?? 'list') !== 'list' || !empty($schedule['loop'])) {
            return false;
        }
        $total = count(array_values(array_filter((array) ($schedule['topics'] ?? []))));

        return $total === 0 || (int) ($schedule['cursor'] ?? 0) >= $total;
    }

    /**
     * The topic the next run will use, or '' when there isn't one (list finished, or prompt mode
     * where the titles do not exist yet). Reads the cursor exactly the way titles_for() does.
     *
     * @param array<string, mixed> $schedule
     */
    public static function next_topic(array $schedule): string
    {
        if (($schedule['mode'] ?? 'list') !== 'list') {
            return '';
        }

        $topics = array_values(array_filter((array) ($schedule['topics'] ?? [])));
        $total  = count($topics);
        if ($total === 0) {
            return '';
        }

        $cursor = max(0, (int) ($schedule['cursor'] ?? 0));
        if ($cursor >= $total) {
            if (empty($schedule['loop'])) {
                return '';
            }
            $cursor %= $total;
        }

        return (string) $topics[$cursor];
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

        $tone    = (string) $schedule['tone'];
        $keyword = trim((string) ($schedule['keyword'] ?? ''));
        // "Specific, engaging" got "Master the Art of Brewing Your Perfect Cup of Coffee at Home" —
        // the register the house style bans in the body, on the one line a reader sees first. The
        // title request carried no rules at all, and asked for "exactly 1 … titles".
        $ask     = $count === 1 ? 'one blog post title' : "exactly {$count} blog post titles";
        // "Each one" on a request for one title got five back (simulated 2026-10-02).
        $each    = $count === 1 ? 'It says' : 'Each one says';
        $prompt  = "Suggest {$ask} about \"{$subject}\". "
            . $each . ' plainly what the post covers, the way a person would say it out loud — '
            . 'specific, not clever. Do not use "Ultimate", "Master", "Unlock", "Secrets", "Art of" or '
            . '"Everything You Need to Know", and do not put a slogan after a colon.'
            . ($keyword !== '' ? " Where it fits naturally, work one of these keywords in: {$keyword}." : '')
            . ($count === 1
                ? ' Return ONLY the title on one line — no number, no intro, no description, no quotation marks.'
                : ' Return ONLY a numbered list of the titles — no intro, no descriptions, no quotation marks.')
            . ($tone !== '' ? " Tone: {$tone}." : '');

        $res = \WPWand\Generation\Generator::generate($prompt, 1, ['max_tokens' => 400]);
        if (is_object($res) && isset($res->error)) {
            // Bubble up the real provider message (e.g. model rate-limited/down) for the UI.
            $this->lastError = ErrorFormatter::humanize($res->error, __('Couldn’t come up with titles.', 'ai-content-generation'));
            return [];
        }
        if (!is_object($res) || !isset($res->choices[0])) {
            $this->lastError = __('The AI sent back no title ideas. Try again.', 'ai-content-generation');
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
