<?php

namespace WPWand\Admin;

/**
 * The wordpress.org review ask: who sees it, what step they are on, and the one option that
 * remembers the answer.
 *
 * Ported from the ConvertPro flow. Two steps, in this order:
 *
 *   1. "WP Wand working out for you?" — Yes, it is / Not really / Ask me later / No thanks.
 *      happy + unhappy are recorded and move on to step 2. "later" goes quiet for 30 days.
 *      "No thanks" ends it permanently.
 *   2. happy gets a thank-you, unhappy gets an apology and the support forum — and BOTH get the
 *      review link. Showing the link only to happy users is review gating: wordpress.org bans it,
 *      and it makes the rating a lie. The answer picks the tone, never who gets the door.
 *
 * Nobody is asked until the plugin has actually done something for them: 10+ history rows in the
 * last 14 days, spread over at least 2 separate days. One long session on a single afternoon is
 * somebody trying the plugin out, not somebody using it. History::record() runs on the free
 * Assistant path (GenerateController + StreamController, both in the free AssistantModule), so
 * this counts free installs, not just Pro.
 *
 * State lives in ONE option, {@see self::OPTION}, per site rather than per user — the site gets
 * asked once, not once per administrator.
 *
 * Off switch for anyone who wants nothing to do with it:
 *   add_filter( 'wpwand_show_review_ask', '__return_false' );
 */
final class ReviewPrompt
{
    /**
     * The whole flow, in one option: asked_at, answer, answered_at, clicked_at (all unix
     * timestamps except answer). Deliberately NOT autoloaded — nothing outside wp-admin reads it,
     * so the front end shouldn't carry it on every page view.
     */
    public const OPTION = 'wpwand_review';

    /** Where the review link goes. Slug confirmed against the wordpress.org listing: ai-content-generation. */
    public const REVIEW_URL = 'https://wordpress.org/support/plugin/ai-content-generation/reviews/';

    /** Where an unhappy answer is pointed instead of the review page. */
    public const SUPPORT_URL = 'https://wordpress.org/support/plugin/ai-content-generation/';

    /** Every answer the flow accepts. */
    public const ANSWERS = ['happy', 'unhappy', 'later', 'dismissed'];

    /** Answers that close the question for good — the ask never comes back after one of these. */
    private const FINAL_ANSWERS = ['happy', 'unhappy', 'dismissed'];

    /** Script handles the config is attached to. These are the screens Wave 2 mounts on. */
    private const HANDLES = [
        'wpwand-assistant-app',
        'wpwand-bulk-app',
        'wpwand-automation-app',
    ];

    /** "Ask me later" means this many days of silence. */
    private const LATER_DAYS = 30;

    /** The step-2 follow-up gives up on its own after this many days. */
    private const FOLLOWUP_DAYS = 14;

    /** The usage window, and what has to be in it. */
    private const WINDOW_DAYS = 14;
    private const MIN_ROWS    = 10;
    private const MIN_DAYS    = 2;

    /**
     * Cache for the usage query only — not state. The answer barely moves, and without this every
     * admin page load would COUNT() the history table (created_at carries no index).
     */
    private const USAGE_CACHE = 'wpwand_review_usage';
    private const USAGE_TTL   = 12 * HOUR_IN_SECONDS;

    public function register(): void
    {
        // Late (100) so the host screen's own enqueue has already run and its handle is registered.
        add_action('admin_enqueue_scripts', [$this, 'localize'], 100);

        add_action('rest_api_init', static function () {
            (new \WPWand\Rest\Controllers\ReviewController())->register_routes();
        });

        // Three fields ride along with the weekly Finestics ping. See add_telemetry().
        add_filter(self::tracker_filter(), [$this, 'add_telemetry']);
    }

    /**
     * Put the current step on window.wpwandApi.review for whichever WP Wand bundle this screen
     * loaded, so the component knows what to render without a round trip of its own.
     */
    public function localize(): void
    {
        // state(), not script_config(): enqueuing a bundle is not the same as showing anybody the
        // question. The assistant loads on every admin screen and its panel is usually shut, so
        // stamping here made asked_at mean "became eligible" on sites where nothing was ever shown.
        // The component stamps it when it actually renders, through POST /review/shown.
        $config = self::state();

        foreach (self::HANDLES as $handle) {
            if (wp_script_is($handle, 'enqueued')) {
                ScriptConfig::merge($handle, ['review' => $config]);
            }
        }
    }

    /**
     * What the component needs, and the side effect of showing it: the first time step 1 is put in
     * front of somebody, stamp asked_at.
     *
     * Public so Wave 2 can attach it to a handle this class doesn't know about.
     *
     * @return array<string, mixed>
     */
    public static function script_config(): array
    {
        $state = self::state();

        if ($state['step'] === 'ask') {
            self::stamp_asked();
        }

        return $state;
    }

