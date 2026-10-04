<?php

namespace WPWand\Data\Migrations;

use WPWand\Generation\UsageLimits;

/**
 * Give the free run counters their own options instead of reading legacy Pro's.
 *
 * The free tier counted its 5 runs per 30 days in wpwand_pgc_total_bulk_generated and
 * wpwand_pgc_total_automation_generated — the same options legacy Pro had been writing to for
 * years, with caps of 10/20/unlimited. So a site that used legacy Pro and is now unlicensed
 * arrived at 2.0.0 with part of its free allowance already spent, and was told so on plain page
 * load before generating anything. Measured on a real install: automation showing 2/5 with zero
 * 2.0.0 runs.
 *
 * The legacy options are left exactly as they are — Pro still keeps its per-post accounting there,
 * and this is the option REBUILD-PLAN §5 deliberately carries over on upgrade. Only the free
 * counters move.
 *
 * Seeding here rather than letting get_option() default to 0 is the point: the split is recorded
 * against a schema version, so it happens once and a later rename cannot hand out a second free
 * allowance by accident.
 */
final class Migration_1_2_0_FreeUsageCounters implements MigrationInterface
{
    public function version(): string
    {
        return '1.2.0';
    }

    public function describe(): string
    {
        return 'Separate the free run counters from legacy Pro bulk/automation totals.';
    }

    public function up(): void
    {
        // add_option() is a no-op when the option already exists, which is the idempotency guard:
        // a repeat run cannot wipe runs recorded since the split.
        add_option(UsageLimits::OPT_FREE_BULK_USED, 0, '', false);
        add_option(UsageLimits::OPT_FREE_AUTO_USED, 0, '', false);

        // Start the 30-day clock now if nothing has started it, so the fresh allowance expires on a
        // real period rather than inheriting a window that may already be most of the way through.
        if (!get_option(UsageLimits::OPT_PERIOD)) {
            add_option(UsageLimits::OPT_PERIOD, (int) current_time('timestamp'), '', false);
        }
    }
}
