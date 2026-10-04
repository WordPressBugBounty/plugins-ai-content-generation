<?php

namespace WPWand\Generation;

use WP_REST_Request;

/**
 * Builds the final generation command from a /generate-style request — the {placeholder}
 * substitution, the house style on the templates that write prose, and Pro extras (AI character,
 * point of view, business/targeted-customer args).
 *
 * Extracted from GenerateController so the non-streaming and streaming endpoints produce the
 * IDENTICAL prompt. Behavior mirrors the legacy wpwand_api_fields_validate() / wpwand_pro_request.
 */
final class Prompt
{
    /**
     * Markdown templates the house style does NOT go to.
     *
     * The markdown flag is the base — it marks the templates that write running prose — and this is
     * the one subtraction from it. A WooCommerce description is the merchant's own product page:
     * there is no second option on it to weigh, so "say which option you would actually pick and
     * which you would not bother with" leaves the model either inventing a rival to talk down or
     * hedging about the very thing being sold. It is also the one short-form entry in the markdown
     * set — features, benefits and bullet points, no word count collected — so paragraph rhythm has
     * nothing to work on either.
     *
     * @var string[]
     */
    private const NO_HOUSE_STYLE = ['WooCommerce Product Description'];

    /**
     * @return array{command: string, language: string, number: int, markdown: bool, args: array<string, mixed>}
     */
    public static function build(WP_REST_Request $request): array
    {
        $template     = (string) $request->get_param('prompt');
        $templateName = trim(sanitize_text_field((string) $request->get_param('template_name')));
        $fields       = self::fields($request);
        $language     = sanitize_text_field((string) $request->get_param('language'));
        $markdown     = (bool) $request->get_param('markdown');
        $number       = (int) $fields['no_of_results'];

        // Two placeholders joined directly by a comma or period — e.g. "{topic},{keywords}" — must
        // not strand that punctuation when one side is an optional field left blank, so
        // "{topic},{keywords}" becomes just the topic instead of "topic," when Keywords is empty.
        // This has to happen in the SAME pass as plain placeholder substitution: a field's own value
        // is never fed back through the pattern, so braces the user typed (e.g. a description of
        // "Sizes: {S},{M}") pass through untouched instead of being mistaken for template syntax.
        $command = preg_replace_callback(
            '/\{([^}]+)\}(?:([,.])\{([^}]+)\})?/',
            static function ($matches) use ($fields) {
                $left = isset($fields[trim($matches[1])]) ? (string) $fields[trim($matches[1])] : '';

                if (!isset($matches[3])) {
                    return $left;
                }

                $right = isset($fields[trim($matches[3])]) ? (string) $fields[trim($matches[3])] : '';
                if ($left === '') {
                    return $right;
                }
                if ($right === '') {
                    return $left;
                }
                return $left . $matches[2] . $right;
            },
            $template
        );

        // Keyword is the one field a template may leave blank, and every prompt that uses it
        // introduces it with a colon — "Work in any keywords the reader listed: {keywords}."
        // Blank, that ships as "...listed: ." Drop the punctuation that was only there to hand
        // over a value, so the sentence still reads as a whole one. Runs after the substitution
        // above and does not touch it.
        //
        // Both passes are kept to the exact shape that stranding produces, because this also runs
        // over prompts the user wrote themselves — a custom prompt reaches /generate the same way a
        // shipped one does. A colon at the end of a line is how people lay out "Output format:",
        // and leading spaces are how they indent what follows, so neither is touched: the first
        // pass only fires where the punctuation is immediately followed by sentence-ending
        // punctuation, and the second only collapses runs of spaces that sit between two words.
        $command = (string) preg_replace('/[ \t]*[:,;][ \t]*(?=[.!?])/u', '', (string) $command);
        $command = (string) preg_replace('/(?<=\S)[ \t]{2,}(?=\S)/', ' ', (string) $command);

        $pov      = sanitize_text_field((string) $request->get_param('point_of_view'));
        $aichar   = sanitize_text_field((string) $request->get_param('aichar'));
        // The two switches are Pro, and the panel disables them on a free install — but the panel
        // is the client. A request that sent them anyway used to be believed.
        $pro      = class_exists('WPWand\\Core\\Pro') && \WPWand\Core\Pro::unlocked();
        $inc_biz  = $pro && filter_var($request->get_param('inc_biz'), FILTER_VALIDATE_BOOLEAN);
        $inc_tgdc = $pro && filter_var($request->get_param('inc_tgdc'), FILTER_VALIDATE_BOOLEAN);

        $business = $inc_biz ? (string) get_option('wpwand_busines_details', '') : '';
        $audience = $inc_tgdc ? (string) get_option('wpwand_targated_customer', '') : '';

        // The house style goes to the templates that write prose, and only those. The catalogue
        // already marks them: every markdown template is a piece of running text (blog post,
        // review, comparison, Quora answer), and the ones that return a single title, a meta
        // description or a list of keywords are not marked. Paragraph rhythm and "take a side"
        // are meaningless advice for a 10-word headline, and would eat input tokens on every one
        // of them. HouseStyle::rules() drops the business and audience blocks itself when those
        // are blank, so nothing empty gets quoted into the prompt.
        $house = $markdown && !in_array($templateName, self::NO_HOUSE_STYLE, true)
            ? HouseStyle::rules(['business' => $business, 'audience' => $audience])
            : '';

        $person_cmd = $pov !== '' ? " The content must be written in {$pov}." : '';
        $full       = trim($aichar . ' ' . $command . ' ' . $person_cmd) . $house;

        $args = ['language' => $language];

        // Where the house style is on the command it is already carrying the business and audience
        // text, in the wording that was measured. Generator wraps these args in its own older
        // phrasing and puts them in the system message, so sending both hands the model the same
        // paragraph twice, two different ways. They still travel as args in the two cases where
        // nothing else carries them: a template with no house style, and a long-form template,
        // whose command is thrown away and rebuilt section by section — GenerateController reads
        // them straight back out of here to build the same rules for every section.
        $carry_as_args = $house === '' || JobRunner::handles_template($templateName);

        if ($inc_biz && $carry_as_args) {
            $args['biz_details'] = $business;
        }
        if ($inc_tgdc && $carry_as_args) {
            $args['targated_customer'] = $audience;
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
            'word_count'       => $word_limit === null || $word_limit === '' ? '' : intval($word_limit),
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
