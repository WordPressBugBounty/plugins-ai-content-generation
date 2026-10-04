<?php

namespace WPWand\Data\Migrations;

use WPWand\Generation\UsageLimits;

/**
 * Give back the allowance that failed generations took, once, on every install that already has it.
 *
 * The allowance was spent when posts were QUEUED and never returned when the generation failed, so
 * a run that produced nothing still cost the user a unit. That is fixed going forward — JobRunner
 * refunds at its retry limit — but the fix does nothing for a counter that is already wrong, and
 * every install has one. On the machine this was found on, the counter read 5 of 5 against three
 * posts actually delivered: one failure, and one run that queued nothing at all.
 *
 * So: count the failed jobs in the user's CURRENT period and take them off the counter.
 *
 * WHY ONLY THE CURRENT PERIOD. The counters reset every 30 days and hold no history, so an older
 * period cannot be reconstructed and, more to the point, is already spent and gone. What matters is
 * the window the user is standing in right now — the one telling them they have nothing left.
 *
 * WHY THE FREE COUNTERS ONLY. Free is billed per RUN and Pro per POST, and the Pro totals
 * (wpwand_pgc_total_*) are lifetime figures the licence server also reads — the same line
 * Migration_1_2_0_FreeUsageCounters and Migration_1_3_0_AutomationRunCounter both drew. A Pro
 * install's cap is high or absent, so the harm this fixes barely exists there.
 *
 * ERRS GENEROUS, DELIBERATELY. A job whose row was deleted leaves no trace, so a failure may go
 * uncounted; nothing here can over-refund, because the count is floored at what was actually spent.
 * Giving a user back a run they were owed is the right way to be wrong.
 *
 * IDEMPOTENT. Version-gated by the runner, and it writes an absolute value rather than subtracting,
 * so running it twice lands on the same number.
 */
final class Migration_1_5_0_RefundFailedRuns implements MigrationInterface
{
    public function version(): string
    {
        return '1.5.0';
    }

    public function describe(): string
    {
        return 'Return the monthly allowance that failed generations took before failures were refunded.';
    }

    public function up(): void
    {
        global $wpdb;

        $start = (int) get_option(UsageLimits::OPT_PERIOD, 0);
        if ($start <= 0) {
            return; // The clock has never started, so nothing has been charged.
        }

        $jobs = $wpdb->prefix . 'wpwand_gen_jobs';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $jobs)) !== $jobs) { // phpcs:ignore WordPress.DB
            return; // Pre-1.1.0 install, or the table was never created.
        }

        // created_at is stored in site time, the same clock OPT_PERIOD is set from.
        $since = gmdate('Y-m-d H:i:s', $start);

        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB
            $wpdb->prepare(
                "SELECT settings FROM {$jobs} WHERE status = 'failed' AND created_at >= %s", // phpcs:ignore
                $since
            ),
            ARRAY_A
        );

        $automation = 0;
        $bulk       = 0;

        foreach ($rows ?: [] as $row) {
            $settings = json_decode((string) $row['settings'], true) ?: [];
            // A scheduled job carries the schedule it belongs to; a bulk job does not.
            if (($settings['schedule_id'] ?? '') !== '') {
                $automation++;
            } else {
                $bulk++;
            }
        }

        if ($automation > 0) {
            self::give_back(UsageLimits::OPT_FREE_AUTO_USED, $automation);
        }
        if ($bulk > 0) {
            self::give_back(UsageLimits::OPT_FREE_BULK_USED, $bulk);
        }
    }

    /**
     * Take $count off a counter without letting it go negative.
     */
    private static function give_back(string $option, int $count): void
    {
        $used = (int) get_option($option, 0);
        if ($used <= 0) {
            return;
        }

        update_option($option, max(0, $used - $count), false);
    }
}
