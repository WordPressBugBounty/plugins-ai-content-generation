<?php

namespace WPWand\Data\Migrations;

/**
 * Runs ordered, idempotent migrations and tracks progress in the wpwand_db_version
 * option. This is the mechanism that lets the 1k+ existing installs upgrade safely:
 * the data contract (option keys + table names) is preserved, and only additive or
 * deliberate shape changes are applied here, each guarded and version-gated.
 *
 * @see REBUILD-PLAN.md §5
 */
final class MigrationRunner
{
    /**
     * The schema version this build of the plugin expects. Bumped when a new
     * migration is added.
     */
    public const TARGET_VERSION = '1.1.0';

    private const OPTION_KEY = 'wpwand_db_version';

    /**
     * Ordered list of migrations. Add new ones to the end.
     *
     * @return MigrationInterface[]
     */
    private function migrations(): array
    {
        return [
            new Migration_1_0_0_BaselineSchema(),
            new Migration_1_1_0_GenJobs(),
        ];
    }

    public function run(): void
    {
        $current = (string) get_option(self::OPTION_KEY, '0.0.0');

        if (version_compare($current, self::TARGET_VERSION, '>=')) {
            return;
        }

        foreach ($this->migrations() as $migration) {
            if (version_compare($current, $migration->version(), '<')) {
                $migration->up();
                $current = $migration->version();
                update_option(self::OPTION_KEY, $current, false);
            }
        }
    }

    /**
     * Current stored schema version (for diagnostics / WP-CLI).
     */
    public function current_version(): string
    {
        return (string) get_option(self::OPTION_KEY, '0.0.0');
    }
}
