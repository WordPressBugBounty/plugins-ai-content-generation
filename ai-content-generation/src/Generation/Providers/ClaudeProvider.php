<?php

namespace WPWand\Generation\Providers;

/**
 * Anthropic Claude (/v1/messages). Auth and request/stream shape differ from OpenAI: the key goes
 * in x-api-key, an anthropic-version header is required, and max_tokens is mandatory.
 *
 * Claude is kept non-streamable: the browser SSE reader parses OpenAI's `choices[].delta.content`
 * shape, whereas Anthropic streams a different event format. Marking it not-streamable makes the
 * client fall back to the normal /generate endpoint (which already handles Claude correctly). The
 * Anthropic-shaped streamBody() is provided so a future client-side parser can switch this on.
 */
final class ClaudeProvider extends AbstractProvider
{
    private const MAX_TOKENS = 4096;

    public function id(): string
    {
        return 'claude';
    }

    public function label(): string
    {
        return 'Claude';
    }

    public function endpoint(): string
    {
        return 'https://api.anthropic.com/v1/messages';
    }

    protected function keyOption(): string
    {
        return 'wpwand_claude_api_key';
    }

    public function supportsStreaming(): bool
    {
        return false;
    }

    public function headers(): array
    {
        return [
            'Content-Type: application/json',
            'x-api-key: ' . $this->apiKey(),
            'anthropic-version: 2023-06-01',
        ];
    }

    public function streamBody(string $prompt, float $temperature): array
    {
        return [
            'model'       => $this->model(),
            'max_tokens'  => self::MAX_TOKENS,
            'messages'    => [['role' => 'user', 'content' => $prompt]],
            'temperature' => $temperature,
            'stream'      => true,
        ];
    }
}
