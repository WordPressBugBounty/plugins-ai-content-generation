<?php

namespace WPWand\Generation;

use WPWand\Generation\Providers\ProviderFactory;

/**
 * AI text generation in the new architecture — a faithful port of the legacy
 * wpwand_generate_ai_content() (inc/api.php) so the React/REST backend no longer depends on the
 * procedural generator.
 *
 * The contract is IDENTICAL to legacy on purpose: same signature, same option keys (read-only —
 * nothing is written here), same request shapes per provider, and the SAME return object so every
 * caller keeps working unchanged:
 *   - success → stdClass with ->choices[], each ->message->content
 *   - failure → stdClass with ->error->{message, type, code}, where message is the json-encoded
 *     provider error (so {@see ErrorFormatter} unwraps it exactly as before)
 *
 * Routing is via {@see ProviderFactory}; the provider classes own the endpoint, key option and
 * model. Legacy `wpwand_api_response` filter is preserved for back-compat.
 */
final class Generator
{
    /**
     * Seconds to wait on a provider before giving up.
     *
     * 120 was shorter than the request the flagship "One Click Blog Post" template sends — it needs
     * ~135s — so every run of it died on "cURL error 28" before a word came back. Filter
     * `wpwand_http_timeout` to tune it for a host that can hold a worker longer (or has a gateway
     * that cuts sooner).
     */
    public const HTTP_TIMEOUT = 180;

    /**
     * Output-token budget used when wpwand_max_tokens was never saved.
     *
     * ONE number in ONE place: {@see self::auto_max_tokens()} and the settings schema both read it,
     * so an install that never touched the field can't get a different budget depending on which
     * code path asked. 3600 is what generation has actually been using.
     */
    public const DEFAULT_MAX_TOKENS = 3600;

    /** Variation prefixes for multi-result requests (verbatim from legacy). */
    /**
     * The one sentence every request ends on, in its own paragraph.
     *
     * Two used to be glued on, in two places, both in broken English and both on the same line as
     * whatever came last: "You must need only answer the question. Do not write any other
     * text/explanation or multiple answer." from attempt(), then " . You must follow this
     * instructions strictly. Don't add any other text/explanation/multiple results. Just write the
     * content." from the request builder. "Answer the question" on a request to write an article,
     * a stray full stop, and the pair stuck to the end of the last house rule. Captured off the
     * wire on 2026-10-02 (docs/reviews/2026-10-02-prompt-simulation.md, finding 8).
     */
    private const CLOSING = 'Return only the content itself: no preamble, no notes about what you did, no alternatives.';

    private const VARIATIONS = [
        'Provide a unique perspective on this: ',
        'Give a different take on this topic: ',
        'Approach this from another angle: ',
        'Offer an alternative view on this: ',
        'Present a fresh perspective on this: ',
    ];

    /**
     * Which provider to reach for first when the user hasn't chosen a model.
     *
     * Same order as the key fields in Settings, and chosen the same way: whichever is cheapest to
     * get working. This only decides where somebody lands who has keys but has picked nothing —
     * a chosen model is always honoured, so nobody's selection moves because of this list.
     */
    private const PROVIDER_ORDER = ['gemini', 'openrouter', 'openai', 'claude', 'deepseek'];

    /**
     * Preferred starting model per provider, keyed by provider id.
     *
     * Checked against the live catalogue by {@see self::default_model()} before use, so a model the
     * provider later retires degrades to one the account really has instead of a dead id.
     */
    private const DEFAULT_MODEL = [
        'openai'     => 'chatgpt-4o-latest',
        'claude'     => 'claude-sonnet-5',
        'deepseek'   => 'deepseek-chat',
        // OpenRouter's own Free Models Router: zero-priced, and it routes across whatever free
        // models are up rather than pinning one that can be retired, rate-limited, or just return
        // nothing. A new key therefore generates without spending anything.
        'openrouter' => 'oprtr-openrouter/free',
        // Flash rather than Pro: the free tier reaches it, and an undated alias follows Google's
        // repointing instead of pinning a version that gets retired.
        'gemini'     => 'gemini-2.5-flash',
    ];

    /**
     * @param string               $prompt
     * @param int                  $number Number of results.
     * @param array<string, mixed> $args   model, language, biz_details, targated_customer,
     *                                     temperature, max_tokens (null = auto).
     * @return object stdClass with ->choices or ->error (legacy-identical).
     */
    public static function generate(string $prompt, int $number = 1, array $args = []): object
    {
        // Automatic failover is opt-in and off by default. When it is off — every install that has
        // not turned it on — this is one option read and then the same single request that has
        // always been made, with the same object coming back. See {@see Failover}.
        if (!Failover::engages($args)) {
            return self::attempt($prompt, $number, $args);
        }

        return Failover::run($args, static function (array $failoverArgs) use ($prompt, $number) {
            return self::attempt($prompt, $number, $failoverArgs);
        });
    }

