<?php

namespace WPWand\Admin;

use WPWand\Rest\Controllers\OAuthController;

/**
 * The return leg of the OpenRouter connect flow.
 *
 * OpenRouter sends the user back to an admin URL carrying a `code`. This class picks it up,
 * trades it for a key through {@see OAuthController}, and bounces to a clean settings URL with
 * a one-word outcome so a refresh never re-runs anything.
 *
 * Two rules from docs/LEARNINGS.md shape everything below, both written after a nonce check on a
 * shared hook returned 403 on every admin page:
 *
 *  - This runs on `admin_init`, which nearly every request reaches, so it leaves immediately
 *    unless it sees its own namespaced parameter. `code` is OpenRouter's name, not ours, and is
 *    only ever read once `wpwand-oauth` is present and its nonce checks out.
 *  - A failed check FALLS THROUGH. Never wp_die(), never wp_nonce_ays(). A callback URL gets
 *    bookmarked, refreshed and revisited, and the honest answer to "this link is spent" is the
 *    settings screen with a sentence explaining it, not a dead page.
 */
final class OAuthCallback
{
    public function register(): void
    {
        add_action('admin_init', [$this, 'maybe_handle']);
        add_action('admin_notices', [$this, 'maybe_notice']);
    }

    /**
     * Keep an authorization code intact.
     *
     * sanitize_text_field() removes percent-encoded sequences, which is right for display text and
     * wrong for a credential-bearing code. Unreserved URL characters plus % is the safe whitelist.
     */
    /**
     * Is this a return leg whose query string the provider threw away?
     *
     * Deliberately narrow: a bare `code` is a common parameter, so it only counts when this very
     * user has a live, unexpired flow waiting. Outside that window it is somebody else's request
     * and we leave it alone.
     */
    private static function looks_like_stripped_callback(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- no state changes here; the flow is authorised by the PKCE verifier read below.
        return !empty($_GET['code']) && self::has_live_state();
    }

