<?php

namespace WPWand\Data;

/**
 * Writes generation history rows — restoring the legacy contract that every successful Assistant
 * generation is recorded (the legacy admin-ajax handler called wpwand_pro_add_history(); the new
 * REST endpoints dropped it, so History showed nothing for React-era generations).
 *
 * Schema (wpwand_history, see Migration_1_0_0_BaselineSchema and
 * Migration_1_8_0_HistoryTitleAndSource): template_name, title, source, prompt_info (JSON of the
 * request), response (JSON of the AI response object — the {choices:[{message:{content}}]} shape
 * HistoryController decodes back). Mirrors wp-wand-pro/inc/api.php::wpwand_pro_add_history().
 *
 * `title` and `source` are what make this a record of the plugin rather than a record of the
 * Assistant. Every path that writes text records here now — the Assistant, the block editor, and
 * the engine behind Bulk Posts and Automated Posts — and `source` is how a row says which.
 */
final class History
{
    /** What `source` may hold, and nothing else writes a fourth value. */
    public const SOURCE_EDITOR     = 'editor';
    public const SOURCE_BULK       = 'bulk';
    public const SOURCE_AUTOMATION = 'automation';

    /**
     * @param string               $templateName Display name of the template used.
     * @param array<string, mixed> $promptInfo   The request params (stored as JSON).
     * @param object|array         $response     The AI response object (must carry ->choices).
     * @param string               $title        What this piece is. Empty is allowed: the screen
     *                                           falls back to the body preview, which is what every
     *                                           row written before this stored nothing but.
     * @param string               $source       One of the SOURCE_* constants. Empty means a caller
     *                                           that predates the column; the migration reads that
     *                                           as the post editor, because nothing else wrote here.
     */
    public static function record(
        string $templateName,
        array $promptInfo,
        $response,
        string $title = '',
        string $source = ''
    ): void {
        if ($templateName === '' || empty($response)) {
            return;
        }

        global $wpdb;
        // Custom history table; $wpdb->insert is prepared. phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->insert(
            $wpdb->prefix . 'wpwand_history',
            [
                'template_name' => $templateName,
                'title'         => self::clip($title, 250),
                'source'        => $source,
                'prompt_info'   => wp_json_encode($promptInfo),
                'response'      => wp_json_encode($response),
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * A title for the paths that do not have one.
     *
     * Bulk and automation jobs carry a real title and never come here. The Assistant and the block
     * editor produce a body and nothing else, so the title has to be read out of the text: the first
     * markdown heading if there is one, otherwise the first line that says something.
     *
     * One helper rather than four, because four call sites inventing their own rule is how a column
     * ends up holding four different shapes of the same idea.
     */
    public static function title_from(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        // "## Cold brew without the bitterness" → "Cold brew without the bitterness". Trailing
        // hashes are the closed-ATX form and are not part of the heading.
        if (preg_match('/^\s{0,3}#{1,6}\s+(.+?)\s*#*\s*$/m', $text, $m)) {
            $line = $m[1];
        } else {
            $line = '';
            foreach (preg_split('/\r\n|\r|\n/', $text) as $candidate) {
                $candidate = trim($candidate);
                if ($candidate !== '') {
                    $line = $candidate;
                    break;
                }
            }
        }

        // Emphasis, code ticks and list bullets are formatting, not part of what the piece is
        // called. A title reading "**SEO Meta-description:**" is the markdown leaking through.
        $line = preg_replace('/^\s*(?:[-*+]|\d+[.)])\s+/u', '', $line);
        $line = str_replace(['**', '__', '`'], '', (string) $line);
        $line = trim(preg_replace('/\s+/u', ' ', $line) ?? '');

        return self::clip($line, 120);
    }

    /**
     * Cut to a length without cutting a word in half, and without cutting a multi-byte character in
     * half either — `substr()` on Bengali returns a broken glyph, which is the same class of bug as
     * `str_word_count()` returning 0 for it.
     */
    private static function clip(string $text, int $limit): string
    {
        $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        if ($length <= $limit) {
            return $text;
        }

        $cut = function_exists('mb_substr') ? mb_substr($text, 0, $limit, 'UTF-8') : substr($text, 0, $limit);

        // Back up to the last space so the title ends on a word. If there is no space at all — one
        // very long token, or a script that does not space its words — the hard cut stands.
        $space = function_exists('mb_strrpos') ? mb_strrpos($cut, ' ', 0, 'UTF-8') : strrpos($cut, ' ');
        if ($space !== false && $space > (int) ($limit * 0.6)) {
            $cut = function_exists('mb_substr') ? mb_substr($cut, 0, $space, 'UTF-8') : substr($cut, 0, $space);
        }

        return rtrim($cut, " \t\n\r\0\x0B.,;:-") . '…';
    }

    /** Build a response object in the stored shape from already-assembled plain text. */
    public static function response_from_text(string $text): array
    {
        return ['choices' => [['message' => ['content' => $text]]]];
    }
}
