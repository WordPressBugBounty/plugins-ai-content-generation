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
    /** Variation prefixes for multi-result requests (verbatim from legacy). */
    private const VARIATIONS = [
        'Provide a unique perspective on this: ',
        'Give a different take on this topic: ',
        'Approach this from another angle: ',
        'Offer an alternative view on this: ',
        'Present a fresh perspective on this: ',
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
        $model = '';
        try {
            $model = self::validated_model((string) ($args['model'] ?? ''));

            $args = wp_parse_args($args, [
                'model'             => $model,
                'language'          => get_option('wpwand_language', 'English'),
                'biz_details'       => '',
                'targated_customer' => '',
                'temperature'       => (float) get_option('wpwand_temperature', 1.0),
                'max_tokens'        => get_option('wpwand_max_tokens', null),
            ]);
            $model = (string) $args['model'];

            $prompt .= ' You must need only answer the question. Do not write any other text/explanation or multiple answer.';

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
                    __('%s API key is missing', 'wp-wand'),
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
                    ['role' => 'system', 'content' => 'You are a professional content writer. You must follow the system instructions strictly.'],
                    ['role' => 'system', 'content' => "You must write in {$args['language']}. {$args['biz_details']} {$args['targated_customer']}"],
                    ['role' => 'user', 'content' => "{$prefix}{$prompt} . You must follow this instructions strictly. Don't add any other text/explanation/multiple results. Just write the content."],
                ],
                'temperature'       => (float) $args['temperature'] + ($i * 0.1),
                'frequency_penalty' => (float) get_option('wpwand_frequency', 0),
                'presence_penalty'  => (float) get_option('wpwand_presence_penalty', 0),
            ];

            // gpt-5 family uses max_completion_tokens; everything else max_tokens (legacy parity).
            if ('gpt-5' === $model || 'gpt-5-nano' === $model) {
                $body['max_completion_tokens'] = (int) $args['max_tokens'];
            } else {
                $body['max_tokens'] = (int) $args['max_tokens'];
            }

            // Trusted first-party API endpoint (hardcoded per provider) — use wp_remote_post, NOT the
            // "safe" variant. wp_safe_remote_post runs wp_http_validate_url(), which rejects these on
            // some hosts (DNS/IP validation) and surfaces as "A valid URL was not provided." Matches
            // the legacy behaviour + ImageController/ModelCatalog, which never used the safe variant.
            $resp = wp_remote_post($provider->endpoint(), [
                'timeout'     => 120,
                'data_format' => 'body',
                'headers'     => $headers,
                'body'        => wp_json_encode($body),
            ]);

            if (is_wp_error($resp)) {
                throw new \Exception($resp->get_error_message()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught internally, never echoed
            }
            $decoded = json_decode((string) wp_remote_retrieve_body($resp));
            if (is_object($decoded) && isset($decoded->error)) {
                throw new \Exception((string) wp_json_encode($decoded->error)); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught internally, never echoed
            }
            if (is_object($decoded) && isset($decoded->choices[0])) {
                $combined->choices[] = $decoded->choices[0];
            }
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
                    'content' => "{$prefix}{$prompt} You must write in {$args['language']}. {$args['biz_details']} {$args['targated_customer']}",
                ]],
                'temperature' => min(1.0, (float) $args['temperature'] + ($i * 0.1)),
            ];

            // Trusted first-party API endpoint (hardcoded per provider) — use wp_remote_post, NOT the
            // "safe" variant. wp_safe_remote_post runs wp_http_validate_url(), which rejects these on
            // some hosts (DNS/IP validation) and surfaces as "A valid URL was not provided." Matches
            // the legacy behaviour + ImageController/ModelCatalog, which never used the safe variant.
            $resp = wp_remote_post($provider->endpoint(), [
                'timeout'     => 120,
                'data_format' => 'body',
                'headers'     => $headers,
                'body'        => wp_json_encode($body),
            ]);

            if (is_wp_error($resp)) {
                throw new \Exception($resp->get_error_message()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught internally, never echoed
            }
            $decoded = json_decode((string) wp_remote_retrieve_body($resp));
            if (is_object($decoded) && isset($decoded->error)) {
                throw new \Exception((string) wp_json_encode($decoded->error)); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught internally, never echoed
            }
            $combined->choices[] = (object) [
                'message' => (object) [
                    'content' => isset($decoded->content[0]->text) ? (string) $decoded->content[0]->text : '',
                ],
            ];
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

        $defaults = [
            'openai'     => 'chatgpt-4o-latest',
            'claude'     => 'claude-3-5-sonnet-20240620',
            'deepseek'   => 'deepseek-chat',
            'openrouter' => 'openrouter/google/gemini-flash-1.5',
        ];

        if ($requested === '') {
            foreach (['openai', 'claude', 'deepseek', 'openrouter'] as $id) {
                if (ProviderFactory::forModel($defaults[$id])->isConfigured()) {
                    return $defaults[$id];
                }
            }
            return 'chatgpt-4o-latest';
        }

        if (ProviderFactory::forModel($requested)->isConfigured()) {
            return $requested;
        }

        // Requested provider has no key — fall back to the first configured one.
        $fallbacks = [
            'openai'     => 'chatgpt-4o-latest',
            'claude'     => 'claude-opus-4-20250514',
            'deepseek'   => 'deepseek-chat',
            'openrouter' => 'oprtr-x-ai/grok-4.1-fast',
        ];
        foreach (['openai', 'claude', 'deepseek', 'openrouter'] as $id) {
            if (ProviderFactory::forModel($fallbacks[$id])->isConfigured()) {
                return $fallbacks[$id];
            }
        }

        return $requested; // nothing configured — let it fail with a clear provider error
    }

    /** Port of wpwangd_get_max_token(): trim max_tokens so prompt+output stays within ~4000. */
    private static function auto_max_tokens(string $command, string $model): int
    {
        $configured = (int) get_option('wpwand_max_tokens', 3600);
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
