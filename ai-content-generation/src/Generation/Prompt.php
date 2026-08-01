<?php

namespace WPWand\Generation;

use WP_REST_Request;

/**
 * Builds the final generation command from a /generate-style request — the {placeholder}
 * substitution + Pro extras (AI character, point of view, business/targeted-customer args).
 *
 * Extracted from GenerateController so the non-streaming and streaming endpoints produce the
 * IDENTICAL prompt. Behavior mirrors the legacy wpwand_api_fields_validate() / wpwand_pro_request.
 */
final class Prompt
{
    /**
     * @return array{command: string, language: string, number: int, markdown: bool, args: array<string, mixed>}
     */
    public static function build(WP_REST_Request $request): array
    {
        $template = (string) $request->get_param('prompt');
        $fields   = self::fields($request);
        $language = sanitize_text_field((string) $request->get_param('language'));
        $markdown = (bool) $request->get_param('markdown');
        $number   = (int) $fields['no_of_results'];

        $command = preg_replace_callback(
            '/\{([^}]+)\}/',
            static function ($matches) use ($fields) {
                $key = trim($matches[1]);
                return isset($fields[$key]) ? (string) $fields[$key] : '';
            },
            $template
        );

        $pov      = sanitize_text_field((string) $request->get_param('point_of_view'));
        $aichar   = sanitize_text_field((string) $request->get_param('aichar'));
        $inc_biz  = filter_var($request->get_param('inc_biz'), FILTER_VALIDATE_BOOLEAN);
        $inc_tgdc = filter_var($request->get_param('inc_tgdc'), FILTER_VALIDATE_BOOLEAN);

        $person_cmd = $pov !== '' ? " The content must be written in {$pov}." : '';
        $full       = trim($aichar . ' ' . $command . ' ' . $person_cmd);

        $args = ['language' => $language];
        if ($inc_biz) {
            $args['biz_details'] = (string) get_option('wpwand_busines_details', '');
        }
        if ($inc_tgdc) {
            $args['targated_customer'] = (string) get_option('wpwand_targated_customer', '');
        }

        return [
            'command'  => $full,
            'language' => $language,
            'number'   => max(1, $number),
            'markdown' => $markdown,
            'args'     => $args,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function fields(WP_REST_Request $request): array
    {
        $p = static fn (string $key) => $request->get_param($key);

        $word_limit = $p('word_limit');

        return [
            'topic'            => sanitize_text_field((string) $p('topic')),
            'keywords'         => sanitize_text_field((string) $p('keyword')),
            'no_of_results'    => max(1, absint($p('result_number') ?: 1)),
            'tone'             => sanitize_text_field((string) $p('tone')),
            'word_count'       => $word_limit === null || $word_limit === '' ? '' : (intval($word_limit) + 1000),
            'product_name'     => sanitize_text_field((string) $p('product_name')),
            'description'      => sanitize_text_field((string) $p('description')),
            'content'          => wp_kses_post(sanitize_text_field((string) $p('content'))),
            'content_textarea' => wp_kses_post(sanitize_text_field((string) $p('content_textarea'))),
            'custom_textarea'  => wp_kses_post(sanitize_text_field((string) $p('custom_textarea'))),
            'product_1'        => wp_kses_post(sanitize_text_field((string) $p('product_1'))),
            'product_2'        => wp_kses_post(sanitize_text_field((string) $p('product_2'))),
            'description_1'    => wp_kses_post(sanitize_text_field((string) $p('description_1'))),
            'description_2'    => wp_kses_post(sanitize_text_field((string) $p('description_2'))),
            'subject'          => sanitize_text_field((string) $p('subject')),
            'question'         => sanitize_text_field((string) $p('question')),
            'comment'          => sanitize_text_field((string) $p('comment')),
        ];
    }
}
