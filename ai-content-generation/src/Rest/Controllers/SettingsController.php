<?php

namespace WPWand\Rest\Controllers;

use WPWand\Admin\ReviewPrompt;
use WPWand\Data\Brand;
use WPWand\Data\Languages;
use WPWand\Generation\Failover;
use WPWand\Generation\Generator;
use WPWand\Generation\ModelCatalog;
use WP_REST_Request;
use WP_REST_Response;

/**
 * /wpwand/v1/settings — read and write plugin settings.
 *
 * The settings SCHEMA is the single source of truth. It maps each editable option to its
 * type, sanitizer and (for secrets) validation rules. Option keys are IDENTICAL to the
 * legacy plugin's, so the React UI reads and writes the very same data the old jQuery
 * form did — full round-trip parity, zero migration (REBUILD-PLAN.md §5).
 *
 * GET also returns `meta` (active providers + signup links, grouped model lists, language
 * list) so the React dropdowns mirror the legacy page exactly without duplicating data.
 *
 * Security: secret (API-key) values are never returned; only a boolean "is it set". On
 * write, a blank/absent secret leaves the stored key untouched.
 */
final class SettingsController extends AbstractController
{
    protected string $rest_base = 'settings';

    /**
     * @var array<string, array<string, mixed>>
     */
    private const SCHEMA = [
        'wpwand_model'                 => ['type' => 'string',   'secret' => false, 'default' => 'chatgpt-4o-latest'],
        'wpwand_language'              => ['type' => 'string',   'secret' => false, 'default' => 'English'],
        'wpwand_temperature'           => ['type' => 'float',    'secret' => false, 'default' => 1.0, 'min' => 0.0, 'max' => 2.0],
        // One owner for this number: Generator is what actually sends it when the field was never saved.
        'wpwand_max_tokens'            => ['type' => 'int_empty', 'secret' => false, 'default' => Generator::DEFAULT_MAX_TOKENS],
        'wpwand_presence_penalty'      => ['type' => 'float',    'secret' => false, 'default' => 0.0, 'min' => -2.0, 'max' => 2.0],
        'wpwand_frequency'             => ['type' => 'float',    'secret' => false, 'default' => 0.0, 'min' => -2.0, 'max' => 2.0],
        'wpwand_hide_ai_bar_gutenberg' => ['type' => 'bool',     'secret' => false, 'default' => 0],
        'toggler_position'             => ['type' => 'enum',     'secret' => false, 'default' => 'top', 'options' => ['top', 'side', 'hidden']],
        // Experimental: background generation engine + live streaming (compare which performs best).
        'wpwand_gen_engine'            => ['type' => 'enum',     'secret' => false, 'default' => 'browser', 'options' => ['browser', 'wp_cron', 'system_cron']],
        'wpwand_stream'                => ['type' => 'bool',     'secret' => false, 'default' => 0],
        // Backup providers. Off by default: an install that has not opted in generates exactly as before.
        'wpwand_failover_enabled'      => ['type' => 'bool',     'secret' => false, 'default' => 0,  'cap' => 'pro'],
        // Comma-separated provider ids, in the order they should be tried. Read through
        // Failover::order(), which whitelists and dedupes; '' means the natural order.
        'wpwand_failover_order'        => ['type' => 'string',   'secret' => false, 'default' => '', 'cap' => 'pro'],
        // Which of those the user unticked. Separate from the order so unticking a provider does
        // not erase where it sat — see Failover::OPTION_SKIP.
        'wpwand_failover_skip'         => ['type' => 'string',   'secret' => false, 'default' => '', 'cap' => 'pro'],
        // Advanced (Pro) features.
        'wpwand_ai_character'          => ['type' => 'textarea', 'secret' => false, 'default' => '', 'cap' => 'pro'],
        'wpwand_busines_details'       => ['type' => 'textarea', 'secret' => false, 'default' => '', 'cap' => 'pro'],
        'wpwand_targated_customer'     => ['type' => 'textarea', 'secret' => false, 'default' => '', 'cap' => 'pro'],
        // White Label (Agency) features.
        'wpwand_logo'                  => ['type' => 'url',      'secret' => false, 'default' => '', 'cap' => 'agency'],
        'wpwand_logo_icon'             => ['type' => 'url',      'secret' => false, 'default' => '', 'cap' => 'agency'],
        'wpwand_brand_name'            => ['type' => 'string',   'secret' => false, 'default' => '', 'cap' => 'agency'],
        'wpwand_brand_color'           => ['type' => 'color',    'secret' => false, 'default' => '', 'cap' => 'agency'],
        'wpwand_plugin_name'           => ['type' => 'string',   'secret' => false, 'default' => '', 'cap' => 'agency'],
        'wpwand_plugin_description'    => ['type' => 'textarea', 'secret' => false, 'default' => '', 'cap' => 'agency'],
        'wpwand_author_name'           => ['type' => 'string',   'secret' => false, 'default' => '', 'cap' => 'agency'],
        'wpwand_author_url'            => ['type' => 'url',      'secret' => false, 'default' => '', 'cap' => 'agency'],
        'wpwand_white_label_disable'   => ['type' => 'bool',     'secret' => false, 'default' => 0,  'cap' => 'agency'],
        'wpwand_api_key'               => ['type' => 'key', 'secret' => true, 'provider' => 'openai',     'prefix' => 'sk-'],
        'wpwand_claude_api_key'        => ['type' => 'key', 'secret' => true, 'provider' => 'claude',     'prefix' => ''],
        'wpwand_deepseek_api_key'      => ['type' => 'key', 'secret' => true, 'provider' => 'deepseek',   'prefix' => ''],
        'wpwand_openrouter_api_key'    => ['type' => 'key', 'secret' => true, 'provider' => 'openrouter', 'prefix' => 'sk-or-'],
        'wpwand_gemini_api_key'        => ['type' => 'key', 'secret' => true, 'provider' => 'gemini',     'prefix' => 'AIza'],
    ];

