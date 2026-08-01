<?php

namespace WPWand\Data\Migrations;

/**
 * Generation Engine v2 — step-based job queue table.
 *
 * Additive, migration-safe: the existing wpwand_generated_post table is the list source and is
 * left untouched. This new table holds the per-post STEP MACHINE state so long content can be
 * generated section-by-section (outline → sections → assemble) instead of in one request that
 * times out on shared hosts. dbDelta() makes it a no-op on installs that already have it.
 *
 * @see generation-engine-v2 (memory)
 */
final class Migration_1_1_0_GenJobs implements MigrationInterface
{
    public function version(): string
    {
        return '1.1.0';
    }

    public function describe(): string
    {
        return 'Add wpwand_gen_jobs (step-based generation queue for sectioned long-content).';
    }

    public function up(): void
    {
        global $wpdb;

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset_collate = $wpdb->get_charset_collate();
        $prefix          = $wpdb->prefix;

        // row_id  -> wpwand_generated_post.id (the post row this job fills)
        // settings/outline/sections -> JSON working state
        // step    -> 0 outline, 1..N sections, then assemble; status pending|processing|done|failed
        // claimed_at -> lock timestamp so concurrent ticks/drivers don't double-process
        $jobs = "CREATE TABLE {$prefix}wpwand_gen_jobs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            row_id bigint(20) unsigned NOT NULL,
            title varchar(255) NOT NULL,
            settings longtext DEFAULT NULL,
            outline longtext DEFAULT NULL,
            sections longtext DEFAULT NULL,
            step int(11) NOT NULL DEFAULT 0,
            total_steps int(11) NOT NULL DEFAULT 0,
            status varchar(32) NOT NULL DEFAULT 'pending',
            attempts int(11) NOT NULL DEFAULT 0,
            error text DEFAULT NULL,
            claimed_at datetime DEFAULT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY row_id (row_id),
            KEY status (status)
        ) {$charset_collate};";

        dbDelta($jobs);
    }
}
