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

    /**
     * Administrator-only routes. Named apart from ReviewController's own can_manage(), which
     * predates this and answers a plain bool.
     *
     * Returns a WP_Error rather than false on purpose. A bare `false` gives the caller
     * rest_forbidden's generic "you are not allowed to do that", which tells somebody who just
     * pressed a button nothing about why it did not work. The message here is the one they see.
     *
     * @return true|\WP_Error
     */
    public function require_admin()
    {
        if (current_user_can('manage_options')) {
            return true;
        }

        return new \WP_Error(
            'wpwand_admin_only',
            __(
                'Only an administrator can switch a paused provider back on. It was paused because its account stopped working, and turning it on again starts spending money on that account.',
                'ai-content-generation'
            ),
            ['status' => 403]
        );
    }
}