    public function register_routes(): void
    {
        register_rest_route($this->rest_namespace, '/' . $this->rest_base, [
            ['methods' => 'GET',  'callback' => [$this, 'get_settings'],    'permission_callback' => [$this, 'can_use']],
            ['methods' => 'POST', 'callback' => [$this, 'update_settings'], 'permission_callback' => [$this, 'can_use']],
        ]);

        register_rest_route($this->rest_namespace, '/' . $this->rest_base . '/validate-key', [
            ['methods' => 'POST', 'callback' => [$this, 'validate_key'], 'permission_callback' => [$this, 'can_use']],
        ]);

        register_rest_route($this->rest_namespace, '/' . $this->rest_base . '/remove-key', [
            ['methods' => 'POST', 'callback' => [$this, 'remove_key'], 'permission_callback' => [$this, 'can_use']],
        ]);

        register_rest_route($this->rest_namespace, '/' . $this->rest_base . '/sync', [
            ['methods' => 'POST', 'callback' => [$this, 'sync_data'], 'permission_callback' => [$this, 'can_use']],
        ]);

        register_rest_route($this->rest_namespace, '/' . $this->rest_base . '/status', [
            ['methods' => 'GET', 'callback' => [$this, 'get_status'], 'permission_callback' => [$this, 'can_use']],
        ]);

        register_rest_route($this->rest_namespace, '/' . $this->rest_base . '/model-limit', [
            ['methods' => 'GET', 'callback' => [$this, 'model_limit'], 'permission_callback' => [$this, 'can_use']],
        ]);

        register_rest_route($this->rest_namespace, '/' . $this->rest_base . '/cron-test', [
            ['methods' => 'POST', 'callback' => [$this, 'cron_test'], 'permission_callback' => [$this, 'can_use']],
        ]);

        register_rest_route($this->rest_namespace, '/' . $this->rest_base . '/failover-notice/dismiss', [
            ['methods' => 'POST', 'callback' => [$this, 'dismiss_failover_notice'], 'permission_callback' => [$this, 'can_use']],
        ]);

        // Administrator-only: un-pausing a provider resumes spending on that provider's account.
        // Dismissing the notice below is deliberately NOT restricted — it only clears a message, and
        // an Author who cannot clear it is stuck looking at it.
        register_rest_route($this->rest_namespace, '/' . $this->rest_base . '/failover/unpark', [
            ['methods' => 'POST', 'callback' => [$this, 'unpark_failover'], 'permission_callback' => [$this, 'require_admin']],
        ]);
    }

