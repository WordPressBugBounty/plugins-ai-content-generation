<?php

namespace WPWand\Admin;

use WPWand\Generation\Failover;

/**
 * The "we finished that one on a different provider" notice.
 *
 * {@see Failover} writes what happened to an option and stops there; this prints it, and clears it
 * when the reader says they're done with it. The message itself is built by Failover and is already
 * translated — nothing here rewords it, so the notice and the line in the result area can never
 * drift apart.
 *
 * WHERE IT SHOWS. Only on the screens that opt in, by calling {@see self::register()} from their own
 * register(). Today that is Bulk Generation and Automated Posts: long runs, where a silent switch of
 * provider is both most likely and least visible. The Settings screen renders the same option itself,
 * inside the React app, so it deliberately isn't a host here — one event, one notice per screen.
 *
 * Two rules from docs/LEARNINGS.md, both written after a nonce check on a shared hook returned 403
 * on every admin page:
 *
 *  - The dismiss parameter is namespaced (`wpwand-failover-dismiss`), because an unprefixed one
 *    collides with somebody else's — core's own `force-check` is the one that bit us.
 *  - A bad nonce FALLS THROUGH. `wp_verify_nonce()`, never `check_admin_referer()`: `admin_init`
 *    runs on requests that have nothing to do with us, and killing one of those is never the right
 *    answer.
 */
final class FailoverNotice
{
    /** Our dismiss parameter. Namespaced on purpose — see the class docblock. */
    private const DISMISS_ARG = 'wpwand-failover-dismiss';

    /** Nonce action for that parameter. */
    private const NONCE_ACTION = 'wpwand-failover-notice';

    /**
     * Page slugs allowed to show the notice, filled in by whoever calls register().
     *
     * @var array<string, bool>
     */
    private static array $hosts = [];

    /**
     * Let a screen host the notice.
     *
     * The hooks are static callbacks, so a second screen registering adds itself to the host list
     * without adding the actions twice — add_action() keys on the callback.
     *
     * @param string $page_slug The screen's own `page` slug, e.g. 'wpwand-bulk-new'.
     */
    public static function register(string $page_slug): void
    {
        if ($page_slug === '') {
            return;
        }

        self::$hosts[$page_slug] = true;

        add_action('admin_init', [self::class, 'maybe_dismiss']);
        add_action('admin_notices', [self::class, 'maybe_render']);
    }

    /**
     * "Dismiss" was clicked: forget the switch and reload without the spent parameter, so a refresh
     * or a back button never replays it.
     */
    public static function maybe_dismiss(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is what this reads.
        $nonce = isset($_GET[self::DISMISS_ARG])
            ? sanitize_text_field(wp_unslash((string) $_GET[self::DISMISS_ARG]))
            : '';

        // Not our request. This hook runs on nearly every admin page load, so leave at once.
        if ($nonce === '') {
            return;
        }

        if (!current_user_can('edit_posts') || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return;
        }

        Failover::dismiss_notice();

        wp_safe_redirect(esc_url_raw(remove_query_arg(self::DISMISS_ARG)));
        exit;
    }

    /** Print it, if there is something to say and this is a screen that says it. */
    public static function maybe_render(): void
    {
        if (!current_user_can('edit_posts') || !self::on_host_screen()) {
            return;
        }

        $notice  = Failover::notice();
        $message = isset($notice['message']) ? (string) $notice['message'] : '';

        if ($message === '') {
            return;
        }

        // 'warning' is a key that stopped working; everything else is informational — the post still
        // got written, just somewhere else.
        $severity = (isset($notice['severity']) && $notice['severity'] === 'warning') ? 'warning' : 'info';

        printf(
            '<div class="notice notice-%1$s"><p>%2$s</p><p><a class="button button-secondary" href="%3$s">%4$s</a> <a href="%5$s">%6$s</a></p></div>',
            esc_attr($severity),
            esc_html($message),
            esc_url(admin_url('admin.php?page=wpwand')),
            esc_html__('Open settings', 'ai-content-generation'),
            esc_url(self::dismiss_url()),
            esc_html__('Dismiss', 'ai-content-generation')
        );
    }

    /**
     * Is this one of the screens that asked to host the notice?
     *
     * Matched on the tail of the screen id rather than the whole thing: the id is built from the
     * parent menu's title, which the white-label settings can change.
     */
    private static function on_host_screen(): bool
    {
        if (self::$hosts === [] || !function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();
        if (!$screen || !isset($screen->id)) {
            return false;
        }

        $id = (string) $screen->id;

        foreach (array_keys(self::$hosts) as $slug) {
            if (substr($id, -strlen($slug)) === $slug) {
                return true;
            }
        }

        return false;
    }

    /** This same screen, plus the dismiss parameter and its nonce. */
    private static function dismiss_url(): string
    {
        return wp_nonce_url(
            remove_query_arg(self::DISMISS_ARG),
            self::NONCE_ACTION,
            self::DISMISS_ARG
        );
    }
}
