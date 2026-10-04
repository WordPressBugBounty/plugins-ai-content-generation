<?php

namespace WPWand\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WPWand\Automation\AutomationRunner;
use WPWand\Automation\Schedules;
use WPWand\Generation\JobRunner;
use WPWand\Generation\UsageLimits;

/**
 * REST API for scheduled post automation (Pro). CRUD over saved schedules plus a "run now" action.
 *
 *   GET    /wpwand/v1/automation/schedules          list schedules + meta
 *   POST   /wpwand/v1/automation/schedules          create or update a schedule
 *   DELETE /wpwand/v1/automation/schedules/{id}     delete a schedule
 *   POST   /wpwand/v1/automation/schedules/{id}/run run a schedule immediately
 */
final class AutomationController extends AbstractController
{
    protected string $rest_base = 'automation/schedules';

    public function register_routes(): void
    {
        register_rest_route($this->rest_namespace, '/' . $this->rest_base, [
            ['methods' => 'GET',  'callback' => [$this, 'index'], 'permission_callback' => [$this, 'can_use']],
            ['methods' => 'POST', 'callback' => [$this, 'save'],  'permission_callback' => [$this, 'can_use']],
        ]);

        register_rest_route($this->rest_namespace, '/' . $this->rest_base . '/(?P<id>[A-Za-z0-9\-]+)', [
            ['methods' => 'DELETE', 'callback' => [$this, 'delete'], 'permission_callback' => [$this, 'can_use']],
        ]);

        register_rest_route($this->rest_namespace, '/' . $this->rest_base . '/(?P<id>[A-Za-z0-9\-]+)/run', [
            ['methods' => 'POST', 'callback' => [$this, 'run'], 'permission_callback' => [$this, 'can_use']],
        ]);

        // Clone a schedule (paused, no run history) so the user can tweak a copy.
        register_rest_route($this->rest_namespace, '/' . $this->rest_base . '/(?P<id>[A-Za-z0-9\-]+)/duplicate', [
            ['methods' => 'POST', 'callback' => [$this, 'duplicate'], 'permission_callback' => [$this, 'can_use']],
        ]);

        // Pause / resume a schedule without opening the edit form.
        register_rest_route($this->rest_namespace, '/' . $this->rest_base . '/(?P<id>[A-Za-z0-9\-]+)/toggle', [
            ['methods' => 'POST', 'callback' => [$this, 'toggle'], 'permission_callback' => [$this, 'can_use']],
        ]);

        // One generation step + snapshot — the client polls this to show live run progress.
        register_rest_route($this->rest_namespace, '/' . $this->rest_base . '/tick', [
            ['methods' => 'POST', 'callback' => [$this, 'tick'], 'permission_callback' => [$this, 'can_use']],
        ]);

        // Publish a generated draft straight from the schedule's post list.
        register_rest_route($this->rest_namespace, '/automation/post/(?P<id>\d+)/publish', [
            ['methods' => 'POST', 'callback' => [$this, 'publish_post'], 'permission_callback' => [$this, 'can_publish']],
        ]);

        // Delete a generated post (trash) from the schedule's post list.
        register_rest_route($this->rest_namespace, '/automation/post/(?P<id>\d+)', [
            ['methods' => 'DELETE', 'callback' => [$this, 'delete_post'], 'permission_callback' => [$this, 'can_delete']],
        ]);

        // Remove a post from the list only (drops the schedule tag; the post itself is untouched).
        register_rest_route($this->rest_namespace, '/automation/post/(?P<id>\d+)/delist', [
            ['methods' => 'DELETE', 'callback' => [$this, 'delist_post'], 'permission_callback' => [$this, 'can_use']],
        ]);

        // Clear a schedule's whole post list (drops the tag on every post; posts stay untouched).
        register_rest_route($this->rest_namespace, '/automation/schedules/(?P<id>[A-Za-z0-9\-]+)/clear-posts', [
            ['methods' => 'POST', 'callback' => [$this, 'clear_posts'], 'permission_callback' => [$this, 'can_use']],
        ]);

        // Add topics to a schedule that has run out, and re-arm it in the same call. The row's
        // "Add topics" link is the only way in, and it exists because the alternative was: open the
        // edit form, scroll to the topic box, paste, save, then find the enable toggle.
        register_rest_route($this->rest_namespace, '/' . $this->rest_base . '/(?P<id>[A-Za-z0-9\-]+)/topics', [
            ['methods' => 'POST', 'callback' => [$this, 'add_topics'], 'permission_callback' => [$this, 'can_use']],
        ]);

        // Clear a failed generation row from a schedule's failure list.
        register_rest_route($this->rest_namespace, '/automation/failure/(?P<id>\d+)', [
            ['methods' => 'DELETE', 'callback' => [$this, 'clear_failure'], 'permission_callback' => [$this, 'can_use']],
        ]);
    }

