<?php

namespace WPWand\Rest\Controllers;

use WPWand\Rest\RestServiceProvider;

/**
 * Base class for wpwand/v1 REST controllers.
 */
abstract class AbstractController
{
    protected string $rest_namespace = RestServiceProvider::REST_NAMESPACE;

    /**
     * The route segment, e.g. 'settings' for /wpwand/v1/settings.
     */
    protected string $rest_base = '';

    abstract public function register_routes(): void;

    /**
     * Capability gate. Matches the legacy plugin, which gates on edit_posts.
     */
    public function can_use(): bool
    {
        return current_user_can('edit_posts');
    }
}
