<?php

namespace WPWand\Generation;

use WPWand\Generation\Providers\ProviderFactory;
use WPWand\Generation\Providers\ProviderInterface;

/**
 * Thin back-compat facade over the native provider classes (see src/Generation/Providers/).
 *
 * Existing callers expect a plain config array; this flattens a {@see ProviderInterface} into the
 * legacy {source, endpoint, key, model, streamable} shape. New code should prefer ProviderFactory
 * and the provider object directly (it also exposes per-provider headers + stream body).
 */
final class Provider
{
    /** @return array{source:string, endpoint:string, key:string, model:string, streamable:bool} */
    public static function active(): array
    {
        return self::toArray(ProviderFactory::active());
    }

    /** @return array{source:string, endpoint:string, key:string, model:string, streamable:bool} */
    public static function forModel(string $model): array
    {
        return self::toArray(ProviderFactory::forModel($model));
    }

    /** Can the active provider stream on this host (streamable + key set + cURL)? */
    public static function can_stream(): bool
    {
        $p = ProviderFactory::active();

        return $p->supportsStreaming() && $p->isConfigured() && function_exists('curl_init');
    }

    private static function toArray(ProviderInterface $p): array
    {
        return [
            'source'     => $p->id(),
            'endpoint'   => $p->endpoint(),
            'key'        => $p->apiKey(),
            'model'      => $p->model(),
            'streamable' => $p->supportsStreaming(),
        ];
    }
}