    public function index(): WP_REST_Response
    {
        return new WP_REST_Response([
            'schedules' => array_map([$this, 'present'], Schedules::all()),
            'meta'      => [
                'frequencies' => ['hourly', 'daily', 'weekly', 'monthly'],
                // The same list Settings already ships. One more key, no new data — the schedule
                // editor took a free-text language, so a typo saved a typo and the model was
                // handed it.
                'languages'   => \WPWand\Data\Languages::all(),
                // What one run costs the month. Every tier bills a whole run however many posts it
                // makes (Pro billed each post until 2026-09-13). Sent from here so the screen never
                // has to work out the price.
                'per_run_help' => __('However many you pick, one run counts as one against your month.', 'ai-content-generation'),
                'statuses'    => ['draft', 'pending', 'publish'],
                // Live queue state — lets the page show/advance generation without a manual refresh.
                'queue'       => (new JobRunner())->snapshot('automation'),
                // Monthly automation allowance (mirrors the Bulk page's counter + upgrade gate).
                'usage'       => [
                    'used'      => UsageLimits::automation_used(),
                    'limit'     => UsageLimits::automation_limit(),
                    'text'      => UsageLimits::automation_text(),
                    'can_run'   => UsageLimits::can_automation(),
                    // "3/5" never said what it was counting. Every tier counts runs since 2026-09-13;
                    // the word is still sent so the screen never has to guess.
                    'unit'        => 'runs',
                    'unit_label'  => __('runs', 'ai-content-generation'),
                    'used_label'  => $this->usage_label(),
                    'resets_on'   => UsageLimits::reset_date(),
                    'upgrade_url' => 'https://wpwand.com/pro-plugin',
                ],
            ],
        ], 200);
    }

    public function save(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_params();
        }

        $mode = ($params['mode'] ?? 'list') === 'prompt' ? 'prompt' : 'list';
        if ($mode === 'list' && empty(array_filter((array) ($params['topics'] ?? [])))) {
            return new WP_REST_Response(['error' => __('Add at least one topic.', 'ai-content-generation')], 400);
        }
        if ($mode === 'prompt' && trim((string) ($params['subject'] ?? '')) === '') {
            return new WP_REST_Response(['error' => __('Tell the AI what to write about.', 'ai-content-generation')], 400);
        }

