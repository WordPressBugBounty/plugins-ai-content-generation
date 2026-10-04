<?php

namespace WPWand\Rest\Controllers;

use WPWand\Generation\ModelCatalog;
use WP_REST_Request;
use WP_REST_Response;

/**
 * /wpwand/v1/oauth/openrouter/* — connect an OpenRouter account without pasting an API key.
 *
 * Why this exists: "can't get api key" is the most common thing people write on their way out.
 * OpenRouter is the only provider we support with a real OAuth flow, and one OpenRouter key
 * reaches Gemini, GPT and Claude models, so a single button removes the setup step entirely.
 *
 * This sits ALONGSIDE the OpenRouter key field, it does not replace it. The key it obtains is
 * written to `wpwand_openrouter_api_key` — the very same option the field writes — so every
 * downstream caller (ProviderFactory, ModelCatalog, Generator) needs no change at all.
 *
 * The protocol is OpenRouter's OAuth PKCE flow, verified against their docs 2026-08-20:
 *
 *   1. GET  https://openrouter.ai/auth?callback_url=…&code_challenge=…&code_challenge_method=S256
 *   2. user signs in and approves
 *   3. OpenRouter sends them back to callback_url with a `code` (single use, expires in 10 minutes)
 *   4. POST https://openrouter.ai/api/v1/auth/keys  {code, code_verifier, code_challenge_method}
 *      → {"key": "sk-or-…"}
 *
 * OpenRouter has no `state` parameter, so the CSRF binding is ours: the callback URL carries a
 * WordPress nonce, and the verifier is stored against the user who started the flow and deleted
 * the moment it is read. {@see \WPWand\Admin\OAuthCallback} handles the return leg.
 *
 * The key is never logged, echoed, or returned to the browser — not by /status, not in an error
 * message. An error message never repeats the exchange response body, because that body is the
 * key's own transport.
 */
final class OAuthController extends AbstractController
{
    protected string $rest_base = 'oauth';

    /** Where we send the user to sign in. */
    public const AUTHORIZE_URL = 'https://openrouter.ai/auth';

    /** Where the returned code is traded for a user-owned key. */
    public const EXCHANGE_URL = 'https://openrouter.ai/api/v1/auth/keys';

    /** The existing option the key field already writes. Do not rename: it is live on every install. */
    public const KEY_OPTION = 'wpwand_openrouter_api_key';

    /** '1' when the stored key arrived through OAuth rather than being typed in by hand. */
    public const FLAG_OPTION = 'wpwand_openrouter_oauth_connected';

    /** Per-user PKCE state. Hidden meta, single use, deleted as soon as it is read. */
    public const USER_META = '_wpwand_openrouter_pkce';

    /** Nonce action for the callback URL. */
    public const NONCE_ACTION = 'wpwand-openrouter-oauth';

    /** Query arg the callback answers to. Namespaced, because an unprefixed one collides with core. */
    public const QUERY_ARG = 'wpwand-oauth';

    /** Query arg carrying the outcome back to the settings screen. */
    public const RESULT_ARG = 'wpwand-oauth-result';

    /** Transient holding one failure message for the next page load. */
    public const ERROR_TRANSIENT = 'wpwand_oauth_error_';

    /** How long a started flow stays valid. OpenRouter's own code dies after 10 minutes. */
    public const STATE_TTL = 900;

    /** Settings screen slug, mirrored from SettingsPage::PAGE_SLUG (private there). */
    private const PAGE_SLUG = 'wpwand';

    public function register_routes(): void
    {
        register_rest_route($this->rest_namespace, '/' . $this->rest_base . '/openrouter/start', [
            ['methods' => 'POST', 'callback' => [$this, 'start'], 'permission_callback' => [$this, 'can_manage']],
        ]);

        register_rest_route($this->rest_namespace, '/' . $this->rest_base . '/openrouter/status', [
            ['methods' => 'GET', 'callback' => [$this, 'status'], 'permission_callback' => [$this, 'can_manage']],
        ]);

        register_rest_route($this->rest_namespace, '/' . $this->rest_base . '/openrouter/disconnect', [
            ['methods' => 'POST', 'callback' => [$this, 'disconnect'], 'permission_callback' => [$this, 'can_manage']],
        ]);
    }

