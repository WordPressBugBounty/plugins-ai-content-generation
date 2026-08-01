<?php

namespace WPWand\Generation;

/**
 * Monthly usage caps for the Pro generation features, by license tier.
 *
 * Two independent monthly buckets:
 *   - BULK       (manual "Bulk Posts")     Solo 100 / Growth 300 / Agency unlimited
 *   - AUTOMATION (scheduled automation)    2× the bulk cap → Solo 200 / Growth 600 / Agency ∞
 *
 * The effective cap is derived from the tier at check time via {@see limits()}, NOT from a value
 * frozen at activation — so changing these numbers takes effect for every existing install with no
 * re-activation. The stored `wpwand_pgc_limit` (-1 / 20 / 10) is still written on activation and is
 * used here purely as the tier MARKER (agency / growth / solo), keeping backward compatibility.
 *
 * Both counters reset automatically every 30 days **while the license is active** (the requirement:
 * "limits reset monthly if the license is working"). Agency (-1) is unlimited and never blocks.
 */
class UsageLimits
{
    public const OPT_BULK_USED = 'wpwand_pgc_total_bulk_generated';
    public const OPT_AUTO_USED = 'wpwand_pgc_total_automation_generated';
    public const OPT_PERIOD    = 'wpwand_usage_period_start';

    private const PERIOD       = 30 * DAY_IN_SECONDS;
    private const UNLIMITED    = -1;

    /** Free-tier monthly caps when Pro is not unlocked (both buckets: 5 per period). */
    private const FREE_BULK       = 5;
    private const FREE_AUTOMATION = 5;

    /**
     * Free-tier PER-RUN cap: one bulk generation may enqueue at most this many posts. Pro (unlocked)
     * has no per-run limit. Note the free MONTHLY bulk cap counts RUNS (one queue() call = 1 unit),
     * NOT posts — so free reality is up to 5 runs/period, each run ≤ 10 posts.
     */
    public const FREE_BULK_PER_RUN = 10;

    /** Whether Pro is unlocked — exposed so callers can branch per-run limits and localize isPro. */
    public static function is_pro(): bool
    {
        return self::pro_unlocked();
    }

    /** Effective monthly caps for the current tier. @return array{bulk:int, automation:int} */
    public static function limits(): array
    {
        // Free / standalone: fixed caps, no license tier involved.
        if (!self::pro_unlocked()) {
            return ['bulk' => self::FREE_BULK, 'automation' => self::FREE_AUTOMATION];
        }

        $marker = (int) get_option('wpwand_pgc_limit', 10);

        if ($marker === self::UNLIMITED) {
            return ['bulk' => self::UNLIMITED, 'automation' => self::UNLIMITED]; // agency
        }
        $bulk = $marker >= 20 ? 300 : 100; // growth : solo

        return ['bulk' => $bulk, 'automation' => $bulk * 2];
    }

    /** Whether Pro is unlocked (safe when the Pro gate helper is absent). */
    private static function pro_unlocked(): bool
    {
        return class_exists('WPWand\\Core\\Pro') && \WPWand\Core\Pro::unlocked();
    }

    // ---- Bulk ------------------------------------------------------------------------------

    public static function bulk_limit(): int
    {
        return self::limits()['bulk'];
    }

    public static function bulk_used(): int
    {
        self::maybe_reset();
        return (int) get_option(self::OPT_BULK_USED, 0);
    }

    /** How many more bulk posts may be generated now (PHP_INT_MAX when unlimited). */
    public static function bulk_remaining(): int
    {
        $limit = self::bulk_limit();
        if ($limit === self::UNLIMITED) {
            return PHP_INT_MAX;
        }
        return max(0, $limit - self::bulk_used());
    }

    public static function can_bulk(int $need = 1): bool
    {
        return self::bulk_limit() === self::UNLIMITED || self::bulk_remaining() >= max(1, $need);
    }

    public static function consume_bulk(int $count): void
    {
        if (self::bulk_limit() === self::UNLIMITED || $count < 1) {
            return;
        }
        update_option(self::OPT_BULK_USED, self::bulk_used() + $count);
    }

    /**
     * Consume the bulk allowance for ONE bulk generation (one queue() call).
     *
     * Free tier: the monthly bulk cap counts RUNS, so a whole run consumes exactly 1 unit regardless
     * of how many posts it produces (per-run post volume is gated separately by FREE_BULK_PER_RUN).
     * Pro tier: keep the legacy per-post accounting so tier caps (100/300/∞) stay in posts.
     *
     * @param int $posts Number of posts this run will enqueue (used for the Pro per-post count).
     */
    public static function consume_bulk_run(int $posts): void
    {
        self::consume_bulk(self::is_pro() ? $posts : 1);
    }

    // ---- Automation ------------------------------------------------------------------------

    public static function automation_limit(): int
    {
        return self::limits()['automation'];
    }

    public static function automation_used(): int
    {
        self::maybe_reset();
        return (int) get_option(self::OPT_AUTO_USED, 0);
    }

    public static function automation_remaining(): int
    {
        $limit = self::automation_limit();
        if ($limit === self::UNLIMITED) {
            return PHP_INT_MAX;
        }
        return max(0, $limit - self::automation_used());
    }

    public static function can_automation(int $need = 1): bool
    {
        return self::automation_limit() === self::UNLIMITED || self::automation_remaining() >= max(1, $need);
    }

    public static function consume_automation(int $count): void
    {
        if (self::automation_limit() === self::UNLIMITED || $count < 1) {
            return;
        }
        update_option(self::OPT_AUTO_USED, self::automation_used() + $count);
    }

    // ---- Monthly reset ---------------------------------------------------------------------

    /**
     * Reset both counters every 30 days while the license is active. No-op when the license isn't
     * active (Pro features don't run then anyway) so a lapsed license can't silently refill its quota.
     */
    public static function maybe_reset(): void
    {
        // When Pro/License is present, only run the clock while the license is active (so a lapsed
        // license can't silently refill its quota). When Pro is absent (Free standalone), the caps
        // are fixed and must still reset every period.
        if (class_exists('WPWand\\License\\LicenseService')
            && get_option(\WPWand\License\LicenseService::OPT_STATUS) !== 'activated') {
            return;
        }

        $now   = (int) current_time('timestamp');
        $start = (int) get_option(self::OPT_PERIOD, 0);

        if ($start <= 0) {
            update_option(self::OPT_PERIOD, $now); // first run — start the clock
            return;
        }
        if ($now >= $start + self::PERIOD) {
            update_option(self::OPT_BULK_USED, 0);
            update_option(self::OPT_AUTO_USED, 0);
            update_option(self::OPT_PERIOD, $now);
        }
    }

    /** Human label for the bulk counter, e.g. "12/100" or "Unlimited". */
    public static function bulk_text(): string
    {
        $limit = self::bulk_limit();
        return $limit === self::UNLIMITED
            ? __('Unlimited', 'wp-wand-pro')
            : self::bulk_used() . '/' . $limit;
    }

    /** Human label for the automation counter, e.g. "30/600" or "Unlimited". */
    public static function automation_text(): string
    {
        $limit = self::automation_limit();
        return $limit === self::UNLIMITED
            ? __('Unlimited', 'wp-wand-pro')
            : self::automation_used() . '/' . $limit;
    }
}
