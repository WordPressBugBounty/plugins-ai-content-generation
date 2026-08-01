<?php

namespace WPWand\Generation\Providers;

/**
 * Shared base for the OpenAI-compatible providers (OpenAI, DeepSeek, OpenRouter) and Claude.
 *
 * Subclasses declare the endpoint, label, id and the option that holds the key; the OpenAI-style
 * defaults here (Bearer auth, chat/completions body) cover everything except Claude, which
 * overrides headers / streamBody and opts out of streaming.
 */
abstract class AbstractProvider implements ProviderInterface
{
    /** The selected model, exactly as stored in the wpwand_model option (prefixes intact). */
    protected string $rawModel;

    public function __construct(string $model)
    {
        $this->rawModel = $model;
    }

    /** The option key that stores this provider's API key. */
    abstract protected function keyOption(): string;

    public function apiKey(): string
    {
        return (string) get_option($this->keyOption(), '');
    }

    public function model(): string
    {
        return $this->rawModel;
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== '';
    }

    public function supportsStreaming(): bool
    {
        return true;
    }

    public function headers(): array
    {
        return [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey(),
        ];
    }

    public function streamBody(string $prompt, float $temperature): array
    {
        return [
            'model'       => $this->model(),
            'messages'    => [['role' => 'user', 'content' => $prompt]],
            'temperature' => $temperature,
            'stream'      => true,
        ];
    }
}
