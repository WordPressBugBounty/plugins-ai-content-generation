<?php

namespace WPWand\Generation\Providers;

/**
 * One AI provider (OpenAI, Claude, DeepSeek, OpenRouter).
 *
 * A provider owns everything that differs between vendors: the endpoint, which option holds the
 * API key, how the wire model id is derived from the selected model, the HTTP headers, whether it
 * can stream to the browser, and the shape of a streaming request body. Routing lives in
 * {@see ProviderFactory}; resolve a provider with ProviderFactory::forModel() / ::active().
 *
 * Scope: these classes are the single source of truth for provider routing/config and power the
 * live-streaming endpoint. The non-streaming bulk path still runs through the legacy
 * wpwand_generate_ai_content() (battle-tested across installs) — intentionally left untouched.
 */
interface ProviderInterface
{
    /** Stable machine id, e.g. 'openai'. Matches the legacy wpwand_api_source() value. */
    public function id(): string;

    /** Human label for settings / diagnostics, e.g. 'OpenAI'. */
    public function label(): string;

    /** Chat/completions (or messages) endpoint URL. */
    public function endpoint(): string;

    /** The configured API key (empty string when unset). */
    public function apiKey(): string;

    /** The wire model id sent to the provider (vendor prefixes such as 'oprtr-' stripped). */
    public function model(): string;

    /** True when an API key is present for this provider. */
    public function isConfigured(): bool;

    /** True when this provider can stream to the browser with the OpenAI-compatible SSE the client parses. */
    public function supportsStreaming(): bool;

    /** HTTP headers for a chat / streaming request, as an array of "Key: value" strings. */
    public function headers(): array;

    /** Request body for a streaming chat completion. */
    public function streamBody(string $prompt, float $temperature): array;
}
