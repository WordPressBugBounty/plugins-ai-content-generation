<?php

namespace WPWand\Generation\Providers;

/**
 * OpenRouter — OpenAI-compatible, but the selected model carries an 'oprtr-' prefix that must be
 * stripped before it hits the wire, and OpenRouter asks integrators to send HTTP-Referer / X-Title
 * so traffic is attributed to the site (the legacy common request omitted these).
 */
final class OpenRouterProvider extends AbstractProvider
{
    public function id(): string
    {
        return 'openrouter';
    }

    public function label(): string
    {
        return 'OpenRouter';
    }

    public function endpoint(): string
    {
        return 'https://openrouter.ai/api/v1/chat/completions';
    }

    protected function keyOption(): string
    {
        return 'wpwand_openrouter_api_key';
    }

    public function model(): string
    {
        return str_replace('oprtr-', '', $this->rawModel);
    }

    public function headers(): array
    {
        return array_merge(parent::headers(), [
            'HTTP-Referer: ' . home_url(),
            'X-Title: ' . wp_strip_all_tags((string) get_bloginfo('name')),
        ]);
    }
}
