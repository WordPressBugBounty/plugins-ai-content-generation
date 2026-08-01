<?php

namespace WPWand\Generation;

/**
 * Step-based generation engine (v2).
 *
 * A post is generated as a JOB with small steps so no single HTTP request is long enough to
 * hit a shared-host/gateway timeout — the failure mode that killed long (4000+ word) posts on
 * the old Action-Scheduler path:
 *
 *   step 0            → outline   (ask the AI for N section headings; 1 short call)
 *   step 1..N         → sections  (one short call per heading, ~600 words each)
 *   step N+1          → assemble  (local string work: title + optional TOC + sections; no AI)
 *
 * Short posts skip the outline and write in a single block. Every step persists progress to
 * wpwand_gen_jobs, and the growing content is mirrored into wpwand_generated_post so the list
 * shows live progress. Drivers (browser heartbeat / WP-Cron / Action Scheduler) all advance the
 * same queue via tick(); only the trigger differs.
 *
 * @see generation-engine-v2 (memory)
 */
final class JobRunner
{
    private const LOCK = 'wpwand_tick_lock';
    private const LOCK_TTL = 120;          // seconds a single step may hold the queue
    private const PER_SECTION = 600;       // target words per section
    private const MAX_SECTIONS = 12;
    private const MAX_ATTEMPTS = 3;
    private const SINGLE = '__single__';   // sentinel: write the whole post in one block

