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

    /**
     * A transport failure is not an answer, so it is held only briefly before we ask again. Backoff
     * doubles it per consecutive failure up to FAIL_TTL_MAX, so a host with outbound blocked settles
     * into occasional retries instead of paying the full probe on every settings visit.
     */
    private const FAIL_TTL     = 2 * MINUTE_IN_SECONDS;
    private const FAIL_TTL_MAX = 30 * MINUTE_IN_SECONDS;

    /** How long a known-good model list is kept to fall back on while the provider is unreachable. */
    private const LAST_GOOD_TTL = WEEK_IN_SECONDS;
    private const REC_CAP   = 8000;                   // recommended default never exceeds this

    private const KEY_OPTION = [
        'openai'     => 'wpwand_api_key',
        'claude'     => 'wpwand_claude_api_key',
        'deepseek'   => 'wpwand_deepseek_api_key',
        'gemini'     => 'wpwand_gemini_api_key',
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
        return [
            'status'       => $c['status'],
            'message'      => $c['message'],
            'stale_models' => !empty($c['stale_models']),
        ];
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

        // No probe yet this cycle. Offer the last known list so the picker paints something usable
        // before the first status call lands.
        $models = self::recall_models($provider);

        return [
            'status'       => 'unknown',
            'message'      => '',
            'models'       => $models,
            'stale_models' => !empty($models),
        ];
    }

    /**
     * Validate a RAW key (typed in the field, not yet saved) by hitting the provider directly.
     * Lets the "Test" button work before saving. @return array{ok:bool, message:string}
     */
    public static function check_key(string $provider, string $key): array
    {
        $key = trim($key);
        if ($key === '') {
            return ['ok' => false, 'message' => __('Enter a key to test.', 'ai-content-generation')];
        }

        if ($provider === 'openrouter') {
            [$status, $message] = self::openrouter_status($key);
        } else {
            [$url, $headers] = self::models_request($provider, $key);
            $resp = wp_remote_get($url, ['timeout' => 12, 'headers' => $headers]);
            $s       = self::classify($resp, $provider);
            $status  = $s['status'];
            $message = $s['message'];
        }

        if ($status === 'active') {
            return ['ok' => true, 'message' => __('Key is working.', 'ai-content-generation')];
        }
        return ['ok' => false, 'message' => $message !== '' ? $message : __('Key is not valid.', 'ai-content-generation')];
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
            'gemini'     => 'gemini-2.5-flash-lite',
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

        if ($result['status'] === 'active') {
            self::remember_models($provider, $result['models']);
            delete_transient('wpwand_catalog_fails_' . $provider);
            set_transient($cacheKey, $result, self::CACHE_TTL);
            return $result;
        }

        if ($result['status'] === 'unreachable') {
            // We could not ask, so we do not know anything new about the key. Serve the last list we
            // did get, and keep the status honest so the UI can say the provider is unreachable
            // rather than painting a green badge over a stale answer.
            $result['models']       = self::recall_models($provider);
            $result['stale_models'] = !empty($result['models']);

            $fails = (int) get_transient('wpwand_catalog_fails_' . $provider);
            $fails = min($fails + 1, 8);
            set_transient('wpwand_catalog_fails_' . $provider, $fails, self::FAIL_TTL_MAX);
            $ttl = min(self::FAIL_TTL * (2 ** ($fails - 1)), self::FAIL_TTL_MAX);

            set_transient($cacheKey, $result, $ttl);
            return $result;
        }

        // invalid / exceeded / unset are real answers about the key; an empty list is correct.
        set_transient($cacheKey, $result, self::CACHE_TTL);
        return $result;
    }

    /**
     * Keep the last good model list under its own key, tied to the key that produced it.
     *
     * It lives outside the catalogue transient on purpose: flush() clears the catalogue so the Test
     * button forces a live re-probe, and that must not throw away the fallback at the same time.
     * Binding it to a hash of the key means swapping or revoking a key cannot resurrect the old
     * account's models.
     *
     * @param array<int, array<string, mixed>> $models
     */
    private static function remember_models(string $provider, array $models): void
    {
        if (empty($models)) {
            return;
        }
        set_transient(self::last_good_key($provider), $models, self::LAST_GOOD_TTL);
    }

    /** @return array<int, array<string, mixed>> */
    private static function recall_models(string $provider): array
    {
        $models = get_transient(self::last_good_key($provider));
        return is_array($models) ? $models : [];
    }

    private static function last_good_key(string $provider): string
    {
        $key = (string) get_option(self::KEY_OPTION[$provider] ?? '', '');
        return 'wpwand_models_' . $provider . '_' . substr(md5($key), 0, 8);
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

    /** OpenAI / Claude / DeepSeek / Gemini: one /models call gives both validity and the list. */
    private static function fetch_models(string $provider, string $key): array
    {
        [$url, $headers] = self::models_request($provider, $key);
        $resp = wp_remote_get($url, ['timeout' => 12, 'headers' => $headers]);

        $status = self::classify($resp, $provider);
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
        if ($provider === 'gemini') {
            // Google's OpenAI-compatible listing. The native /v1beta/models answers the same
            // question in Google's own shape; this one is used so the key check and the parse stay
            // on the single surface GeminiProvider generates against.
            return ['https://generativelanguage.googleapis.com/v1beta/openai/models', [
                'Authorization' => 'Bearer ' . $key,
            ]];
        }
        return ['https://api.openai.com/v1/models', ['Authorization' => 'Bearer ' . $key]];
    }

    /** Map an HTTP response to a key status. @return array{status:string, message:string} */
    private static function classify($resp, string $provider = ''): array
    {
        if (is_wp_error($resp)) {
            // Raw cURL text otherwise — the formatter's transport branch names the provider and says
            // what to do about it.
            $message = ErrorFormatter::humanize(
                $resp->get_error_message(),
                __('Could not reach the provider. Please try again.', 'ai-content-generation'),
                $provider
            );
            return ['status' => 'unreachable', 'message' => $message];
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code === 200) {
            return ['status' => 'active', 'message' => ''];
        }

        // Pull the provider's own error sentence out of the body so the user sees the real reason
        // ("No endpoints found for model X", "Incorrect API key provided: sk-…") rather than a bare
        // HTTP code. Empty if the body has nothing useful.
        $detail = ErrorFormatter::humanize((string) wp_remote_retrieve_body($resp), '', $provider);
        $generic = static function (string $label) use ($detail) {
            // Avoid echoing our own fallback text back as "detail".
            $clean = ($detail !== '' && stripos($detail, 'went wrong') === false) ? $detail : '';
            return $clean !== '' ? $clean : $label;
        };

        if ($code === 401 || $code === 403) {
            return ['status' => 'invalid', 'message' => $generic(__('Key is invalid or expired.', 'ai-content-generation'))];
        }
        if ($code === 429) {
            return ['status' => 'exceeded', 'message' => $generic(__('Rate limit or quota exceeded.', 'ai-content-generation'))];
        }
        if ($code === 402) {
            return ['status' => 'exceeded', 'message' => $generic(__('Insufficient credit / quota exceeded.', 'ai-content-generation'))];
        }
        // Google answers a bad key with 400 INVALID_ARGUMENT — {"error":{"code":400,"message":
        // "Please pass a valid API key","status":"INVALID_ARGUMENT"}} — where every other provider
        // here uses 401. Measured against the live endpoint with both a junk string and a
        // correctly-shaped AIza… key; both give 400. Without this branch a simple typo falls into
        // 'unreachable' below and the user is sent to their host to debug a firewall that is fine.
        // Scoped to gemini on purpose: elsewhere a 400 really is a malformed request, not a key.
        if ($code === 400 && $provider === 'gemini') {
            return ['status' => 'invalid', 'message' => $generic(__('Key is invalid or expired.', 'ai-content-generation'))];
        }
        /* translators: %d: HTTP status code */
        $fallback = sprintf(__('Provider returned HTTP %d.', 'ai-content-generation'), $code);
        return ['status' => 'unreachable', 'message' => $generic($fallback)];
    }

    /** OpenRouter validity via its /key endpoint (the public /models doesn't authenticate). */
    private static function openrouter_status(string $key): array
    {
        $resp = wp_remote_get('https://openrouter.ai/api/v1/key', [
            'timeout' => 12,
            'headers' => ['Authorization' => 'Bearer ' . $key],
        ]);
        $s = self::classify($resp, 'openrouter');
        if ($s['status'] !== 'active') {
            return [$s['status'], $s['message']];
        }
        $body  = json_decode((string) wp_remote_retrieve_body($resp), true);
        $usage = $body['data']['usage'] ?? null;
        $limit = $body['data']['limit'] ?? null;
        if ($limit !== null && $usage !== null && (float) $usage >= (float) $limit) {
            return ['exceeded', __('OpenRouter credit limit reached.', 'ai-content-generation')];
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
            if ($provider === 'gemini') {
                // Google's OpenAI-compatible listing does NOT match OpenAI's shape: ids come back
                // as Google resource names — "models/gemini-2.5-pro", not "gemini-2.5-pro". Strip
                // the prefix so what we store in wpwand_model is the id the docs tell people to
                // use, and so ProviderFactory sees a plain model string.
                if (strpos($id, 'models/') === 0) {
                    $id = substr($id, 7);
                }
                if (!self::gemini_is_chat_model($id)) {
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
        if ($provider === 'openai' || $provider === 'deepseek' || $provider === 'gemini') {
            usort($out, static fn ($a, $b) => strcasecmp($a['value'], $b['value']));
        }
        return $out;
    }

    /**
     * Whether a Gemini model can answer a chat completion.
     *
     * Google returns its whole catalogue in one listing — embeddings, image and speech models, the
     * Live API variants, the older PaLM-era ids — and none of those serve /chat/completions. Left
     * unfiltered the picker fills with entries that only ever error, so this keeps it to the
     * generative gemini-* families and drops the jobs that name their modality.
     *
     * Deliberately a denylist of modality words rather than an allowlist of ids: Google ships new
     * Gemini generations often, and an allowlist would silently hide every model released after
     * this line was written. Preview and dated ids are kept — someone choosing Gemini may well want
     * the newest thing, and the id is validated against this same list before use.
     */
    private static function gemini_is_chat_model(string $id): bool
    {
        // Everything Google generates text with is gemini-*; this also drops embedding-001,
        // imagen-*, veo-*, aqa and the retired text-bison/chat-bison ids in one go.
        if (strpos($id, 'gemini-') !== 0) {
            return false;
        }

        return !preg_match('/(embedding|image|vision|tts|audio|live|dialog|computer-use)/i', $id);
    }

    /**
     * Whether OpenRouter charges nothing for this model.
     *
     * Both halves have to be zero — a model can be free to send and paid to receive. Prices arrive
     * as strings like "0.0000014", so they are compared numerically rather than to the literal "0".
     *
     * @param array<string, mixed> $model One entry from OpenRouter's /models payload.
     */
    private static function is_free(array $model): bool
    {
        $pricing = $model['pricing'] ?? null;
        if (!is_array($pricing) || !isset($pricing['prompt'], $pricing['completion'])) {
            return false;
        }

        return (float) $pricing['prompt'] === 0.0 && (float) $pricing['completion'] === 0.0;
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
                'free'       => self::is_free($m),
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
        if ($provider === 'gemini') {
            // The 2.5 series onwards returns up to 65,536 output tokens; 1.5 and 2.0 cap at 8,192.
            // Only the reported ceiling differs — REC_CAP still decides what we actually ask for.
            if (preg_match('/^gemini-(2\.5|[3-9])/', $m)) {
                return 65536;
            }
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
            // Stable, undated aliases only. Google's dated and -preview ids get retired on a
            // schedule, and a hardcoded id that outlives the model is exactly the failure recorded
            // in docs/LEARNINGS.md — these four track whatever Google currently points them at.
            'gemini' => [
                ['gemini-3.7-flash', 'Gemini 3.7 Flash'],
                ['gemini-3.5-flash-lite', 'Gemini 3.5 Flash Lite'],
                ['gemini-2.5-pro', 'Gemini 2.5 Pro'],
                ['gemini-2.5-flash', 'Gemini 2.5 Flash'],
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
