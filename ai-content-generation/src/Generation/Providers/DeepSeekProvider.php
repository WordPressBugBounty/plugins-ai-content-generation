<?php

namespace WPWand\Generation\Providers;

/**
 * DeepSeek — OpenAI-compatible chat/completions, so it inherits the base behaviour wholesale.
 */
final class DeepSeekProvider extends AbstractProvider
{
    public function id(): string
    {
        return 'deepseek';
    }

    public function label(): string
    {
        return 'DeepSeek';
    }

    public function endpoint(): string
    {
        return 'https://api.deepseek.com/v1/chat/completions';
    }

    protected function keyOption(): string
    {
        return 'wpwand_deepseek_api_key';
    }
}