    /**
     * POST /failover-notice/dismiss — the user closed the "we finished on a different provider"
     * notice. Runtime state, deliberately not a setting: a settings save must not be able to
     * clear it, and clearing it must not be a settings save.
     */
    public function dismiss_failover_notice(WP_REST_Request $request): WP_REST_Response
    {
        Failover::dismiss_notice();

        return new WP_REST_Response(['ok' => true], 200);
    }

    /**
     * POST /failover/unpark — "Try it now". A provider that failed sits out for an hour; this is
     * how someone who has just topped up their balance gets back on their own account immediately.
     */
    public function unpark_failover(WP_REST_Request $request): WP_REST_Response
    {
        Failover::unpark_all();

        return new WP_REST_Response(['ok' => true], 200);
    }

    /**
     * POST /cron-test — confirm WordPress cron can actually fire by doing a loopback to wp-cron.php
     * (exactly what a real server crontab hits) and reporting the pseudo-cron + schedule state.
     */
    public function cron_test(WP_REST_Request $request): WP_REST_Response
    {
        $url  = site_url('wp-cron.php?doing_wp_cron=' . sprintf('%.22F', microtime(true)));
        $resp = wp_remote_post($url, [
            'timeout'   => 12,
            'blocking'  => true,
            'sslverify' => false, // localhost loopback to wp-cron.php
            'headers'   => ['Cache-Control' => 'no-cache'],
        ]);

        $reachable = !is_wp_error($resp) && (int) wp_remote_retrieve_response_code($resp) < 500;
        $disabled  = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $next      = wp_next_scheduled('wpwand_automation_tick');

        if ($reachable) {
            $message = __('wp-cron.php is reachable. Your server cron can run the schedules.', 'ai-content-generation');
        } elseif (is_wp_error($resp)) {
            $message = sprintf(
                /* translators: %s: error message */
                __('Could not reach wp-cron.php (%s). Your host may block loopback requests. Point a real server cron at the URL above instead.', 'ai-content-generation'),
                $resp->get_error_message()
            );
        } else {
            $message = sprintf(
                /* translators: %d: HTTP status code */
                __('wp-cron.php returned HTTP %d. Check the URL is publicly reachable.', 'ai-content-generation'),
                (int) wp_remote_retrieve_response_code($resp)
            );
        }

        return new WP_REST_Response([
            'ok'                => $reachable,
            'pseudo_cron_off'   => $disabled,
            'next_run'          => $next ? get_date_from_gmt(gmdate('Y-m-d H:i:s', (int) $next), 'M j, Y g:i a') : null,
            'message'           => $message,
        ], 200);
    }

    /**
     * GET /model-limit?model=… — the recommended max output tokens for a model, fetched live from
     * the provider's models API where available (OpenRouter exposes per-model limits), otherwise a
     * sensible static default. The provider list is cached so this stays cheap.
     */
    public function model_limit(WP_REST_Request $request): WP_REST_Response
    {
        $model = sanitize_text_field((string) $request->get_param('model'));
        if ($model === '') {
            return new WP_REST_Response(['error' => __('Pick a model first.', 'ai-content-generation')], 400);
        }

        return new WP_REST_Response(ModelCatalog::limit_for($model), 200);
    }

    public function get_settings(WP_REST_Request $request): WP_REST_Response
    {
        $state             = $this->current_state();
        $state['meta']     = $this->meta();
        $state['failover'] = $this->failover_state();
        return new WP_REST_Response($state, 200);
    }

