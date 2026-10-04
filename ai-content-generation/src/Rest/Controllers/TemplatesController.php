<?php

namespace WPWand\Rest\Controllers;

use WPWand\Data\Brand;
use WPWand\Data\Languages;
use WP_REST_Request;
use WP_REST_Response;

/**
 * GET /wpwand/v1/templates — the content templates for the assistant.
 *
 * Reads the wpwand_data option — one template list, plus the user's custom prompts — and
 * normalizes each template's comma-separated `fields` string into structured
 * field descriptors the React form can render directly. No dependency on legacy init —
 * the option is read directly so this is deterministic in any request context.
 */
final class TemplatesController extends AbstractController
{
    protected string $rest_base = 'templates';

    /**
     * Tone choices (mirrors the legacy assistant select).
     *
     * @var string[]
     */
    private const TONES = [
        'friendly', 'helpful', 'informative', 'aggressive', 'professional', 'Formal',
        'Informal', 'Conversational', 'Persuasive', 'Witty', 'Descriptive', 'Expository',
        'Humorous', 'Inspirational', 'Funny', 'Poetic', 'Technical', 'Argumentative',
        'Instructional', 'Sarcastic', 'Urgent', 'Optimistic',
    ];

    /**
     * Maps a legacy `fields` token to a structured input descriptor. The `key` is the
     * param name /generate expects (matching the legacy POST/field names).
     *
     * @return array<string, array<string, mixed>>
     */
    private function field_map(): array
    {
        return [
            'Topic'                 => ['key' => 'topic', 'type' => 'text', 'label' => 'Topic', 'placeholder' => 'Write in detail about your topic'],
            'Name'                  => ['key' => 'product_name', 'type' => 'text', 'label' => 'Name', 'placeholder' => 'Write your product name'],
            'Comment'               => ['key' => 'comment', 'type' => 'text', 'label' => 'Comment'],
            'Question'              => ['key' => 'question', 'type' => 'text', 'label' => 'Question'],
            'Subject'               => ['key' => 'subject', 'type' => 'text', 'label' => 'Subject'],
            'Product 1'             => ['key' => 'product_1', 'type' => 'text', 'label' => 'Product 1'],
            'Product 2'             => ['key' => 'product_2', 'type' => 'text', 'label' => 'Product 2'],
            'Description'           => ['key' => 'description', 'type' => 'text', 'label' => 'Description', 'placeholder' => 'Write a meaningful description for a better result'],
            'Product 1 Description' => ['key' => 'description_1', 'type' => 'text', 'label' => 'Product 1 Description'],
            'Product 2 Description' => ['key' => 'description_2', 'type' => 'text', 'label' => 'Product 2 Description'],
            'Content'               => ['key' => 'content', 'type' => 'text', 'label' => 'Content', 'placeholder' => 'Write your content'],
            'Content Text Area'     => ['key' => 'content_textarea', 'type' => 'textarea', 'label' => 'Content', 'placeholder' => 'Write your content'],
            'custom_textarea'       => ['key' => 'custom_textarea', 'type' => 'textarea', 'label' => 'Write Anything'],
            'Keywords'              => ['key' => 'keyword', 'type' => 'text', 'label' => 'Keyword to Include', 'placeholder' => 'Separate keywords with commas', 'optional' => true],
            'Tone'                  => ['key' => 'tone', 'type' => 'select', 'label' => 'Tone', 'options' => self::TONES],
            // 'Minimum Word' was never accurate — every prompt that uses this value treats it as a
            // target length, some phrased as a ceiling ("must not exceed"), none as a floor. This is
            // now the exact number the prompt asks the model for — no hidden offset. 2000 is what a
            // One Click Blog Post run actually produced when asked for a length that fits its token
            // budget (docs/audit-2026-08-22/token-cost.md: 2,334-2,447 words). Short-form templates
            // ask the user to lower this; it's a starting point, not a fit for every template that
            // collects it.
            'Word Count'            => ['key' => 'word_limit', 'type' => 'number', 'label' => 'Word Count', 'default' => 2000],
        ];
    }

    public function register_routes(): void
    {
        register_rest_route(
            $this->rest_namespace,
            '/' . $this->rest_base,
            [
                ['methods' => 'GET', 'callback' => [$this, 'get_templates'], 'permission_callback' => [$this, 'can_use']],
            ]
        );
    }

    public function get_templates(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(
            [
                'templates' => $this->templates(),
                'tones'     => self::TONES,
                'languages'     => Languages::all(),
                'caps'          => ['pro' => \WPWand\Core\Pro::unlocked()],
                'brand'         => Brand::resolve(),
                'ai_characters' => $this->ai_characters(),
                'defaults'      => [
                    'language' => (string) get_option('wpwand_language', 'English'),
                ],
                // Live streaming (experimental). 'stream' = user toggle; 'can_stream' = the active
                // provider can actually stream here (OpenAI-compatible + key + curl). Client falls back.
                'stream'        => (bool) get_option('wpwand_stream', 0),
                'can_stream'    => \WPWand\Generation\Provider::can_stream(),
                // Images run on OpenAI only, whatever provider writes the text. Without this the
                // Image tab looks available to a Claude/DeepSeek/OpenRouter user and only fails
                // once they've written a prompt.
                'image_ready'   => get_option('wpwand_api_key', '') !== '',
            ],
            200
        );
    }

    /**
     * AI character options (Pro): the user's custom characters + the built-in ones.
     * Empty unless Pro is active.
     *
     * @return array<int, array{title: string, prompt: string}>
     */
    private function ai_characters(): array
    {
        // The Pro plugin fills this via the 'wpwand_ai_characters' filter (WPWand\Data\ProData):
        // the user's custom characters + the built-in premade ones, only when licensed. Free → [].
        $chars = apply_filters('wpwand_ai_characters', []);
        return is_array($chars) ? $chars : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function templates(): array
    {
        $data        = get_option('wpwand_data', []);
        $custom_data = get_option('wpwand_custom_data', []);

        if (!is_array($custom_data)) {
            $custom_data = [];
        }

        // One catalogue, everyone gets all of it. Templates::flatten() also accepts the old
        // {free, pro} shape, which is what a Pro licence sync still writes into this option.
        // Custom prompts first, so a user's own edit of a shipped template name wins.
        $merged = array_merge($custom_data, \WPWand\Data\Templates::flatten($data));

        $map = $this->field_map();
        $out = [];

        foreach ($merged as $title => $t) {
            if (!is_array($t)) {
                continue;
            }

            $tokens = array_filter(array_map('trim', explode(',', (string) ($t['fields'] ?? ''))));
            $specs  = [];
            foreach ($tokens as $token) {
                if (isset($map[$token])) {
                    $specs[] = array_merge(['token' => $token], $map[$token]);
                }
            }

            $out[] = [
                'id'                => (string) ($t['title'] ?? $title),
                'title'             => (string) ($t['title'] ?? $title),
                'description'       => (string) ($t['description'] ?? ''),
                'prompt'            => (string) ($t['prompt'] ?? ''),
                'is_pro'            => !empty($t['is_pro']),
                'markdown'          => !empty($t['markdown']),
                'number_of_results' => array_key_exists('number_of_results', $t) ? (bool) $t['number_of_results'] : true,
                'point_of_view'     => array_key_exists('point_of_view', $t) ? (bool) $t['point_of_view'] : true,
                'fields'            => $specs,
            ];
        }

        return $out;
    }
}