    /**
     * The component says it has actually put the question on screen. This is the only honest place
     * to stamp asked_at, and the reason GET /review no longer writes anything.
     */
    public static function mark_shown(): array
    {
        $state = self::state();

        if ($state['step'] === 'ask') {
            self::stamp_asked();
        }

        return $state;
    }

    /**
     * Which step this user is on.
     *
     * step is one of:
     *   'none'   — show nothing
     *   'ask'    — step 1, the question
     *   'thanks' — step 2 after "Yes, it is"
     *   'sorry'  — step 2 after "Not really"
     *
     * @return array<string, mixed>
     */
    public static function state(): array
    {
        $step = 'none';

        if (self::enabled() && current_user_can('manage_options')) {
            $option = self::option();

            if (self::should_ask($option)) {
                $step = 'ask';
            } elseif (self::should_follow_up($option)) {
                $step = $option['answer'] === 'happy' ? 'thanks' : 'sorry';
            }
        }

        return [
            'step'       => $step,
            'reviewUrl'  => self::REVIEW_URL,
            'supportUrl' => self::SUPPORT_URL,
        ];
    }

    /**
     * Record an answer and hand back the step that follows it.
     *
     * @param string $answer One of self::ANSWERS.
     *
     * @return array<string, mixed>|null Null when the answer isn't one we know.
     */
    public static function record_answer(string $answer): ?array
    {
        if (!in_array($answer, self::ANSWERS, true)) {
            return null;
        }

        $option = self::option();

        // A final answer is final. Without this, two admin tabs are enough to undo it: "No thanks"
        // in one, then a stale "Yes, it is" in the other, and a site that asked never to be asked
        // again is shown the review link. Replaying the request does the same.
        if (in_array($option['answer'], self::FINAL_ANSWERS, true)) {
            return self::state(); // same shape the caller gets on the normal path
        }

        // An answer is proof they saw the question, even if the enqueue that normally stamps
        // asked_at never ran (the component can be handed its state over REST instead).
        if ($option['asked_at'] <= 0) {
            $option['asked_at'] = time();
        }

        $option['answer']      = $answer;
        $option['answered_at'] = time();

        self::save($option);

        return self::state();
    }

    /**
     * Record that they went to the review page.
     *
     * clicked_at means "opened the wordpress.org review form", NOT "left a review". wordpress.org
     * sends nothing back, so whether a review was actually written is something this plugin cannot
     * know and must never claim to.
     *
     * @return array<string, mixed>
     */
    public static function record_click(): array
    {
        $option               = self::option();
        $option['clicked_at'] = time();

        self::save($option);

        return self::state();
    }

    /**
     * The three fields that ride along with the weekly Finestics ping.
     *
     * Shaped for Insights::get_extra_data() — it accepts an array or a callable returning one, and
     * get_tracking_data() drops whatever comes back under $data['extra']. Timestamps go out as UTC
     * 'Y-m-d H:i:s' so a server-side reader doesn't have to guess a timezone; never happened is an
     * empty string rather than 0, which would read as 1970.
     *
     * @return array<string, string>
     */
    public static function telemetry(): array
    {
        $option = self::option();

        return [
            'review_asked_at'   => self::as_utc($option['asked_at']),
            'review_answer'     => $option['answer'],
            // Went to the review page. Not "left a review" — see record_click().
            'review_clicked_at' => self::as_utc($option['clicked_at']),
        ];
    }

    /**
     * Merge the review fields into the tracker payload's `extra` bag.
     *
     * The Insights instance is created and thrown away inside wpwand_init(), so there is no handle
     * to call add_extra() on from here. This filter is the same seam one step later: Insights sets
     * $data['extra'] from add_extra() and then runs the payload through
     * apply_filters( '{slug}_tracker_data', $data ). Merging (rather than assigning) means this
     * still behaves if add_extra() is ever wired up as well.
     *
     * @param mixed $data Tracker payload.
     *
     * @return mixed
     */
    public function add_telemetry($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        $extra = (isset($data['extra']) && is_array($data['extra'])) ? $data['extra'] : [];

        $data['extra'] = array_merge($extra, self::telemetry());

        return $data;
    }

    /** The site owner's off switch. */
    private static function enabled(): bool
    {
        return (bool) apply_filters('wpwand_show_review_ask', true);
    }