    /**
     * GET /status — the SLOW part of the old meta payload, split out so the settings form can
     * paint immediately. Runs the live per-provider ModelCatalog probe (key status + model lists)
     * and returns it in the SAME shape the frontend already consumes from meta:
     * providers/models/model_tokens/model_max.
     */
    public function get_status(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response($this->provider_payload(true), 200);
    }

    /**
     * POST — validate then persist. All-or-nothing: if any field fails, nothing is saved.
     */
    public function update_settings(WP_REST_Request $request): WP_REST_Response
    {
        $incoming = (array) $request->get_param('settings');
        $caps     = $this->caps();
        $errors   = [];
        $warnings = [];
        $to_save  = [];

        foreach ($incoming as $key => $raw) {
            if (!isset(self::SCHEMA[$key])) {
                continue;
            }
            $spec = self::SCHEMA[$key];

            // Locked feature (Pro/Agency) — silently ignore writes when not entitled.
            if (isset($spec['cap']) && empty($caps[$spec['cap']])) {
                continue;
            }

            // Secrets: empty/absent means "leave unchanged".
            if (!empty($spec['secret']) && ($raw === '' || $raw === null)) {
                continue;
            }

            $clean = $this->sanitize_value($spec, $raw, $error);
            if ($error !== null) {
                $errors[$key] = $error;
                continue;
            }

            // A key that does not look like the provider's usual format is worth flagging, but it
            // never blocks the save — only the provider can say whether a key works.
            if ('key' === $spec['type']) {
                $prefix = (string) ($spec['prefix'] ?? '');
                if ('' !== $prefix && '' !== $clean && 0 !== strpos($clean, $prefix)) {
                    $warnings[$key] = sprintf(
                        /* translators: %s: the prefix this provider's keys usually start with, e.g. sk- */
                        __('That doesn’t look like a key for this provider. They usually start with "%s". Saved anyway. Press Test to check it.', 'ai-content-generation'),
                        $prefix
                    );
                }
            }

            $to_save[$key] = $clean;
        }

        if (!empty($errors)) {
            return new WP_REST_Response(['saved' => false, 'errors' => $errors], 400);
        }

        foreach ($to_save as $key => $value) {
            update_option($key, $value);
        }

        // A key typed into the box did not come from the connect flow, whatever the flag says. Left
        // set, the screen goes on reporting "Connected with OpenRouter" over somebody's own key —
        // and Disconnect would then delete a key OAuth never put there.
        if (isset($to_save['wpwand_openrouter_api_key'])) {
            delete_option(OAuthController::FLAG_OPTION);
        }

        // A new/changed API key may unlock a different model list — refetch on the next meta build.
        $keyChanged = (bool) array_intersect(array_keys($to_save), [
            'wpwand_api_key',
            'wpwand_claude_api_key',
            'wpwand_deepseek_api_key',
            'wpwand_openrouter_api_key',
            'wpwand_gemini_api_key',
        ]);
        if ($keyChanged) {
            ModelCatalog::flush();
        }

        // Return cached-only meta so save stays fast; the client refetches /settings/status
        // (via query invalidation) to repaint provider badges/models after a key change.
        $state             = $this->current_state();
        $state['meta']     = $this->meta();
        $state['failover'] = $this->failover_state();
        $state['saved']    = true;
        $state['warnings'] = $warnings;
        return new WP_REST_Response($state, 200);
    }

    /**
     * POST /validate-key — live-test the SAVED key for a provider via a tiny generation.
     */
    /**
     * POST /remove-key — forget the stored key for one provider.
     *
     * A blank key field means "leave what is saved alone", so there was no way to take a dead key
     * back out once it had expired or run dry. This is that way.
     */
    public function remove_key(WP_REST_Request $request): WP_REST_Response
    {
        $provider = sanitize_text_field((string) $request->get_param('provider'));

        $option = '';
        foreach (self::SCHEMA as $key => $spec) {
            if ('key' === ($spec['type'] ?? '') && $provider === ($spec['provider'] ?? '')) {
                $option = $key;
                break;
            }
        }

        if ('' === $option) {
            return new WP_REST_Response(['ok' => false, 'message' => __('Unknown provider.', 'ai-content-generation')], 400);
        }

        delete_option($option);

        // An OpenRouter key that came from the connect flow leaves a flag behind saying so; drop it
        // with the key, or the screen keeps claiming the account is still connected.
        if ('openrouter' === $provider) {
            delete_option(OAuthController::FLAG_OPTION);
        }

        ModelCatalog::flush();

        $state             = $this->current_state();
        $state['meta']     = $this->meta();
        $state['failover'] = $this->failover_state();
        $state['ok']       = true;
        return new WP_REST_Response($state, 200);
    }