    /**
     * One generation, one provider — the request Generator has always made.
     *
     * @param array<string, mixed> $args
     * @return object stdClass with ->choices or ->error (legacy-identical).
     */
    private static function attempt(string $prompt, int $number, array $args): object
    {
        $model = '';
        try {
            $model = self::validated_model((string) ($args['model'] ?? ''));

            $args = wp_parse_args($args, [
                'model'             => $model,
                'language'          => get_option('wpwand_language', 'English'),
                'biz_details'       => '',
                'targated_customer' => '',
                'temperature'       => (float) get_option('wpwand_temperature', 1.0),
                // null = "size it to the prompt" (auto_max_tokens below), which is also where the
                // single DEFAULT_MAX_TOKENS fallback lives.
                'max_tokens'        => self::saved_max_tokens(),
            ]);
            $model = (string) $args['model'];


            if (null === $args['max_tokens']) {
                $args['max_tokens'] = self::auto_max_tokens($prompt, $model);
            }

            $args['biz_details'] = !empty($args['biz_details'])
                ? "Write this based on our business details, which this: {$args['biz_details']}"
                : '';
            $args['targated_customer'] = !empty($args['targated_customer'])
                ? "Write this focusing the benefits of our targeted customer, which this: {$args['targated_customer']}"
                : '';

            @ini_set('max_execution_time', '300'); // phpcs:ignore

            $provider = ProviderFactory::forModel($model);
            if (!$provider->isConfigured()) {
                throw new \Exception(sprintf(
                    /* translators: %s: provider label */
                    __('%s API key is missing', 'ai-content-generation'),
                    $provider->label()
                ));
            }

            $response = $provider->id() === 'claude'
                ? self::request_claude($provider, $prompt, $number, $args)
                : self::request_openai_style($provider, $prompt, $number, $args);

            return apply_filters('wpwand_api_response', $response, $provider->id() === 'claude');
        } catch (\Throwable $e) {
            $source = $model !== '' ? ProviderFactory::forModel($model)->id() : 'unknown';
            return (object) [
                'error' => (object) [
                    'message' => $e->getMessage(),
                    'type'    => $source . '_error',
                    'code'    => 500,
                ],
            ];
        }
    }

    /**
     * The language, and the two legacy business sentences when the caller sent them.
     *
     * @param array<string, mixed> $args
     */
    private static function standing_instructions(array $args): string
    {
        return implode(' ', array_filter([
            "You must write in {$args['language']}.",
            trim((string) $args['biz_details']),
            trim((string) $args['targated_customer']),
        ]));
    }

