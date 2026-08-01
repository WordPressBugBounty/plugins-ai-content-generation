<?php

namespace WPWand\Rest\Controllers;

use WPWand\Data\Brand;
use WPWand\Data\Languages;
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
        'wpwand_max_tokens'            => ['type' => 'int_empty', 'secret' => false, 'default' => 3450],
        'wpwand_presence_penalty'      => ['type' => 'float',    'secret' => false, 'default' => 0.0, 'min' => -2.0, 'max' => 2.0],
        'wpwand_frequency'             => ['type' => 'float',    'secret' => false, 'default' => 0.0, 'min' => -2.0, 'max' => 2.0],
        'wpwand_hide_ai_bar_gutenberg' => ['type' => 'bool',     'secret' => false, 'default' => 0],
        'toggler_position'             => ['type' => 'enum',     'secret' => false, 'default' => 'top', 'options' => ['top', 'side']],
        // Experimental: background generation engine + live streaming (compare which performs best).
        'wpwand_gen_engine'            => ['type' => 'enum',     'secret' => false, 'default' => 'browser', 'options' => ['browser', 'wp_cron', 'system_cron', 'action_scheduler']],
        'wpwand_stream'                => ['type' => 'bool',     'secret' => false, 'default' => 0],
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
            $message = __('Success — wp-cron.php is reachable. Your server cron can trigger scheduled generation.', 'wp-wand');
        } elseif (is_wp_error($resp)) {
            $message = sprintf(
                /* translators: %s: error message */
                __('Could not reach wp-cron.php (%s). Loopback requests may be blocked — use a real server cron pointed at the URL above.', 'wp-wand'),
                $resp->get_error_message()
            );
        } else {
            $message = sprintf(
                /* translators: %d: HTTP status code */
                __('wp-cron.php returned HTTP %d. Check the URL is publicly reachable.', 'wp-wand'),
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
            return new WP_REST_Response(['error' => __('Missing model.', 'wp-wand')], 400);
        }

        return new WP_REST_Response(ModelCatalog::limit_for($model), 200);
    }

    public function get_settings(WP_REST_Request $request): WP_REST_Response
    {
        $state         = $this->current_state();
        $state['meta'] = $this->meta();
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
            $to_save[$key] = $clean;
        }

        if (!empty($errors)) {
            return new WP_REST_Response(['saved' => false, 'errors' => $errors], 400);
        }

        foreach ($to_save as $key => $value) {
            update_option($key, $value);
        }

        // A new/changed API key may unlock a different model list — refetch on the next meta build.
        $keyChanged = (bool) array_intersect(array_keys($to_save), [
            'wpwand_api_key',
            'wpwand_claude_api_key',
            'wpwand_deepseek_api_key',
            'wpwand_openrouter_api_key',
        ]);
        if ($keyChanged) {
            ModelCatalog::flush();
        }

        // Return cached-only meta so save stays fast; the client refetches /settings/status
        // (via query invalidation) to repaint provider badges/models after a key change.
        $state          = $this->current_state();
        $state['meta']  = $this->meta();
        $state['saved'] = true;
        return new WP_REST_Response($state, 200);
    }

    /**
     * POST /validate-key — live-test the SAVED key for a provider via a tiny generation.
     */
    public function validate_key(WP_REST_Request $request): WP_REST_Response
    {
        $provider = sanitize_text_field((string) $request->get_param('provider'));

        if (!in_array($provider, ['openai', 'claude', 'deepseek', 'openrouter'], true)) {
            return new WP_REST_Response(['ok' => false, 'message' => __('Unknown provider.', 'wp-wand')], 400);
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
            return new WP_REST_Response(['ok' => false, 'message' => __('Save your key, then try again.', 'wp-wand')], 200);
        }

        // Use a model the provider currently offers (fetched live) — a hardcoded id can 404 once
        // the provider retires it (which broke the OpenRouter test).
        $testModel = ModelCatalog::test_model($provider);
        $content   = \WPWand\Generation\Generator::generate('Reply with the single word: ok', 1, ['model' => $testModel]);

        // Re-validate the cached status/model list against this fresh result.
        ModelCatalog::flush();

        if (is_object($content) && isset($content->error)) {
            $raw = isset($content->error->message) ? (string) $content->error->message : '';
            return new WP_REST_Response(['ok' => false, 'message' => $this->readable_error($raw)], 200);
        }
        return new WP_REST_Response(['ok' => true, 'message' => __('Key is working.', 'wp-wand')], 200);
    }

    /**
     * Turn a provider error (which can arrive as a raw JSON blob) into a short human sentence.
     */
    private function readable_error(string $raw): string
    {
        return \WPWand\Generation\ErrorFormatter::humanize(
            $raw,
            __('Validation failed. Please check your key and try again.', 'wp-wand')
        );
    }

    /**
     * POST /sync — refresh the cached template data (mirrors the legacy "Sync" button).
     */
    public function sync_data(WP_REST_Request $request): WP_REST_Response
    {
        \WPWand\Data\Templates::seed(true);
        return new WP_REST_Response(['ok' => true, 'message' => __('Data synced.', 'wp-wand')], 200);
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
            $settings[$key] = get_option($key, $spec['default']);
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
     * Feature entitlements. Mirrors the legacy gating: Pro features need the Pro plugin
     * active (wpwand_pro_init), white-label needs the Agency tier on top of that.
     *
     * @return array<string, bool>
     */
    private function caps(): array
    {
        // "pro"/"agency" mean UNLOCKED (plugin present + license active), so deactivating re-locks.
        return ['pro' => \WPWand\Core\Pro::unlocked(), 'agency' => \WPWand\Core\Pro::agency()];
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
                    $error = __('Invalid color value.', 'wp-wand');
                    return '';
                }
                return $color ?? '';

            case 'float':
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
                $key    = sanitize_text_field((string) $raw);
                $prefix = (string) ($spec['prefix'] ?? '');
                if ($prefix !== '' && strpos($key, $prefix) !== 0) {
                    /* translators: %s: required key prefix, e.g. sk- */
                    $error = sprintf(__('Invalid API key — it should start with "%s".', 'wp-wand'), $prefix);
                    return '';
                }
                return $key;

            default:
                return sanitize_text_field((string) $raw);
        }
    }
}