    public function validate_key(WP_REST_Request $request): WP_REST_Response
    {
        $provider = sanitize_text_field((string) $request->get_param('provider'));

        if (!in_array($provider, ['openai', 'claude', 'deepseek', 'gemini', 'openrouter'], true)) {
            return new WP_REST_Response(['ok' => false, 'message' => __('Unknown provider.', 'ai-content-generation')], 400);
        }

        // If a key was typed in the field (not yet saved), validate THAT key directly so "Test"
        // works without saving first. Empty → fall through to testing the saved key.
        $typed = trim((string) $request->get_param('key'));
        if ($typed !== '') {
            $res = ModelCatalog::check_key($provider, $typed);
            ModelCatalog::flush();
            return new WP_REST_Response($res, 200);
        }

        if (!class_exists('WPWand\Generation\Generator')) {
            return new WP_REST_Response(['ok' => false, 'message' => __('Save your key, then try again.', 'ai-content-generation')], 200);
        }

        // Use a model the provider currently offers (fetched live) — a hardcoded id can 404 once
        // the provider retires it (which broke the OpenRouter test).
        // 'wpwand_failover' => false pins the call to this provider. Failover already skips any
        // call that names a model, but saying it here is what stops a dead key ever being reported
        // as working because a backup answered for it.
        $testModel = ModelCatalog::test_model($provider);
        $content   = \WPWand\Generation\Generator::generate('Reply with the single word: ok', 1, [
            'model'           => $testModel,
            'wpwand_failover' => false,
        ]);

        // Re-validate the cached status/model list against this fresh result.
        ModelCatalog::flush();

        if (is_object($content) && isset($content->error)) {
            $raw = isset($content->error->message) ? (string) $content->error->message : '';
            return new WP_REST_Response(['ok' => false, 'message' => $this->readable_error($raw)], 200);
        }
        return new WP_REST_Response(['ok' => true, 'message' => __('Key is working.', 'ai-content-generation')], 200);
    }

    /**
     * Turn a provider error (which can arrive as a raw JSON blob) into a short human sentence.
     */
    private function readable_error(string $raw): string
    {
        return \WPWand\Generation\ErrorFormatter::humanize(
            $raw,
            __('The provider rejected this key. Check it and try again.', 'ai-content-generation')
        );
    }

    /**
     * POST /sync — refresh the cached template data (mirrors the legacy "Sync" button).
     */
    public function sync_data(WP_REST_Request $request): WP_REST_Response
    {
        \WPWand\Data\Templates::seed(true);
        return new WP_REST_Response(['ok' => true, 'message' => __('Templates updated.', 'ai-content-generation')], 200);
    }

    /**
     * @return array<string, mixed>
     */
    private function current_state(): array
    {
        $settings = [];
        $keys_set = [];

        foreach (self::SCHEMA as $key => $spec) {
            if (!empty($spec['secret'])) {
                $keys_set[$key] = (bool) get_option($key, '');
                continue;
            }
            $value = get_option($key, $spec['default']);

            // A stored enum value that is no longer offered (e.g. the retired 'action_scheduler'
            // engine) would leave the dropdown with nothing selected, so read it back as the
            // default instead. Writes go through sanitize_value(), which does the same thing.
            if ($spec['type'] === 'enum' && !in_array($value, (array) ($spec['options'] ?? []), true)) {
                $value = $spec['default'];
            }

            $settings[$key] = $value;
        }

        return ['settings' => $settings, 'keys_set' => $keys_set];
    }

