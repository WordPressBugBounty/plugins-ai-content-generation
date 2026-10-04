<?php

namespace WPWand\Data\Migrations;

use WPWand\Generation\UsageLimits;

/**
 * Clear legacy Pro totals that 2.0.0 reads as this month's usage.
 *
 * Before 2.0.0 the two wpwand_pgc_total_* options were lifetime counters — nothing ever reset them.
 * 2.0.0 introduced a 30-day period and kept the same option names, so on upgrade a customer's
 * whole history is read as if it had all happened this month. Somebody who generated 5,000 posts
 * over two years lands on 2.0.0 already over the cap and is refused for a full 30 days without
 * generating anything, and the min() clamp on the counter shows a tidy "100/100" that gives no clue
 * why.
 *
 * There is no way to tell a lifetime 5,000 from a genuine 5,000 this month, so this only zeroes a
 * counter that is at or above the smallest cap Pro ever grants — 100 bulk, 200 automation. Those are
 * exactly the people who are locked out; below that nobody is blocked and their real usage is left
 * alone. It costs one fresh allowance to somebody already at the wall, which is the cheaper of the
 * two mistakes.
 *
 * The 30-day clock is deliberately untouched. Whoever is mid-period keeps their window and gets the
 * remainder of it with a clean counter, which is the same call Migration_1_2_0_FreeUsageCounters and
 * Migration_1_3_0_AutomationRunCounter both made. Stamping a new period here would hand everyone a
 * full extra month on top.
 *
 * Free-tier counters (wpwand_free_*_runs_used) are not touched: they were born with the period and
 * never carried anything over.
 */
final class Migration_1_4_0_LegacyProCarryover implements MigrationInterface
{
    /** Smallest monthly cap Pro grants, per bucket. At or above this, the user is refused. */
    private const BLOCKING_BULK       = 100;
    private const BLOCKING_AUTOMATION = 200;

    public function version(): string
    {
        return '1.4.0';
    }

    public function describe(): string
    {
        return 'Clear pre-2.0.0 lifetime Pro totals that would otherwise read as this month’s usage.';
    }

    public function up(): void
    {
        $this->clear_if_blocking(UsageLimits::OPT_BULK_USED, self::BLOCKING_BULK);
        $this->clear_if_blocking(UsageLimits::OPT_AUTO_USED, self::BLOCKING_AUTOMATION);
    }

    /**
     * Idempotent by value as well as by the version gate: once the option is under the threshold
     * this does nothing, so running it twice is the same as running it once.
     */
    private function clear_if_blocking(string $option, int $threshold): void
    {
        if ((int) get_option($option, 0) >= $threshold) {
            update_option($option, 0, false);
        }
    }
}
