<?php

namespace WPWand\Data\Migrations;

/**
 * Baseline schema migration.
 *
 * Centralizes creation of the three custom tables the plugin (free + pro) has always
 * used, with their FULL column set — including the columns Pro previously added via
 * ad-hoc ALTERs (action_id, featured_image_id). It uses dbDelta(), so on existing
 * installs it is a no-op diff: tables/columns that already exist are left untouched and
 * NO data is lost. On fresh installs it creates everything up front.
 *
 * Table names and column definitions are kept byte-compatible with the legacy code
 * (inc/config.php and wp-wand-pro/inc/db.php) so both old and new code read the same data.
 */
final class Migration_1_0_0_BaselineSchema implements MigrationInterface
{
    public function version(): string
    {
        return '1.0.0';
    }

    public function describe(): string
    {
        return 'Ensure baseline tables exist: wpwand_generated_post, wpwand_history, wpwand_custom_prompts.';
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
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) {$charset_collate};";

        $history = "CREATE TABLE {$prefix}wpwand_history (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            template_name varchar(255) NOT NULL,
            prompt_info longtext NOT NULL,
            response longtext NOT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) {$charset_collate};";

        $custom_prompts = "CREATE TABLE {$prefix}wpwand_custom_prompts (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            prompt longtext DEFAULT NULL,
            type varchar(255) DEFAULT 'template',
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) {$charset_collate};";

        dbDelta($generated_post);
        dbDelta($history);
        dbDelta($custom_prompts);
    }
}
