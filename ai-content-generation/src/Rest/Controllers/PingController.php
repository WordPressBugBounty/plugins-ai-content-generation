<?php

namespace WPWand\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;

/**
 * GET /wpwand/v1/ping — sanity endpoint used to confirm the REST layer is wired up.
 * Useful during the rebuild for smoke tests; harmless in production.
 */
final class PingController extends AbstractController
{
    protected string $rest_base = 'ping';

    public function register_routes(): void
    {
        register_rest_route(
            $this->rest_namespace,
            '/' . $this->rest_base,
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'handle'],
                'permission_callback' => [$this, 'can_use'],
            ]
        );
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(
            [
                'ok'        => true,
                'namespace' => $this->rest_namespace,
                'time'      => current_time('mysql'),
            ],
            200
        );
    }
}
