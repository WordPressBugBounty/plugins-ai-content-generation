<?php

namespace WPWand\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WPWand\Data\History;
use WPWand\Generation\Completeness;
use WPWand\Generation\ErrorFormatter;
use WPWand\Generation\Generator;
use WPWand\Generation\HouseStyle;
use WPWand\Generation\JobRunner;
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

    /**
     * Words to aim for when a long-form template collects no Word Count.
     *
     * Blog Post Writer has no such field; without a number the runner would fall back to writing the
     * whole post in one block, which is the request shape this change exists to stop making.
     */
    private const LONG_FORM_WORDS = 1500;

    /** Characters of user-typed background carried into each section prompt. */
    private const MAX_CONTEXT = 2000;

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
                ['error' => __('Nothing can write yet. Add an API key in Settings first.', 'ai-content-generation')],
                503
            );
        }

        if ((string) $request->get_param('prompt') === '') {
            return new WP_REST_Response(['error' => __('This template has no prompt (Pro template?).', 'ai-content-generation')], 400);
        }

        // Shared with the streaming endpoint so both build the IDENTICAL command.
        $built    = Prompt::build($request);
        $markdown = $built['markdown'];

        $template = sanitize_text_field((string) $request->get_param('template_name'));
        if ($template === '') {
            $template = __('Custom', 'ai-content-generation');
        }

        // A long-form template is written section by section instead of in one request. One request
        // for a whole article is the thing that gets cut off; this route has no request big enough
        // to be cut off, and it costs the same money because the extra tokens are input.
        if (JobRunner::handles_template($template)) {
            return $this->long_form($request, $template, $built);
        }

        $content = \WPWand\Generation\Generator::generate($built['command'], $built['number'], $built['args']);

        if (is_object($content) && isset($content->error)) {
            $message = ErrorFormatter::humanize($content->error, __('Generation failed.', 'ai-content-generation'));
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

        // A provider can answer 200 with an empty string — DeepSeek's reasoning models do it when the
        // whole token budget went to reasoning_content. empty() is false for [''], so filter first or
        // the user gets a blank result panel and no explanation.
        $results = array_values(array_filter($results, static fn($r) => trim((string) $r) !== ''));

        if (empty($results)) {
            return new WP_REST_Response(['error' => __('The AI returned an empty response. Try again, or ask for something shorter.', 'ai-content-generation')], 200);
        }

        // Long-form output gets judged before it is filed. A body that stops mid-sentence, or that
        // names more sections in its own contents list than it wrote, is a failure — filing it as a
        // finished draft is what put a Complete badge on half an article.
        $verdict = $markdown ? Completeness::check($results[0]) : null;

        // Record the generation in History (restores legacy behaviour). Store the full AI object so
        // every result is recoverable, keyed by the template name the client sent. The Assistant
        // runs inside the post editor, so it files under the same source the block-editor path does.
        History::record(
            $template,
            $this->history_info($request, $verdict),
            $content,
            History::title_from($results[0]),
            History::SOURCE_EDITOR
        );

        $payload = ['results' => $results, 'markdown' => $markdown] + $this->shortfall($verdict);

        // If this generation finished on a different provider than the one the user picked, say so.
        // Failover::tag() has always attached the explanation and this endpoint used to drop it, so
        // an Assistant-panel or Classic-editor generation was billed to a second account in silence.
        // Bulk and Automated Posts have their own notice; this is the same fact, for everyone else.
        if (is_object($content) && isset($content->wpwand_failover)) {
            $payload['failover'] = $content->wpwand_failover;
        }

        return new WP_REST_Response($payload, 200);
    }

    /**
     * Write a long-form template outline-first, one request per section.
     *
     * The section count comes from the word count the form collected, exactly as the queue derives
     * it, and the contents list is assembled from the headings that were actually written — so it
     * can no longer name 25 sections against the 6 that turned up. Where the user typed their own
     * outline (Blog Post Writer's Content Text Area) that outline is followed instead of asking the
     * model for one.
     *
     * @param array{command: string, language: string, number: int, markdown: bool, args: array<string, mixed>} $built
     */
    private function long_form(WP_REST_Request $request, string $template, array $built): WP_REST_Response
    {
        $topic = sanitize_text_field((string) $request->get_param('topic'));
        if (trim($topic) === '') {
            return new WP_REST_Response(
                ['error' => __('Add a topic first — the article gets built around it.', 'ai-content-generation')],
                200
            );
        }

        $words = (int) $request->get_param('word_limit');

        // What the user typed into the template's own text area. Blog Post Writer calls it Content,
        // so it may be an outline or it may be a couple of lines about their business — the runner
        // decides which, and either way every section prompt gets to see it.
        $typed = trim((string) $request->get_param('content_textarea'));

        $settings = [
            'word_count'     => $words > 0 ? $words : self::LONG_FORM_WORDS,
            'tone'           => sanitize_text_field((string) $request->get_param('tone')),
            'keyword'        => sanitize_text_field((string) $request->get_param('keyword')),
            'language'       => (string) ($built['args']['language'] ?? ''),
            // The model is no longer asked for a contents list; this one is built from real headings.
            'toc_include'    => true,
            // No H1. This markdown goes into an editor where the post already has a title, and the
            // rewritten prompt tells the model not to repeat it — assemble() would have added one
            // back and made the prompt's promise false.
            'title_heading'  => false,
            'faq_include'    => true,
            // Even a 900-word article goes through the outline here, so it still gets a contents
            // list and an FAQ. Without this the runner would write it in one block and skip both.
            'force_outline'  => true,
            // Nothing retries this path, so the messages must not promise a retry.
            'sync'           => true,
            'outline_text'   => $typed,
            'context_text'   => self::clip($typed),
            // The Pro AI Character and point of view. Prompt::build() puts these on the command,
            // and this path throws the command away and writes section by section instead.
            'persona'        => sanitize_text_field((string) $request->get_param('aichar')),
            'point_of_view'  => sanitize_text_field((string) $request->get_param('point_of_view')),
            // The same house style Prompt::build() puts on a single-request markdown template, so a
            // sectioned article is held to the rules its one-shot twin is held to.
            'style_rules'    => HouseStyle::rules([
                'business' => (string) ($built['args']['biz_details'] ?? ''),
                'audience' => (string) ($built['args']['targated_customer'] ?? ''),
            ]),
            'max_tokens_cap' => self::length_limit(),
        ];

        $runner = new JobRunner();

        try {
            $text = $runner->run_article($topic, $settings);
        } catch (\Throwable $e) {
            // Nothing was written, so there is nothing to hand back. A run that failed partway
            // keeps its sections and comes back below, marked partial.
            // Already a sentence a person can read — JobRunner runs the provider envelope through
            // ErrorFormatter before it throws, and humanizing twice printed it doubled.
            $message = trim($e->getMessage());
            return new WP_REST_Response(
                ['error' => $message !== '' ? $message : __('Generation failed.', 'ai-content-generation')],
                200
            );
        }

        $verdict = Completeness::check($text);

        // A section the provider would not write is a shortfall even when what arrived reads whole.
        // The contents list still names it, so Completeness usually catches it on its own; this
        // makes sure the reason says what actually went wrong.
        $stopped = $runner->last_section_error();
        if ($stopped !== '') {
            if (Completeness::isComplete($verdict)) {
                $verdict['status'] = Completeness::PARTIAL;
                $verdict['reason'] = $stopped;
            } else {
                $verdict['reason'] = trim($verdict['reason'] . ' ' . $stopped);
            }
        }

        $response = History::response_from_text($text);

        History::record(
            $template,
            $this->history_info($request, $verdict),
            $response,
            // The topic, not the text's first heading: a long article opens on its contents list,
            // so every one of them was filed in History as "Table of Contents".
            $topic,
            History::SOURCE_EDITOR
        );

        $payload = ['results' => [$text], 'markdown' => true] + $this->shortfall($verdict);

        // Same fact the single-request branch reports: this generation finished on a provider the
        // user did not pick, so it was billed somewhere else.
        $failover = $runner->last_failover();
        if (null !== $failover) {
            $payload['failover'] = $failover;
        }

        return new WP_REST_Response($payload, 200);
    }

    /** Trim user-typed background to something a per-section prompt can carry. */
    private static function clip(string $text): string
    {
        $text = trim($text);
        if ($text === '' || mb_strlen($text) <= self::MAX_CONTEXT) {
            return $text;
        }

        return trim(mb_substr($text, 0, self::MAX_CONTEXT));
    }

    /**
     * What goes back to the client when a generation came up short.
     *
     * The text stays in `results` either way — the provider was paid for it and throwing it away
     * helps nobody. `partial` is the flag that says do not badge this one finished.
     *
     * The reason travels as `partial_reason`, never as `error`: the client treats `error` as fatal
     * and clears the results with it, which threw away the 2,334 words this whole change exists to
     * keep. `error` stays reserved for a run that produced nothing.
     *
     * @param array{status: string, reason: string, missing: array<int, string>}|null $verdict
     *
     * @return array<string, mixed>
     */
    private function shortfall(?array $verdict): array
    {
        if (null === $verdict || Completeness::isComplete($verdict)) {
            return [];
        }

        return [
            'partial_reason' => $verdict['reason'],
            'partial'        => true,
            'status'         => $verdict['status'],
        ];
    }

    /** The configured output-token ceiling. No single request in the long-form path may pass it. */
    private static function length_limit(): int
    {
        $saved = get_option('wpwand_max_tokens', '');
        $limit = ('' === $saved || null === $saved) ? Generator::DEFAULT_MAX_TOKENS : (int) $saved;

        return $limit > 0 ? $limit : Generator::DEFAULT_MAX_TOKENS;
    }

    /**
     * The request fields worth keeping for history (skip the raw prompt template + internal flags).
     *
     * A run that came up short carries its verdict here, so a History row can never present a
     * truncated article as a finished one.
     *
     * @param array{status: string, reason: string, promised: int, arrived: int, missing: array<int, string>}|null $verdict
     *
     * @return array<string, mixed>
     */
    private function history_info(WP_REST_Request $request, ?array $verdict = null): array
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_params();
        }
        unset($params['prompt'], $params['markdown']);

        if (null !== $verdict && !Completeness::isComplete($verdict)) {
            $params['wpwand_completeness'] = [
                'status'    => $verdict['status'],
                'reason'    => $verdict['reason'],
                'promised'  => $verdict['promised'],
                'arrived'   => $verdict['arrived'],
                'missing'   => $verdict['missing'],
                'sensitive' => Completeness::sensitiveMissing($verdict['missing']),
            ];
        }

        return $params;
    }
}
