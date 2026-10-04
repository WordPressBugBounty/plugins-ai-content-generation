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

    /**
     * What the Template column shows for a row this endpoint wrote. There is no template on this
     * path — the writer types a prompt and gets text back — and the column has to say something
     * true rather than sit empty. Stored, not computed at display time, so one string decides it.
     */
    public const TEMPLATE_LABEL = 'Custom prompt';

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
            return new WP_REST_Response(['error' => __('Nothing can write yet. Add an API key in Settings first.', 'ai-content-generation')], 503);
        }

        $prompt = trim((string) $request->get_param('prompt'));
        if ($prompt === '') {
            return new WP_REST_Response(['error' => __('Empty prompt.', 'ai-content-generation')], 400);
        }

        $content = \WPWand\Generation\Generator::generate($prompt);

        if (is_object($content) && isset($content->error)) {
            // Generator hands back the provider's error json-encoded, so it has to be unwrapped here
            // the way every other controller does it — the editor shows this string in an alert, and
            // a raw JSON blob tells the writer nothing about what actually went wrong.
            $msg = \WPWand\Generation\ErrorFormatter::humanize(
                $content->error,
                __('Generation failed. Please try again.', 'ai-content-generation')
            );
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
            return new WP_REST_Response(['error' => __('No response from the AI. Please try again.', 'ai-content-generation')], 200);
        }

        // The block editor writes text and recorded none of it, so anything written from the
        // Gutenberg toolbar was missing from History entirely. There is no template here — the
        // writer typed a prompt — so the row carries the label the Template column will show for
        // exactly this case, and the title is read out of the text.
        \WPWand\Data\History::record(
            self::TEMPLATE_LABEL,
            ['prompt' => $prompt],
            \WPWand\Data\History::response_from_text($text),
            \WPWand\Data\History::title_from($text),
            \WPWand\Data\History::SOURCE_EDITOR
        );

        return new WP_REST_Response(['text' => $text], 200);
    }
}
