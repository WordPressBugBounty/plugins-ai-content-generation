<?php

namespace WPWand\Generation;

use WPWand\Generation\Providers\ProviderFactory;

/**
 * Dynamic model catalogue + live key validation.
 *
 * For each provider we hit its API (cached) to learn two things at once: whether the saved key
 * actually works right now (active / invalid / expired / quota-exceeded / unreachable / unset), and
 * the list of models it offers with per-model output-token limits. Models are only returned when
 * the key is valid, so the settings UI can show the model picker + an "active" badge for working
 * keys and a clear status (with models disabled) for keys that fail. OpenRouter exposes real
 * per-model limits and a /key status endpoint; the others report status via their /models call and
 * use a known-limits table. Everything degrades to a static fallback list when the key works but
 * the catalogue can't be read, so the picker is never empty for a valid key.
 */
final class ModelCatalog
{
    private const CACHE_TTL = 30 * MINUTE_IN_SECONDS; // key status can change (expire/quota)
    private const REC_CAP   = 8000;                   // recommended default never exceeds this

    private const KEY_OPTION = [
        'openai'     => 'wpwand_api_key',
        'claude'     => 'wpwand_claude_api_key',
        'deepseek'   => 'wpwand_deepseek_api_key',
        'openrouter' => 'wpwand_openrouter_api_key',
    ];

    // -- public accessors -------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> models for a provider ([] when the key isn't valid) */
    public static function for_provider(string $provider): array
    {
        return self::catalog($provider)['models'];
    }

    /** @return array{status:string, message:string} live key status for a provider */
    public static function status(string $provider): array
    {
        $c = self::catalog($provider);
        return ['status' => $c['status'], 'message' => $c['message']];
    }

    /**
     * Cache-only snapshot — returns the last-known catalogue WITHOUT probing the provider.
     * Used by the fast GET /settings so the form paints instantly; the live probe happens
     * later via the lazy /settings/status endpoint. When nothing is cached yet the provider
     * reports as 'unknown' with an empty model list.
     *
     * @return array{status:string, message:string, models:array<int, array<string, mixed>>}
     */
    public static function cached(string $provider): array
    {
        $cached = get_transient('wpwand_catalog_' . $provider);
        if (is_array($cached) && isset($cached['status'])) {
            return $cached;
        }
        return ['status' => 'unknown', 'message' => '', 'models' => []];
    }

    /**
     * Validate a RAW key (typed in the field, not yet saved) by hitting the provider directly.
     * Lets the "Test" button work before saving. @return array{ok:bool, message:string}
     */
    public static function check_key(string $provider, string $key): array
    {
        $key = trim($key);
        if ($key === '') {
            return ['ok' => false, 'message' => __('Enter a key to test.', 'wp-wand')];
        }

        if ($provider === 'openrouter') {
            [$status, $message] = self::openrouter_status($key);
        } else {
            [$url, $headers] = self::models_request($provider, $key);
            $resp = wp_remote_get($url, ['timeout' => 12, 'headers' => $headers]);
            $s       = self::classify($resp);
            $status  = $s['status'];
            $message = $s['message'];
        }

        if ($status === 'active') {
            return ['ok' => true, 'message' => __('Key is working.', 'wp-wand')];
        }
        return ['ok' => false, 'message' => $message !== '' ? $message : __('Key is not valid.', 'wp-wand')];
    }

    /** Recommended + maximum output tokens for one model value. */
    public static function limit_for(string $model): array
    {
        $provider = ProviderFactory::forModel($model);
        foreach (self::for_provider($provider->id()) as $m) {
            if ($m['value'] === $model) {
                return [
                    'max_tokens' => (int) $m['max_tokens'],
                    'model_max'  => $m['model_max'] !== null ? (int) $m['model_max'] : null,
                    'source'     => $m['model_max'] !== null ? 'api' : 'fallback',
                ];
            }
        }
        $max = self::known_max($provider->id(), $model);
        return ['max_tokens' => (int) min($max, self::REC_CAP), 'model_max' => null, 'source' => 'fallback'];
    }

    /** A model id usable for the live key test — prefers a cheap/fast one. */
    public static function test_model(string $provider): string
    {
        foreach (self::for_provider($provider) as $m) {
            if (preg_match('/(mini|flash|haiku|nano|small|lite)/i', (string) $m['value'])) {
                return (string) $m['value'];
            }
        }
        $models = self::for_provider($provider);
        if (!empty($models)) {
            return (string) $models[0]['value'];
        }
        $defaults = [
            'openai'     => 'gpt-4o-mini',
            'claude'     => 'claude-3-5-haiku-20241022',
            'deepseek'   => 'deepseek-chat',
            'openrouter' => 'oprtr-openai/gpt-4o-mini',
        ];
        return $defaults[$provider] ?? 'gpt-4o-mini';
    }

    public static function flush(): void
    {
        foreach (array_keys(self::KEY_OPTION) as $p) {
            delete_transient('wpwand_catalog_' . $p);
        }
    }

