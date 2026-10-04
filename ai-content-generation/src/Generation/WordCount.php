<?php

namespace WPWand\Generation;

/**
 * How long a generated post is, in words, on any writing system.
 *
 * WHY THIS EXISTS. The Length column ran on `str_word_count()`, which counts runs of the bytes in
 * `[A-Za-z'-]` and nothing else. On a Bengali, Arabic, Hindi, Chinese or Japanese post it returns
 * 0, so a customer writing in their own language saw an em dash where the number should be — on
 * every row, forever. The count below reads UTF-8 and gives all of them a number.
 *
 * Two definitions of "word" are in play and both are honest:
 *
 *   - **Scripts that separate words with spaces** — Latin, Bengali, Arabic, Devanagari, Cyrillic,
 *     Greek, Thai's spaced phrases — are counted by splitting on whitespace, which is what a
 *     reader means by a word.
 *   - **CJK, which does not use spaces**, is counted one character at a time. That is the count
 *     Chinese and Japanese publishing itself uses (字数), so it is not a stand-in for a "real"
 *     number; it is the real number for that script.
 *
 * The markdown is stripped first, or a heading's `#` and a bold run's `**` are counted as words.
 * That stripper lived inside `BulkController` and was private to it; it is here now because the
 * count has to happen in two places — once in `JobRunner` when the post is assembled, and once on
 * read for rows written before the column existed.
 */
final class WordCount
{
    /**
     * Characters that carry meaning one at a time: CJK ideographs and their extension A block,
     * kana, and Hangul syllables.
     */
    private const CJK = '\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}\x{F900}-\x{FAFF}'
        . '\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{AC00}-\x{D7AF}';

    /**
     * Words in a piece of markdown.
     */
    public static function of(string $markdown): int
    {
        if (trim($markdown) === '') {
            return 0;
        }

        return self::of_plain(self::strip_markdown($markdown));
    }

    /**
     * Words in text that has already had its markdown taken off.
     */
    public static function of_plain(string $text): int
    {
        $text = trim($text);

        if ($text === '') {
            return 0;
        }

        // Anything that is not valid UTF-8 cannot be measured this way, and the regexes below
        // return false rather than a number on it. Fall back to the old count so a broken row
        // shows something rather than a zero that reads as "nothing was written".
        if (!preg_match('//u', $text)) {
            return str_word_count($text);
        }

        $cjk = preg_match_all('/[' . self::CJK . ']/u', $text);
        $cjk = false === $cjk ? 0 : $cjk;

        // Take the CJK out before splitting, or one unspaced Japanese paragraph counts as a
        // single word on top of the per-character count.
        $rest  = (string) preg_replace('/[' . self::CJK . ']/u', ' ', $text);
        $words = preg_split('/\s+/u', trim($rest), -1, PREG_SPLIT_NO_EMPTY);

        return $cjk + (is_array($words) ? count($words) : 0);
    }

    /**
     * Plain text for counting and for a preview — strips the Markdown syntax the engine emits
     * (ATX headings, bold/italic/code, links, blockquotes, list markers, rules).
     */
    public static function strip_markdown(string $md): string
    {
        $text = wp_strip_all_tags($md);
        $text = (string) preg_replace('/^#{1,6}\s+/m', '', $text);
        $text = (string) preg_replace('/^(\*\*\*+|---+|___+)\s*$/m', '', $text);
        $text = (string) preg_replace('/^>\s?/m', '', $text);
        $text = (string) preg_replace('/^[-*+]\s+/m', '', $text);
        $text = (string) preg_replace('/^\d+[.)]\s+/m', '', $text);
        $text = (string) preg_replace('/\*\*(.+?)\*\*/s', '$1', $text);
        $text = (string) preg_replace('/__(.+?)__/s', '$1', $text);
        $text = (string) preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/s', '$1', $text);
        $text = (string) preg_replace('/(?<![\w_])_(?!\s)(.+?)(?<!\s)_(?![\w_])/s', '$1', $text);
        $text = (string) preg_replace('/`(.+?)`/s', '$1', $text);
        $text = (string) preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text);

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }
}