    /** Does this user have an unexpired, unspent connect flow waiting? */
    private static function has_live_state(): bool
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return false;
        }

        $state = get_user_meta($user_id, OAuthController::USER_META, true);

        return is_array($state)
            && !empty($state['verifier'])
            && (int) ($state['expires'] ?? 0) >= time();
    }

    private static function clean_code(string $raw): string
    {
        return (string) preg_replace('/[^A-Za-z0-9._~%-]/', '', $raw);
    }

    /**
     * Finish the flow when this really is our callback, and do nothing at all otherwise.
     *
     * OpenRouter does not append to the callback URL it was handed — it REPLACES the query string
     * with its own `?code=…`. A real return looked like:
     *
     *   http://example.com/wp-admin/admin.php?code=ef5e181f-…
     *
     * so both `page=wpwand` and our own parameter were gone: nothing here fired, and wp-admin
     * rendered a blank page because admin.php had no `page` to show. Recognising our own parameter
     * is therefore the happy path, not the only one.
     *
     * The fallback is safe because the state does the work the URL cannot: it is stored against
     * THIS user, it is single-use, and it expires in fifteen minutes. So a bare `?code=` only means
     * anything at all in the short window after this user pressed Connect, and it is consumed once.
     * PKCE, not the nonce, is what stops a code from another flow being useful.
     */
    public function maybe_handle(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is what this reads.
        $raw = isset($_GET[OAuthController::QUERY_ARG])
            ? sanitize_text_field(wp_unslash((string) $_GET[OAuthController::QUERY_ARG]))
            : '';

        if ($raw === '' && !self::looks_like_stripped_callback()) {
            return;
        }

        // No live flow for this user means there is nothing here to finish, whatever the URL says.
        // Without this, any admin URL carrying wpwand-oauth= threw the admin over to the settings
        // screen — harmless, but somebody else could put that link in front of them.
        if (!self::has_live_state()) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- gated on the namespaced arg above, nonce verified below.
        // NOT sanitize_text_field(): it strips %xx sequences, so a code containing one would be
        // silently corrupted and the flow would fail forever, blaming the ten-minute expiry.
        // Whitelist the unreserved URL characters plus % instead.
        $code = isset($_GET['code']) ? self::clean_code(wp_unslash((string) $_GET['code'])) : '';

        $nonce = $raw;

        // Some providers build the return URL by sticking "?code=…" onto whatever callback they
        // were handed, which folds the code into the tail of OUR parameter instead of adding one
        // of its own. Recover both halves so the flow survives either behaviour.
        if (preg_match('/^([^?&]*)[?&]code=(.*)$/', $raw, $parts) === 1) {
            $nonce = $parts[1];
            if ($code === '') {
                $code = self::clean_code($parts[2]);
            }
        }

        // A user who cannot manage options cannot store a site-wide credential. Say nothing and
        // let the page render normally — this is not their request to answer.
        if (!current_user_can('manage_options')) {
            return;
        }

        // Only when the provider kept our parameter. On the stripped form there is no nonce to
        // verify, and refusing there would mean the flow can never complete at all.
        if ($raw !== '' && !wp_verify_nonce($nonce, OAuthController::NONCE_ACTION)) {
            $this->finish('expired');
            return;
        }

        $user_id  = get_current_user_id();
        $verifier = OAuthController::take_state($user_id);

        // No live flow: the link was already spent, or it timed out, or it was started in another
        // session. Nothing has changed either way.
        if ($verifier === '' || $code === '') {
            $this->finish('expired');
            return;
        }

        $result = OAuthController::exchange($code, $verifier);

        if (empty($result['ok'])) {
            // The verifier is already gone, the key was never written: no half-connected state
            // to clean up. Keep the detail for one page load so the notice can be specific.
            //
            // Worth being careful about what we claim here. The state is spent before the request
            // goes out, so a network timeout lands in this branch too — and OpenRouter may have
            // created a key on the user's account before the connection dropped. Saying "nothing
            // was saved" would be a guess about somebody else's billing.
            set_transient(OAuthController::ERROR_TRANSIENT . $user_id, (string) $result['message'], 300);
            $this->finish('failed');
            return;
        }

        OAuthController::store_key((string) $result['key']);
        $this->finish('connected');
    }

    /**
     * Send the browser to the settings screen carrying the outcome, dropping the spent code from
     * the address bar on the way.
     */
    private function finish(string $result): void
    {
        wp_safe_redirect(OAuthController::settings_url($result));
        exit;
    }

    /**
     * Report what happened. Display only — no nonce here on purpose: this hook runs on every admin
     * screen, and a nonce check on a shared hook is the mistake that took wp-admin down before.
     */
    public function maybe_notice(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only notice keyed on a namespaced arg.
        $result = isset($_GET[OAuthController::RESULT_ARG])
            ? sanitize_key(wp_unslash((string) $_GET[OAuthController::RESULT_ARG]))
            : '';

        if ($result === '' || !current_user_can('manage_options')) {
            return;
        }

        $user_id = get_current_user_id();
        $detail  = (string) get_transient(OAuthController::ERROR_TRANSIENT . $user_id);

        if ($detail !== '') {
            delete_transient(OAuthController::ERROR_TRANSIENT . $user_id);
        }

        if ($result === 'connected') {
            // The result lives in the address bar, so it survives a refresh — and it survived a
            // disconnect too, leaving the screen insisting the account was still connected after
            // the user had just unhooked it. The stored flag is the truth; ask it.
            if ('1' !== (string) get_option(OAuthController::FLAG_OPTION, '')) {
                return;
            }
            $this->notice(
                'success',
                __('OpenRouter is connected. Pick a model below and you are ready to generate.', 'ai-content-generation')
            );
            return;
        }

        if ($result === 'expired') {
            $this->notice(
                'warning',
                __('That connect link is spent. It had already been used, or it sat too long, or you pressed Connect again somewhere else and that newer attempt took over. Nothing changed — start again from Settings.', 'ai-content-generation')
            );
            return;
        }

        if ($result === 'failed') {
            $this->notice(
                'error',
                $detail !== ''
                    ? $detail
                    : __("Couldn't finish connecting to OpenRouter. This site saved nothing — but if the connection dropped partway, check your OpenRouter account for a new key before making another one.", 'ai-content-generation')
            );
        }
    }

    private function notice(string $type, string $message): void
    {
        printf(
            '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
            esc_attr($type),
            esc_html($message)
        );
    }
}
