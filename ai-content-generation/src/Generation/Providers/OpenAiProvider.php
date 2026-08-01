<?php

namespace WPWand\Generation\Providers;

/**
 * OpenAI (chat/completions). The default provider when the model matches no other vendor.
 */
final class OpenAiProvider extends AbstractProvider
{
    public function id(): string
    {
        return 'openai';
    }

    public function label(): string
    {
        return 'OpenAI';
    }

    public function endpoint(): string
    {
        return 'https://api.openai.com/v1/chat/completions';
    }

    protected function keyOption(): string
    {
        return 'wpwand_api_key';
    }
}
