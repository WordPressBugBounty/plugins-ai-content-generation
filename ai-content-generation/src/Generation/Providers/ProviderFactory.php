<?php

namespace WPWand\Generation\Providers;

/**
 * Resolves a model id to its provider — the single source of truth for routing, following the
 * legacy wpwand_api_source() keyword match ('claude' / 'deepseek' / 'oprtr', else OpenAI).
 *
 * One deliberate improvement over legacy order: the 'oprtr-' prefix is checked FIRST. It is an
 * explicit, purpose-built marker for OpenRouter, which itself hosts deepseek/claude/etc. models —
 * so 'oprtr-deepseek/deepseek-chat' must route to OpenRouter, not to deepseek.com. Legacy matched
 * 'deepseek'/'claude' before 'oprtr' and would misroute those. The prefix never appears on a
 * native model, so this is safe and strictly more correct.
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

        return new OpenAiProvider($model);
    }

    /** The provider for the currently selected model (wpwand_model option). */
    public static function active(): ProviderInterface
    {
        return self::forModel((string) get_option('wpwand_model', 'chatgpt-4o-latest'));
    }
}
