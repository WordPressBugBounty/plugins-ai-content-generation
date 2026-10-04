<?php

namespace WPWand\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WPWand\Generation\JobRunner;
use WPWand\Generation\WordCount;

/**
 * /wpwand/v1/bulk-posts — bulk post generation (free, capped; Pro lifts the caps).
 *
 * Rows go into the legacy {prefix}wpwand_generated_post table, but generation itself runs on
 * the step-based JobRunner queue (outline → sections → optional TOC/FAQ → conclusion → row
 * status 'done'), drained by the page heartbeat or by wp-cron. The old Action Scheduler route
 * ('wpwand_bulk_post_schedule', group 'wpwand_bulk_sheduler') is gone: its handler went with
 * the legacy Post_Generator, so anything queued onto that hook was never generated.
 *
 * - POST /bulk-posts/titles  { topic, count }                  → title options
 * - POST /bulk-posts         { titles[], tone, keyword, ... }  → queue jobs
 * - GET  /bulk-posts                                           → recent rows
 * - GET  /bulk-posts/progress?ids=1,2                          → per-row status + totals
 */
final class BulkController extends AbstractController
{
    protected string $rest_base = 'bulk-posts';

    public function register_routes(): void
    {
        register_rest_route($this->rest_namespace, '/' . $this->rest_base, [
            ['methods' => 'GET',  'callback' => [$this, 'index'], 'permission_callback' => [$this, 'can_use']],
            ['methods' => 'POST', 'callback' => [$this, 'queue'], 'permission_callback' => [$this, 'can_use']],
        ]);
        register_rest_route($this->rest_namespace, '/' . $this->rest_base . '/titles', [
            ['methods' => 'POST', 'callback' => [$this, 'titles'], 'permission_callback' => [$this, 'can_use']],
        ]);
        register_rest_route($this->rest_namespace, '/' . $this->rest_base . '/progress', [
            ['methods' => 'GET', 'callback' => [$this, 'progress'], 'permission_callback' => [$this, 'can_use']],
        ]);
        register_rest_route($this->rest_namespace, '/' . $this->rest_base . '/tick', [
            ['methods' => 'POST', 'callback' => [$this, 'tick'], 'permission_callback' => [$this, 'can_use']],
        ]);
        register_rest_route($this->rest_namespace, '/' . $this->rest_base . '/(?P<id>\d+)', [
            ['methods' => 'GET',    'callback' => [$this, 'show'],    'permission_callback' => [$this, 'can_use']],
            ['methods' => 'DELETE', 'callback' => [$this, 'destroy'], 'permission_callback' => [$this, 'can_use']],
        ]);
        register_rest_route($this->rest_namespace, '/' . $this->rest_base . '/(?P<id>\d+)/approve', [
            ['methods' => 'POST', 'callback' => [$this, 'approve'], 'permission_callback' => [$this, 'can_use']],
        ]);
        // Re-queue a failed bulk post so the engine generates it again (manual restart).
        register_rest_route($this->rest_namespace, '/' . $this->rest_base . '/(?P<id>\d+)/retry', [
            ['methods' => 'POST', 'callback' => [$this, 'retry'], 'permission_callback' => [$this, 'can_use']],
        ]);
    }

    public function destroy(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $id = absint($request['id']);
        $ok = $wpdb->delete($this->table(), ['id' => $id], ['%d']);

        return new WP_REST_Response(['deleted' => (bool) $ok], 200);
    }