    /**
     * Dropdown + provider data so the React UI matches the legacy page exactly.
     *
     * @return array<string, mixed>
     */
    private function meta(): array
    {
        // GET /settings must return instantly, so it reads only the CACHED catalogue (no live
        // provider probe). The live status/models arrive async via GET /settings/status, which
        // returns the same providers/models shape merged into this meta on the client.
        return array_merge($this->provider_payload(false), [
            'languages'    => $this->languages(),
            'caps'         => $this->caps(),
            'brand'        => $this->brand(),
            'support'      => $this->support(),
        ]);
    }

    /**
     * Per-provider status + model lists, in the shape the React dropdowns/badges consume.
     *
     * @param bool $live When true, probe each provider live (slow); when false, use only the
     *                    cached catalogue so the caller never blocks on a provider request.
     * @return array{providers:array<string,mixed>, models:array<string,mixed>, model_tokens:array<string,int>, model_max:array<string,mixed>}
     */
    private function provider_payload(bool $live): array
    {
        $links = [
            'openai'     => 'https://platform.openai.com/account/api-keys',
            'claude'     => 'https://console.anthropic.com/settings/keys',
            'deepseek'   => 'https://platform.deepseek.com/api_keys',
            'openrouter' => 'https://openrouter.ai/keys',
            'gemini'     => 'https://aistudio.google.com/apikey',
        ];

        // Providers carry their key status (active / invalid / expired / quota-exceeded /
        // unreachable / unset / unknown-when-uncached). Models are only listed for a valid key, so
        // the UI can show the picker + an "active" badge for working keys and a clear status otherwise.
        $providers    = [];
        $models        = [];
        $model_tokens  = [];
        $model_max     = [];
        foreach ($links as $prov => $link) {
            $cat = $live ? ModelCatalog::status($prov) : ModelCatalog::cached($prov);
            $providers[$prov] = [
                'active'         => $cat['status'] === 'active',
                'status'         => $cat['status'],
                'status_message' => $cat['message'],
                'link'           => $link,
                // The models below came from an earlier successful probe; we couldn't reach the
                // provider just now. The picker stays usable, the badge still tells the truth.
                'stale_models'   => !empty($cat['stale_models']),
            ];

            $list          = $live ? ModelCatalog::for_provider($prov) : ($cat['models'] ?? []);
            $models[$prov] = $list;
            foreach ($list as $m) {
                $model_tokens[$m['value']] = (int) $m['max_tokens'];
                $model_max[$m['value']]    = $m['model_max'];
            }
        }

        return [
            'providers'    => $providers,
            'models'       => $models,
            'model_tokens' => $model_tokens,
            'model_max'    => $model_max,
        ];
    }

    /**
     * Failover runtime state, for the Backup providers section.
     *
     * Parked providers and the switch notice are written by the generation path, not by this
     * endpoint, so they are NOT in SCHEMA and cannot be cleared by a settings round-trip. They
     * have no other way out to the UI, which is why they ride along here.
     *
     * @return array<string, mixed>
     */
    private function failover_state(): array
    {
        return [
            'enabled' => Failover::is_enabled(),
            // The screen's list, not the runtime's. `order()` drops whatever is unticked, and
            // reading that here made an unticked provider lose the place the user gave it.
            'order'   => Failover::order_all(),
            'parked'  => Failover::parked(),
            'notice'  => Failover::notice(),
        ];
    }

    /**
     * Feature entitlements. Mirrors the legacy gating: Pro features need the Pro plugin
     * active (wpwand_pro_init), white-label needs the Agency tier on top of that.
     *
     * @return array<string, bool>
     */
    private function caps(): array
    {
        // "pro"/"agency" mean UNLOCKED (plugin present + license active), so deactivating re-locks.
        return [
            'pro'     => \WPWand\Core\Pro::unlocked(),
            'agency'  => \WPWand\Core\Pro::agency(),
            'manage'  => current_user_can('manage_options'),
        ];
    }

