<?php

namespace WPWand\Rest;

/**
 * Holds the REST namespace shared by all controllers.
 *
 * Controllers are no longer registered here — each feature {@see \WPWand\Modules\Module} registers
 * its own controllers on rest_api_init, so disabling a module also disables its endpoints.
 */
final class RestServiceProvider
{
    public const REST_NAMESPACE = 'wpwand/v1';
}