    // -- catalogue (cached) -----------------------------------------------------------------

    /** @return array{status:string, message:string, models:array<int, array<string, mixed>>} */
    private static function catalog(string $provider): array
    {
        $cacheKey = 'wpwand_catalog_' . $provider;
        $cached   = get_transient($cacheKey);
        if (is_array($cached) && isset($cached['status'])) {
            return $cached;
        }

        $result = self::build($provider);
        set_transient($cacheKey, $result, self::CACHE_TTL);
        return $result;
    }

    /** @return array{status:string, message:string, models:array<int, array<string, mixed>>} */
    private static function build(string $provider): array
    {
        $key = (string) get_option(self::KEY_OPTION[$provider] ?? '', '');
        if ($key === '') {
            return ['status' => 'unset', 'message' => '', 'models' => []];
        }

        if ($provider === 'openrouter') {
            [$status, $message] = self::openrouter_status($key);
            $models = $status === 'active' ? self::openrouter_models() : [];
            if ($status === 'active' && empty($models)) {
                $models = self::fallback($provider);
            }
            return ['status' => $status, 'message' => $message, 'models' => $models];
        }

        return self::fetch_models($provider, $key);
    }

    /** OpenAI / Claude / DeepSeek: one /models call gives both validity and the list. */
    private static function fetch_models(string $provider, string $key): array
    {
        [$url, $headers] = self::models_request($provider, $key);
        $resp = wp_remote_get($url, ['timeout' => 12, 'headers' => $headers]);

        $status = self::classify($resp);
        if ($status['status'] !== 'active') {
            return ['status' => $status['status'], 'message' => $status['message'], 'models' => []];
        }

        $body   = json_decode((string) wp_remote_retrieve_body($resp), true);
        $data   = is_array($body['data'] ?? null) ? $body['data'] : [];
        $models = self::map_models($provider, $data);
        if (empty($models)) {
            $models = self::fallback($provider);
        }

        return ['status' => 'active', 'message' => '', 'models' => $models];
    }

    /** @return array{0:string,1:array<string,string>} */
    private static function models_request(string $provider, string $key): array
    {
        if ($provider === 'claude') {
            return ['https://api.anthropic.com/v1/models?limit=100', [
                'x-api-key'         => $key,
                'anthropic-version' => '2023-06-01',
            ]];
        }
        if ($provider === 'deepseek') {
            return ['https://api.deepseek.com/models', ['Authorization' => 'Bearer ' . $key]];
        }
        return ['https://api.openai.com/v1/models', ['Authorization' => 'Bearer ' . $key]];
    }

    /** Map an HTTP response to a key status. @return array{status:string, message:string} */
    private static function classify($resp): array
    {
        if (is_wp_error($resp)) {
            return ['status' => 'unreachable', 'message' => $resp->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code === 200) {
            return ['status' => 'active', 'message' => ''];
        }

        // Pull the provider's own error sentence out of the body so the user sees the real reason
        // ("No endpoints found for model X", "Incorrect API key provided: sk-…") rather than a bare
        // HTTP code. Empty if the body has nothing useful.
        $detail = ErrorFormatter::humanize((string) wp_remote_retrieve_body($resp), '');
        $generic = static function (string $label) use ($detail) {
            // Avoid echoing our own fallback text back as "detail".
            $clean = ($detail !== '' && stripos($detail, 'went wrong') === false) ? $detail : '';
            return $clean !== '' ? $clean : $label;
        };

        if ($code === 401 || $code === 403) {
            return ['status' => 'invalid', 'message' => $generic(__('Key is invalid or expired.', 'wp-wand'))];
        }
        if ($code === 429) {
            return ['status' => 'exceeded', 'message' => $generic(__('Rate limit or quota exceeded.', 'wp-wand'))];
        }
        if ($code === 402) {
            return ['status' => 'exceeded', 'message' => $generic(__('Insufficient credit / quota exceeded.', 'wp-wand'))];
        }
        /* translators: %d: HTTP status code */
        $fallback = sprintf(__('Provider returned HTTP %d.', 'wp-wand'), $code);
        return ['status' => 'unreachable', 'message' => $generic($fallback)];
    }

    /** OpenRouter validity via its /key endpoint (the public /models doesn't authenticate). */
    private static function openrouter_status(string $key): array
    {
        $resp = wp_remote_get('https://openrouter.ai/api/v1/key', [
            'timeout' => 12,
            'headers' => ['Authorization' => 'Bearer ' . $key],
        ]);
        $s = self::classify($resp);
        if ($s['status'] !== 'active') {
            return [$s['status'], $s['message']];
        }
        $body  = json_decode((string) wp_remote_retrieve_body($resp), true);
        $usage = $body['data']['usage'] ?? null;
        $limit = $body['data']['limit'] ?? null;
        if ($limit !== null && $usage !== null && (float) $usage >= (float) $limit) {
            return ['exceeded', __('OpenRouter credit limit reached.', 'wp-wand')];
        }
        return ['active', ''];
    }

    // -- model mapping ----------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private static function map_models(string $provider, array $data): array
    {
        $out = [];
        foreach ($data as $m) {
            $id = $m['id'] ?? '';
            if ($id === '') {
                continue;
            }
            if ($provider === 'openai') {
                if (!preg_match('/^(gpt-|o1|o3|o4|chatgpt)/', $id)) {
                    continue;
                }
                if (preg_match('/(instruct|audio|realtime|search|transcribe|tts|image|embedding|moderation|dall|whisper|vision-preview)/', $id)) {
                    continue;
                }
            }
            $max   = self::known_max($provider, $id);
            $label = $provider === 'claude' ? (string) ($m['display_name'] ?? self::pretty($id)) : self::pretty($id);
            $out[] = [
                'value'      => $id,
                'label'      => $label,
                'model_max'  => $max,
                'max_tokens' => (int) min($max, self::REC_CAP),
            ];
        }
        if ($provider === 'openai' || $provider === 'deepseek') {
            usort($out, static fn ($a, $b) => strcasecmp($a['value'], $b['value']));
        }
        return $out;
    }

    /** OpenRouter's public model list (with real per-model limits). */
    private static function openrouter_models(): array
    {
        $resp = wp_remote_get('https://openrouter.ai/api/v1/models', ['timeout' => 12]);
        if (is_wp_error($resp) || (int) wp_remote_retrieve_response_code($resp) !== 200) {
            return [];
        }
        $body = json_decode((string) wp_remote_retrieve_body($resp), true);
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];

        $out = [];
        foreach ($data as $m) {
            $id = $m['id'] ?? '';
            if ($id === '') {
                continue;
            }
            $mct = $m['top_provider']['max_completion_tokens'] ?? null;
            $ctx = $m['context_length'] ?? null;
            $max = is_numeric($mct) ? (int) $mct : (is_numeric($ctx) ? (int) $ctx : null);
            $out[] = [
                'value'      => 'oprtr-' . $id,
                'label'      => (string) ($m['name'] ?? $id),
                'model_max'  => $max,
                'max_tokens' => $max !== null ? (int) min($max, self::REC_CAP) : 4000,
            ];
        }
        usort($out, static fn ($a, $b) => strcasecmp($a['label'], $b['label']));
        return $out;
    }