    /**
     * Resolved brand identity for theming. Replicates the legacy gating directly (instead
     * of depending on the legacy resolver functions, which only load after wpwand_init):
     * the custom name/color/logo apply ONLY under an active Agency white-label; otherwise
     * the WP Wand defaults are returned (brand color #3767fb). This is the correct source
     * for theming — NOT the raw, ungated wpwand_brand_color option.
     *
     * @return array<string, string>
     */
    private function brand(): array
    {
        return Brand::resolve();
    }

    /**
     * Where this install should go for help.
     *
     * Three different answers, because three different people are asking. A free user's support
     * lives on the wordpress.org forum, where the plugin is listed. A paying customer has bought a
     * reply from us. And an Agency reseller's client should be asking the reseller — their client
     * has never heard of WP Wand and must not be sent to a page that says so, which is why a
     * white-labelled install gets the reseller's own address and no review link at all.
     *
     * The wordpress.org slug lives in exactly one place (ReviewPrompt) and is read from there.
     *
     * @return array{mode:string, contact:string, review:string}
     */
    private function support(): array
    {
        if (\WPWand\Core\Pro::agency()) {
            $author = trim((string) get_option('wpwand_author_url', ''));
            if ('' !== $author) {
                return ['mode' => 'custom', 'contact' => esc_url_raw($author), 'review' => ''];
            }
        }

        if (\WPWand\Core\Pro::unlocked()) {
            return [
                'mode'    => 'pro',
                'contact' => 'https://wpwand.com/contact',
                'review'  => ReviewPrompt::REVIEW_URL,
            ];
        }

        return [
            'mode'    => 'free',
            'contact' => ReviewPrompt::SUPPORT_URL,
            'review'  => ReviewPrompt::REVIEW_URL,
        ];
    }

    /**
     * Language names (the value stored in wpwand_language is the name). Prefers the legacy
     * list when loaded, with a complete built-in fallback so the dropdown is never empty
     * regardless of load order.
     *
     * @return string[]
     */
    private function languages(): array
    {
        return Languages::all();
    }

    /**
     * @param array<string, mixed> $spec
     * @param mixed                $raw
     * @param string|null          $error Out-param.
     * @return mixed
     */
    private function sanitize_value(array $spec, $raw, ?string &$error)
    {
        $error = null;

        switch ($spec['type']) {
            case 'string':
                return sanitize_text_field((string) $raw);

            case 'textarea':
                return sanitize_textarea_field((string) $raw);

            case 'url':
                return esc_url_raw((string) $raw);

            case 'bool':
                return (!empty($raw) && $raw !== '0') ? 1 : 0;

            case 'enum':
                $val = sanitize_text_field((string) $raw);
                if (!in_array($val, (array) ($spec['options'] ?? []), true)) {
                    return (string) ($spec['default'] ?? '');
                }
                return $val;

            case 'color':
                $color = sanitize_hex_color((string) $raw);
                if ($raw !== '' && $color === null) {
                    $error = __('That isn’t a hex color. Use one like #3767FB.', 'ai-content-generation');
                    return '';
                }
                return $color ?? '';

            case 'float':
                // Reject before casting. (float) 'nope' is 0.0, so a typo used to save silently and
                // set temperature to 0 — a materially different model for every later generation —
                // while the endpoint still answered saved:true.
                if (!is_numeric($raw)) {
                    $error = __('That needs to be a number.', 'ai-content-generation');
                    return 0.0;
                }
                $val = (float) $raw;
                if (isset($spec['min']) && $val < $spec['min']) {
                    $val = (float) $spec['min'];
                }
                if (isset($spec['max']) && $val > $spec['max']) {
                    $val = (float) $spec['max'];
                }
                return $val;

            case 'int_empty':
                if ($raw === '' || $raw === null) {
                    return '';
                }
                return absint($raw);

            case 'key':
                // The shape of a key belongs to the provider, not to us — a hard prefix gate turns
                // any format change on their side into "your valid key is rejected and you cannot
                // save". Keep the shape as a hint (see update_settings) and let Test decide.
                return sanitize_text_field((string) $raw);

            default:
                return sanitize_text_field((string) $raw);
        }
    }
}