        $record = Schedules::save($params);
        return new WP_REST_Response(['schedule' => $this->present($record)], 200);
    }

    public function delete(WP_REST_Request $request): WP_REST_Response
    {
        $ok = Schedules::delete((string) $request->get_param('id'));
        return new WP_REST_Response(['deleted' => $ok], $ok ? 200 : 404);
    }

    public function run(WP_REST_Request $request): WP_REST_Response
    {
        $schedule = Schedules::get((string) $request->get_param('id'));
        if (!$schedule) {
            return new WP_REST_Response(['error' => __('Schedule not found.', 'ai-content-generation')], 404);
        }

        $runner = new AutomationRunner();
        $queued = $runner->run_schedule($schedule);

        // Enqueue only and return immediately — the client then polls /tick to drive generation
        // and show live progress. (If the page is closed, the WP-Cron drainer finishes the queue.)
        $fresh = Schedules::get((string) $request->get_param('id'));

        return new WP_REST_Response([
            'queued'   => $queued,
            // Why nothing was queued (model error / limit reached), so the UI shows the REAL reason
            // instead of always blaming the topic list.
            'error'    => $runner->last_error(),
            'snapshot' => (new JobRunner())->snapshot('automation'),
            'schedule' => $fresh ? $this->present($fresh) : null,
        ], 200);
    }

    /** Clone a schedule (paused, no copied run history) so the user can tweak a copy. */
    public function duplicate(WP_REST_Request $request): WP_REST_Response
    {
        $record = Schedules::duplicate((string) $request->get_param('id'));
        if (!$record) {
            return new WP_REST_Response(['error' => __('Schedule not found.', 'ai-content-generation')], 404);
        }
        return new WP_REST_Response(['schedule' => $this->present($record)], 200);
    }

    /** Pause or resume a schedule. Resuming recomputes the next run from now. */
    public function toggle(WP_REST_Request $request): WP_REST_Response
    {
        $id       = (string) $request->get_param('id');
        $schedule = Schedules::get($id);
        if (!$schedule) {
            return new WP_REST_Response(['error' => __('Schedule not found.', 'ai-content-generation')], 404);
        }

        $enabled = empty($schedule['enabled']);
        Schedules::update($id, [
            'enabled'  => $enabled,
            'next_run' => $enabled ? Schedules::next_run_from_now((string) $schedule['frequency']) : 0,
        ]);

        $fresh = Schedules::get($id);
        return new WP_REST_Response(['schedule' => $fresh ? $this->present($fresh) : null], 200);
    }

    /** Advance the generation queue by one step; returns a progress snapshot. */
    public function tick(): WP_REST_Response
    {
        return new WP_REST_Response((new JobRunner())->tick(), 200);
    }

    public function can_delete(): bool
    {
        return current_user_can('delete_posts');
    }

    /** Trash a generated post (only posts this plugin scheduled). */
    public function delete_post(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = absint($request->get_param('id'));
        if (!$post_id || get_post_meta($post_id, '_wpwand_schedule_id', true) === '') {
            return new WP_REST_Response(['error' => __('Not a scheduled post.', 'ai-content-generation')], 400);
        }

        $trashed = wp_trash_post($post_id);
        if (!$trashed) {
            return new WP_REST_Response(['error' => __('Could not delete the post.', 'ai-content-generation')], 200);
        }

        return new WP_REST_Response(['deleted' => true, 'id' => $post_id], 200);
    }

    /**
     * Remove a post from the schedule's list without touching the post: just drop the
     * _wpwand_schedule_id tag so posts_for() stops returning it. A published post stays live.
     */
    public function delist_post(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = absint($request->get_param('id'));
        if (!$post_id || get_post_meta($post_id, '_wpwand_schedule_id', true) === '') {
            return new WP_REST_Response(['error' => __('Not a scheduled post.', 'ai-content-generation')], 400);
        }

        delete_post_meta($post_id, '_wpwand_schedule_id');
        return new WP_REST_Response(['delisted' => true, 'id' => $post_id], 200);
    }

    /**
     * Clear a schedule's entire post list — drops the tag on every tagged post so the list
     * empties, while the posts themselves (published or draft) are left completely untouched.
     */
    public function clear_posts(WP_REST_Request $request): WP_REST_Response
    {
        $schedule_id = (string) $request->get_param('id');

        $query = new \WP_Query([
            'post_type'      => 'post',
            'post_status'    => ['draft', 'pending', 'publish', 'future', 'private'],
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_key'       => '_wpwand_schedule_id',
            'meta_value'     => $schedule_id,
            'no_found_rows'  => true,
        ]);

        $cleared = 0;
        foreach ($query->posts as $pid) {
            delete_post_meta((int) $pid, '_wpwand_schedule_id');
            $cleared++;
        }

        return new WP_REST_Response(['cleared' => $cleared], 200);
    }

    /** Remove a failed generation row (and its job) so it stops showing in the failure list. */
    /**
     * Append topics to a schedule and start it again, or run the list it already has from the top.
     *
     * Two things have to happen together for the row to go back to Active, and doing one without
     * the other leaves a schedule that looks armed and never fires: the list has to stop being
     * finished, and `next_run` has to be recomputed. The second only happens inside
     * `Schedules::normalize()`, and only when a disabled schedule is being enabled — which is why
     * this goes through `save()` with `enabled => true` rather than patching the option directly.
     *
     * The cursor is never rewound when topics are added. It carries through `normalize()` from the
     * existing record, so the schedule picks up at the first NEW topic instead of rewriting
     * everything it has already written. Starting the old list over is the other branch, and it is
     * only reachable when no new topics were sent.
     */
    public function add_topics(WP_REST_Request $request): WP_REST_Response
    {
        $id       = (string) $request->get_param('id');
        $schedule = Schedules::get($id);
        if (!$schedule) {
            return new WP_REST_Response(['error' => __('Schedule not found.', 'ai-content-generation')], 404);
        }

        // A textarea sends one string with newlines in it; the API may send an array. Both here.
        $raw = $request->get_param('topics');
        if (is_string($raw)) {
            $raw = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        }

        $added = [];
        foreach ((array) $raw as $topic) {
            $topic = trim(sanitize_text_field((string) $topic));
            if ($topic !== '') {
                $added[] = $topic;
            }
        }

        $restart = (bool) $request->get_param('restart');
        if (!$added && !$restart) {
            return new WP_REST_Response(['error' => __('Add at least one topic.', 'ai-content-generation')], 400);
        }

        $topics = [];
        foreach ((array) ($schedule['topics'] ?? []) as $topic) {
            $topic = trim((string) $topic);
            if ($topic !== '') {
                $topics[] = $topic;
            }
        }

        // Enabling is what makes normalize() recompute next_run, so it goes in the same save.
        $record = Schedules::save([
            'id'      => $id,
            'topics'  => array_merge($topics, $added),
            'enabled' => true,
        ]);

        // Only when nothing new was pasted. Adding topics and rewinding at once would re-write
        // every title the schedule has already published.
        if (!$added && $restart) {
            Schedules::update($id, ['cursor' => 0]);
            $record = Schedules::get($id) ?: $record;
        }

        return new WP_REST_Response([
            'schedule' => $this->present($record),
            'added'    => count($added),
        ], 200);
    }

    public function clear_failure(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $row_id = absint($request->get_param('id'));
        if (!$row_id) {
            return new WP_REST_Response(['error' => __('That row isn’t here any more.', 'ai-content-generation')], 400);
        }
        $jt = $wpdb->prefix . 'wpwand_gen_jobs';
        $pt = $wpdb->prefix . 'wpwand_generated_post';
        $wpdb->delete($jt, ['row_id' => $row_id], ['%d']); // phpcs:ignore
        $wpdb->delete($pt, ['id' => $row_id, 'status' => 'failed'], ['%d', '%s']); // phpcs:ignore

        return new WP_REST_Response(['cleared' => true, 'id' => $row_id], 200);
    }

    public function can_publish(): bool
    {
        return current_user_can('publish_posts');
    }

    /** Publish a generated draft (only posts this plugin scheduled). */
    public function publish_post(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = absint($request->get_param('id'));
        if (!$post_id || get_post_meta($post_id, '_wpwand_schedule_id', true) === '') {
            return new WP_REST_Response(['error' => __('Not a scheduled post.', 'ai-content-generation')], 400);
        }

        $res = wp_update_post(['ID' => $post_id, 'post_status' => 'publish'], true);
        if (is_wp_error($res)) {
            return new WP_REST_Response(['error' => $res->get_error_message()], 200);
        }

        return new WP_REST_Response(['published' => true, 'id' => $post_id], 200);
    }

    /**
     * Shape a stored schedule for the client: ISO-ish next/last run + a human topic count.
     *
     * @param array<string, mixed> $s
     * @return array<string, mixed>
     */
    private function present(array $s): array
    {
        $s['next_run_h'] = !empty($s['next_run'])
            ? get_date_from_gmt(gmdate('Y-m-d H:i:s', (int) $s['next_run']), 'M j, Y g:i a')
            : '';
        $s['last_run_h'] = !empty($s['last_run'])
            ? get_date_from_gmt(gmdate('Y-m-d H:i:s', (int) $s['last_run']), 'M j, Y g:i a')
            : '';
        // The two arrays the modal reads. They are capped at twenty, and they only hold what still
        // exists. Beside each one, the number it was cut from: the modal used to show twenty rows and
        // say nothing about the rest, so a schedule that had written sixty-three looked like twenty.
        $posts               = $this->posts_for((string) $s['id']);
        $s['posts']          = $posts['rows'];
        $s['posts_total']    = $posts['total'];
        $s['failures']       = $this->failures_for((string) $s['id']);
        $s['failures_total'] = $this->failures_total_for((string) $s['id']);

        // The two numbers the ROW reads. The row used to count the arrays above, so a schedule
        // that had written forty posts said twenty, and deleting one of them made it nineteen.
        // These are running totals kept on the schedule itself and nothing after the fact moves
        // them.
        $s['posts_written'] = (int) ($s['posts_written'] ?? 0);
        $s['posts_failed']  = (int) ($s['posts_failed'] ?? 0);

        // What this schedule writes next, and — if it will not run — why not.
        $s['next_topic']        = AutomationRunner::next_topic($s);
        $s['next_topic_source'] = ($s['mode'] ?? 'list') === 'prompt' ? 'prompt' : 'list';
        $s['next_topic_text']   = $this->next_topic_text($s);

        $stall                = $this->stall($s);
        $s['stalled_reason']  = $stall['reason'];
        $s['stalled_label']   = $stall['label'];
        // What the Next run cell says. Every one of these was a string in App.js beside a PHP
        // string that had to agree with it; now the row cannot disagree with the runner.
        $s['stalled_cell']    = $stall['cell'];
        $s['stalled_text']    = $stall['text'];

        return $s;
    }

    /**
     * Why this schedule will not run, as a reason the row can act on rather than a grey pill.
     *
     * Three unrelated causes look identical today. When more than one is true at once — the dev
     * site's zz-probe has a finished list AND the allowance at 5/5 — the finished list wins, for two
     * reasons. It is the one the user has to do something about; the allowance comes back on a date
     * we can name. And the runner is what switched this schedule off, so calling it "you paused it"
     * would be a straight lie. That is also why "paused" is only reported once the list is ruled out.
     *
     * @param array<string, mixed> $s
     * @return array{reason:string, label:string, cell:string, text:string}
     */
    private function stall(array $s): array
    {
        // Same function the runner stops the schedule with, so the row cannot disagree with it.
        if (AutomationRunner::list_finished($s)) {
            return [
                'reason' => 'list_finished',
                // "Out of topics", not "List finished": the row is saying what the schedule needs
                // from you, not congratulating it on finishing. The Next run cell says the other
                // half — "Every topic written" — and the two together read as one sentence.
                'label'  => __('Out of topics', 'ai-content-generation'),
                // The other half of that sentence, in the Next run cell. It lived in the browser
                // and the label lived here, so two halves of one sentence could be translated
                // apart or changed apart.
                'cell'   => __('Every topic written', 'ai-content-generation'),
                'text'   => __('Every topic on the list has been written. Add more topics, or turn on looping to run through them again.', 'ai-content-generation'),
            ];
        }

        if (empty($s['enabled'])) {
            return [
                'reason' => 'paused',
                'label'  => __('Paused', 'ai-content-generation'),
                'cell'   => __('Paused', 'ai-content-generation'),
                'text'   => __('You paused this one. Switch it back on when you want it running again.', 'ai-content-generation'),
            ];
        }

        if (!UsageLimits::can_automation()) {
            $reset = UsageLimits::reset_date();
            return [
                'reason' => 'allowance_spent',
                // The pill beside this sentence has room for three words, and it used to hardcode
                // "No runs left" while this line said something else on Pro. Decide both in one
                // place; every tier counts runs since 2026-09-13.
                'label'  => __('No runs left', 'ai-content-generation'),
                /* translators: %s: the date the monthly allowance starts again. */
                'cell'   => sprintf(__('No runs left in this month\'s allowance. It starts again on %s.', 'ai-content-generation'), $reset),
                /* translators: %s: the date the monthly allowance starts again. */
                'text'   => sprintf(__('No runs left in this month\'s allowance. It starts again on %s.', 'ai-content-generation'), $reset),
            ];
        }

        return ['reason' => '', 'label' => '', 'cell' => '', 'text' => ''];
    }

    /**
     * What the row shows in the "next topic" space. A prompt-mode schedule has no list to read from,
     * so it says what it works from instead of leaving the cell blank.
     *
     * @param array<string, mixed> $s
     */
    private function next_topic_text(array $s): string
    {
        if (($s['mode'] ?? 'list') === 'prompt') {
            $subject = trim((string) ($s['subject'] ?? ''));
            return $subject !== ''
                /* translators: %s: the subject the schedule writes about. */
                ? sprintf(__('A new title about %s, picked each run', 'ai-content-generation'), $subject)
                : __('A new title picked each run', 'ai-content-generation');
        }

        // Empty when the list is finished — the row shows the stalled reason in this space instead.
        return AutomationRunner::next_topic($s);
    }

    /** The monthly counter with its unit named: "3 of 5 runs used this month". */
    private function usage_label(): string
    {
        $limit = UsageLimits::automation_limit();
        $used  = UsageLimits::automation_used();

        // Bulk Posts says the same sentence about a different counter, so both chips have to name
        // what they are counting. Read side by side they used to look like one number disagreeing
        // with itself. Every tier counts runs since 2026-09-13.
        if ($limit < 0) {
            return __('Unlimited automated runs this month', 'ai-content-generation');
        }

        /* translators: 1: runs used so far. 2: the monthly limit. */
        return sprintf(__('%1$d of %2$d automated runs used this month', 'ai-content-generation'), min($used, $limit), $limit);
    }

    /**
     * Failed generations for a schedule. These never became WP posts (the job hit its retry limit),
     * so they live only in the engine tables — surfaced here with the human reason so the user can
     * see WHY a scheduled run produced nothing.
     *
     * @return array<int, array<string, mixed>>
     */
    private function failures_for(string $scheduleId): array
    {
        global $wpdb;
        $jt = $wpdb->prefix . 'wpwand_gen_jobs';
        $pt = $wpdb->prefix . 'wpwand_generated_post';

        // schedule_id is stored inside the job's settings JSON: ..."schedule_id":"<id>"...
        $needle = '%' . $wpdb->esc_like('"schedule_id":"' . $scheduleId . '"') . '%';
        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB
            $wpdb->prepare(
                "SELECT j.row_id AS id, j.title AS title, j.error AS error, p.content AS content,
                        j.updated_at AS updated_at
                 FROM {$jt} j LEFT JOIN {$pt} p ON p.id = j.row_id
                 WHERE j.status = 'failed' AND j.settings LIKE %s
                 ORDER BY j.id DESC LIMIT 20",
                $needle
            ),
            ARRAY_A
        );

        $out = [];
        foreach ($rows ?: [] as $r) {
            $reason = (string) ($r['error'] ?? '');
            if ($reason === '') {
                $reason = (string) ($r['content'] ?? '');
            }
            // When it failed. `updated_at` is `datetime DEFAULT NULL` (Migration_1_1_0_GenJobs) and
            // is written by one place, JobRunner::update_job(), as current_time('mysql') — the
            // SITE's wall clock, not UTC and not MySQL's zone. The screen shows those digits as
            // they are (the same read History and Bulk settled on), so the raw string goes out
            // as `updated_at`. `ts` is the same moment as a real GMT unix timestamp — it used to
            // be strtotime($stamp . ' GMT'), which read the site clock as UTC and was wrong by
            // the site's offset on any site not set to UTC. `date` is the formatted last resort.
            $stamp = (string) ($r['updated_at'] ?? '');
            $ts    = $stamp !== '' ? (int) get_gmt_from_date($stamp, 'U') : 0;

            $out[] = [
                'id'     => (int) $r['id'],
                'title'  => (string) ($r['title'] ?? ''),
                'reason' => $reason !== '' ? $reason : __('It didn’t finish, and no reason came back.', 'ai-content-generation'),
                'updated_at' => $stamp,
                'ts'     => $ts > 0 ? $ts : null,
                'date'   => $ts > 0
                    ? get_date_from_gmt(gmdate('Y-m-d H:i:s', $ts), 'M j, Y g:i a')
                    : '',
            ];
        }
        return $out;
    }

    /**
     * How many failures failures_for() was cut from. Same WHERE, no join and no rows: the join
     * above only exists to read the content column, and a count does not need it.
     */
    private function failures_total_for(string $scheduleId): int
    {
        global $wpdb;
        $jt = $wpdb->prefix . 'wpwand_gen_jobs';

        $needle = '%' . $wpdb->esc_like('"schedule_id":"' . $scheduleId . '"') . '%';
        return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$jt} WHERE status = 'failed' AND settings LIKE %s",
                $needle
            )
        );
    }

    /**
     * The posts a schedule has generated (tagged with _wpwand_schedule_id), newest first.
     *
     * Returns the capped rows and the number they were cut from, together, because the count comes
     * out of the same query: with no_found_rows off, WP_Query asks MySQL for FOUND_ROWS() after the
     * LIMIT 20, and that is one trivial extra statement. A second WP_Query with fields => 'ids' and
     * no limit would run the meta join again and pull every id over the wire, and this runs once
     * per schedule on every list load.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    private function posts_for(string $scheduleId): array
    {
        $query = new \WP_Query([
            'post_type'      => 'post',
            'post_status'    => ['draft', 'pending', 'publish', 'future', 'private'],
            'posts_per_page' => 20,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_key'       => '_wpwand_schedule_id',
            'meta_value'     => $scheduleId,
            'no_found_rows'  => false,
        ]);

        $out = [];
        foreach ($query->posts as $post) {
            $out[] = [
                'id'       => $post->ID,
                'title'    => get_the_title($post) ?: __( '(no title)', 'ai-content-generation' ),
                'status'   => $post->post_status,
                // The site's wall clock, `Y-m-d H:i:s`, straight off the post — what the Posts
                // list shows for the same row. The screen reads the digits as a local date; it
                // does not convert. There was no timestamp here before, so the modal's Written
                // column was showing the day-only `date` and never a time. `post_date_gmt` is
                // deliberately not used: it is 0000-00-00 on every draft, and most of these are.
                'post_date' => (string) $post->post_date,
                'date'     => get_the_date('M j, Y', $post),
                'edit_url' => get_edit_post_link($post->ID, 'raw'),
                'view_url' => get_permalink($post->ID),
            ];
        }
        return ['rows' => $out, 'total' => (int) $query->found_posts];
    }
}