    /** Known maximum output tokens for providers that don't report it. */
    private static function known_max(string $provider, string $model): int
    {
        $m = strtolower($model);

        if ($provider === 'openai') {
            if (strpos($m, 'o1') === 0 || strpos($m, 'o3') === 0 || strpos($m, 'o4') === 0) {
                return 100000;
            }
            if (strpos($m, 'gpt-5') !== false || strpos($m, 'gpt-4o') !== false || strpos($m, 'gpt-4.1') !== false || strpos($m, 'chatgpt-4o') !== false) {
                return 16384;
            }
            if ($m === 'gpt-4') {
                return 8192;
            }
            return 4096;
        }
        if ($provider === 'claude') {
            if (
                strpos($m, 'claude-opus-4') !== false || strpos($m, 'claude-sonnet-4') !== false
                || strpos($m, 'claude-3-7') !== false || strpos($m, 'claude-3-5') !== false
            ) {
                return 8192;
            }
            return 4096;
        }
        if ($provider === 'deepseek') {
            return 8192;
        }
        return 4096;
    }

    private static function pretty(string $id): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $id));
    }

    /** Static list used when the key is valid but the catalogue can't be read. */
    private static function fallback(string $provider): array
    {
        $lists = [
            'openai' => [
                ['gpt-5', 'GPT-5'],
                ['gpt-4.1-mini', 'GPT 4.1 Mini'],
                ['chatgpt-4o-latest', 'ChatGPT 4o Latest'],
                ['gpt-4o-mini', 'GPT 4o Mini'],
                ['gpt-4o', 'GPT 4o'],
            ],
            'claude' => [
                ['claude-opus-4-20250514', 'Claude 4 Opus'],
                ['claude-sonnet-4-20250514', 'Claude 4 Sonnet'],
                ['claude-3-5-haiku-20241022', 'Claude 3.5 Haiku'],
            ],
            'deepseek' => [
                ['deepseek-chat', 'DeepSeek Chat'],
                ['deepseek-reasoner', 'DeepSeek Reasoner (R1)'],
            ],
            'openrouter' => [
                ['oprtr-openai/gpt-4o-mini', 'OpenAI: GPT-4o-mini'],
                ['oprtr-anthropic/claude-3.5-sonnet', 'Anthropic: Claude 3.5 Sonnet'],
            ],
        ];

        $out = [];
        foreach ($lists[$provider] ?? [] as [$value, $label]) {
            $max = self::known_max($provider, $value);
            $out[] = ['value' => $value, 'label' => $label, 'model_max' => null, 'max_tokens' => (int) min($max, self::REC_CAP)];
        }
        return $out;
    }
}