    /**
     * Step 1 shows when the question is still open and the plugin has earned it.
     *
     * @param array<string, mixed> $option
     */
    private static function should_ask(array $option): bool
    {
        if (in_array($option['answer'], self::FINAL_ANSWERS, true)) {
            return false;
        }

        if ($option['answer'] === 'later') {
            $due = $option['answered_at'] + (self::LATER_DAYS * DAY_IN_SECONDS);
            if ($option['answered_at'] <= 0 || time() < $due) {
                return false;
            }
        }

        return self::used_enough();
    }

    /**
     * Step 2 shows after a happy/unhappy answer, until they follow the link or 14 days pass.
     *
     * @param array<string, mixed> $option
     */
    private static function should_follow_up(array $option): bool
    {
        if ($option['answer'] !== 'happy' && $option['answer'] !== 'unhappy') {
            return false;
        }

        if ($option['clicked_at'] > 0) {
            return false;
        }

        return time() < $option['answered_at'] + (self::FOLLOWUP_DAYS * DAY_IN_SECONDS);
    }

    /** Did the plugin actually work for them? Cached, because the raw query is a table scan. */
    private static function used_enough(): bool
    {
        $cached = get_transient(self::USAGE_CACHE);
        if ($cached === 'yes') {
            return true;
        }
        if ($cached === 'no') {
            return false;
        }

        $ok = self::measure_usage();
        set_transient(self::USAGE_CACHE, $ok ? 'yes' : 'no', self::USAGE_TTL);

        return $ok;
    }

    /**
     * 10+ generations in the last 14 days, on 2+ separate days.
     *
     * The window is computed by MySQL rather than PHP on purpose: created_at is filled by the
     * column's own CURRENT_TIMESTAMP default, so NOW() is the only clock guaranteed to agree with
     * it. A PHP-side threshold would silently drift wherever the site timezone and the database
     * timezone disagree.
     */
    private static function measure_usage(): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wpwand_history';

        // The table is missing until the baseline migration runs; a first-load miss shouldn't print
        // a database error into the admin.
        $suppressed = $wpdb->suppress_errors(true);

        // Custom history table; the table name is a constant with $wpdb->prefix and the window is
        // prepared. Admin-only, cached in a transient above.
        // phpcs:disable WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT COUNT(*) AS rows_seen, COUNT(DISTINCT DATE(created_at)) AS days_seen
                 FROM {$table}
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
                self::WINDOW_DAYS
            ),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB

        $wpdb->suppress_errors($suppressed);

        if (!is_array($row)) {
            return false;
        }

        return (int) $row['rows_seen'] >= self::MIN_ROWS
            && (int) $row['days_seen'] >= self::MIN_DAYS;
    }

    /** First time the question is put in front of somebody. Written once. */
    private static function stamp_asked(): void
    {
        $option = self::option();

        if ($option['asked_at'] > 0) {
            return;
        }

        $option['asked_at'] = time();
        self::save($option);
    }

    /**
     * The stored option, normalised. Anything unrecognised reads as "never answered" rather than
     * throwing — this option is a hint, not a source of truth about anything important.
     *
     * @return array{asked_at:int, answer:string, answered_at:int, clicked_at:int}
     */
    private static function option(): array
    {
        $stored = get_option(self::OPTION, []);
        if (!is_array($stored)) {
            $stored = [];
        }

        $answer = isset($stored['answer']) ? (string) $stored['answer'] : '';
        if (!in_array($answer, self::ANSWERS, true)) {
            $answer = '';
        }

        return [
            'asked_at'    => isset($stored['asked_at']) ? (int) $stored['asked_at'] : 0,
            'answer'      => $answer,
            'answered_at' => isset($stored['answered_at']) ? (int) $stored['answered_at'] : 0,
            'clicked_at'  => isset($stored['clicked_at']) ? (int) $stored['clicked_at'] : 0,
        ];
    }

    /** @param array<string, mixed> $option */
    private static function save(array $option): void
    {
        update_option(self::OPTION, $option, false);
    }

    private static function as_utc(int $timestamp): string
    {
        return $timestamp > 0 ? gmdate('Y-m-d H:i:s', $timestamp) : '';
    }

    /**
     * The Finestics payload filter, which is named after the plugin's directory: Client builds its
     * slug from plugin_basename(), so it is 'ai-content-generation' on a wordpress.org install and
     * whatever the folder is called anywhere else. Derive it the same way instead of hardcoding it.
     */
    private static function tracker_filter(): string
    {
        $slug = 'ai-content-generation';

        if (defined('WPWAND_PLUGIN_DIR')) {
            $dir = basename(untrailingslashit(WPWAND_PLUGIN_DIR));
            if ($dir !== '' && $dir !== '.') {
                $slug = $dir;
            }
        }

        return $slug . '_tracker_data';
    }
}
