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
    private const PAUSE = 'wpwand_rate_pause';  // the provider asked the queue to wait; see tick()
    private const MAX_WAITS = 5;                // pauses in a row on one step before it is given up
    private const SINGLE = '__single__';   // sentinel: write the whole post in one block
    private const INTRO = '__intro__';     // sentinel: the opening paragraphs, which carry no heading
    private const OUTRO = '__outro__';     // sentinel: the closing section; the model writes its heading
    private const FAQ_HEADING = 'Frequently Asked Questions (FAQ)';

    /**
     * Settings key: the batch a job was queued in. One enqueue() call is one run, and one run is
     * exactly what the free allowance is charged for, so this is what a refund has to be grouped by.
     */
    private const RUN_KEY = 'wpw_run_id';

    /** Settings key: this job's failure has already been given back, so it cannot be refunded twice. */
    private const REFUND_KEY = 'wpw_refunded';

    /**
     * Settings key: this job has already been counted against its schedule's failure tally.
     * `tick()` can reach `fail()` more than once for the same job, and a retried job is one
     * failure, not three — the same reason {@see self::REFUND_KEY} exists.
     */
    private const COUNTED_KEY = 'wpw_failure_counted';
    private const MIN_SECTION_WORDS = 150; // floor for a per-section target derived from word_count

    /**
     * The failover explanation from the first call of the current run_article(), when one failed over.
     *
     * run_article() throws the Generator response objects away — it only keeps the text — so this is
     * where the tag survives long enough for the caller to put it on the response. Without it an
     * assistant generation that quietly moved to a second account said nothing at all.
     *
     * @var mixed
     */
    private $run_failover = null;

    /** Why the current run_article() stopped early, when it stopped early but had sections in hand. */
    private string $run_error = '';

    /**
     * Templates the assistant hands to {@see self::run_article()} instead of asking one request for
     * the whole article.
     *
     * These are the two that declare a long-form target. Asking a single call for 2,000+ words is
     * what stopped a run mid-bullet with `finish_reason: length` and a contents list naming 25
     * sections against 6 written. The same outline-then-sections route the queue has always used
     * cannot do that, because no request contains the whole article.
     */
    public const LONG_FORM_TEMPLATES = ['One Click Blog Post', 'Blog Post Writer'];

    /**
     * The six shapes a batch can be written to, and what each one asks the model for.
     *
     * WHY A MAP AND NOT SIX PROMPTS. Every batch went through one prompt — "Create a blog post
     * outline" — whatever the wizard's Template field said, so picking Listicle and picking News
     * piece produced the same article. Each entry below changes two things and nothing else: the
     * noun in the outline request, and one sentence added to the end of every section prompt.
     *
     * `blog` is the fallback and its two strings are written so the prompt comes out byte for byte
     * what it was before this map existed — an unset or unknown template must generate exactly
     * what it generates today, on 2,296 installs that never chose one.
     *
     * The keys are what the row stores. They are short and stable; the label is the screen's
     * business, not the prompt's.
     *
     * THREE STRINGS SINCE 2026-10-02. A batch at the default length is one request for the whole
     * post, and it was being sent the per-section sentence: "Write this as one item on a list" on a
     * request for a complete article got one 284-word bullet with no headings. `article` is the
     * sentence for the whole post; `section` is the sentence for one section of a longer one. Nothing
     * here is byte-identical to the old prompts any more — the closing line, the keyword line and
     * the section list all changed the same day (docs/reviews/2026-10-02-prompt-simulation.md).
     *
     * @var array<string, array{label: string, outline: string, section: string, article: string}>
     */
    public const TEMPLATE_FALLBACK = 'blog';

    public const TEMPLATES = [
        'blog' => [
            'label'   => 'Blog post',
            'outline' => 'a blog post outline',
            'section' => '',
            'article' => '',
        ],
        'listicle' => [
            'label'   => 'Listicle',
            'outline' => 'a listicle outline, where every heading is one numbered item on the list',
            'section' => ' Write this as one item on a list: make the point quickly, back it up, and stop.',
            'article' => ' Write it as a list: a short opening, then each item under its own numbered ## heading with a paragraph or two that makes the point and backs it up. Do not pad an item to match the others.',
        ],
        'howto' => [
            'label'   => 'How-to guide',
            'outline' => 'a how-to guide outline, where every heading is one step in order',
            'section' => ' Write this as one step someone follows: what to do, in order, and what it should look like when it has worked.',
            'article' => ' Write it as a how-to: what the reader needs before starting, then the steps in order, each under its own ## heading, saying what to do and what it should look like when it has worked.',
        ],
        'review' => [
            'label'   => 'Product review',
            'outline' => 'a product review outline covering what it is, how it performs, what it costs and who it suits',
            // "A reviewer who has used it" was an invitation to invent the using: "I tested cold brew at
            // three different ratios" came back for a title that is not a product at all.
            'section' => ' Write as a reviewer: say what is good, say what is not, and be specific rather than enthusiastic. Do not claim to have used or tested it yourself unless the background below says so.',
            'article' => ' Write it as a review: what it is, what is good, what is not, what it costs and who it suits, under ## headings. Be specific rather than enthusiastic. Do not claim to have used or tested it yourself unless the background below says so.',
        ],
        'comparison' => [
            'label'   => 'Comparison',
            'outline' => 'a comparison outline, where every heading is one thing the options are being compared on',
            'section' => ' Compare the options directly on this point and say which comes out ahead, and for whom.',
            'article' => ' Write it as a comparison: take the options one point at a time under ## headings, say which comes out ahead on each and for whom, and finish with which to pick.',
        ],
        'news' => [
            'label'   => 'News piece',
            'outline' => 'a news article outline, leading with what happened and following with the detail behind it',
            'section' => ' Write it as news: what happened first, then the detail. Plain reporting, no opinion and no sales language.',
            'article' => ' Write it as news: what happened first, then the detail behind it, under ## headings. Plain reporting, no opinion and no sales language.',
        ],
    ];

    /**
     * The template a job was queued with, falling back to the blog shape.
     *
     * Anything unknown lands on the fallback rather than throwing: the key travels in a JSON blob
     * written by an older build, and a batch that was queued before this existed still has to run.
     *
     * @param array<string, mixed> $settings
     * @return array{label: string, outline: string, section: string, article: string}
     */
    public static function template(array $settings): array
    {
        $key = is_string($settings['template'] ?? null) ? $settings['template'] : '';

        return self::TEMPLATES[$key] ?? self::TEMPLATES[self::TEMPLATE_FALLBACK];
    }

    /** True when a template should be written section by section rather than in one request. */
    public static function handles_template(string $name): bool
    {
        return in_array(trim($name), self::LONG_FORM_TEMPLATES, true);
    }

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

        // Both callers charge the allowance for this one batch, so stamp every job in it with the
        // same id. Without it a refund cannot tell which jobs shared a single charge.
        $settings[self::RUN_KEY] = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : md5(uniqid('', true));

        // Normalised once for the whole batch. A caller that names nothing — every automation
        // schedule, and every batch queued before the field existed — stores NULL, and the screen
        // says so in its own words rather than printing a shape that was never chosen.
        $template = isset($settings['template']) && isset(self::TEMPLATES[$settings['template']])
            ? (string) $settings['template']
            : null;

        // Which screen asked for this. Bulk Posts and Automated Posts share one table, and the
        // Bulk list used to tell them apart by accident — it hid anything with a post_id, and an
        // automated post gets one as soon as it is finalised. That accident also hid a bulk row
        // the moment a draft was made from it. Saying it outright costs one column.
        $source = isset($settings['schedule_id']) ? 'automation' : 'bulk';

        foreach ($titles as $title) {
            $title = trim((string) $title);
            if ($title === '') {
                continue;
            }

            $wpdb->insert($this->posts_table(), [
                'title'    => $title,
                'content'  => '',
                'post_id'  => 0,
                'status'   => 'pending',
                // Stamped here, not at assembly: a row that never comes back still has to say
                // what shape it was trying to write.
                'template' => $template,
                'source'   => $source,
            ]);
            $row_id = (int) $wpdb->insert_id;
            if ($row_id === 0) {
                // The post row failed to insert. Queuing a job against row 0 leaves a pending job
                // nothing can ever satisfy: the drainer still makes paid provider calls and writes
                // sections into a row that matches no post, and is_running() stays true forever.
                continue;
            }

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
        // The provider said "too many requests, come back in N seconds". Until then a tick asks for
        // nothing: the browser calls this every 400 ms, and three calls inside that wait used to be
        // the job's three attempts.
        $pause = get_transient(self::PAUSE);
        $pause = is_array($pause) ? $pause : null;
        if ($pause !== null && (int) ($pause['until'] ?? 0) > time()) {
            $left = (int) $pause['until'] - time();
            return [
                'waiting'      => $left,
                'waiting_note' => RateLimited::note((string) ($pause['label'] ?? ''), $left),
            ] + $this->snapshot();
        }

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
                if ($pause !== null) {
                    // A step got through, so the count of pauses in a row starts again.
                    delete_transient(self::PAUSE);
                }
            } catch (RateLimited $e) {
                if (!$e->long && $this->pause_for($job, $e, $pause)) {
                    return [
                        'waiting'      => $e->wait + 1,
                        'waiting_note' => RateLimited::note($e->label, $e->wait + 1),
                    ] + $this->snapshot();
                }
                // A daily allowance, or a provider that has asked for a pause five times running.
                // Two more attempts would be two more refusals, so this one is final.
                $job['attempts'] = self::MAX_ATTEMPTS - 1;
                $this->fail($job, $e->long ? $e->getMessage() : $e->stuck);
            } catch (\Throwable $e) {
                $this->fail($job, $e->getMessage());
            }
        } finally {
            delete_transient(self::LOCK);
        }

        return ['ticked' => true] + $this->snapshot();
    }

    /**
     * Hold the queue until the provider will take a request again. The job is left exactly as it
     * is — no attempt counted, nothing written — and the next tick after the wait runs the same step.
     *
     * The pause is the whole queue's, not the job's: the limit belongs to the key, so the job behind
     * this one would be refused too.
     *
     * @param array<string, mixed>      $job
     * @param array<string, mixed>|null $pause The pause already on record, if any.
     * @return bool False when this step has been paused {@see self::MAX_WAITS} times in a row.
     */
    private function pause_for(array $job, RateLimited $e, ?array $pause): bool
    {
        $same  = $pause !== null && (int) ($pause['job'] ?? 0) === (int) $job['id'] && (int) ($pause['step'] ?? -1) === (int) $job['step'];
        $count = $same ? (int) ($pause['count'] ?? 0) + 1 : 1;
        if ($count > self::MAX_WAITS) {
            delete_transient(self::PAUSE);
            return false;
        }

        // Kept well past the wait itself: the count has to still be there when the step is tried
        // again, or a provider that never lets go would be waited on for ever.
        set_transient(self::PAUSE, [
            'until' => time() + $e->wait + 1,
            'label' => $e->label,
            'job'   => (int) $job['id'],
            'step'  => (int) $job['step'],
            'count' => $count,
        ], $e->wait + 10 * MINUTE_IN_SECONDS);

        return true;
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

            // The closing words of the section written last tick. `run_article()` threads this
            // through its own loop; the queue never did, so a batch lost the one rule measured to
            // put callbacks into a sectioned article at all — 0 to 3 in 2,400 words. The sections
            // are already in hand, so it costs a read.
            $settings['previous_tail'] = empty($sections)
                ? ''
                : $this->tail_of((string) end($sections));
            // The part before the first section is the opening paragraphs. "Open by picking up where
            // that left off" made the first section start mid-thought — "That said, a mason jar
            // works" as the first words under the first heading.
            if ($step >= 2 && (string) $outline[$step - 2] === self::INTRO) {
                $settings['previous_tail'] = '';
            }

            // What each part needs to know about the rest of the article, and how many words it has.
            $settings['headings'] = self::prose_headings($outline);
            if (empty($settings['headings']) && !empty($sections)) {
                // A single-block post: its own headings are what the FAQ must not repeat.
                $settings['headings'] = self::h2s((string) $sections[0]);
            }
            if (empty($settings['section_words'])) {
                $settings['section_words'] = $this->section_words($outline, $settings);
            }
            $settings['written'] = implode("\n\n", array_map('strval', $sections));

            // The post arrived with its FAQ already in it, so the FAQ step has nothing to ask for.
            if (self::is_faq($heading) && $step === 2 && (string) $outline[0] === self::SINGLE && self::carries_faq((string) ($sections[0] ?? ''))) {
                $sections[] = '';
                $this->update_job($job['id'], [
                    'sections' => wp_json_encode($sections),
                    'step'     => $step + 1,
                    'status'   => 'processing',
                ]);
                return;
            }

            $body = $this->write_section($job['title'], $heading, $settings, $count);

            $sections[] = self::section_markdown($heading, $body);

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
        // Counted here, once, rather than off every body on every poll of the list.
        $this->update_post($job['row_id'], $content, 'done', WordCount::of($content));
        $this->update_job($job['id'], ['status' => 'done', 'step' => $step]);
        update_option('wpwand_pgc_task_completed', (int) get_option('wpwand_pgc_task_completed', 0) + 1);

        // Scheduled-automation jobs carry a target status and auto-create the WP post (unattended).
        // Manual bulk jobs omit auto_status and stay in the list until the user clicks Approve.
        if (!empty($settings['auto_status'])) {
            $this->finalize_post((int) $job['row_id'], (string) $job['title'], $content, $settings);
        }

        // History is a record of what the plugin wrote, and until now it was a record of the
        // Assistant only — this engine finished a job and never touched that table, so neither Bulk
        // Posts nor Automated Posts appeared in it at all.
        //
        // Here, and only here: this is the final assemble. A six-section post reaches write_section()
        // six times and this line once, so it writes one row rather than six. The failed branch does
        // not come through here either, which is deliberate — a failure has its own list, its own
        // reason and its own refund, and filing it here would turn a record of writing into a record
        // of trying.
        \WPWand\Data\History::record(
            self::template($settings)['label'],
            $settings,
            \WPWand\Data\History::response_from_text($content),
            (string) $job['title'],
            isset($settings['schedule_id'])
                ? \WPWand\Data\History::SOURCE_AUTOMATION
                : \WPWand\Data\History::SOURCE_BULK
        );

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
                $schedule_id = (string) $settings['schedule_id'];
                update_post_meta($post_id, '_wpwand_schedule_id', $schedule_id);

                // And count it. The post is counted here, where it exists — not where the title
                // was queued: `enqueue()` drops any title whose row failed to insert, which is
                // why the biller upstream counts what came back rather than what went in.
                //
                // A running total, not a length. The screen counted the rows it had been handed,
                // and that array is capped at twenty and shrinks when someone deletes a post the
                // schedule wrote. This number only goes up, and it survives the deletion.
                $schedule = \WPWand\Automation\Schedules::get($schedule_id);
                if ($schedule) {
                    \WPWand\Automation\Schedules::update($schedule_id, [
                        'posts_written' => (int) ($schedule['posts_written'] ?? 0) + 1,
                    ]);
                }
            }
        }
    }

    /**
     * Write one long-form article start to finish, inside the current request.
     *
     * The same three moves the queue makes — outline, one call per section, assemble locally — with
     * no job row and no driver, because an assistant generation has to answer the request it is in.
     * Nothing here touches wpwand_gen_jobs or wpwand_generated_post, so a run started from the
     * assistant cannot collide with a bulk run that is draining at the same time.
     *
     * When a section fails, everything already written is kept and the run stops there: the
     * contents list still names the section that never arrived, so Completeness::check() marks the
     * article short and the caller reports why. Only a run with nothing in hand throws — on a
     * 2,000-word article, throwing at section six used to bin five calls the user had paid for.
     *
     * @param string               $title    What the article is about; becomes the H1.
     * @param array<string, mixed> $settings word_count, tone, keyword, language, toc_include,
     *                                       faq_include, plus the three this entry point adds:
     *                                       outline_text (a user-supplied outline to follow instead
     *                                       of asking for one), style_rules (the house style, which
     *                                       also switches on the section-to-section continuity rule)
     *                                       and max_tokens_cap (the configured Longest reply; no
     *                                       single call may exceed it).
     *
     * @return string The assembled Markdown.
     *
     * @throws \RuntimeException When the outline fails, or the very first section does.
     */
    public function run_article(string $title, array $settings): string
    {
        $this->run_failover = null;
        $this->run_error    = '';

        $title = trim($title);
        if ($title === '') {
            throw new \RuntimeException(
                __('Add a topic first. The article gets built around it.', 'ai-content-generation') // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught by the caller, never echoed
            );
        }

        $outline = $this->supplied_outline($settings);
        if (empty($outline)) {
            $outline = $this->build_outline($title, $settings);
        } else {
            // The text was the outline, so it is already driving the run. Repeating it as
            // background in every section prompt buys nothing and costs input tokens.
            unset($settings['context_text']);
        }

        $count    = count($outline);
        $sections = [];
        $tail     = '';

        // Split the word count the form collected across the sections that carry prose, so a
        // 900-word article does not come back as three 600-word sections. The FAQ has its own
        // budget and the single-block case already writes to the full target.
        $settings['section_words'] = $this->section_words($outline, $settings);
        $settings['headings']      = self::prose_headings($outline);

        foreach ($outline as $heading) {
            $heading = (string) $heading;

            if (empty($settings['headings']) && !empty($sections)) {
                // A single-block post: its own headings are what the FAQ must not repeat.
                $settings['headings'] = self::h2s((string) $sections[0]);
            }

            if (self::is_faq($heading) && (string) $outline[0] === self::SINGLE && !empty($sections) && self::carries_faq((string) $sections[0])) {
                // A single-block post that arrived with its FAQ in it.
                $sections[] = '';
                continue;
            }

            // Hand each section the closing words of the one before it. This is the whole answer to
            // "does sectioning flatten the writing" — measured, the sectioned runs carried more
            // thread than the single call, because each one can see where the last left off.
            $settings['previous_tail'] = $tail;
            $settings['written']       = implode("\n\n", $sections);

            try {
                $body = $this->write_section($title, $heading, $settings, $count);
            } catch (\Throwable $e) {
                if (empty($sections)) {
                    // Nothing written yet, so there is nothing to keep — report the failure.
                    throw $e;
                }
                // Keep what the provider was already paid for and stop here. The contents list
                // still names this heading, which is how the caller learns what is missing.
                $this->run_error = trim($e->getMessage());
                break;
            }

            // The first section after the opening paragraphs starts fresh; see step().
            $tail = $heading === self::INTRO ? '' : $this->tail_of($body);

            $sections[] = self::section_markdown($heading, $body);
        }

        return $this->assemble($title, $outline, $sections, $settings);
    }

    /**
     * The failover explanation from the last run_article(), or null when nothing failed over.
     *
     * @return mixed
     */
    public function last_failover()
    {
        return $this->run_failover;
    }

    /** Why the last run_article() stopped before the end. Empty string when it ran to the end. */
    public function last_section_error(): string
    {
        return $this->run_error;
    }

    /**
     * Words to ask each prose section for, given the outline and the target for the whole article.
     *
     * Returns 0 when there is nothing to divide — a caller that leaves word_count unset keeps the
     * flat per-section target.
     *
     * The queue never called this; only the assistant did. A batch asked for 2,000 words wrote four
     * sections at a flat 600 and a FAQ on top, and came back at 3,053 (measured 2026-10-02). Both
     * paths divide the same budget now.
     *
     * @param string[]             $outline
     * @param array<string, mixed> $settings
     */
    private function section_words(array $outline, array $settings): int
    {
        $target = (int) ($settings['word_count'] ?? 0);
        if ($target <= 0) {
            return 0;
        }

        $prose = count(self::prose_headings($outline));
        if ($prose < 1) {
            return 0;
        }

        $budget = self::budget($target, self::has_faq($outline), in_array(self::INTRO, $outline, true));

        return max(self::MIN_SECTION_WORDS, (int) floor($budget['prose'] / $prose));
    }

    /**
     * How the words asked for are divided, so the article comes back near the number on the form.
     *
     * The FAQ used to sit outside the target on every path — 1,500 asked was 1,500 of sections plus
     * a FAQ, 2,084 measured — and the opening and closing did not exist. All four parts come out of
     * the one number now (the owner's call, 2026-10-02). The shares are small and capped: a
     * 4,000-word article does not want a 600-word FAQ.
     *
     * @param bool $faq    The article has a FAQ.
     * @param bool $framed The article has a separate opening and closing (the sectioned route).
     *
     * @return array{intro: int, outro: int, faq: int, prose: int}
     */
    private static function budget(int $target, bool $faq, bool $framed): array
    {
        $faqWords   = $faq ? (int) max(150, min(300, round($target * 0.15))) : 0;
        $introWords = $framed ? (int) max(60, min(120, round($target * 0.06))) : 0;
        $outroWords = $framed ? (int) max(70, min(140, round($target * 0.07))) : 0;

        return [
            'intro' => $introWords,
            'outro' => $outroWords,
            'faq'   => $faqWords,
            'prose' => max(300, $target - $faqWords - $introWords - $outroWords),
        ];
    }

    /**
     * The headings that carry a section of prose: not the single block, the opening, the closing or
     * the FAQ.
     *
     * @param array<int, mixed> $outline
     *
     * @return string[]
     */
    private static function prose_headings(array $outline): array
    {
        $out = [];
        foreach ($outline as $heading) {
            $heading = (string) $heading;
            if (!in_array($heading, [self::SINGLE, self::INTRO, self::OUTRO], true) && !self::is_faq($heading)) {
                $out[] = $heading;
            }
        }

        return $out;
    }

    /** @param array<int, mixed> $outline */
    private static function has_faq(array $outline): bool
    {
        foreach ($outline as $heading) {
            if (self::is_faq((string) $heading)) {
                return true;
            }
        }

        return false;
    }


    /**
     * True when a single-block post came back with its FAQ in it.
     *
     * By its heading first. A model writing in another language may translate the heading it was
     * told to copy, and a second FAQ under the first is worse than none — so the last section is
     * also read for what a FAQ looks like: two or more bold lines that end in a question mark.
     */
    private static function carries_faq(string $body): bool
    {
        if (self::has_faq(self::h2s($body))) {
            return true;
        }

        $parts = preg_split('/^##[ \t]+.*$/mu', $body) ?: [];
        if (count($parts) < 2) {
            return false;
        }

        return preg_match_all('/^\*\*[^*\n]+[?？]\*\*/mu', (string) end($parts)) >= 2;
    }

    /** True when a heading is the FAQ section — one test, used everywhere it is asked. */
    private static function is_faq(string $heading): bool
    {
        return stripos($heading, 'FAQ') !== false || stripos($heading, 'Frequently Asked') !== false;
    }

    /** The closing words of a section — enough for the next one to pick up the thread. */
    private function tail_of(string $body): string
    {
        $words = preg_split('/\s+/u', trim($body)) ?: [];

        return trim(implode(' ', array_slice($words, -30)));
    }

    /**
     * The outline the user typed, when they typed one.
     *
     * Blog Post Writer collects an outline in Content Text Area and the old prompt multiplied its
     * line count by a per-title word floor, so a 25-line outline asked for ten thousand words. Here
     * the same 25 lines are just 25 sections, bounded like every other outline.
     *
     * The field is labelled "Content", not "Outline", so plenty of people type a description into
     * it. Only text that actually reads as a heading list is used as one — numbered or bulleted
     * lines, or short lines with no sentence punctuation. Anything else stays background for every
     * section prompt (see the caller's `context_text`) and the outline is asked for instead, so a
     * typed paragraph is never chopped into H2s and never silently dropped.
     *
     * @param array<string, mixed> $settings
     *
     * @return string[]
     */
    private function supplied_outline(array $settings): array
    {
        $text = trim((string) ($settings['outline_text'] ?? ''));
        if ($text === '' || !$this->looks_like_outline($text)) {
            return [];
        }

        $headings = $this->parse_outline($text);
        if (count($headings) < 2) {
            return [];
        }

        $headings = array_slice($headings, 0, self::MAX_SECTIONS);

        $has_faq = false;
        foreach ($headings as $heading) {
            if (self::is_faq((string) $heading)) {
                $has_faq = true;
                break;
            }
        }

        // An outline that already ends with an FAQ line does not need a second one bolted on —
        // both would be written, and both would show up in the contents list.
        if (!empty($settings['faq_include']) && !$has_faq) {
            $headings[] = self::FAQ_HEADING;
        }

        return $headings;
    }

    /**
     * Does this text read as a list of section headings, or as prose someone typed?
     *
     * Two or more marked-up lines (1., 2), -, *, #) settle it. Failing that, two or more short
     * lines that do not end in sentence punctuation are a bare heading list. A couple of sentences
     * are neither, and turning "I run a small bakery in Dhaka." into an H2 is the failure this
     * catches.
     */
    private function looks_like_outline(string $text): bool
    {
        $lines  = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $marked = 0;
        $bare   = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^\s*(\d+[\.\)]|[-*•]|#{1,6})\s+\S/u', $line)) {
                $marked++;
                continue;
            }

            if (mb_strlen($line) <= 70 && !preg_match('/[.!?]["\')]?$/u', $line)) {
                $bare++;
            }
        }

        return $marked >= 2 || $bare >= 2;
    }

    /**
     * Decide the section headings. Short posts → a single block (no AI outline call); long posts
     * → ask the AI for N headings. FAQ (if requested) becomes an extra section.
     *
     * `force_outline` overrides the short-post shortcut. A long-form template asking for 900 words
     * still wants a contents list and an FAQ, and neither exists on the single-block path — the
     * whole point of routing those templates here.
     *
     * @return string[]
     */
    private function build_outline(string $title, array $settings): array
    {
        $words  = (int) ($settings['word_count'] ?? 0);
        $forced = !empty($settings['force_outline']);
        $faq    = !empty($settings['faq_include']);

        // One request for the whole post — and, when the FAQ is switched on, a second one for that.
        // The single block used to come back alone, so at the default length, and at anything up to
        // 1,200 words, the FAQ and the contents list were on in the wizard and missing from the
        // article: four out of four, measured 2026-10-02.
        $single = $faq ? [self::SINGLE, self::FAQ_HEADING] : [self::SINGLE];

        if (!$forced && $words <= 1200) {
            return $single;
        }

        $target  = $words > 0 ? $words : 800;
        $budget  = self::budget($target, $faq, true);
        $n       = max(3, min(self::MAX_SECTIONS, (int) ceil($budget['prose'] / self::PER_SECTION)));
        $tone    = (string) ($settings['tone'] ?? '');
        $keyword = (string) ($settings['keyword'] ?? '');
        $tpl     = self::template($settings);

        $prompt = "Create {$tpl['outline']} for the title \"{$title}\".\n"
            . "Return ONLY a numbered list of exactly {$n} concise section headings, as plain text with no # marks "
            . 'and no descriptions. Make them specific and non-overlapping. Do not include an introduction or a '
            . 'conclusion among them; those are written separately.'
            . ($keyword !== '' ? "\nRelevant keywords: {$keyword}." : '')
            . ($tone !== '' ? "\nTone: {$tone}." : '');

        $text     = $this->ai_text($prompt, $settings, 600);
        $headings = array_slice($this->parse_outline($text), 0, self::MAX_SECTIONS);

        if (count($headings) < 2) {
            return $single;
        }

        // An opening above the first heading and a closing below the last — a sectioned article
        // used to start on its contents list and stop on its last section (the owner's call,
        // 2026-10-02). Only where the model built the outline: one the user typed is followed as
        // typed, and may well have its own.
        $outline = array_merge([self::INTRO], $headings, [self::OUTRO]);
        if ($faq) {
            $outline[] = self::FAQ_HEADING;
        }

        return $outline;
    }


    /** Generate one part's text: the whole post, the opening, a section, the closing, or the FAQ. */
    private function write_section(string $title, string $heading, array $settings, int $count): string
    {
        $tone    = (string) ($settings['tone'] ?? '');
        $keyword = trim((string) ($settings['keyword'] ?? ''));
        $toneTxt = $tone !== '' ? " Tone: {$tone}." : '';

        // The house style, when the caller sent it. Bulk, Automated Posts and the assistant all do.
        $rulesTxt = (string) ($settings['style_rules'] ?? '');

        // The Pro AI Character and the point of view, which the single-request path puts on the
        // command. Rebuilding the article section by section threw both away, so a chosen character
        // wrote nothing at all on the two templates that route through here.
        $persona = trim((string) ($settings['persona'] ?? ''));
        $lead    = $persona !== '' ? rtrim($persona) . ' ' : '';
        $pov     = trim((string) ($settings['point_of_view'] ?? ''));
        $povTxt  = $pov !== '' ? " The content must be written in {$pov}." : '';

        // Whatever the user typed into the template's own text area, whether or not it read as an
        // outline. It is the main input on Blog Post Writer and every section should see it.
        $context = trim((string) ($settings['context_text'] ?? ''));
        $ctxTxt  = $context !== ''
            ? "\n\nBackground from the user — write in line with it:\n" . $context
            : '';

        // The other headings, by name. A section used to be told its own heading and the last
        // thirty words of the one before, and nothing else — so the first section of an article
        // wrote the next two as well, and the FAQ asked what the body had already answered
        // ("steep time" in all four parts of one article, measured 2026-10-02).
        $headings = [];
        foreach ((array) ($settings['headings'] ?? []) as $h) {
            $h = trim((string) $h);
            if ($h !== '') {
                $headings[] = $h;
            }
        }
        $numbered = [];
        foreach ($headings as $i => $h) {
            $numbered[] = ($i + 1) . '. ' . $h;
        }
        $listed = implode('; ', $numbered);

        // Everything written before this part, so it can avoid saying it again and avoid saying
        // the opposite. The thirty-word tail gave a section its opening line and nothing else: a
        // closing section recommended a 1:5 ratio after a body that had settled on 1:4, a FAQ gave
        // the shelf life a fourth time, and an author's "I roast about 40 kg a week" came back in
        // five sections (the blind read of 2026-10-02 named all three). It is input, not output —
        // a 2,000-word article adds about two thousand words of it across all its requests.
        //
        // It goes FIRST, with the instructions after it. Put last, it sat between the house rules
        // and the end of the request, and the words the rules ban started coming back.
        $written = self::clip_written((string) ($settings['written'] ?? ''));
        $soFar   = $written !== ''
            ? "Here is the post so far, for reference only. Do not repeat what it already says, do not contradict it, "
                . "and do not introduce the author or their figures again:\n<<<\n{$written}\n>>>\n\nNow the task. "
            : '';

        $tpl     = self::template($settings);
        $words   = (int) ($settings['word_count'] ?? 0);
        $target  = $words > 0 ? $words : 800;
        $withFaq = !empty($settings['faq_include']);

        // The rules were written for a whole piece. On a part of eighty words "include real
        // numbers" and "take a side" read as orders to cram both in.
        $short = $rulesTxt !== ''
            ? "\n- This part is short. The rules about numbers, taking a side and paragraph rhythm are for the sections, not for this."
            : '';

        if ($heading === self::SINGLE) {
            $budget = self::budget($target, $withFaq, false);
            // Once for the article. The old line sat on every section and on the FAQ, and one
            // phrase came back fifteen times in three thousand words.
            $kwTxt  = $keyword !== ''
                ? " Work these keywords in where they belong, each no more than two or three times in the whole post: {$keyword}."
                : '';
            // The FAQ rides in the same request. It was a second request for a morning: on a free
            // Gemini key that is twenty requests a day and five a minute (measured 2026-10-02, the
            // provider's own 429), so a second request an article halves what a batch can write. The
            // model also knows what it has just said, which a separate FAQ request has to be shown.
            // If the heading does not come back, the FAQ step that follows writes one after all.
            $faqPairs = $budget['faq'] <= 200 ? '3-4' : '4-6';
            $faqTxt   = $withFaq
                ? ' End the post with a final section headed exactly "## ' . self::FAQ_HEADING . "\": {$faqPairs} short "
                    . "question-and-answer pairs (bold question, then the answer), about {$budget['faq']} words, asking "
                    . 'what the post has not already answered.'
                : '';
            $total    = $budget['prose'] + $budget['faq'];

            // The whole-article sentence for the template, not the per-section one. The per-section
            // sentence used to be sent here: a listicle came back as one 284-word bullet.
            $prompt = $lead . "Write a complete, original blog post titled \"{$title}\" of about {$total} words in "
                . "Markdown. Use ## headings for sections. Do not include the title as an H1.{$faqTxt}"
                . $povTxt . $toneTxt . $kwTxt . $tpl['article'] . $rulesTxt . $ctxTxt;

            return $this->ai_text($prompt, $settings, $this->tokens_for($total));
        }

        if (self::is_faq($heading)) {
            $budget  = self::budget($target, true, false);
            // Six answers do not fit in 150 words; asked for anyway, they came back at 190 to 300.
            $pairs   = $budget['faq'] <= 200 ? '3-4' : '4-6';
            $covered = $written !== ''
                ? ' Ask what a reader would still want to know after reading the post above, and do not answer again what it already answers.'
                : ($listed !== ''
                    ? " The post already covers: {$listed}. Ask what a reader would still want to know after reading those, and do not repeat what they answer."
                    : '');

            // No keyword line and no continuity rule: a FAQ told to "open by picking up where that
            // left off" wrote "That's the trade-off we mentioned" into its first answer.
            $prompt = $soFar . $lead . "Write a FAQ section for a blog post titled \"{$title}\". Provide {$pairs} concise "
                . "question-and-answer pairs in Markdown (bold question, then answer), about {$budget['faq']} words in "
                . "total. Do NOT repeat the section heading.{$covered}"
                . $povTxt . $toneTxt . $rulesTxt . $short . $ctxTxt;

            return $this->ai_text($prompt, $settings, $this->tokens_for($budget['faq']));
        }

        if ($heading === self::INTRO) {
            $budget = self::budget($target, $withFaq, true);
            $kwTxt  = $keyword !== '' ? " If one of these fits naturally, use it once: {$keyword}." : '';
            $next   = $listed !== '' ? " For your orientation only — do not name or list them — the sections that follow are: {$listed}." : '';

            $prompt = $lead . "Write ONLY the opening of a blog post titled \"{$title}\": about {$budget['intro']} words, "
                . 'one or two short paragraphs, no heading. Say what the reader will come away with and why it '
                . "matters to them. Do not write \"In this post\" or \"This guide covers\".{$next}"
                . $povTxt . $toneTxt . $kwTxt . $rulesTxt . $short . $ctxTxt;

            return $this->ai_text($prompt, $settings, $this->tokens_for($budget['intro']));
        }

        if ($heading === self::OUTRO) {
            $budget  = self::budget($target, $withFaq, true);
            $lang    = trim((string) ($settings['language'] ?? ''));
            $inLang  = $lang !== '' ? " in {$lang}" : '';
            $covered = $listed !== '' ? " The sections were: {$listed}." : '';

            $prompt = $soFar . $lead . "Write ONLY the closing section of a blog post titled \"{$title}\": about {$budget['outro']} words. "
                . "Start with a ## heading of one to five words{$inLang} — \"Conclusion\" or something more specific "
                . "to this post — then the text.{$covered} Pull together what the post established, using its own "
                . 'figures, and end on the one thing the reader should do next. No new topics, no "In conclusion", '
                . 'and do not narrate the article ("you have now read", "we covered").'
                . $povTxt . $toneTxt . $rulesTxt . $short . $ctxTxt;

            return $this->ai_text($prompt, $settings, $this->tokens_for($budget['outro']));
        }

        // A per-section target derived from the word count, when the caller worked one out.
        $per = (int) ($settings['section_words'] ?? 0);
        if ($per <= 0) {
            $per = self::PER_SECTION;
        }

        $kwTxt = $keyword !== ''
            ? " Keywords for the whole post: {$keyword}. Use one here only if this section is really about it, and no more than once; otherwise leave them out."
            : '';

        $where = '';
        if (count($headings) > 1) {
            $pos   = array_search($heading, $headings, true);
            $where = " The post's sections, in order: {$listed}."
                . ($pos !== false ? ' This is section ' . ($pos + 1) . ' of ' . count($headings) . '.' : '')
                . " Cover only this section's subject and leave the others' to them.";
        }

        // A section written in isolation is where continuity goes first, so each one is handed the
        // tail of the part before it. Both the queue and the assistant send it.
        $continuity = $rulesTxt !== '' && array_key_exists('previous_tail', $settings)
            ? HouseStyle::continuity((string) $settings['previous_tail'])
            : '';

        // The template's sentence goes BEFORE the rules. It used to be added last, which put it on
        // the end of the final bullet of the house style.
        $prompt = $soFar . $lead . "Write ONLY the body text for the \"{$heading}\" section of a blog post titled "
            . "\"{$title}\". About {$per} words, in Markdown. Do NOT output the heading "
            . "itself, the post title, or a conclusion for the whole post.{$where}"
            . $povTxt . $toneTxt . $kwTxt . $tpl['section'] . $rulesTxt . $continuity . $ctxTxt;

        return $this->ai_text($prompt, $settings, $this->tokens_for($per));
    }


    /** Combine title + opening + optional TOC + sections into the final Markdown. Local — no AI, no timeout. */
    private function assemble(string $title, array $outline, array $sections, array $settings): string
    {
        // A queued job creates the post itself, so the H1 is how the article gets a visible title.
        // The assistant hands its markdown to an editor where the post ALREADY has a title, and the
        // rewritten prompts now promise not to repeat it — so that path asks for the heading off.
        // Defaults to on, which is what every existing caller gets.
        $parts = [];
        if (!array_key_exists('title_heading', $settings) || !empty($settings['title_heading'])) {
            $parts[] = '# ' . $title;
        }

        $outline  = array_values($outline);
        $sections = array_values(array_map(static fn ($s) => trim((string) $s), $sections));

        // The opening paragraphs sit above the contents list, where a reader meets them first.
        $bodies = $sections;
        if (($outline[0] ?? null) === self::INTRO && isset($bodies[0])) {
            $parts[] = $bodies[0];
            unset($bodies[0]);
        }

        if (!empty($settings['toc_include'])) {
            // A heading the outline named is listed whether or not it has been written yet — that
            // is how a run that stopped early still shows what is missing. The single block and the
            // closing section have no heading in the outline (the model writes them), so theirs are
            // read off the text once it exists. A single-block post used to get no contents list at
            // all, with the switch on.
            $entries = [];
            foreach ($outline as $i => $h) {
                $h = (string) $h;
                if ($h === self::INTRO) {
                    continue;
                }
                if ($h === self::SINGLE || $h === self::OUTRO) {
                    foreach (self::h2s($sections[$i] ?? '') as $found) {
                        $entries[] = $found;
                    }
                    continue;
                }
                if (self::is_faq($h) && (self::has_faq($entries) || (isset($sections[$i]) && $sections[$i] === ''))) {
                    continue; // written inside the single block, and listed from there
                }
                $entries[] = $h;
            }

            if (!empty($entries)) {
                $toc = "## Table of Contents\n";
                foreach ($entries as $h) {
                    $toc .= '- [' . $h . '](#' . sanitize_title($h) . ")\n";
                }
                $parts[] = trim($toc);
            }
        }

        foreach ($bodies as $s) {
            if ($s !== '') {
                $parts[] = $s;
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * The H2 headings in a piece of Markdown, in order.
     *
     * @return string[]
     */
    private static function h2s(string $markdown): array
    {
        if (!preg_match_all('/^##[ \t]+(.+?)[ \t#]*$/mu', $markdown, $m)) {
            return [];
        }

        $out = [];
        foreach ($m[1] as $h) {
            $h = trim((string) $h, " \t*_");
            if ($h !== '') {
                $out[] = $h;
            }
        }

        return $out;
    }

    /**
     * The article so far, short enough to hand to the next request.
     *
     * Whole when it fits; otherwise the most recent part of it, cut at a paragraph. Eight thousand
     * characters is about 1,300 words — every article up to roughly 2,000 words fits whole.
     */
    private static function clip_written(string $text, int $cap = 8000): string
    {
        $text = trim($text);
        if ($text === '' || mb_strlen($text) <= $cap) {
            return $text;
        }

        $tail = mb_substr($text, -$cap);
        $cut  = mb_strpos($tail, "\n\n");

        return '[…]' . ($cut !== false ? mb_substr($tail, $cut) : "\n\n" . $tail);
    }

    /** One written part as it sits in the article: under its heading, or bare where it has none. */
    private static function section_markdown(string $heading, string $body): string
    {
        $body = trim($body);

        if ($heading === self::SINGLE) {
            return $body;
        }

        if ($heading === self::INTRO) {
            // The opening has no heading. A model that adds one anyway has it taken off.
            return trim((string) preg_replace('/\A(?:#{1,6}[ \t]+.*\R+)+/u', '', $body));
        }

        if ($heading === self::OUTRO) {
            // The closing writes its own heading, so it is in the post's language rather than a
            // hardcoded English word. Whatever level it came back at becomes an H2; a reply with
            // no heading at all gets the plain one.
            if (preg_match('/\A#{1,6}[ \t]+(.+?)[ \t#]*\R/u', $body, $m)) {
                return '## ' . trim($m[1]) . "\n\n" . trim((string) substr($body, strlen($m[0])));
            }

            return "## Conclusion\n\n{$body}";
        }

        // A section is told not to output a heading and sometimes writes one anyway. An H2 inside a
        // section is a heading the contents list does not know about, so it goes down a level; a
        // first line that only repeats the section's own heading is dropped.
        $body = (string) preg_replace('/\A#{1,6}[ \t]+' . preg_quote($heading, '/') . '[ \t#]*\R+/ui', '', $body);
        $body = (string) preg_replace('/^#{1,2}[ \t]+/mu', '### ', $body);

        return "## {$heading}\n\n" . trim($body);
    }


    // ---- AI plumbing ---------------------------------------------------------------------

    /** One short AI call → plain text. Throws on failure so tick() can retry/fail the job. */
    private function ai_text(string $prompt, array $settings, int $max_tokens, int $slept = 0): string
    {
        if (!class_exists('WPWand\Generation\Generator')) {
            self::load_generator();
        }
        if (!class_exists('WPWand\Generation\Generator')) {
            throw new \RuntimeException(__('Nothing can write yet. Add an API key in Settings first.', 'ai-content-generation')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught internally, becomes the row's reason
        }

        // No single call may ask for more than the Longest reply the user configured. Only the
        // assistant path sets a cap today; a queued job leaves it unset and keeps the budget it has
        // always had.
        $cap = (int) ($settings['max_tokens_cap'] ?? 0);
        if ($cap > 0 && $max_tokens > $cap) {
            $max_tokens = $cap;
        }

        $args = ['max_tokens' => $max_tokens];
        if (!empty($settings['language'])) {
            $args['language'] = (string) $settings['language'];
        }

        $res = \WPWand\Generation\Generator::generate($prompt, 1, $args);

        if (is_object($res) && isset($res->error)) {
            $limit = RateLimited::from($res->error, self::after_the_wait($settings));
            if ($limit !== null) {
                // The assistant has no queue to wait in, so a short pause is sat out here, twice at
                // most. A queued job is paused by tick() instead, where the page can say so.
                if (!empty($settings['sync']) && !$limit->long && $limit->wait <= RateLimited::SHORT && $slept < 2) {
                    $slept++;
                    sleep($limit->wait + 1);
                    return $this->ai_text($prompt, $settings, $max_tokens, $slept);
                }
                throw $limit;
            }

            $msg = \WPWand\Generation\ErrorFormatter::humanize($res->error, __('The provider sent back an error it didn’t explain.', 'ai-content-generation'));
            throw new \RuntimeException($msg); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught internally
        }

        // Keep the first failover explanation of the run. run_article() hands it to its caller,
        // which is the only way an assistant generation can say it was billed to a second account.
        if (null === $this->run_failover && is_object($res) && isset($res->wpwand_failover)) {
            $this->run_failover = $res->wpwand_failover;
        }

        $text = '';
        if (is_object($res) && isset($res->choices[0])) {
            $choice = $res->choices[0];
            $text   = isset($choice->message->content) ? (string) $choice->message->content
                : (isset($choice->text) ? (string) $choice->text : '');
        }

        $text = trim($text);
        if ($text === '') {
            // A reasoning model can answer 200 with an empty `content` when the whole token budget
            // went to reasoning_content. Retrying the same step usually succeeds, so this stays a
            // normal failure the tick loop can retry — but the sentence has to be one a user can read.
            // Nothing retries an assistant run, so it gets told to run it again itself rather than
            // promised a retry that will never come.
            $msg = empty($settings['sync'])
                ? __('The AI sent back nothing for this section. It’ll be tried again. If it keeps happening, lower the word count or pick a different model.', 'ai-content-generation')
                : __('The AI sent back nothing for one section. Run it again, and if it keeps happening, lower the word count or pick a different model.', 'ai-content-generation');
            throw new \RuntimeException($msg); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught internally, never echoed
        }

        return $text;
    }

    /**
     * What the reader can do once a provider's limit has lifted, as one sentence.
     *
     * It names a button, so it has to be true of the screen the failure is read on: Bulk Posts has
     * "Try again" on a failed row, the assistant has nothing but running it again, and a failed
     * scheduled post has neither — its schedule writes a new one on the next run.
     *
     * @param array<string, mixed> $settings
     */
    private static function after_the_wait(array $settings): string
    {
        if (!empty($settings['sync'])) {
            return __('Run it again then.', 'ai-content-generation');
        }
        if (!empty($settings['schedule_id'])) {
            return '';
        }

        return __('Press Try again then and the post picks up where it stopped.', 'ai-content-generation');
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
        // Room for the words asked for and for a model that writes half as much again. On
        // 2026-10-02 Gemini 2.5 Flash answered a request for 650 words with 962 and was cut off at
        // the old ceiling (finish_reason "length", 1,425 tokens) — the same request used to ask for
        // 800 and was given more room than the smaller one got. About 1.5 tokens a word, doubled.
        // It is a ceiling, not a spend: a model that stops at 650 words is charged for 650.
        return (int) min(4000, max(600, $words * 3));
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
            // A retried job is paid for again. When it failed, refund_failure() gave the allowance
            // back and stamped the job; without re-charging here a free user could fail, take the
            // refund, hit Retry, succeed, and keep the post for nothing — repeatable per row, so the
            // five-a-month cap would stop being a cap. Re-charge exactly what was given back, and
            // clear the stamp so a second failure can refund a second time.
            $this->recharge_retry($job_id);

            // Pick up where it stopped. This used to reset the job to step 0 and throw the outline
            // and every written section away, so a post that failed on its last request was written
            // again from its first — on a key with twenty requests a day, that is the day.
            $job      = $wpdb->get_row(
                $wpdb->prepare("SELECT title, outline, sections, settings FROM {$this->jobs_table()} WHERE id = %d", $job_id), // phpcs:ignore
                ARRAY_A
            );
            $outline  = json_decode((string) ($job['outline'] ?? ''), true);
            $sections = json_decode((string) ($job['sections'] ?? ''), true);

            if (is_array($outline) && !empty($outline) && is_array($sections) && !empty($sections) && count($sections) <= count($outline)) {
                $this->update_job($job_id, [
                    'status'   => 'pending',
                    'attempts' => 0,
                    'step'     => count($sections) + 1,
                    'error'    => '',
                ]);
                $settings = json_decode((string) ($job['settings'] ?? ''), true) ?: [];
                $this->update_post($row_id, $this->assemble((string) $job['title'], $outline, $sections, $settings), 'pending');

                return true;
            }

            // Nothing was written yet, so there is nothing to keep — start from the outline.
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

    private function update_post(int $row_id, string $content, string $status, ?int $words = null): void
    {
        global $wpdb;
        $data = ['content' => $content, 'status' => $status];

        // Only the finished write carries a length. A partial mirror and a failure both leave the
        // column alone rather than storing the word count of half an article.
        if (null !== $words) {
            $data['word_count'] = $words;
        }

        $wpdb->update($this->posts_table(), $data, ['id' => $row_id]);
    }

    /**
     * Strip a promise of a retry from a message that is about to be stored as final.
     *
     * ai_text() writes "It will be retried" because, at the moment it throws, that is true — the
     * tick loop picks the job up again. After MAX_ATTEMPTS it is no longer true, and the sentence
     * outlives the thing it described. Say what happened and what is left to do instead.
     */
    private static function without_retry_promise(string $message, int $attempts): string
    {
        if (stripos($message, 'will be retried') === false) {
            return $message;
        }

        return sprintf(
            /* translators: %d: how many times the generation was attempted */
            __(
                'The AI sent back nothing for this section, %d times running. Lower the word count or pick a different model, then run it again.',
                'ai-content-generation'
            ),
            $attempts
        );
    }

    private function fail(array $job, string $error): void
    {
        $attempts = (int) $job['attempts'] + 1;
        if ($attempts >= self::MAX_ATTEMPTS) {
            // Do NOT humanize here. fail() has one caller (tick(), with $e->getMessage()), and every
            // exception that reaches it was already formatted — ai_text() runs the provider envelope
            // through humanize() before throwing. A second pass re-wrapped its own output and the
            // user read the message doubled.
            $human = $error !== '' ? $error : __('It didn’t finish, and no reason came back.', 'ai-content-generation');
            // The message was written for a run that still had attempts left, and by the time
            // anyone reads it the retrying is over. A user opening the Automated Posts modal to
            // find out what went wrong was being told to wait for something that would never come.
            $human = self::without_retry_promise($human, $attempts);
            $this->update_job($job['id'], ['status' => 'failed', 'attempts' => $attempts, 'error' => $human]);
            // Store the failure reason in the post row's content so the Bulk list popup can show
            // *why* it failed (the row keeps its own title column, so content is free to reuse).
            $this->update_post($job['row_id'], $human, 'failed');
            // This is the one place a generation is finally given up on, so it is the one place the
            // allowance is given back. Runs after the row is marked failed, so the run-level check
            // below counts this job among the failures.
            $this->refund_failure($job);
            $this->count_schedule_failure($job);
            return;
        }
        // Leave it pending for the next tick to retry.
        $this->update_job($job['id'], ['status' => 'pending', 'attempts' => $attempts, 'error' => $error]);
    }

    /**
     * Return the allowance a permanently failed job was charged, once and only once.
     *
     * The allowance is spent when posts are queued, not when they arrive, so a job that ends here
     * has been paid for and produced nothing. Every tier is billed per RUN — one unit for the whole
     * batch however many posts it queued (Pro was billed per post until 2026-09-13). The unit only
     * comes back when every job in that batch has failed; if any of them was written, the run
     * delivered work and the unit stands.
     *
     * Jobs queued before the run id existed carry no batch to group by. Those are skipped rather
     * than guessed at: refunding one unit per job would hand back allowance that was never charged.
     */
    /**
     * Take the allowance back for a job the user has asked to run again.
     *
     * The mirror of {@see refund_failure()}. It only does anything when that ran: a job that was
     * never refunded — because a sibling in its run delivered, or because it predates the stamp —
     * was never given anything back, so retrying it costs nothing extra.
     */
    private function recharge_retry(int $job_id): void
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT id, settings FROM {$this->jobs_table()} WHERE id = %d", $job_id), // phpcs:ignore
            ARRAY_A
        );
        if (!$row) {
            return;
        }

        $settings = json_decode((string) $row['settings'], true) ?: [];
        if (empty($settings[self::REFUND_KEY])) {
            return; // Nothing was given back, so there is nothing to take.
        }

        $automation = ($settings['schedule_id'] ?? '') !== '';
        if ($automation) {
            UsageLimits::consume_automation_run();
        } else {
            UsageLimits::consume_bulk_run();
        }

        // Clear the stamp last: if this dies halfway the job stays marked and a second failure
        // simply will not refund again, which costs the user one unit rather than paying twice.
        unset($settings[self::REFUND_KEY]);
        $wpdb->update( // phpcs:ignore
            $this->jobs_table(),
            ['settings' => wp_json_encode($settings)],
            ['id' => $job_id],
            ['%s'],
            ['%d']
        );
    }

    /**
     * Add one to the failure tally of the schedule this job belonged to, once.
     *
     * The Automated Posts row used to show `failures.length`, an array the endpoint caps at twenty
     * and which the modal reads for its own purposes. A schedule that had failed thirty times
     * reported twenty, and the number moved when someone cleared a failure from the modal. This
     * counts the giving-up, which happens exactly once per job.
     */
    private function count_schedule_failure(array $job): void
    {
        global $wpdb;

        // Re-read the settings rather than trusting the copy handed in. `refund_failure()` runs
        // first and writes its own stamp into this same JSON blob; writing the stale array back
        // would erase it, and the guard that stops a run being refunded twice is the user's money.
        $stored   = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT settings FROM {$this->jobs_table()} WHERE id = %d", // phpcs:ignore
            (int) $job['id']
        ));
        $settings = json_decode($stored, true) ?: [];
        $id       = (string) ($settings['schedule_id'] ?? '');

        if ($id === '' || !empty($settings[self::COUNTED_KEY])) {
            return;
        }

        $schedule = \WPWand\Automation\Schedules::get($id);
        if ($schedule) {
            \WPWand\Automation\Schedules::update($id, [
                'posts_failed' => (int) ($schedule['posts_failed'] ?? 0) + 1,
            ]);
        }

        $settings[self::COUNTED_KEY] = true;
        $this->update_job((int) $job['id'], ['settings' => wp_json_encode($settings)]);
    }

    private function refund_failure(array $job): void
    {
        global $wpdb;

        $settings = json_decode((string) $job['settings'], true) ?: [];

        if (!empty($settings[self::REFUND_KEY])) {
            return; // Already given back — tick() can reach fail() again for the same job.
        }

        $automation = ($settings['schedule_id'] ?? '') !== '';

        // One unit was charged for the whole run, on every tier, so it goes back once and only
        // when nothing from the run is still coming.
        $run_id = (string) ($settings[self::RUN_KEY] ?? '');
        if ($run_id === '') {
            return;
        }

        $t        = $this->jobs_table();
        $run_like = '%' . $wpdb->esc_like('"' . self::RUN_KEY . '":"' . $run_id . '"') . '%';

        $delivered = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$t} WHERE settings LIKE %s AND status <> 'failed'", // phpcs:ignore
            $run_like
        ));
        if ($delivered > 0) {
            return; // Something from this run is still coming, or already arrived.
        }

        $refunded = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$t} WHERE settings LIKE %s AND settings LIKE %s", // phpcs:ignore
            $run_like,
            '%' . $wpdb->esc_like('"' . self::REFUND_KEY . '":true') . '%'
        ));
        if ($refunded > 0) {
            return; // A sibling already gave the run's single unit back.
        }

        // Mark first, then pay. A crash between the two costs one refund; the other order could pay
        // twice, and the counter is the user's money either way.
        $settings[self::REFUND_KEY] = true;
        $this->update_job($job['id'], ['settings' => wp_json_encode($settings)]);

        if ($automation) {
            UsageLimits::refund_automation_run();
            return;
        }
        UsageLimits::refund_bulk_run();
    }
}
