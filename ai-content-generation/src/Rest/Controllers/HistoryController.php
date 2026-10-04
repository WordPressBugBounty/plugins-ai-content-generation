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
 *
 * All three routes carry the Pro gate. Two of them did not: both /history/{id} routes were
 * registered with the plain edit_posts callback, so on a Free install — where the screen shows a
 * lock card and no rows at all — anyone who could write a post could read the stored text of any
 * row by asking for its id, or delete it. The list route refuses in the shape the screen reads
 * (200 with pro:false, which is what raises that lock card); the other two refuse with 403,
 * because nothing on the screen calls them in that state.
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
            ['methods' => 'GET',    'callback' => [$this, 'show'],    'permission_callback' => [$this, 'require_pro']],
            ['methods' => 'DELETE', 'callback' => [$this, 'destroy'], 'permission_callback' => [$this, 'require_pro']],
        ]);
    }

    /**
     * Gate for the two routes that touch a single stored generation: the editing capability AND an
     * active Pro license.
     *
     * The capability half runs first and answers a bare `false`, so a signed-out or under-privileged
     * caller gets exactly what `can_use` gives them today — the status code does not move for anyone
     * who was already being refused. Only then does the Pro half speak, and it explains itself:
     * rest_forbidden's generic "you are not allowed to do that" would leave an Editor on a Free
     * install with no idea which of the two halves turned them away.
     *
     * That message is on-screen copy, not a hidden API string: the History app prints the REST
     * body's `message` verbatim in the detail panel and in the delete notice, so a session that
     * loses its license with a row open reads this sentence. It says what happened and stops there —
     * it must not tell anyone to open Activate Pro, because the free plugin registers no such page
     * (docs/DECISIONS.md, 2026-08-26) and the same refusal is what a lapsed Pro install gets.
     *
     * @return bool|\WP_Error
     */
    public function require_pro()
    {
        if (!$this->can_use()) {
            return false;
        }

        if (!\WPWand\Core\Pro::unlocked()) {
            return new \WP_Error(
                'wpwand_history_pro_only',
                __('History is a Pro feature. Opening or deleting a saved generation needs an active Pro license, and this site doesn’t have one.', 'ai-content-generation'),
                ['status' => 403]
            );
        }

        return true;
    }

    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'wpwand_history';
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        if (!\WPWand\Core\Pro::unlocked()) {
            return new WP_REST_Response(['error' => __('History is a Pro feature.', 'ai-content-generation'), 'pro' => false], 200);
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
            $like = '%' . $wpdb->esc_like($search) . '%';
            // Titles first, because a title is what someone remembers. The body match is kept, but
            // only for rows that have no title — everything written before this change. Without
            // that restriction, typing a headline you remember returns every article that merely
            // mentions the word, which is what the old `template_name OR response` clause did.
            $where  = "WHERE title LIKE %s OR template_name LIKE %s OR (title = '' AND response LIKE %s)";
            $params = [$like, $like, $like];
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
            $wpdb->prepare("SELECT id, template_name, title, source, response, created_at, UNIX_TIMESTAMP(created_at) AS created_ts FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d", $list_params),
            ARRAY_A
        );
        // phpcs:enable

        $items = array_map(function ($r) {
            return [
                'id'            => (int) $r['id'],
                'template_name' => (string) $r['template_name'],
                // What the piece is. Empty on every row written before this column existed; the
                // screen falls back to the preview for those rather than showing "Untitled", so an
                // old row reads as a row and not as a bug.
                'title'         => (string) $r['title'],
                // Which screen wrote it: editor, bulk or automation. The screen turns it into a
                // label; the value stays short and stable.
                'source'        => (string) $r['source'],
                // Site time, the way WordPress itself shows every date. `created_at` is a TIMESTAMP
                // column that MySQL displays in its own zone (+06 on the dev machine, while the site is
                // UTC), so the raw string was six hours off the Posts list for the same piece.
                // UNIX_TIMESTAMP() reads the epoch the column really stores, and that is turned into
                // the site's wall clock here — every row, old and new, the same way.
                'created_at'    => $this->site_time((int) $r['created_ts'], 'Y-m-d H:i:s'),
                'date'          => $this->site_time((int) $r['created_ts'], 'g:i a - F j, Y'),
                'preview'       => $this->preview($r['response']),
                // How long the piece is. The first thing anyone checks before reusing generated
                // copy, and the number the Word Count field and Longest reply setting exist to
                // control — it appeared on no screen in the product until now.
                'word_count'    => $this->word_count($r['response']),
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
        $row = $wpdb->get_row($wpdb->prepare("SELECT *, UNIX_TIMESTAMP(created_at) AS created_ts FROM {$this->table()} WHERE id = %d", $id), ARRAY_A); // phpcs:ignore

        if (!$row) {
            return new WP_REST_Response(['error' => __('Not found.', 'ai-content-generation')], 404);
        }

        return new WP_REST_Response([
            'id'            => (int) $row['id'],
            'template_name' => (string) $row['template_name'],
            'title'         => (string) ($row['title'] ?? ''),
            'source'        => (string) ($row['source'] ?? ''),
            'created_at'    => $this->site_time((int) $row['created_ts'], 'Y-m-d H:i:s'),
            'date'          => $this->site_time((int) $row['created_ts'], 'g:i a - F j, Y'),
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

    /**
     * Words in a stored response.
     *
     * This ran on `str_word_count()`, which counts runs of `[A-Za-z'-]` and nothing else — so a
     * Bengali post came back as 0 and the Length column showed an em dash on every row a
     * non-Latin-script customer had ever written. `WordCount` reads UTF-8 and gives all of them a
     * number; it is the same counter Bulk Posts uses, so the two screens cannot disagree about how
     * long the same piece is.
     *
     * @param mixed $response The raw stored response.
     */
    private function word_count($response): int
    {
        // NOT preview() — that truncates to 140 characters, which would report every article as
        // about 22 words. Count the whole stored result.
        $results = $this->results($response);

        return \WPWand\Generation\WordCount::of((string) ($results[0] ?? ''));
    }

    /**
     * The first line of a stored generation, for the rows that have no title of their own.
     *
     * Every row written before `title` existed falls back to this, and it goes where a title goes —
     * so it is read through the same helper the title paths use rather than handing the column a
     * raw slice of markdown. Before this, an old row's first cell read
     * `## Your Friendly Guide to AI **Meta-Description:**`, which is a bug on the face of it.
     *
     * Deliberately computed on read and never written to the column: a guess stored as a fact
     * outlives the guess, and this one can be improved later without a second migration.
     */
    /**
     * An epoch as the site's own wall clock, in the given format.
     *
     * The convention every WP Wand screen follows from 2026-09-10: dates are shown in site time, the
     * zone WordPress uses for the Posts list and everything else. History's column is written by
     * MySQL in the server's zone and Bulk's is the same; the epoch underneath is the one thing both
     * agree on, so it is the thing that gets converted.
     */
    private function site_time(int $ts, string $format): string
    {
        return $ts > 0 ? get_date_from_gmt(gmdate('Y-m-d H:i:s', $ts), $format) : '';
    }

    private function preview($json): string
    {
        $results = $this->results($json);
        $text    = trim(wp_strip_all_tags((string) ($results[0] ?? '')));

        $line = \WPWand\Data\History::title_from($text);

        // A generation that is one unbroken block with no first line to find still gets something.
        return $line !== '' ? $line : mb_substr($text, 0, 140);
    }
}
