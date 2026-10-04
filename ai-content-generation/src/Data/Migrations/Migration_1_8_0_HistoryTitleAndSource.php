<?php

namespace WPWand\Data\Migrations;

/**
 * Give every History row a title and a source.
 *
 * WHY. `wpwand_history` has only ever held `(id, template_name, prompt_info, response, created_at)`,
 * so the screen had nothing to name a row with. It showed the first 140 characters of the body
 * instead, cut mid-sentence, and search matched the template name or the body — type a headline you
 * remember and every article that merely mentions it comes back. A record of what the plugin wrote
 * has to be able to say what each piece was.
 *
 * `source` exists because History was never a record of the plugin at all. `record()` had three call
 * sites and all three were the Assistant; the engine behind Bulk Posts and Automated Posts finished
 * a job and never touched this table. Once those paths record, a row has to say where it came from.
 *
 * WHAT THE BACKFILL IS BASED ON. Ten rows on the local install were read before this was written —
 * ids 48, 61, 65, 96, 100, 157, 200, 220, 229 and 256 — plus a count of every distinct
 * `template_name` in the table (11 values, 253 rows). Every row is a template generation, in one of
 * two shapes: the legacy admin-ajax one (`{"action":"wpwand_request","nonce":…,"prompt":…}`) and the
 * REST one (`{"template_name":…,"language":…,"topic":…}`). Both are the Assistant, which runs inside
 * the post editor. Nothing else has ever written here, so every existing row is backfilled to
 * `editor`.
 *
 * `title` is deliberately NOT backfilled. The title of an old row would have to be invented from its
 * body, and the screen already falls back to the preview it has always shown — a guess stored in a
 * column reads as a fact, and a fallback rendered at display time does not.
 *
 * IDEMPOTENT. `dbDelta` adds only the columns that are missing, and the backfill is scoped to
 * `source = ''`, so a second run touches no row it has already set.
 */
final class Migration_1_8_0_HistoryTitleAndSource implements MigrationInterface
{
    public function version(): string
    {
        return '1.8.0';
    }

    public function describe(): string
    {
        return 'Add title and source to wpwand_history, and mark every existing row as written in the post editor — ten rows and all 11 template names were read first, and every one of them is an Assistant generation.';
    }

    public function up(): void
    {
        global $wpdb;

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset_collate = $wpdb->get_charset_collate();
        $prefix          = $wpdb->prefix;

        // The baseline shape plus the two new columns. Both default, so every existing row stays
        // valid without being rewritten.
        $history = "CREATE TABLE {$prefix}wpwand_history (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            template_name varchar(255) NOT NULL,
            title varchar(255) NOT NULL DEFAULT '',
            source varchar(32) NOT NULL DEFAULT '',
            prompt_info longtext NOT NULL,
            response longtext NOT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) {$charset_collate};";

        dbDelta($history);

        // Only rows the column default left empty. A row already carrying a source was written by
        // the code that knows its own source, and that is the truth.
        $table = $prefix . 'wpwand_history';
        $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
            "UPDATE {$table} SET source = 'editor' WHERE source = ''"
        );
    }
}
