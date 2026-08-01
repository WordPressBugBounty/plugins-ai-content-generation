<?php

namespace WPWand\Data\Migrations;

/**
 * A single, idempotent schema/data migration.
 *
 * Migrations MUST be safe to run more than once — they run only when the stored
 * wpwand_db_version is below their version(), but should still guard with
 * IF NOT EXISTS / column-existence checks so a partial or repeated run cannot lose data.
 */
interface MigrationInterface
{
    /**
     * Ordering key, compared with version_compare(), e.g. '1.0.0'.
     */
    public function version(): string;

    /**
     * Short human-readable description (for logs / WP-CLI status).
     */
    public function describe(): string;

    /**
     * Apply the migration. Must be idempotent.
     */
    public function up(): void;
}
