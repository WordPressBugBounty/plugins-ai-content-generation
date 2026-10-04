<?php

namespace WPWand\Generation\Providers;

/**
 * Resolves a model id to its provider — the single source of truth for routing, following the
 * legacy wpwand_api_source() keyword match ('claude' / 'deepseek' / 'gemini' / 'oprtr', else OpenAI).
 *
 * One deliberate improvement over legacy order: the 'oprtr-' prefix is checked FIRST. It is an
 * explicit, purpose-built marker for OpenRouter, which itself hosts deepseek/claude/gemini/etc.
 * models — so 'oprtr-deepseek/deepseek-chat' and 'oprtr-google/gemini-2.5-pro' must route to
 * OpenRouter, not to deepseek.com or Google. Legacy matched 'deepseek'/'claude' before 'oprtr' and
 * would misroute those. The prefix never appears on a native model, so this is safe and strictly
 * more correct.
 *
 * Every keyword below therefore has to stay BELOW the 'oprtr' check, 'gemini' included: OpenRouter
 * ids carry the vendor in the path, so 'gemini' matches an OpenRouter model id as readily as a
 * Google one.
 */
final class ProviderFactory
{
    /** Build the provider that owns the given model. */
    public static function forModel(string $model): ProviderInterface
    {
        if (strpos($model, 'oprtr') !== false) {
            return new OpenRouterProvider($model);
        }
        if (strpos($model, 'claude') !== false) {
            return new ClaudeProvider($model);
        }
        if (strpos($model, 'deepseek') !== false) {
            return new DeepSeekProvider($model);
        }
        if (strpos($model, 'gemini') !== false) {
            return new GeminiProvider($model);
        }

        return new OpenAiProvider($model);
    }

    /**
     * Build a provider from its id — 'openai' | 'claude' | 'deepseek' | 'gemini' | 'openrouter'.
     *
     * Use this wherever the provider is already known. Asking forModel() with an invented model
     * string makes the answer depend on that string carrying the right marker, and an id that reads
     * correctly to a human can still route wrong: 'openrouter/…' does not contain 'oprtr', so it
     * lands on OpenAI.
     */
    public static function forProvider(string $provider, string $model = ''): ProviderInterface
    {
        switch ($provider) {
            case 'openrouter':
                return new OpenRouterProvider($model);
            case 'claude':
                return new ClaudeProvider($model);
            case 'deepseek':
                return new DeepSeekProvider($model);
            case 'gemini':
                return new GeminiProvider($model);
            default:
                return new OpenAiProvider($model);
        }
    }

    /** The provider for the currently selected model (wpwand_model option). */
    public static function active(): ProviderInterface
    {
        return self::forModel((string) get_option('wpwand_model', 'chatgpt-4o-latest'));
    }
}