    /**
     * Pull a provider's error out of a decoded response, whichever shape it arrived in.
     *
     * OpenAI, DeepSeek and OpenRouter return `{"error": {...}}`. Google returns `[{"error": {...}}]`.
     * Reading only the first shape is what made a Gemini failure invisible.
     *
     * @param mixed $decoded
     * @return object|null The error, or null when the response carries none.
     */
    private static function error_from($decoded)
    {
        if (is_object($decoded) && isset($decoded->error)) {
            return (object) $decoded->error;
        }

        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                if (is_object($item) && isset($item->error)) {
                    return (object) $item->error;
                }
            }
        }

        return null;
    }

    /**
     * Keep the HTTP status and the Retry-After header on a provider's error.
     *
     * The body alone cannot always say "you are going too fast, come back in twenty seconds": a
     * provider may put the wait in the header only. {@see RateLimited} reads both.
     *
     * @param object $error The provider's error object.
     * @param mixed  $resp  The wp_remote_post() response it came with.
     */
    private static function with_transport(object $error, $resp): object
    {
        $error->wpwand_http = (int) wp_remote_retrieve_response_code($resp);

        $after = wp_remote_retrieve_header($resp, 'retry-after');
        if (is_string($after) && $after !== '') {
            $error->wpwand_retry_after = $after;
        }

        return $error;
    }

    /**
     * OpenAI / DeepSeek / OpenRouter — chat/completions, one request per result.
     *
     * @param array<string, mixed> $args
     */
    private static function request_openai_style($provider, string $prompt, int $number, array $args): object
    {
        $model = $provider->model();
        if ($provider->id() === 'openrouter') {
            $model = str_replace('oprtr-', '', $model); // the API wants the bare model id
        }

        $headers = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $provider->apiKey(),
        ];

        $combined = new \stdClass();
        $combined->choices = [];

        for ($i = 0; $i < $number; $i++) {
            $prefix = $number > 1 ? (self::VARIATIONS[$i % count(self::VARIATIONS)] ?? '') : '';

            $body = [
                'model'    => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a professional content writer. Follow the instructions in the request exactly.'],
                    // Joined from what is actually there. The business and audience sentences are
                    // empty on most requests, and the bare template left two trailing spaces.
                    ['role' => 'system', 'content' => self::standing_instructions($args)],
                    ['role' => 'user', 'content' => "{$prefix}{$prompt}\n\n" . self::CLOSING],
                ],
                'temperature'       => (float) $args['temperature'] + ($i * 0.1),
            ];

            // Not every OpenAI-compatible surface accepts OpenAI's whole body. Google's rejects the
            // two penalty fields with a 400 rather than ignoring them, which failed every
            // non-streaming Gemini generation on the site. Ask the provider before sending.
            if ($provider->supportsPenalties()) {
                $body['frequency_penalty'] = (float) get_option('wpwand_frequency', 0);
                $body['presence_penalty']  = (float) get_option('wpwand_presence_penalty', 0);
            } else {
                // Gemini refuses the fields and Claude never sees this body at all, so the two
                // sliders did nothing on either — they saved, they moved, and the writing was
                // identical. Ask for the same thing in words instead. It is a request rather than
                // a rule, and the Settings screen says so rather than letting people believe
                // otherwise.
                $asked = self::variety_instruction();
                if ('' !== $asked) {
                    $body['messages'][] = ['role' => 'system', 'content' => $asked];
                }
            }

            foreach ($provider->chatBodyExtras() as $field => $value) {
                $body[$field] = $value;
            }

            // A thinking model is charged for its reasoning out of this same budget, so the number
            // the caller asked for has to be the number left AFTER that. Without the reserve a
            // request for 600 words came back with 94 and finish_reason "length" — a truncation
            // the caller never asked for and could not see.
            $budget = (int) $args['max_tokens'] + $provider->tokenOverhead();

            // gpt-5 family uses max_completion_tokens; everything else max_tokens (legacy parity).
            if ('gpt-5' === $model || 'gpt-5-nano' === $model) {
                $body['max_completion_tokens'] = $budget;
            } else {
                $body['max_tokens'] = $budget;
            }

            // Trusted first-party API endpoint (hardcoded per provider) — use wp_remote_post, NOT the
            // "safe" variant. wp_safe_remote_post runs wp_http_validate_url(), which rejects these on
            // some hosts (DNS/IP validation) and surfaces as "A valid URL was not provided." Matches
            // the legacy behaviour + ImageController/ModelCatalog, which never used the safe variant.
            $resp = wp_remote_post($provider->endpoint(), [
                'timeout'     => self::http_timeout(),
                'data_format' => 'body',
                'headers'     => $headers,
                'body'        => wp_json_encode($body),
            ]);

            if (is_wp_error($resp)) {
                throw new \Exception($resp->get_error_message()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught internally, never echoed
            }
            $decoded = json_decode((string) wp_remote_retrieve_body($resp));

            // Providers do not agree on the shape of a failure. OpenAI answers with an object
            // carrying `error`; Google answers with an ARRAY containing one — and the old check
            // only looked at objects, so a Gemini 400 fell straight through both branches below
            // and came back as {"choices":[]}: success-shaped, empty, and silent. That is what the
            // uninstall queue means by "the AI returned an empty response".
            $error = self::error_from($decoded);
            if ($error !== null) {
                throw new \Exception((string) wp_json_encode(self::with_transport($error, $resp))); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught internally, never echoed
            }

            $code = (int) wp_remote_retrieve_response_code($resp);
            if ($code < 200 || $code >= 300) {
                // phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught in attempt(), never echoed
                throw new \Exception(sprintf(
                    /* translators: %d: HTTP status code returned by the AI provider */
                    __('The provider answered with HTTP %d and no explanation.', 'ai-content-generation'),
                    $code
                ));
                // phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }

            if (!is_object($decoded) || !isset($decoded->choices[0])) {
                throw new \Exception(__('The provider accepted the request and returned no content.', 'ai-content-generation')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught in attempt(), never echoed
            }

            // A choice can arrive with nothing in it. A thinking model that spends the whole budget
            // on reasoning returns exactly that: one well-formed choice whose content is empty, with
            // finish_reason "length". Checking only that a choice EXISTS let those through as a
            // success and wrote an empty section into the article.
            $choice = $decoded->choices[0];
            $text   = (string) ($choice->message->content ?? $choice->text ?? '');
            if (trim($text) === '') {
                throw new \Exception(__('The provider accepted the request and returned no content.', 'ai-content-generation')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught in attempt(), never echoed
            }

            $combined->choices[] = $choice;
        }

        return $combined;
    }

    /**
     * Anthropic Claude — /messages, mapped into the OpenAI-style choices shape.
     *
     * @param array<string, mixed> $args
     */
    private static function request_claude($provider, string $prompt, int $number, array $args): object
    {
        $headers = [
            'Content-Type'      => 'application/json',
            'x-api-key'         => $provider->apiKey(),
            'anthropic-version' => '2023-06-01',
        ];

        $combined = new \stdClass();
        $combined->choices = [];

        for ($i = 0; $i < $number; $i++) {
            $prefix = $number > 1 ? (self::VARIATIONS[$i % count(self::VARIATIONS)] ?? '') : '';

            $body = [
                'model'      => $provider->model(),
                'max_tokens' => (int) $args['max_tokens'],
                'messages'   => [[
                    'role'    => 'user',
                    'content' => "{$prefix}{$prompt}\n\n" . self::CLOSING . ' ' . self::standing_instructions($args),
                ]],
                'temperature' => min(1.0, (float) $args['temperature'] + ($i * 0.1)),
            ];

            // Claude has no frequency_penalty or presence_penalty, and this body never passes the
            // branch that would have set them, so the two variety sliders did nothing here either.
            // Anthropic takes a top-level `system` string; put the same request there.
            $asked = self::variety_instruction();
            if ('' !== $asked) {
                $body['system'] = $asked;
            }

            // Trusted first-party API endpoint (hardcoded per provider) — use wp_remote_post, NOT the
            // "safe" variant. wp_safe_remote_post runs wp_http_validate_url(), which rejects these on
            // some hosts (DNS/IP validation) and surfaces as "A valid URL was not provided." Matches
            // the legacy behaviour + ImageController/ModelCatalog, which never used the safe variant.
            $resp = wp_remote_post($provider->endpoint(), [
                'timeout'     => self::http_timeout(),
                'data_format' => 'body',
                'headers'     => $headers,
                'body'        => wp_json_encode($body),
            ]);

            if (is_wp_error($resp)) {
                throw new \Exception($resp->get_error_message()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught internally, never echoed
            }
            $decoded = json_decode((string) wp_remote_retrieve_body($resp));
            if (is_object($decoded) && isset($decoded->error)) {
                throw new \Exception((string) wp_json_encode(self::with_transport((object) $decoded->error, $resp))); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught internally, never echoed
            }

            $code = (int) wp_remote_retrieve_response_code($resp);
            if ($code < 200 || $code >= 300) {
                // phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught in attempt(), never echoed
                throw new \Exception(sprintf(
                    /* translators: %d: HTTP status code returned by the AI provider */
                    __('The provider answered with HTTP %d and no explanation.', 'ai-content-generation'),
                    $code
                ));
                // phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }

            // Claude returns an array of blocks, and with extended thinking on the first one is the
            // thinking, not the answer. Reading only block 0 and defaulting to '' turned both that
            // and a genuinely empty reply into a silent success — the same fault that made a Gemini
            // failure invisible.
            $text = '';
            foreach ((array) ($decoded->content ?? []) as $block) {
                if (isset($block->text) && trim((string) $block->text) !== '') {
                    $text = (string) $block->text;
                    break;
                }
            }
            if (trim($text) === '') {
                throw new \Exception(__('The provider accepted the request and returned no content.', 'ai-content-generation')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught in attempt(), never echoed
            }

            $combined->choices[] = (object) ['message' => (object) ['content' => $text]];
        }

        return $combined;
    }

    /**
     * Resolve the model to use, falling back to a configured provider's default when the requested
     * model's provider has no key (port of legacy wpwand_get_validated_model()).
     */
    private static function validated_model(string $requested): string
    {
        if ($requested === '') {
            $requested = (string) get_option('wpwand_model', '');
        }

        if ($requested !== '' && ProviderFactory::forModel($requested)->isConfigured()) {
            return $requested;
        }

        // Either nothing is selected yet, or the selected model's provider has no key. Take the
        // first provider that does have one and start it on its default model.
        foreach (self::PROVIDER_ORDER as $provider) {
            if (ProviderFactory::forProvider($provider)->isConfigured()) {
                return self::default_model($provider);
            }
        }

        // Nothing configured at all — let it fail with a clear provider error.
        return $requested !== '' ? $requested : self::DEFAULT_MODEL['openai'];
    }

    /**
     * The model to start a provider on when the user hasn't picked one.
     *
     * The curated id is a preference, not a promise: it is checked against the models the account
     * actually has before being used, so a retired id can't strand someone on a model that 404s.
     * If the catalogue can't be reached (no key yet, provider down) the curated id still stands —
     * which is all the old hardcoded map ever did.
     *
     * Public so {@see Failover} can start a stand-in provider on the same model this would have
     * picked, rather than keeping a second copy of DEFAULT_MODEL that goes stale on its own schedule.
     */
    public static function default_model(string $provider): string
    {
        $curated = self::DEFAULT_MODEL[$provider] ?? self::DEFAULT_MODEL['openai'];

        $available = array_column(ModelCatalog::for_provider($provider), 'value');
        if (empty($available)) {
            return $curated;
        }
        if (in_array($curated, $available, true)) {
            return $curated;
        }

        // Curated id is gone. A free model is the best possible landing spot, so take one if the
        // provider offers any — only OpenRouter does today.
        foreach (ModelCatalog::for_provider($provider) as $model) {
            if (!empty($model['free'])) {
                return (string) $model['value'];
            }
        }

        // Otherwise prefer a small/fast model over whatever sorts first — OpenRouter alone lists
        // hundreds, and landing a new user on the most expensive one is a bad first bill.
        foreach ($available as $value) {
            if (preg_match('/(mini|flash|fast|haiku|nano|small|lite)/i', (string) $value)) {
                return (string) $value;
            }
        }

        return (string) $available[0];
    }

    /**
     * How long to wait on a provider request, in seconds.
     *
     * Both request builders go through here so there is one number to change and one filter to
     * override it with.
     */
    private static function http_timeout(): int
    {
        /**
         * Filter how long a provider request may take before it is abandoned.
         *
         * @param int $seconds Defaults to {@see self::HTTP_TIMEOUT}.
         */
        return max(1, (int) apply_filters('wpwand_http_timeout', self::HTTP_TIMEOUT));
    }

    /**
     * The saved output-token budget, or null when the field was never set.
     *
     * The settings schema stores a cleared field as '' (int_empty) and (int) '' is 0, which went out
     * as max_tokens: 0 — so treat empty as "not set" and let auto_max_tokens() size it instead.
     */
    private static function saved_max_tokens(): ?int
    {
        $saved = get_option('wpwand_max_tokens', '');

        return ('' === $saved || null === $saved) ? null : (int) $saved;
    }

    /**
     * The two variety sliders, said in words, for providers that will not take them as numbers.
     *
     * frequency_penalty leans on how OFTEN a word has been used; presence_penalty only asks
     * whether it has appeared at all, which is why one is about wording and the other about
     * subject. Both run -2 to 2 and both sit at 0 by default, so a site that never touched them
     * gets no extra instruction and writes exactly as it does today.
     */
    private static function variety_instruction(): string
    {
        $word  = (float) get_option('wpwand_frequency', 0);
        $topic = (float) get_option('wpwand_presence_penalty', 0);
        $lines = [];

        if ($word >= 0.6) {
            $lines[] = $word >= 1.4
                ? 'Do not lean on the same words and phrases. Reach for a different way of saying it each time.'
                : 'Vary your wording. Avoid repeating the same phrases.';
        } elseif ($word <= -0.6) {
            $lines[] = 'Keep the wording consistent. Use the same terms for the same things rather than finding synonyms.';
        }

        if ($topic >= 0.6) {
            $lines[] = $topic >= 1.4
                ? 'Keep moving forward. Every section must add something new rather than restating an earlier point.'
                : 'Move the piece along. Do not circle back over ground you have already covered.';
        } elseif ($topic <= -0.6) {
            $lines[] = 'Stay close to the central subject rather than branching into new ones.';
        }

        return implode(' ', $lines);
    }

    /** Port of wpwangd_get_max_token(): trim max_tokens so prompt+output stays within ~4000. */
    private static function auto_max_tokens(string $command, string $model): int
    {
        $configured = self::saved_max_tokens() ?? self::DEFAULT_MAX_TOKENS;
        if ($model === 'gpt-3.5-turbo-16k') {
            return $configured;
        }

        $prompt_tokens = (int) round(str_word_count($command) * 1.2);
        $excess        = ($prompt_tokens + $configured) - 4000;
        if ($excess > 0) {
            $configured -= $excess;
        }

        return max(256, $configured); // never go to (or below) zero
    }
}
