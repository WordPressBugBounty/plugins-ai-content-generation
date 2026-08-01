<?php

namespace WPWand\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;

/**
 * /wpwand/v1/history — past generations (Pro). Reads the SAME {prefix}wpwand_history table
 * the legacy History page used (id, template_name, prompt_info, response, created_at).
 *
 * - GET    /history?page=&search=&per_page=  → paginated list with a response preview
 * - GET    /history/{id}                     → one record, decoded
 * - DELETE /history/{id}                     → remove a record
 */
final class HistoryController extends AbstractController
{
    protected string $rest_base = 'history';

    public function register_routes(): void
    {
        register_rest_route($this->rest_namespace, '/' . $this->rest_base, [
            ['methods' => 'GET', 'callback' => [$this, 'index'], 'permission_callback' => [$this, 'can_use']],
        ]);
        register_rest_route($this->rest_namespace, '/' . $this->rest_base . '/(?P<id>\d+)', [
            ['methods' => 'GET',    'callback' => [$this, 'show'],    'permission_callback' => [$this, 'can_use']],
            ['methods' => 'DELETE', 'callback' => [$this, 'destroy'], 'permission_callback' => [$this, 'can_use']],
        ]);
    }

    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'wpwand_history';
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        if (!\WPWand\Core\Pro::unlocked()) {
            return new WP_REST_Response(['error' => __('History is a Pro feature.', 'wp-wand'), 'pro' => false], 200);
        }

        global $wpdb;
        $table    = $this->table();
        $per_page = min(50, max(5, absint($request->get_param('per_page') ?: 20)));
        $page     = max(1, absint($request->get_param('page') ?: 1));
        $offset   = ($page - 1) * $per_page;
        $search   = trim((string) $request->get_param('search'));

        $where  = '';
        $params = [];
        if ($search !== '') {
            $like   = '%' . $wpdb->esc_like($search) . '%';
            $where  = 'WHERE template_name LIKE %s OR response LIKE %s';
            $params = [$like, $like];
        }

        // Custom history table; table/where are constant + prefixed and values are prepared. Direct
        // query without object caching is intentional for this admin-only paginated list.
        // phpcs:disable WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB
        $total = (int) $wpdb->get_var(
            $params
                ? $wpdb->prepare("SELECT COUNT(*) FROM {$table} {$where}", $params)
                : "SELECT COUNT(*) FROM {$table}"
        );

        $list_params = array_merge($params, [$per_page, $offset]);
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT id, template_name, response, created_at FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d", $list_params),
            ARRAY_A
        );
        // phpcs:enable

        $items = array_map(function ($r) {
            return [
                'id'            => (int) $r['id'],
                'template_name' => (string) $r['template_name'],
                'created_at'    => (string) $r['created_at'],
                'date'          => gmdate('g:i a - F j, Y', strtotime((string) $r['created_at'])),
                'preview'       => $this->preview($r['response']),
            ];
        }, $rows ?: []);

        return new WP_REST_Response([
            'items' => $items,
            'total' => $total,
            'pages' => (int) ceil($total / $per_page),
            'page'  => $page,
        ], 200);
    }

    public function show(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $id  = absint($request['id']);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id), ARRAY_A); // phpcs:ignore

        if (!$row) {
            return new WP_REST_Response(['error' => __('Not found.', 'wp-wand')], 404);
        }

        return new WP_REST_Response([
            'id'            => (int) $row['id'],
            'template_name' => (string) $row['template_name'],
            'created_at'    => (string) $row['created_at'],
            'date'          => gmdate('g:i a - F j, Y', strtotime((string) $row['created_at'])),
            'results'       => $this->results($row['response']),
        ], 200);
    }

    public function destroy(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $id      = absint($request['id']);
        // Custom history table; $wpdb->delete is prepared. phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->delete($this->table(), ['id' => $id], ['%d']);

        return new WP_REST_Response(['deleted' => (bool) $deleted, 'id' => $id], 200);
    }

    /**
     * Extract the text result(s) from a stored response JSON (the AI content object).
     *
     * @return string[]
     */
    private function results($json): array
    {
        $data = json_decode((string) $json);
        $out  = [];
        if (is_object($data) && isset($data->choices) && is_array($data->choices)) {
            foreach ($data->choices as $choice) {
                if (isset($choice->message->content)) {
                    $out[] = (string) $choice->message->content;
                } elseif (isset($choice->text)) {
                    $out[] = (string) $choice->text;
                }
            }
        }
        return $out;
    }

    private function preview($json): string
    {
        $results = $this->results($json);
        $text    = $results[0] ?? '';
        $text    = trim(wp_strip_all_tags($text));
        return mb_substr($text, 0, 140);
    }
}