    private function jobs_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'wpwand_gen_jobs';
    }

    private function posts_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'wpwand_generated_post';
    }

    /**
     * Create one post row + one job per title. No scheduling here — a driver advances the queue.
     *
     * @param string[]             $titles
     * @param array<string, mixed> $settings
     * @return int[] generated_post row ids
     */
    public function enqueue(array $titles, array $settings): array
    {
        global $wpdb;
        $ids = [];

        foreach ($titles as $title) {
            $title = trim((string) $title);
            if ($title === '') {
                continue;
            }

            $wpdb->insert($this->posts_table(), [
                'title'   => $title,
                'content' => '',
                'post_id' => 0,
                'status'  => 'pending',
            ]);
            $row_id = (int) $wpdb->insert_id;

            $wpdb->insert($this->jobs_table(), [
                'row_id'   => $row_id,
                'title'    => $title,
                'settings' => wp_json_encode($settings),
                'sections' => wp_json_encode([]),
                'step'     => 0,
                'status'   => 'pending',
            ]);

            $ids[] = $row_id;
        }

        return $ids;
    }

    /** True while any job (optionally limited to a scope) still needs work. */
    public function is_running(string $scope = 'all'): bool
    {
        global $wpdb;
        $w = $this->scope_where($scope);
        $n = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->jobs_table()} WHERE status IN ('pending','processing'){$w}" // phpcs:ignore
        );
        return $n > 0;
    }

    /** Pending/processing vs total — for progress display. */
    /**
     * Queue progress. $scope separates the two job sources that share this table: 'automation'
     * (jobs carrying a schedule_id) vs 'bulk' (everything else) vs 'all' — so the Bulk page and the
     * Automation page each only reflect their OWN jobs.
     */
    public function snapshot(string $scope = 'all'): array
    {
        global $wpdb;
        $t   = $this->jobs_table();
        $w   = $this->scope_where($scope);
        $rem = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t} WHERE status IN ('pending','processing'){$w}"); // phpcs:ignore
        $out = [
            'running'   => $rem > 0,
            'remaining' => $rem,
            'done'      => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t} WHERE status='done'{$w}"), // phpcs:ignore
            'failed'    => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t} WHERE status='failed'{$w}"), // phpcs:ignore
        ];

        // For the Automation UI: which schedules actually have work in flight, so only those rows
        // show a "generating" state (not every active schedule).
        if ($scope === 'automation') {
            $rows = $wpdb->get_col("SELECT settings FROM {$t} WHERE status IN ('pending','processing'){$w}"); // phpcs:ignore
            $ids  = [];
            foreach ((array) $rows as $json) {
                if (preg_match('/"schedule_id":"([^"]+)"/', (string) $json, $m)) {
                    $ids[$m[1]] = true;
                }
            }
            $out['active_ids'] = array_keys($ids);
        }

        return $out;
    }

    /** WHERE fragment that limits a query to a job source. Automation jobs carry a schedule_id. */
    private function scope_where(string $scope): string
    {
        if ($scope === 'automation') {
            return " AND settings LIKE '%\"schedule_id\"%'";
        }
        if ($scope === 'bulk') {
            return " AND settings NOT LIKE '%\"schedule_id\"%'";
        }
        return '';
    }

    /**
     * Advance the queue by ONE step (one short AI call, or a local assemble). Serialized with a
     * transient lock so concurrent drivers/tabs never double-process. Returns a progress snapshot.
     */
    public function tick(): array
    {
        if (get_transient(self::LOCK)) {
            return ['busy' => true] + $this->snapshot();
        }
        set_transient(self::LOCK, 1, self::LOCK_TTL);

        try {
            global $wpdb;
            $job = $wpdb->get_row(
                "SELECT * FROM {$this->jobs_table()} WHERE status IN ('pending','processing') ORDER BY id ASC LIMIT 1", // phpcs:ignore
                ARRAY_A
            );

            if (!$job) {
                return ['idle' => true] + $this->snapshot();
            }

            try {
                $this->step($job);
            } catch (\Throwable $e) {
                $this->fail($job, $e->getMessage());
            }
        } finally {
            delete_transient(self::LOCK);
        }

        return ['ticked' => true] + $this->snapshot();
    }

    /** Execute the single next step of a job and persist it. */
    private function step(array $job): void
    {
        $settings = json_decode((string) $job['settings'], true) ?: [];
        $outline  = $job['outline'] !== null ? (json_decode((string) $job['outline'], true) ?: null) : null;
        $sections = json_decode((string) $job['sections'], true) ?: [];
        $step     = (int) $job['step'];

        // ---- Step 0: build the outline (or decide single-block) --------------------------
        if ($outline === null) {
            $outline = $this->build_outline($job['title'], $settings);
            $total   = count($outline) + 1; // sections + assemble
            $this->update_job($job['id'], [
                'outline'     => wp_json_encode($outline),
                'total_steps' => $total,
                'step'        => 1,
                'status'      => 'processing',
            ]);
            return;
        }

        $count = count($outline);

        // ---- Steps 1..N: one section per tick --------------------------------------------
        if ($step >= 1 && $step <= $count) {
            $heading = (string) $outline[$step - 1];
            $body    = $this->write_section($job['title'], $heading, $settings, $count);

            $sections[] = $heading === self::SINGLE
                ? $body
                : "## {$heading}\n\n{$body}";

            $this->update_job($job['id'], [
                'sections' => wp_json_encode($sections),
                'step'     => $step + 1,
                'status'   => 'processing',
            ]);

            // Mirror partial content so the list shows it growing.
            $this->update_post($job['row_id'], $this->assemble($job['title'], $outline, $sections, $settings), 'pending');
            return;
        }

        // ---- Final: assemble + finalize --------------------------------------------------
        $content = $this->assemble($job['title'], $outline, $sections, $settings);
        $this->update_post($job['row_id'], $content, 'done');
        $this->update_job($job['id'], ['status' => 'done', 'step' => $step]);
        update_option('wpwand_pgc_task_completed', (int) get_option('wpwand_pgc_task_completed', 0) + 1);

        // Scheduled-automation jobs carry a target status and auto-create the WP post (unattended).
        // Manual bulk jobs omit auto_status and stay in the list until the user clicks Approve.
        if (!empty($settings['auto_status'])) {
            $this->finalize_post((int) $job['row_id'], (string) $job['title'], $content, $settings);
        }

        do_action('wpwand_job_completed', (int) $job['row_id'], $settings, $content);
    }

    /**
     * Insert the generated content as a real WP post — the unattended equivalent of the manual
     * "Approve" action (BulkController::approve). Used by scheduled automation.
     */
    private function finalize_post(int $row_id, string $title, string $content, array $settings): void
    {
        $status  = (string) $settings['auto_status'];
        $allowed = ['draft', 'pending', 'publish'];
        if (!in_array($status, $allowed, true)) {
            $status = 'draft';
        }

        $author = (int) ($settings['auto_author'] ?? 0);
        if ($author <= 0) {
            $author = 1;
        }

        $post_id = wp_insert_post([
            'post_title'   => $title,
            'post_content' => Markdown::to_post_content($content),
            'post_status'  => $status,
            'post_author'  => $author,
            'post_type'    => 'post',
        ], true);

        if (is_int($post_id) && $post_id > 0) {
            global $wpdb;
            $wpdb->update($this->posts_table(), ['post_id' => $post_id], ['id' => $row_id]);

            // Tag the post with the schedule that produced it, so the Automation UI can list
            // each schedule's posts.
            if (!empty($settings['schedule_id'])) {
                update_post_meta($post_id, '_wpwand_schedule_id', (string) $settings['schedule_id']);
            }
        }
    }

    /**
     * Decide the section headings. Short posts → a single block (no AI outline call); long posts
     * → ask the AI for N headings. FAQ (if requested) becomes an extra section.
     *
     * @return string[]
     */
    private function build_outline(string $title, array $settings): array
    {
        $words = (int) ($settings['word_count'] ?? 0);

        if ($words > 0 && $words <= 1200) {
            return [self::SINGLE];
        }

        $target   = $words > 0 ? $words : 800;
        $n        = max(3, min(self::MAX_SECTIONS, (int) ceil($target / self::PER_SECTION)));
        $tone     = (string) ($settings['tone'] ?? '');
        $keyword  = (string) ($settings['keyword'] ?? '');

        if ($words <= 0) {
            return [self::SINGLE];
        }

        $prompt = "Create a blog post outline for the title \"{$title}\".\n"
            . "Return ONLY a numbered list of exactly {$n} concise H2 section headings — no intro, "
            . "no descriptions, no conclusion label. Make them specific and non-overlapping."
            . ($keyword !== '' ? "\nRelevant keywords: {$keyword}." : '')
            . ($tone !== '' ? "\nTone: {$tone}." : '');

        $text     = $this->ai_text($prompt, $settings, 600);
        $headings = $this->parse_outline($text);

        if (count($headings) < 2) {
            return [self::SINGLE];
        }

        if (!empty($settings['faq_include'])) {
            $headings[] = 'Frequently Asked Questions (FAQ)';
        }

        return array_slice($headings, 0, self::MAX_SECTIONS + 1);
    }

    /** Generate one section's body (or the whole post for the single-block case). */
    private function write_section(string $title, string $heading, array $settings, int $count): string
    {
        $tone    = (string) ($settings['tone'] ?? '');
        $keyword = (string) ($settings['keyword'] ?? '');
        $toneTxt = $tone !== '' ? " Tone: {$tone}." : '';
        $kwTxt   = $keyword !== '' ? " Naturally include these keywords where relevant: {$keyword}." : '';

        if ($heading === self::SINGLE) {
            $words  = (int) ($settings['word_count'] ?? 0);
            $target = $words > 0 ? $words : 800;
            $prompt = "Write a complete, original blog post titled \"{$title}\" of about {$target} words in "
                . "Markdown. Use ## headings for sections. Do not include the title as an H1.{$toneTxt}{$kwTxt}";
            return $this->ai_text($prompt, $settings, $this->tokens_for($target));
        }

        if (stripos($heading, 'FAQ') !== false || stripos($heading, 'Frequently Asked') !== false) {
            $prompt = "Write a FAQ section for a blog post titled \"{$title}\". Provide 4-6 concise "
                . "question-and-answer pairs in Markdown (bold question, then answer). Do NOT repeat the "
                . "section heading.{$toneTxt}{$kwTxt}";
            return $this->ai_text($prompt, $settings, 800);
        }

        $prompt = "Write ONLY the body text for the \"{$heading}\" section of a blog post titled "
            . "\"{$title}\". About " . self::PER_SECTION . " words, in Markdown. Do NOT output the heading "
            . "itself, the post title, or a conclusion for the whole post.{$toneTxt}{$kwTxt}";

        return $this->ai_text($prompt, $settings, $this->tokens_for(self::PER_SECTION));
    }

    /** Combine title + optional TOC + sections into the final Markdown. Local — no AI, no timeout. */
    private function assemble(string $title, array $outline, array $sections, array $settings): string
    {
        $parts = [ '# ' . $title ];

        if (!empty($settings['toc_include']) && (count($outline) !== 1 || $outline[0] !== self::SINGLE)) {
            $toc = "## Table of Contents\n";
            foreach ($outline as $h) {
                if ($h === self::SINGLE) {
                    continue;
                }
                $anchor = sanitize_title($h);
                $toc   .= "- [{$h}](#{$anchor})\n";
            }
            $parts[] = trim($toc);
        }

        foreach ($sections as $s) {
            $parts[] = trim((string) $s);
        }

        return implode("\n\n", $parts);
    }

    // ---- AI plumbing ---------------------------------------------------------------------

    /** One short AI call → plain text. Throws on failure so tick() can retry/fail the job. */
    private function ai_text(string $prompt, array $settings, int $max_tokens): string
    {
        if (!class_exists('WPWand\Generation\Generator')) {
            self::load_generator();
        }
        if (!class_exists('WPWand\Generation\Generator')) {
            throw new \RuntimeException('Generator unavailable.');
        }

        $args = ['max_tokens' => $max_tokens];
        if (!empty($settings['language'])) {
            $args['language'] = (string) $settings['language'];
        }

        $res = \WPWand\Generation\Generator::generate($prompt, 1, $args);

        if (is_object($res) && isset($res->error)) {
            $msg = \WPWand\Generation\ErrorFormatter::humanize($res->error, __('Generation failed.', 'wp-wand-pro'));
            throw new \RuntimeException($msg); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught internally
        }

        $text = '';
        if (is_object($res) && isset($res->choices[0])) {
            $choice = $res->choices[0];
            $text   = isset($choice->message->content) ? (string) $choice->message->content
                : (isset($choice->text) ? (string) $choice->text : '');
        }

        $text = trim($text);
        if ($text === '') {
            throw new \RuntimeException('Empty AI response.');
        }

        return $text;
    }

    /**
     * Load the legacy generation function (inc/api.php + its deps) when it isn't already defined.
     *
     * The legacy bootstrap (wp-wand.php) only includes these files for users who can edit_posts,
     * so in an unattended WP-Cron run — exactly when the engine drains the queue — the function is
     * missing. Pulling in the minimal set here makes background generation work in cron/CLI without
     * touching the legacy guard. No-op once loaded (require_once + function_exists).
     */
    private static function load_generator(): void
    {
        $dir = defined('WPWAND_PLUGIN_DIR') ? WPWAND_PLUGIN_DIR : WP_PLUGIN_DIR . '/wp-wand/';
        foreach (['inc/config.php', 'inc/data.php', 'inc/helper-functions.php', 'inc/api.php'] as $rel) {
            $file = $dir . $rel;
            if (is_readable($file)) {
                require_once $file;
            }
        }
    }

    private function tokens_for(int $words): int
    {
        // ~1.6 tokens/word + headroom, bounded so a single call can never run long.
        return (int) min(2200, max(400, $words * 2));
    }

    /** @return string[] */
    private function parse_outline(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $out   = [];
        foreach ($lines as $line) {
            $line = trim($line);
            // strip "1.", "1)", "-", "#", "##", bullets
            $line = preg_replace('/^\s*(\d+[\.\)]|[-*•]|#{1,6})\s*/', '', $line);
            $line = trim((string) $line, " \t\"'*#");
            if ($line !== '' && mb_strlen($line) <= 120) {
                $out[] = $line;
            }
        }
        return $out;
    }

    // ---- persistence ---------------------------------------------------------------------

    /**
     * Reset a failed/stuck job back to the start of the queue so a driver re-generates it from
     * scratch. Returns false when there's no job for that post row. Powers the Bulk "Retry" action.
     */
    public function retry(int $row_id): bool
    {
        global $wpdb;

        // The post row must exist and have a title to regenerate from.
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT id, title FROM {$this->posts_table()} WHERE id = %d", $row_id), // phpcs:ignore
            ARRAY_A
        );
        if (!$row || trim((string) $row['title']) === '') {
            return false;
        }

        $job_id = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$this->jobs_table()} WHERE row_id = %d", $row_id) // phpcs:ignore
        );

        if ($job_id) {
            // New-engine job exists — reset it to the start of the queue.
            $this->update_job($job_id, [
                'status'   => 'pending',
                'attempts' => 0,
                'step'     => 0,
                'outline'  => null,
                'sections' => wp_json_encode([]),
                'error'    => '',
            ]);
        } else {
            // Legacy/orphan row (generated by the old Action-Scheduler engine, so it has no
            // wpwand_gen_jobs entry). Create a fresh job so the new step-queue regenerates it.
            $wpdb->insert($this->jobs_table(), [ // phpcs:ignore
                'row_id'   => $row_id,
                'title'    => (string) $row['title'],
                'settings' => wp_json_encode(['word_count' => (int) get_option('wpwand_target_word_count', 0)]),
                'sections' => wp_json_encode([]),
                'step'     => 0,
                'status'   => 'pending',
            ]);
        }

        $this->update_post($row_id, '', 'pending');

        return true;
    }

    private function update_job(int $id, array $data): void
    {
        global $wpdb;
        $data['updated_at'] = current_time('mysql');
        $wpdb->update($this->jobs_table(), $data, ['id' => $id]);
    }

    private function update_post(int $row_id, string $content, string $status): void
    {
        global $wpdb;
        $wpdb->update($this->posts_table(), ['content' => $content, 'status' => $status], ['id' => $row_id]);
    }

    private function fail(array $job, string $error): void
    {
        $attempts = (int) $job['attempts'] + 1;
        if ($attempts >= self::MAX_ATTEMPTS) {
            $human = \WPWand\Generation\ErrorFormatter::humanize($error, __('Generation failed.', 'wp-wand-pro'));
            $this->update_job($job['id'], ['status' => 'failed', 'attempts' => $attempts, 'error' => $human]);
            // Store the failure reason in the post row's content so the Bulk list popup can show
            // *why* it failed (the row keeps its own title column, so content is free to reuse).
            $this->update_post($job['row_id'], $human, 'failed');
            return;
        }
        // Leave it pending for the next tick to retry.
        $this->update_job($job['id'], ['status' => 'pending', 'attempts' => $attempts, 'error' => $error]);
    }
}
