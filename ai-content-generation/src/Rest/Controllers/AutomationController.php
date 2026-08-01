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

        // Clear a failed generation row from a schedule's failure list.
        register_rest_route($this->rest_namespace, '/automation/failure/(?P<id>\d+)', [
            ['methods' => 'DELETE', 'callback' => [$this, 'clear_failure'], 'permission_callback' => [$this, 'can_use']],
        ]);
    }

    public function can_pro(): bool
    {
        return $this->can_use() && \WPWand\Core\Pro::unlocked();
    }

    public function index(): WP_REST_Response
    {
        return new WP_REST_Response([
            'schedules' => array_map([$this, 'present'], Schedules::all()),
            'meta'      => [
                'frequencies' => ['hourly', 'daily', 'weekly'],
                'statuses'    => ['draft', 'pending', 'publish'],
                // Live queue state — lets the page show/advance generation without a manual refresh.
                'queue'       => (new JobRunner())->snapshot('automation'),
                // Monthly automation allowance (mirrors the Bulk page's counter + upgrade gate).
                'usage'       => [
                    'used'      => UsageLimits::automation_used(),
                    'limit'     => UsageLimits::automation_limit(),
                    'text'      => UsageLimits::automation_text(),
                    'can_run'   => UsageLimits::can_automation(),
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
            return new WP_REST_Response(['error' => __('Add at least one topic.', 'wp-wand-pro')], 400);
        }
        if ($mode === 'prompt' && trim((string) ($params['subject'] ?? '')) === '') {
            return new WP_REST_Response(['error' => __('Enter a subject for the AI to write about.', 'wp-wand-pro')], 400);
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
            return new WP_REST_Response(['error' => __('Schedule not found.', 'wp-wand-pro')], 404);
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
            return new WP_REST_Response(['error' => __('Schedule not found.', 'wp-wand-pro')], 404);
        }
        return new WP_REST_Response(['schedule' => $this->present($record)], 200);
    }

    /** Pause or resume a schedule. Resuming recomputes the next run from now. */
    public function toggle(WP_REST_Request $request): WP_REST_Response
    {
        $id       = (string) $request->get_param('id');
        $schedule = Schedules::get($id);
        if (!$schedule) {
            return new WP_REST_Response(['error' => __('Schedule not found.', 'wp-wand-pro')], 404);
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
        return current_user_can('delete_posts') && \WPWand\Core\Pro::unlocked();
    }

    /** Trash a generated post (only posts this plugin scheduled). */
    public function delete_post(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = absint($request->get_param('id'));
        if (!$post_id || get_post_meta($post_id, '_wpwand_schedule_id', true) === '') {
            return new WP_REST_Response(['error' => __('Not a scheduled post.', 'wp-wand-pro')], 400);
        }

        $trashed = wp_trash_post($post_id);
        if (!$trashed) {
            return new WP_REST_Response(['error' => __('Could not delete the post.', 'wp-wand-pro')], 200);
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
            return new WP_REST_Response(['error' => __('Not a scheduled post.', 'wp-wand-pro')], 400);
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
    public function clear_failure(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $row_id = absint($request->get_param('id'));
        if (!$row_id) {
            return new WP_REST_Response(['error' => __('Invalid row.', 'wp-wand-pro')], 400);
        }
        $jt = $wpdb->prefix . 'wpwand_gen_jobs';
        $pt = $wpdb->prefix . 'wpwand_generated_post';
        $wpdb->delete($jt, ['row_id' => $row_id], ['%d']); // phpcs:ignore
        $wpdb->delete($pt, ['id' => $row_id, 'status' => 'failed'], ['%d', '%s']); // phpcs:ignore

        return new WP_REST_Response(['cleared' => true, 'id' => $row_id], 200);
    }

    public function can_publish(): bool
    {
        return current_user_can('publish_posts') && \WPWand\Core\Pro::unlocked();
    }

    /** Publish a generated draft (only posts this plugin scheduled). */
    public function publish_post(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = absint($request->get_param('id'));
        if (!$post_id || get_post_meta($post_id, '_wpwand_schedule_id', true) === '') {
            return new WP_REST_Response(['error' => __('Not a scheduled post.', 'wp-wand-pro')], 400);
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
        $s['posts']    = $this->posts_for((string) $s['id']);
        $s['failures'] = $this->failures_for((string) $s['id']);
        return $s;
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
                "SELECT j.row_id AS id, j.title AS title, j.error AS error, p.content AS content
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
            $out[] = [
                'id'     => (int) $r['id'],
                'title'  => (string) ($r['title'] ?? ''),
                'reason' => $reason !== '' ? $reason : __('Generation failed.', 'wp-wand-pro'),
            ];
        }
        return $out;
    }

    /**
     * The posts a schedule has generated (tagged with _wpwand_schedule_id), newest first.
     *
     * @return array<int, array<string, mixed>>
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
            'no_found_rows'  => true,
        ]);

        $out = [];
        foreach ($query->posts as $post) {
            $out[] = [
                'id'       => $post->ID,
                'title'    => get_the_title($post) ?: __( '(no title)', 'wp-wand-pro' ),
                'status'   => $post->post_status,
                'date'     => get_the_date('M j, Y', $post),
                'edit_url' => get_edit_post_link($post->ID, 'raw'),
                'view_url' => get_permalink($post->ID),
            ];
        }
        return $out;
    }
}