    /** Re-queue a failed bulk post so the engine regenerates it (manual restart). */
    public function retry(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $id  = absint($request['id']);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id), ARRAY_A); // phpcs:ignore
        if (!$row) {
            return new WP_REST_Response(['error' => __('Not found.', 'ai-content-generation')], 404);
        }

        if (!(new JobRunner())->retry($id)) {
            return new WP_REST_Response(['error' => __('Nothing to retry for this post.', 'ai-content-generation')], 200);
        }

        // Always arm the new-engine WP-Cron drainer so the re-queued job processes even when the
        // browser heartbeat isn't running (Action-Scheduler-configured site, or the page is closed).
        // On the default 'browser' engine the heartbeat also picks it up immediately.
        if (!wp_next_scheduled('wpwand_gen_cron_tick')) {
            wp_schedule_event(time() + 30, 'wpwand_minutely', 'wpwand_gen_cron_tick');
        }

        return new WP_REST_Response(['retried' => true, 'id' => $id], 200);
    }

    public function show(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $id  = absint($request['id']);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id), ARRAY_A); // phpcs:ignore
        if (!$row) {
            return new WP_REST_Response(['error' => __('Not found.', 'ai-content-generation')], 404);
        }

        $content = (string) $row['content'];

        return new WP_REST_Response([
            'id'         => (int) $row['id'],
            'title'      => (string) $row['title'],
            'content'    => $content,
            'status'     => (string) $row['status'],
            'template'   => self::template_label($row['template'] ?? null),
            'word_count' => self::words_for($row),
        ], 200);
    }

    /**
     * Approve = create a draft post from the stored content (mirrors the legacy
     * "Add to Post"), set the featured image, and stamp post_id so it leaves the list.
     */
    public function approve(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $id  = absint($request['id']);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id), ARRAY_A); // phpcs:ignore
        if (!$row || trim((string) $row['title']) === '') {
            return new WP_REST_Response(['error' => __('Nothing to approve.', 'ai-content-generation')], 400);
        }

        $post_id = wp_insert_post([
            'post_title'   => (string) $row['title'],
            'post_content' => \WPWand\Generation\Markdown::to_post_content((string) $row['content']),
            'post_status'  => 'draft',
            'post_author'  => get_current_user_id(),
        ]);

        if (!$post_id || is_wp_error($post_id)) {
            return new WP_REST_Response(['error' => __('Could not create the post.', 'ai-content-generation')], 200);
        }

        if (!empty($row['featured_image_id'])) {
            set_post_thumbnail($post_id, (int) $row['featured_image_id']);
        }

        $wpdb->update($this->table(), ['post_id' => $post_id], ['id' => $id]);

        return new WP_REST_Response([
            'post_id'  => (int) $post_id,
            'edit_url' => self::edit_url((int) $post_id),
        ], 200);
    }

    /** Where a row's draft lives, or '' when no draft has been made from it yet. */
    private static function edit_url(int $post_id): string
    {
        return $post_id > 0
            ? admin_url('post.php?post=' . $post_id . '&action=edit')
            : '';
    }

    /**
     * The rows this screen owns.
     *
     * Bulk Posts and Automated Posts write to the same table. This list used to filter on
     * `post_id = 0`, which kept automated posts out as a side effect — they get a post id the
     * moment they are finalised — and which also threw a bulk row out the instant a draft was made
     * from it. That was the bug: the row vanished, and vanishing reads as "deleted", not "done".
     *
     * So the filter is now the question it was always standing in for. A row stamped `bulk` is
     * ours whatever its post id. A row written before the column existed has no stamp, and for
     * those the old rule is kept exactly as it was, so nothing that is on the screen today
     * disappears from it and nothing new arrives.
     */
    private const MINE = "(source = 'bulk' OR (source IS NULL AND post_id = 0))";

    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'wpwand_generated_post';
    }

    /**
     * The generation engine to run. 'action_scheduler' was removed once it became clear nothing
     * handled the hook it queued onto, so a site that had already picked it reads back as
     * 'browser' — the queue drains and the client keeps its heartbeat instead of stalling.
     */
    private function engine(): string
    {
        $engine = (string) get_option('wpwand_gen_engine', 'browser');
        return in_array($engine, ['browser', 'wp_cron', 'system_cron'], true) ? $engine : 'browser';
    }

    public function titles(WP_REST_Request $request): WP_REST_Response
    {
        if (!class_exists('WPWand\Generation\Generator')) {
            return new WP_REST_Response(['error' => __('Nothing can write yet. Add an API key in Settings first.', 'ai-content-generation')], 503);
        }

        $topic = sanitize_text_field((string) $request->get_param('topic'));
        // As many headlines as a run may hold on this tier; fifty where there is no ceiling, since
        // this is one request to the model. It used to be a silent ten for everyone.
        $per_run = \WPWand\Generation\UsageLimits::bulk_per_run();
        $count   = max(1, min($per_run > 0 ? $per_run : 50, absint($request->get_param('count') ?: 3)));
        if ($topic === '') {
            return new WP_REST_Response(['error' => __('Say what the batch is about first.', 'ai-content-generation')], 400);
        }

        $language = get_option('wpwand_language') ?: 'English';
        $content  = \WPWand\Generation\Generator::generate(
            "I will give a topic and you will write only one high converting blog title. This title should have a hook and high potential to go viral on social media. My topic is {$topic}. You must write in {$language}.",
            $count
        );

        if (is_object($content) && isset($content->error)) {
            $msg = \WPWand\Generation\ErrorFormatter::humanize($content->error, __('Couldn’t come up with headlines. Try again.', 'ai-content-generation'));
            return new WP_REST_Response(['error' => $msg], 200);
        }

        $titles = [];
        if (is_object($content) && isset($content->choices)) {
            foreach ($content->choices as $choice) {
                $t = isset($choice->message->content) ? $choice->message->content : ($choice->text ?? '');
                $t = trim(trim((string) $t), '"');
                if ($t !== '') {
                    $titles[] = $t;
                }
            }
        }

        return new WP_REST_Response(['titles' => $titles], 200);
    }

    public function queue(WP_REST_Request $request): WP_REST_Response
    {
        $titles = (array) $request->get_param('titles');
        $titles = array_values(array_filter(array_map(static fn ($t) => sanitize_text_field((string) $t), $titles)));
        if (empty($titles)) {
            return new WP_REST_Response(['error' => __('Tick at least one title.', 'ai-content-generation')], 400);
        }

        $is_pro = \WPWand\Generation\UsageLimits::is_pro();

        // CAP B — per-run: a run may hold at most bulk_per_run() posts on this tier (10 free, 20 Solo,
        // 50 Growth, no ceiling on Agency). Over it → block before anything is queued. Free gets the
        // upgrade modal; a paid tier gets the sentence itself, since "Upgrade to Pro" is not its answer.
        $per_run = \WPWand\Generation\UsageLimits::bulk_per_run();
        if ($per_run > 0 && count($titles) > $per_run) {
            return new WP_REST_Response([
                'error'   => $is_pro
                    /* translators: %d: how many posts one bulk run may hold on this plan, e.g. 20. */
                    ? sprintf(__('A bulk run stops at %d posts on your plan.', 'ai-content-generation'), $per_run)
                    /* translators: %d: the free per-run post limit, e.g. 10. */
                    : sprintf(__('A bulk run stops at %d posts. Upgrade to Pro to write more at once.', 'ai-content-generation'), $per_run),
                'upgrade' => ! $is_pro,
            ], 429);
        }

        // CAP A — monthly: every tier counts RUNS (5 on free, 100 / 300 by Pro tier; Agency = ∞ (-1)).
        // remaining === 0 means "not unlimited and nothing left" → block before enqueue.
        $remaining = \WPWand\Generation\UsageLimits::bulk_remaining();
        if ($remaining === 0) {
            return new WP_REST_Response([
                'error'   => \WPWand\Generation\UsageLimits::limit_reached_message('bulk'),
                'upgrade' => true,
            ], 429);
        }

        // Nothing is clamped to the allowance: a run costs one unit whatever its size, on every
        // tier. Pro used to be clamped to its remaining post allowance here (until 2026-09-13).

        $word_count = absint($request->get_param('word_count'));

        // The shape every post in this batch is written to. Anything the build does not know —
        // an empty field, or a key from a newer release — falls back to the blog shape, which is
        // what every batch generated before the field existed.
        $template = sanitize_text_field((string) $request->get_param('template'));
        if (!isset(JobRunner::TEMPLATES[$template])) {
            $template = JobRunner::TEMPLATE_FALLBACK;
        }

        $settings   = [
            'tone'        => sanitize_text_field((string) $request->get_param('tone')),
            'keyword'     => sanitize_text_field((string) $request->get_param('keyword')),
            'toc_include' => (bool) $request->get_param('toc_include'),
            'faq_include' => (bool) $request->get_param('faq_include'),
            'word_count'  => $word_count,
            'language'    => sanitize_text_field((string) $request->get_param('language')),
            'template'    => $template,
            // The house style. Every batch was written without it — `GenerateController` built it
            // for the Write with AI button and nothing built it here, so `JobRunner` read an empty
            // string and a queued post was held to no rules at all: no ceiling on exclamation
            // marks, no named list of words to avoid, no "two actionable numbers", and none of the
            // About your business text the user had already typed into Settings. Same site, same
            // settings, two different qualities depending on which button was pressed.
            //
            // A batch is always running prose, so unlike the assistant there is no template here
            // that should be exempt — the exempt ones are headlines and meta descriptions, and
            // this endpoint does not write those. `HouseStyle::rules()` drops the business and
            // audience blocks itself when they are blank, so a free install sends the rules and
            // nothing empty gets quoted into the prompt.
            'style_rules' => \WPWand\Generation\HouseStyle::rules(\WPWand\Generation\HouseStyle::owner_context()),
        ];

        // Toggleable engine (Settings → experimental). Default 'browser' = step-queue driven by
        // the page heartbeat; reliable on low-config hosts and immune to long-content timeouts.
        $engine = $this->engine();

        // Step-based job queue (sectioned generation, no long requests). A row id of 0 comes back
        // when the insert itself failed, so drop those before anything counts or polls them.
        $ids = array_values(array_filter((new JobRunner())->enqueue($titles, $settings)));

        // Nothing queued means nothing will generate, so don't spend the allowance on it.
        if (empty($ids)) {
            return new WP_REST_Response([
                'error' => __('Could not add these posts to the queue. Try again.', 'ai-content-generation'),
            ], 500);
        }

        // Count this bulk generation against the monthly allowance, now that the queue exists:
        // one run, whatever its size.
        \WPWand\Generation\UsageLimits::consume_bulk_run();
        update_option('wpwand_pgc_task_completed', 0);
        update_option('wpwand_pgc_total_queue', count($ids));

        // wp_cron (pseudo-cron, fires on traffic) and system_cron (real server crontab hitting
        // wp-cron.php) both drain via the same scheduled event — they differ only in how reliably
        // WordPress's cron is triggered, which is a server-config concern, not a code path.
        if ($engine === 'wp_cron' || $engine === 'system_cron') {
            if (!wp_next_scheduled('wpwand_gen_cron_tick')) {
                wp_schedule_event(time() + 30, 'wpwand_minutely', 'wpwand_gen_cron_tick');
            }
        }

        return new WP_REST_Response(['queued' => count($ids), 'ids' => $ids, 'engine' => $engine], 200);
    }

    /**
     * Advance the step-based queue by one step. Called repeatedly by the browser heartbeat (the
     * 'browser' engine) — each call does a single short AI step, so nothing ever times out.
     */
    public function tick(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response((new JobRunner())->tick(), 200);
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        // Every row, including the ones a draft has been made from. They used to be filtered out
        // with `WHERE post_id = 0`, so pressing the button made the row disappear — the only sign
        // anything had happened, and it read as "deleted" rather than "done". The row stays and
        // says Draft made, with a way through to the draft.
        $mine = self::MINE;
        $rows = $wpdb->get_results("SELECT id, title, content, status, template, word_count, post_id, created_at, UNIX_TIMESTAMP(created_at) AS created_ts FROM {$this->table()} WHERE {$mine} ORDER BY created_at DESC LIMIT 100", ARRAY_A); // phpcs:ignore

        // How many rows exist behind the LIMIT 100 above, so the list can say when it's truncated
        // instead of silently dropping anything past the cap. One extra query, not one per row.
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table()} WHERE {$mine}"); // phpcs:ignore

        // Background generation in progress? Matches the legacy "Generating Bulk Post…" header.
        // The active engine decides what "running" means; OR the per-row fallback either way.
        $engine  = $this->engine();
        $running = (new JobRunner())->is_running('bulk');
        $running = $running || (bool) array_filter($rows ?: [], static fn ($r) => 'done' !== $r['status'] && 'failed' !== $r['status']);

        return new WP_REST_Response([
            'items'           => $this->shape($rows),
            'total'           => $total,
            'limit_text'      => \WPWand\Generation\UsageLimits::bulk_text(),
            // The same sentence Automated Posts shows, built the same way. "3/5" never said what it
            // was counting. Every tier counts runs since 2026-09-13.
            'used_label'      => self::usage_label(),
            // On Pro the chip says what was bought rather than counting down to a number nobody
            // is watching. Empty on free, where the count is the thing that matters.
            'plan'            => \WPWand\Generation\UsageLimits::plan_name(),
            // The monthly cap as a number, in runs on every tier; the wizard quotes it on Pro.
            'monthly_limit'   => \WPWand\Generation\UsageLimits::bulk_limit(),
            'can_create'      => \WPWand\Generation\UsageLimits::can_bulk(),
            // The date the count comes back, and how much is left as a number rather than inside a
            // sentence. Both only reached the client inside a 429 before, which is to say only
            // after someone had already been stopped. Same key name Automated Posts uses.
            'resets_on'       => \WPWand\Generation\UsageLimits::reset_date(),
            'remaining'       => \WPWand\Generation\UsageLimits::bulk_remaining(),
            // The most headlines one run may hold on this tier (-1 = no ceiling). The wizard reads
            // it rather than a hardcoded list the server then quietly clamps.
            'per_run_max'     => \WPWand\Generation\UsageLimits::bulk_per_run(),
            'process_running' => $running,
            'engine'          => $engine,
            'queue_total'     => (int) get_option('wpwand_pgc_total_queue', 0),
            'queue_done'      => (int) get_option('wpwand_pgc_task_completed', 0),
        ], 200);
    }

    public function progress(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $ids = array_filter(array_map('absint', explode(',', (string) $request->get_param('ids'))));

        if (empty($ids)) {
            return new WP_REST_Response(['items' => [], 'total' => 0, 'done' => 0], 200);
        }

        $in   = implode(',', $ids);
        $rows = $wpdb->get_results("SELECT id, title, status, post_id FROM {$this->table()} WHERE id IN ({$in}) ORDER BY id ASC", ARRAY_A); // phpcs:ignore
        $items = $this->shape($rows);
        $done  = count(array_filter($items, static fn ($r) => in_array($r['status'], ['done', 'failed'], true)));

        return new WP_REST_Response(['items' => $items, 'total' => count($items), 'done' => $done], 200);
    }

    /**
     * @param array<int, array<string, mixed>>|null $rows
     * @return array<int, array<string, mixed>>
     */
    /**
     * "3 of 5 runs used this month" — the mirror of AutomationController::usage_label(), so the two
     * counters and the upgrade card use one word for one quantity.
     */
    private static function usage_label(): string
    {
        $limit = \WPWand\Generation\UsageLimits::bulk_limit();
        $used  = \WPWand\Generation\UsageLimits::bulk_used();

        // Both screens showed "3 of 5 runs used this month" about two different counters, so the
        // two chips read as one number that disagreed with itself. This one names bulk.
        if ($limit < 0) {
            return __('Unlimited bulk runs this month', 'ai-content-generation');
        }

        /* translators: 1: runs used so far. 2: the monthly limit. */
        return sprintf(__('%1$d of %2$d bulk runs used this month', 'ai-content-generation'), min($used, $limit), $limit);
    }

    private function shape($rows): array
    {
        return array_map(function ($r) {
            $content = isset($r['content']) ? (string) $r['content'] : '';
            $plain   = $content !== '' ? WordCount::strip_markdown($content) : '';

            $failed  = 'failed' === (string) $r['status'];
            $post_id = isset($r['post_id']) ? (int) $r['post_id'] : 0;

            return [
                'id'         => (int) $r['id'],
                'title'      => (string) $r['title'],
                'status'     => (string) $r['status'],
                // 120 characters is the right length for a taste of an article and the wrong
                // length for the reason a run died — the two used to share this field, so a
                // provider's own sentence was cut off mid-word and the rest was unreachable.
                // A body is clipped; a reason travels whole, in its own field.
                'preview'    => $failed || $plain === '' ? '' : mb_substr($plain, 0, 120),
                'reason'     => $failed ? $plain : '',
                'template'   => self::template_label($r['template'] ?? null),
                'word_count' => $this->words_for_row($r, $plain),
                'post_id'    => $post_id,
                'edit_url'   => self::edit_url($post_id),
                // Site time, not the raw column. `created_at` is a TIMESTAMP that MySQL displays in the
                // server's zone, so on the dev machine (+06 server, UTC site) a row read six hours
                // later than the Posts list showed the same draft. UNIX_TIMESTAMP() gives the epoch
                // the column stores; get_date_from_gmt() puts it in the site's clock — the convention
                // every screen follows since 2026-09-10.
                'date'       => ! empty($r['created_ts'])
                    ? get_date_from_gmt(gmdate('Y-m-d H:i:s', (int) $r['created_ts']), 'g:i a : M j, Y')
                    : '',
            ];
        }, $rows ?: []);
    }

    /**
     * The stored length, filled in on the way past for a row written before the column existed.
     *
     * WHY NOT IN THE MIGRATION. Walking a couple of thousand bodies inside an activation hook is
     * how an activation times out. This fills them a page at a time instead, as the list is read,
     * and only for rows that are actually finished — a half-written body would otherwise store the
     * length of half an article and keep it after the row failed.
     *
     * @param array<string, mixed> $r
     */
    private function words_for_row(array $r, string $plain): int
    {
        $stored = isset($r['word_count']) ? (int) $r['word_count'] : 0;

        if ($stored > 0 || $plain === '' || 'done' !== ($r['status'] ?? '')) {
            return $stored;
        }

        global $wpdb;
        $words = WordCount::of_plain($plain);
        $wpdb->update($this->table(), ['word_count' => $words], ['id' => (int) $r['id']]);

        return $words;
    }

    /**
     * The same thing for one row read whole, where the plain text has not been worked out yet.
     *
     * @param array<string, mixed> $row
     */
    private static function words_for(array $row): int
    {
        $stored = isset($row['word_count']) ? (int) $row['word_count'] : 0;

        return $stored > 0 ? $stored : WordCount::of((string) ($row['content'] ?? ''));
    }

    /**
     * What the screen prints under a row's title.
     *
     * A row from before the column existed, and every automated post, stores nothing here. That is
     * a fact about the row rather than a fault, so it says so — it is not dressed up as "Blog post",
     * which would be a claim about a choice nobody made.
     */
    private static function template_label(?string $key): string
    {
        $key = (string) $key;

        return isset(JobRunner::TEMPLATES[$key])
            ? JobRunner::TEMPLATES[$key]['label']
            : __('Template not recorded', 'ai-content-generation');
    }
}
