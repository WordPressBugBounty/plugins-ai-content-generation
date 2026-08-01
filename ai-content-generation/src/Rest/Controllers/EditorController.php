<?php

namespace WPWand\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;

/**
 * POST /wpwand/v1/editor — run a single free-form prompt and return plain text.
 *
 * Backs the Gutenberg toolbar features: "enhance selected text" (Summarize/Expand/…) and
 * "Ask AI to write anything". A JSON port of the legacy wpwand_editor_request /
 * wpwand_only_prompt handlers; delegates to \WPWand\Generation\Generator::generate().
 */
final class EditorController extends AbstractController
{
    protected string $rest_base = 'editor';

    public function register_routes(): void
    {
        register_rest_route(
            $this->rest_namespace,
            '/' . $this->rest_base,
            [
                ['methods' => 'POST', 'callback' => [$this, 'run'], 'permission_callback' => [$this, 'can_use']],
            ]
        );
    }

    public function run(WP_REST_Request $request): WP_REST_Response
    {
        if (!class_exists('WPWand\Generation\Generator')) {
            return new WP_REST_Response(['error' => __('Generator is unavailable. Check that an API key is configured.', 'wp-wand')], 503);
        }

        $prompt = trim((string) $request->get_param('prompt'));
        if ($prompt === '') {
            return new WP_REST_Response(['error' => __('Empty prompt.', 'wp-wand')], 400);
        }

        $content = \WPWand\Generation\Generator::generate($prompt);

        if (is_object($content) && isset($content->error)) {
            $msg = isset($content->error->message) ? (string) $content->error->message : __('Generation failed.', 'wp-wand');
            return new WP_REST_Response(['error' => $msg], 200);
        }

        $text = '';
        if (is_object($content) && isset($content->choices)) {
            foreach ($content->choices as $choice) {
                if (isset($choice->message->content)) {
                    $text = (string) $choice->message->content;
                    break;
                }
                if (isset($choice->text)) {
                    $text = (string) $choice->text;
                    break;
                }
            }
        }

        if ($text === '') {
            return new WP_REST_Response(['error' => __('No response from the AI. Please try again.', 'wp-wand')], 200);
        }

        return new WP_REST_Response(['text' => $text], 200);
    }
}