    /**
     * Connecting an account writes a credential the whole site generates against, so this is
     * an administrator action — stricter than the edit_posts gate the rest of the API uses.
     */
    public function can_manage(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * POST /oauth/openrouter/start — mint a fresh verifier, remember it against this user, and
     * hand back the URL to send the browser to. Starting again simply replaces the old state,
     * so an abandoned attempt can never be resumed.
     */
    public function start(WP_REST_Request $request): WP_REST_Response
    {
        $verifier = self::new_verifier();

        if ($verifier === '') {
            return new WP_REST_Response([
                'ok'      => false,
                'message' => __('This server could not generate a secure code. Paste your OpenRouter key instead.', 'ai-content-generation'),
            ], 500);
        }

        self::store_state(get_current_user_id(), $verifier);

        $nonce = wp_create_nonce(self::NONCE_ACTION);

        return new WP_REST_Response([
            'ok'           => true,
            'url'          => self::authorize_url($verifier, $nonce),
            'callback_url' => self::callback_url($nonce),
        ], 200);
    }

    /**
     * GET /oauth/openrouter/status — is a key stored, and did it come from the button?
     * Deliberately returns no part of the key itself.
     */
    public function status(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(self::current_status(), 200);
    }

    /**
     * POST /oauth/openrouter/disconnect — forget the key and any half-finished flow.
     *
     * The key stays live at OpenRouter; this only stops the site using it. Say so in the UI so
     * nobody thinks their account was touched.
     */
    public function disconnect(WP_REST_Request $request): WP_REST_Response
    {
        self::forget(get_current_user_id());

        $state           = self::current_status();
        $state['ok']     = true;
        $state['message'] = __('Disconnected. The key is gone from this site — your OpenRouter account is untouched.', 'ai-content-generation');

        return new WP_REST_Response($state, 200);
    }

    /**
     * @return array{connected:bool, via_oauth:bool, pending:bool}
     */
    public static function current_status(): array
    {
        $pending = get_user_meta(get_current_user_id(), self::USER_META, true);

        return [
            'connected' => trim((string) get_option(self::KEY_OPTION, '')) !== '',
            'via_oauth' => (string) get_option(self::FLAG_OPTION, '') === '1',
            // Same expiry take_state() enforces. Without it a day-old state reported pending=true
            // for ever, and the secret behind it never looked stale enough to clear.
            'pending'   => is_array($pending)
                && !empty($pending['verifier'])
                && (int) ($pending['expires'] ?? 0) >= time(),
        ];
    }

    // ---------------------------------------------------------------------
    // Protocol pieces. Public and static so the admin callback can reuse them
    // without duplicating a single byte of the crypto.
    // ---------------------------------------------------------------------

    /**
     * A PKCE code verifier: 32 CSPRNG bytes as base64url, which lands at 43 characters — the
     * shortest length RFC 7636 allows, and well inside OpenRouter's limit.
     *
     * Returns '' when the platform has no usable entropy source, which the caller must treat as
     * a hard failure rather than falling back to something weaker.
     */
    public static function new_verifier(): string
    {
        try {
            return self::base64url(random_bytes(32));
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** S256: base64url of the raw sha256 of the verifier. No padding, URL-safe alphabet. */
    public static function challenge(string $verifier): string
    {
        return self::base64url(hash('sha256', $verifier, true));
    }

    public static function base64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * The URL OpenRouter sends the user back to.
     *
     * The nonce rides in our own namespaced parameter rather than `_wpnonce` for a practical
     * reason: OpenRouter documents no `state`, and how it splices `code` onto a callback URL that
     * already has a query string is not documented either. Keeping our payload in one parameter
     * means the callback can recover it whether the code is appended with `&` or with a second `?`.
     */
    public static function callback_url(string $nonce): string
    {
        return admin_url(
            'admin.php?page=' . self::PAGE_SLUG . '&' . self::QUERY_ARG . '=' . rawurlencode($nonce)
        );
    }

    /**
     * Where the settings screen lives, optionally carrying the outcome of a finished flow.
     */
    public static function settings_url(string $result = ''): string
    {
        $url = admin_url('admin.php?page=' . self::PAGE_SLUG);

        return $result === '' ? $url : add_query_arg(self::RESULT_ARG, $result, $url);
    }

    /**
     * Step 1's URL. add_query_arg() does NOT encode values (build_query passes $urlencode=false),
     * so the callback URL is encoded here by hand; the challenge is base64url and needs none.
     */
    public static function authorize_url(string $verifier, string $nonce): string
    {
        return add_query_arg(
            [
                'callback_url'          => rawurlencode(self::callback_url($nonce)),
                'code_challenge'        => self::challenge($verifier),
                'code_challenge_method' => 'S256',
            ],
            self::AUTHORIZE_URL
        );
    }

    public static function store_state(int $user_id, string $verifier): void
    {
        update_user_meta($user_id, self::USER_META, [
            'verifier' => $verifier,
            'expires'  => time() + self::STATE_TTL,
        ]);
    }

    /**
     * Read the verifier and destroy it in the same breath, so a replayed callback — a refresh, a
     * back button, a bookmarked URL — finds nothing to spend. Returns '' when there is no live
     * flow, which the caller reports as "start again" rather than as a failure.
     */
    public static function take_state(int $user_id): string
    {
        $state = get_user_meta($user_id, self::USER_META, true);
        delete_user_meta($user_id, self::USER_META);

        if (!is_array($state) || empty($state['verifier'])) {
            return '';
        }

        if ((int) ($state['expires'] ?? 0) < time()) {
            return '';
        }

        return (string) $state['verifier'];
    }

    /**
     * Step 4: trade the code for a user-owned key.
     *
     * @return array{ok:bool, key:string, message:string}
     */
    public static function exchange(string $code, string $verifier): array
    {
        $response = wp_remote_post(self::EXCHANGE_URL, [
            'timeout' => 20,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body'    => wp_json_encode([
                'code'                  => $code,
                'code_verifier'         => $verifier,
                'code_challenge_method' => 'S256',
            ]),
        ]);

        if (is_wp_error($response)) {
            return self::failure(sprintf(
                /* translators: %s: network error message */
                __('Could not reach OpenRouter (%s). Nothing was saved — try connecting again.', 'ai-content-generation'),
                $response->get_error_message()
            ));
        }

        $http = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        $key  = is_array($body) && isset($body['key']) ? trim((string) $body['key']) : '';

        if ($http === 403) {
            return self::failure(__('OpenRouter turned that sign-in down. The code only lasts ten minutes and works once, so start again.', 'ai-content-generation'));
        }

        if ($http === 400) {
            return self::failure(__('OpenRouter rejected the sign-in request. Start again from Settings.', 'ai-content-generation'));
        }

        // The response body carries the key, so it never goes into a message, a log or a notice.
        if ($http !== 200 || $key === '') {
            return self::failure(sprintf(
                /* translators: %d: HTTP status code */
                __('OpenRouter answered with HTTP %d and no key. Nothing was saved — try again in a minute.', 'ai-content-generation'),
                $http
            ));
        }

        return ['ok' => true, 'key' => $key, 'message' => ''];
    }

    /**
     * @return array{ok:bool, key:string, message:string}
     */
    private static function failure(string $message): array
    {
        return ['ok' => false, 'key' => '', 'message' => $message];
    }

    /**
     * Save the key exactly where the key field saves it, and drop the cached model list so the
     * picker repaints with what this account can actually reach.
     */
    public static function store_key(string $key): bool
    {
        // The manual field requires an sk-or- prefix (SettingsController::SCHEMA). A key arriving
        // by OAuth is no more trustworthy than one that was typed, so it clears the same bar —
        // otherwise a wrong-shaped response would be saved and every later call would fail with a
        // provider error nobody could trace back to here.
        $key = trim(sanitize_text_field($key));
        if ($key === '' || strpos($key, 'sk-or-') !== 0) {
            return false;
        }

        update_option(self::KEY_OPTION, $key);
        update_option(self::FLAG_OPTION, '1');
        ModelCatalog::flush();

        return true;
    }

    /** Clear the key, the marker and any half-finished flow for this user. */
    public static function forget(int $user_id): void
    {
        // Only remove the key if WE put it there. A hand-typed key predates this feature and is not
        // ours to throw away — "Disconnect" would otherwise wipe a working key the user never
        // connected through OAuth, with no warning and no way back.
        if (get_option(self::FLAG_OPTION) === '1') {
            delete_option(self::KEY_OPTION);
        }

        delete_option(self::FLAG_OPTION);

        if ($user_id > 0) {
            delete_user_meta($user_id, self::USER_META);
        }

        ModelCatalog::flush();
    }
}
