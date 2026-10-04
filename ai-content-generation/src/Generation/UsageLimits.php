<?php

namespace WPWand\Generation;

/**
 * Monthly usage caps for the Pro generation features, by license tier.
 *
 * Two independent monthly buckets:
 *   - BULK       (manual "Bulk Posts")     Solo 10 / Growth 30 / Agency unlimited
 *   - AUTOMATION (scheduled automation)    the same → Solo 10 / Growth 30 / Agency ∞
 *
 * The owner's numbers, 2026-10-04 ("solo te 10x growth 30x ar agency unlimited", and "b" to the
 * same cap for automation); bulk had been 100 / 300 and automation twice that since the plan was
 * moved to runs.
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
    /**
     * Legacy Pro totals, in POSTS. Nothing writes them since 2026-09-13 — every tier counts runs
     * now — but the options stay so an old site keeps its number and the 1.4.0 migration keeps
     * meaning.
     */
    public const OPT_BULK_USED = 'wpwand_pgc_total_bulk_generated';
    public const OPT_AUTO_USED = 'wpwand_pgc_total_automation_generated';

    /**
     * The run counters, in RUNS, for every tier since 2026-09-13. The option names say "free"
     * because they were free-only until then, and renaming a stored key is a migration for
     * nothing — the value is the same thing on both tiers: runs this period.
     *
     * They are kept apart from the legacy options above. The two used to share them, so a site
     * that had run legacy Pro bulk arrived here with its free allowance partly spent — against a
     * much smaller cap — and was blocked before generating anything.
     * @see Migration_1_2_0_FreeUsageCounters
     */
    public const OPT_FREE_BULK_USED = 'wpwand_free_bulk_runs_used';
    public const OPT_FREE_AUTO_USED = 'wpwand_free_automation_runs_used';

    public const OPT_PERIOD    = 'wpwand_usage_period_start';

    private const PERIOD       = 30 * DAY_IN_SECONDS;
    private const UNLIMITED    = -1;

    /** What a per-run reader returns when the tier has no ceiling on one run. */
    public const NO_CAP = -1;

    /** Free-tier monthly caps when Pro is not unlocked (both buckets: 5 per period). */
    private const FREE_BULK       = 5;
    private const FREE_AUTOMATION = 5;

    /**
     * Free-tier PER-RUN cap: one bulk generation may enqueue at most this many posts. The paid tiers
     * have their own in {@see bulk_per_run()}. Every tier's MONTHLY bulk cap counts RUNS (one
     * queue() call = 1 unit), NOT posts — so free reality is up to 5 runs/period, each run ≤ 10 posts.
     */
    public const FREE_BULK_PER_RUN = 10;

    /**
     * Posts one bulk run may hold, by tier: 10 on free, 20 on Solo, 50 on Growth, no ceiling on
     * Agency (NO_CAP). The owner's ladder, 2026-09-13 — the monthly allowance counts runs, so this is
     * the only thing that says how big a run may be. The queue gate, the wizard's headline box and
     * the AI-headlines request all read this one number.
     */
    public static function bulk_per_run(): int
    {
        switch (self::tier()) {
            case 'agency':
                return self::NO_CAP;
            case 'growth':
                return 50;
            case 'solo':
                return 20;
            default:
                return self::FREE_BULK_PER_RUN;
        }
    }

    /**
     * Free-tier PER-RUN cap for automation, same idea as FREE_BULK_PER_RUN: one scheduled run may
     * produce at most this many posts, and the run costs 1 unit of the monthly allowance whatever
     * it produces. Without it the schedule form offered 10 posts a run against a cap of 5 — the
     * shape of the "one click and limit exceeded" complaint. Pro (unlocked) keeps the full
     * {@see \WPWand\Automation\Schedules} range.
     */
    public const FREE_AUTOMATION_PER_RUN = 5;

    /** Whether Pro is unlocked — exposed so callers can branch per-run limits and localize isPro. */
    public static function is_pro(): bool
    {
        return self::pro_unlocked();
    }

    /**
     * The tier this site is on: 'free', 'solo', 'growth' or 'agency'.
     *
     * One reading of the marker for everything that branches on it — the monthly caps, the plan
     * badge, and how many posts a scheduled run may ask for. Pro wrote different numbers for the
     * same tiers before 1.2.7 (growth 15, solo 5; now 20 and 10, wp-wand-pro ae2eed7), so both
     * growth values are matched by name rather than by a >= test that would read a legacy growth
     * marker as solo.
     */
    public static function tier(): string
    {
        if (!self::pro_unlocked()) {
            return 'free';
        }
        $marker = (int) get_option('wpwand_pgc_limit', 10);
        if ($marker === self::UNLIMITED) {
            return 'agency';
        }
        return in_array($marker, [15, 20], true) || $marker > 20 ? 'growth' : 'solo';
    }

    /**
     * Effective monthly caps for the current tier, in RUNS.
     *
     * The numbers are the plan's — 100 bulk on Solo, 300 on Growth, automation twice that, no cap
     * on Agency — and since 2026-09-13 they count runs, not posts, the owner's call: the customer
     * writes on their own key, so a run of a hundred posts costs the plugin's owner nothing, and
     * what a paying customer wants to know is how many times, or on how many days, they can run.
     * Free was already runs. The marker only names the tier.
     *
     * @return array{bulk:int, automation:int}
     */
    public static function limits(): array
    {
        switch (self::tier()) {
            case 'agency':
                return ['bulk' => self::UNLIMITED, 'automation' => self::UNLIMITED];
            case 'growth':
                return ['bulk' => 30, 'automation' => 30];
            case 'solo':
                return ['bulk' => 10, 'automation' => 10];
            default:
                return ['bulk' => self::FREE_BULK, 'automation' => self::FREE_AUTOMATION];
        }
    }

    /**
     * The plan this site is on, in words, or '' on free.
     *
     * The screens show a plan badge where a free site shows its remaining runs — a paying customer
     * has no counter worth watching, so the chip says what they bought instead. Derived from the
     * same marker `limits()` reads, so the two can never disagree.
     */
    public static function plan_name(): string
    {
        switch (self::tier()) {
            case 'agency':
                return __('Agency plan', 'ai-content-generation');
            case 'growth':
                return __('Growth plan', 'ai-content-generation');
            case 'solo':
                return __('Solo plan', 'ai-content-generation');
            default:
                return '';
        }
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
        return (int) get_option(self::bulk_option(), 0);
    }

    /**
     * Every tier counts runs in the run options. Pro used to keep counting posts in the legacy
     * totals; that stopped on 2026-09-13, and no migration was needed — the run options hold the
     * current period only, so a site that activates Pro mid-period carries at most its five free
     * runs into a cap of a hundred.
     */
    private static function bulk_option(): string
    {
        return self::OPT_FREE_BULK_USED;
    }

    private static function auto_option(): string
    {
        return self::OPT_FREE_AUTO_USED;
    }

    /** How many more bulk runs may be started this period (PHP_INT_MAX when unlimited). */
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
        update_option(self::bulk_option(), self::bulk_used() + $count);
    }

    /**
     * Consume the bulk allowance for ONE bulk generation (one queue() call).
     *
     * A whole run costs exactly one unit however many posts it produces, on every tier. Free's
     * per-run post volume is gated separately by FREE_BULK_PER_RUN; Pro's is not gated. Pro was
     * charged per post until 2026-09-13.
     */
    public static function consume_bulk_run(): void
    {
        self::consume_bulk(1);
    }

    /**
     * Give back bulk allowance that was charged for work which never arrived.
     *
     * The counter cannot go below zero: a refund with nothing left to give back is a no-op rather
     * than a negative balance that would silently hand out free allowance next period.
     *
     * THE PERIOD: both counters only ever hold the CURRENT 30-day period, so a refund lands in the
     * period it is applied in even when the charge happened in the one before. That is deliberate —
     * a closed period cannot be edited, and the alternative (skip the refund because the month
     * rolled over) leaves the user paying for a generation that never arrived.
     */
    public static function refund_bulk(int $count): void
    {
        if (self::bulk_limit() === self::UNLIMITED || $count < 1) {
            return;
        }
        update_option(self::bulk_option(), max(0, self::bulk_used() - $count));
    }

    /**
     * Give ONE run back — the mirror of {@see consume_bulk_run()}.
     *
     * The run was charged one unit whatever it produced, so the caller only reaches here once the
     * entire run has failed (JobRunner::refund_failure checks the siblings).
     */
    public static function refund_bulk_run(): void
    {
        self::refund_bulk(1);
    }

    // ---- Automation ------------------------------------------------------------------------

    public static function automation_limit(): int
    {
        return self::limits()['automation'];
    }

    public static function automation_used(): int
    {
        self::maybe_reset();
        return (int) get_option(self::auto_option(), 0);
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
        update_option(self::auto_option(), self::automation_used() + $count);
    }

    /**
     * Consume the automation allowance for ONE scheduled run — the twin of {@see consume_bulk_run()}.
     *
     * A whole run costs exactly one unit however many posts it produces, on every tier. Charging
     * per post is what made a daily 5-post schedule die on day 1 and a 1-post-a-day schedule die
     * on day 5 — on free in 2026-08, and on Pro until 2026-09-13, where a 200-post cap read as
     * two hundred days of a one-post schedule or forty days of a five-post one. Per-run volume is
     * the schedule's own count, capped by {@see \WPWand\Automation\Schedules::max_count()}.
     */
    public static function consume_automation_run(): void
    {
        self::consume_automation(1);
    }

    /**
     * Give back automation allowance that was charged for work which never arrived.
     *
     * The allowance is spent at queue time because that is where the count is known, so a
     * generation that then fails has been paid for and produced nothing. Two of this plugin's own
     * empty-response failures took nearly half a free month.
     *
     * The counter cannot go below zero, and — as with {@see refund_bulk()} — the refund lands in
     * the current period even when the charge happened in the previous one. Only the current
     * period is stored, and dropping the refund at a month boundary would keep the user's money
     * for work they never got.
     */
    public static function refund_automation(int $count): void
    {
        if (self::automation_limit() === self::UNLIMITED || $count < 1) {
            return;
        }
        update_option(self::auto_option(), max(0, self::automation_used() - $count));
    }

    /**
     * Give ONE scheduled run back — the mirror of {@see consume_automation_run()}.
     *
     * The run was charged one unit however many posts it queued, so the caller only reaches here
     * once the entire run has failed.
     */
    public static function refund_automation_run(): void
    {
        self::refund_automation(1);
    }

    // ---- Monthly reset ---------------------------------------------------------------------

    /**
     * Reset both counters every 30 days while the license is active. No-op when the license isn't
     * active (Pro features don't run then anyway) so a lapsed license can't silently refill its quota.
     */
    public static function maybe_reset(): void
    {
        // A lapsed licence must not silently refill the Pro tier caps — but the FREE counters are
        // not the licence's to hold. Someone who bought Pro and can't activate it was capped at 5
        // forever, which is the opposite of what a stuck licence should cost them.
        $pro_frozen = class_exists('WPWand\\License\\LicenseService')
            && get_option(\WPWand\License\LicenseService::OPT_STATUS) !== 'activated';

        $now   = (int) current_time('timestamp');
        $start = (int) get_option(self::OPT_PERIOD, 0);

        if ($start <= 0) {
            update_option(self::OPT_PERIOD, $now); // first run — start the clock
            return;
        }
        if ($now < $start + self::PERIOD) {
            return;
        }

        update_option(self::OPT_FREE_BULK_USED, 0);
        update_option(self::OPT_FREE_AUTO_USED, 0);

        if (!$pro_frozen) {
            update_option(self::OPT_BULK_USED, 0);
            update_option(self::OPT_AUTO_USED, 0);
        }

        update_option(self::OPT_PERIOD, $now);
    }

    /** Human label for the bulk counter, e.g. "12/100" or "Unlimited". */
    public static function bulk_text(): string
    {
        $limit = self::bulk_limit();
        return $limit === self::UNLIMITED
            ? __('Unlimited', 'ai-content-generation')
            : min(self::bulk_used(), $limit) . '/' . $limit;
    }

    /** Human label for the automation counter, e.g. "30/600" or "Unlimited". */
    public static function automation_text(): string
    {
        $limit = self::automation_limit();
        return $limit === self::UNLIMITED
            ? __('Unlimited', 'ai-content-generation')
            : min(self::automation_used(), $limit) . '/' . $limit;
    }

    /**
     * The date the current 30-day period rolls over, in the site's date format.
     *
     * The counters reset lazily in {@see maybe_reset()}, so this is when the next usage check hands
     * back a full allowance — the one thing our own limit messages have never told anyone.
     */
    public static function reset_date(): string
    {
        $start = (int) get_option(self::OPT_PERIOD, 0);
        if ($start <= 0) {
            $start = (int) current_time('timestamp');
        }

        return date_i18n((string) get_option('date_format', 'F j, Y'), $start + self::PERIOD);
    }

    /**
     * The message for running out of one of OUR OWN monthly meters.
     *
     * Bulk and automation both print this so the two read alike, and so both say the three things
     * the old copy left out: that the limit is WP Wand's (not the provider's — {@see
     * ErrorFormatter::humanize()} makes the same distinction from the other side), where the meter
     * stands, and the date it comes back.
     *
     * @param string $meter 'automation', or 'bulk' for anything else.
     */
    public static function limit_reached_message(string $meter): string
    {
        if ($meter === 'automation') {
            /* translators: 1: usage so far, e.g. "5/5". 2: the date the allowance resets. */
            $template = __('You\'ve used this month\'s automation allowance (%1$s). It resets on %2$s. Upgrade for a higher limit if you need more now.', 'ai-content-generation');
            $used     = self::automation_text();
        } else {
            /* translators: 1: usage so far, e.g. "5/5". 2: the date the allowance resets. */
            $template = __('You\'ve used this month\'s bulk allowance (%1$s). It resets on %2$s. Upgrade for a higher limit if you need more now.', 'ai-content-generation');
            $used     = self::bulk_text();
        }

        return sprintf($template, $used, self::reset_date());
    }
}
