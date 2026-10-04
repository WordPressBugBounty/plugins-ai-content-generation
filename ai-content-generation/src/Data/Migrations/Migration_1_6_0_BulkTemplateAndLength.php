<?php

namespace WPWand\Data\Migrations;

/**
 * Three facts about a generated post that were never stored: which template shape it was written
 * to, how long it came out, and which screen asked for it.
 *
 * WHY THEY HAVE TO BE COLUMNS. The template was picked in the batch wizard, sent to the queue and
 * then dropped — nothing on the row said what shape it had been asked for, so the list could not
 * print it and a failed row could not say what it had been trying to write. The length was worse
 * than absent: it was recomputed with `str_word_count()` off the full body of up to a hundred rows
 * on every eight-second poll, which is both slow and wrong outside the Latin alphabet.
 *
 * The third, `source`, exists because Bulk Posts and Automated Posts share this table. The Bulk
 * list used to hide everything with a `post_id`, which hid automated posts as a side effect — and
 * hid a bulk row the moment a draft was made from it, which is the thing being fixed. With the
 * filter gone, the list needs to know whose row it is looking at. Rows written before this column
 * keep the old rule exactly: NULL and `post_id = 0` means show it.
 *
 * Additive and idempotent. `dbDelta()` compares the definition below against the live table and
 * only issues the `ADD COLUMN`s that are missing, so a second activation is a no-op — the same
 * property `Migration_1_1_0_GenJobs` relies on. The rest of the definition is the baseline's, byte
 * for byte (`Migration_1_0_0_BaselineSchema`), because dbDelta needs the whole table to work out
 * what is missing and will try to alter anything that disagrees with what it is given.
 *
 * NOT BACKFILLED HERE. Rows written before this migration keep `word_count = 0` and a NULL
 * template. Walking a couple of thousand bodies inside an activation hook is how an activation
 * times out; the length is filled in on read instead, one page of rows at a time.
 */
final class Migration_1_6_0_BulkTemplateAndLength implements MigrationInterface
{
    public function version(): string
    {
        return '1.6.0';
    }

    public function describe(): string
    {
        return 'Add template, word_count and source to wpwand_generated_post (batch shape, a length that survives the poll, and which screen owns the row).';
    }

    public function up(): void
    {
        global $wpdb;

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset_collate = $wpdb->get_charset_collate();
        $prefix          = $wpdb->prefix;

        $generated_post = "CREATE TABLE {$prefix}wpwand_generated_post (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            content longtext DEFAULT NULL,
            post_id bigint(20) unsigned DEFAULT NULL,
            action_id bigint(20) unsigned DEFAULT NULL,
            status varchar(255) DEFAULT 'pending',
            featured_image_id bigint(20) unsigned DEFAULT NULL,
            template varchar(100) DEFAULT NULL,
            word_count int(11) NOT NULL DEFAULT 0,
            source varchar(20) DEFAULT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) {$charset_collate};";

        dbDelta($generated_post);
    }
}
