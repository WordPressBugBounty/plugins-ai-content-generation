<?php

namespace WPWand\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WPWand\Data\History;
use WPWand\Generation\ErrorFormatter;
use WPWand\Generation\Prompt;

/**
 * POST /wpwand/v1/generate — run a content template.
 *
 * A structured-JSON port of the legacy `wpwand_request` admin-ajax handler: it builds the
 * same field map, performs the same {placeholder} substitution, and delegates to the
 * battle-tested \WPWand\Generation\Generator::generate() (which already routes across OpenAI / Claude
 * / DeepSeek / OpenRouter). The difference is the response shape — clean JSON with the raw
 * text per result, instead of pre-baked HTML. The React client renders.
 */
final class GenerateController extends AbstractController
{
    protected string $rest_base = 'generate';

    public function register_routes(): void
    {
        register_rest_route(
            $this->rest_namespace,
            '/' . $this->rest_base,
            [
                ['methods' => 'POST', 'callback' => [$this, 'generate'], 'permission_callback' => [$this, 'can_use']],
            ]
        );
    }

    public function generate(WP_REST_Request $request): WP_REST_Response
    {
        if (!class_exists('WPWand\Generation\Generator')) {
            return new WP_REST_Response(
                ['error' => __('Generator is unavailable. Check that an API key is configured.', 'wp-wand')],
                503
            );
        }

        if ((string) $request->get_param('prompt') === '') {
            return new WP_REST_Response(['error' => __('This template has no prompt (Pro template?).', 'wp-wand')], 400);
        }

        // Shared with the streaming endpoint so both build the IDENTICAL command.
        $built    = Prompt::build($request);
        $markdown = $built['markdown'];

        $content = \WPWand\Generation\Generator::generate($built['command'], $built['number'], $built['args']);

        if (is_object($content) && isset($content->error)) {
            $message = ErrorFormatter::humanize($content->error, __('Generation failed.', 'wp-wand'));
            return new WP_REST_Response(['error' => $message], 200);
        }

        $results = [];
        if (is_object($content) && isset($content->choices)) {
            foreach ($content->choices as $choice) {
                if (isset($choice->message->content)) {
                    $results[] = (string) $choice->message->content;
                } elseif (isset($choice->text)) {
                    $results[] = (string) $choice->text;
                }
            }
        }

        if (empty($results)) {
            return new WP_REST_Response(['error' => __('No response from the AI. Please try again.', 'wp-wand')], 200);
        }

        // Record the generation in History (restores legacy behaviour). Store the full AI object so
        // every result is recoverable, keyed by the template name the client sent.
        $template = sanitize_text_field((string) $request->get_param('template_name'));
        if ($template === '') {
            $template = __('Custom', 'wp-wand');
        }
        History::record($template, $this->history_info($request), $content);

        return new WP_REST_Response(['results' => $results, 'markdown' => $markdown], 200);
    }

    /**
     * The request fields worth keeping for history (skip the raw prompt template + internal flags).
     *
     * @return array<string, mixed>
     */
    private function history_info(WP_REST_Request $request): array
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_params();
        }
        unset($params['prompt'], $params['markdown']);
        return $params;
    }
}
