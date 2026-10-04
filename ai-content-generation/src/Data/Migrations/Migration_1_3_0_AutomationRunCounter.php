<?php

namespace WPWand\Data\Migrations;

use WPWand\Generation\UsageLimits;

/**
 * Re-zero the free automation counter now that it holds RUNS instead of POSTS.
 *
 * wpwand_free_automation_runs_used is named for runs and the cap of 5 was always meant to be 5 runs
 * per 30 days, but AutomationRunner charged it count($titles) — posts. A single schedule set to 10
 * posts a day spent the whole allowance on its first tick; a 1-post-a-day schedule died on day 5.
 * With the charge fixed to 1 per run, every value already stored is a post count being read as a
 * run count, so a mid-period user would start out looking several runs poorer than they are.
 *
 * Only that ONE option is touched. wpwand_free_bulk_runs_used has always counted runs correctly
 * (consume_bulk_run), and the legacy Pro totals wpwand_pgc_total_* stay exactly where they are — the
 * same line Migration_1_2_0_FreeUsageCounters draws.
 *
 * The 30-day clock is left alone too: whoever is mid-period keeps their window and gets the
 * remainder of it with a full allowance, which errs generous, same as the 1.2.0 split did.
 */
final class Migration_1_3_0_AutomationRunCounter implements MigrationInterface
{
    public function version(): string
    {
        return '1.3.0';
    }

    public function describe(): string
    {
        return 'Reset the free automation counter after the switch from per-post to per-run billing.';
    }

    public function up(): void
    {
        // Idempotent by value, not just by version gate: zeroing twice is the same as zeroing once.
        update_option(UsageLimits::OPT_FREE_AUTO_USED, 0, false);
    }
}
