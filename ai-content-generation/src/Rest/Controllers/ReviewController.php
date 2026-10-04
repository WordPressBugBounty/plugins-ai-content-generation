<?php

namespace WPWand\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WPWand\Admin\ReviewPrompt;

/**
 * /wpwand/v1/review — the answer endpoint behind the wordpress.org review ask.
 *
 * - GET  /review          → the step this user is on (the same shape PHP puts on
 *                           window.wpwandApi.review, for a screen that didn't localize it)
 * - POST /review          → record an answer: happy | unhappy | later | dismissed
 * - POST /review/clicked  → record that they opened the review page
 *
 * Gated on manage_options, not the usual edit_posts: an author who can't see the prompt has no
 * business answering it either.
 */
final class ReviewController extends AbstractController
{
    protected string $rest_base = 'review';

    public function register_routes(): void
    {
        register_rest_route($this->rest_namespace, '/' . $this->rest_base, [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'show'],
                'permission_callback' => [$this, 'can_manage'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'answer'],
                'permission_callback' => [$this, 'can_manage'],
                'args'                => [
                    'answer' => [
                        'type'     => 'string',
                        'required' => true,
                        'enum'     => ReviewPrompt::ANSWERS,
                    ],
                ],
            ],
        ]);

        register_rest_route($this->rest_namespace, '/' . $this->rest_base . '/shown', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'shown'],
                'permission_callback' => [$this, 'can_manage'],
            ],
        ]);

        register_rest_route($this->rest_namespace, '/' . $this->rest_base . '/clicked', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'clicked'],
                'permission_callback' => [$this, 'can_manage'],
            ],
        ]);
    }

    /** Only site administrators are asked, so only they can answer. */
    public function can_manage(): bool
    {
        return current_user_can('manage_options');
    }

    public function show(WP_REST_Request $request): WP_REST_Response
    {
        // Reads only. Fetching the step is not the same as showing anybody the question — the
        // component says that separately, through POST /review/shown, and that is what stamps
        // asked_at. A GET that writes is also a GET that cannot be cached or retried safely.
        return new WP_REST_Response(ReviewPrompt::state(), 200);
    }

    /** The question is on screen right now. The only honest moment to stamp asked_at. */
    public function shown(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(ReviewPrompt::mark_shown(), 200);
    }

    public function answer(WP_REST_Request $request): WP_REST_Response
    {
        $answer = (string) $request->get_param('answer');
        $state  = ReviewPrompt::record_answer($answer);

        if ($state === null) {
            return new WP_REST_Response(
                ['error' => __('That answer isn\'t one of the choices.', 'ai-content-generation')],
                400
            );
        }

        return new WP_REST_Response($state, 200);
    }

    public function clicked(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(ReviewPrompt::record_click(), 200);
    }
}
