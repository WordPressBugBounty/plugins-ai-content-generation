<?php

namespace WPWand\Generation;

/**
 * Decides whether a finished generation is a whole article or a cut-off one.
 *
 * Providers disagree about how they report truncation and the streaming path may not surface a
 * stop reason at all, so this judges the assembled Markdown instead of trusting `finish_reason`.
 * Two signals, both computable from text we already hold:
 *
 *   1. The body's last characters do not close a sentence.
 *   2. The body contains fewer headings than its own contents list names.
 *
 * Signal 1 is forgiving about structure, but not blindly. An article that legitimately ends on a
 * table row, a list item, a closed code fence or an image is finished. A list whose earlier items
 * all close their sentences and whose last one does not is a cut, because that list was written in
 * sentences until the moment the response stopped.
 *
 * Statuses:
 *   complete  — neither signal fired.
 *   truncated — one or both fired, and there is a body worth keeping.
 *   partial   — nothing but a title and/or an outline came back; there is no article underneath.
 *
 * WHAT THIS EXPECTS
 * Long-form output: an article, a page, a post — something with headings, or long enough to have
 * them. Short-answer templates (a meta title, a tagline, a button label, a blog outline) have no
 * article shape to measure, and judging them here would throw away good work: they end without a
 * full stop because that is how a title is written. Templates carry a `markdown` flag
 * (src/Rest/Controllers/TemplatesController.php:163) which is true for exactly the long-form ones —
 * gate on it before calling this. As a backstop the class recognises a headingless answer under 60
 * words and calls it complete rather than cut, but the flag is the reliable line.
 *
 * No WordPress calls beyond translation, so it can be exercised on captured files.
 */
final class Completeness
{
    public const COMPLETE  = 'complete';
    public const TRUNCATED = 'truncated';
    public const PARTIAL   = 'partial';

    /** Headings that introduce a contents list rather than a section of the article. */
    private const CONTENTS_HEADING = '/^(?:table\s+of\s+contents|contents|toc)\b/i';

    /**
     * Headings whose absence is worse than a gap: the section that carried the caveat, the licence
     * requirement, the safety step or the data-protection rule. Deliberately narrow — a word that
     * fires on ordinary marketing copy would make the note meaningless.
     */
    private const SENSITIVE_HEADING = '/\b(?:legal|legally|law|laws|lawful|unlawful|lawyer|attorney|liability|liable|complian(?:ce|t)|regulat(?:ion|ions|ory|or|ors)|statutory|jurisdiction|safety|hazard|hazardous|disclaimer|disclaimers|warning|warnings|consent|privacy|gdpr|ccpa|hipaa|licence|licences|license|licenses|licensing|permit|permits|copyright|trademark|tax|taxes|insurance)\b/i';

    /**
     * Judge one generated body.
     *
     * @param string $markdown The assembled generation from a long-form template. See the note on
     *                         the class about short-answer templates, which this must not judge.
     *
     * @return array{
     *     status: string,
     *     reason: string,
     *     promised: int,
     *     arrived: int,
     *     missing: array<int, string>,
     *     ends_mid_sentence: bool
     * }
     *   `promised` is how many sections the contents list names, `arrived` how many headings the
     *   body actually has, and `missing` the promised section titles that never turned up — that
     *   last one is what tells a caller whether a legal or safety section is among the casualties.
     */
    public static function check(string $markdown): array
    {
        $lines = self::lines($markdown);
        $fence = self::fenceMap($lines);

        $headings = self::headings($lines, $fence['inside']);
        $contents = self::contentsEntries($lines, $headings);
        $sections = self::bodyHeadings($headings);

        $promised = count($contents);
        $arrived  = count($sections);
        $missing  = self::missing($contents, $sections);

        // A headingless handful of words is a title, a tagline or a subject line, not an article
        // that stopped early. Nothing about it is measurable here, so leave it alone.
        if (self::isShortAnswer($markdown, $headings, $promised, $fence['open'])) {
            return self::result(self::COMPLETE, '', $promised, $arrived, $missing, false);
        }

        $cut   = self::endsMidSentence($lines, $fence);
        $short = $promised > 0 && $arrived < $promised;

        if (self::isBodyless($lines, $fence['inside'], $headings)) {
            return self::result(
                self::PARTIAL,
                self::withSensitiveNote(self::bodylessReason($markdown), $missing),
                $promised,
                $arrived,
                $missing,
                $cut
            );
        }

        if (!$cut && !$short) {
            return self::result(self::COMPLETE, '', $promised, $arrived, $missing, false);
        }

        return self::result(
            self::TRUNCATED,
            self::withSensitiveNote(
                self::truncatedReason($cut, $short, $promised, $arrived, !empty($headings) || $promised > 0),
                $missing
            ),
            $promised,
            $arrived,
            $missing,
            $cut
        );
    }

    /**
     * The promised sections that never arrived and whose headings name legal, compliance, safety or
     * regulatory ground.
     *
     * A missing "Tips for Beginners" costs the reader a few paragraphs. A missing "Legal
     * Considerations" costs them the warning the rest of the article assumed they had read, under
     * their own byline — so the reason has to name it rather than counting it.
     *
     * @param array<int, string> $missing
     *
     * @return array<int, string>
     */
    public static function sensitiveMissing(array $missing): array
    {
        $flagged = [];

        foreach ($missing as $heading) {
            if (preg_match(self::SENSITIVE_HEADING, (string) $heading)) {
                $flagged[] = (string) $heading;
            }
        }

        return $flagged;
    }

    /**
     * @param array{status: string} $result
     */
    public static function isComplete(array $result): bool
    {
        return isset($result['status']) && self::COMPLETE === $result['status'];
    }

    // -------------------------------------------------------------------------------------------
    // Reasons
    // -------------------------------------------------------------------------------------------

    private static function truncatedReason(bool $cut, bool $short, int $promised, int $arrived, bool $article): string
    {
        $advice = __(
            'Ask for something shorter, or raise the Longest reply in Settings, then run it again.',
            'ai-content-generation'
        );

        if ($cut && $short) {
            return sprintf(
                /* translators: 1: sections the contents list names, 2: sections written, 3: what to do next. */
                __('The article was cut off before it finished. Its contents list names %1$d sections and only %2$d turned up. %3$s', 'ai-content-generation'),
                $promised,
                $arrived,
                $advice
            );
        }

        if ($short) {
            return sprintf(
                /* translators: 1: sections the contents list names, 2: sections written, 3: what to do next. */
                __('The article stops early. Its contents list names %1$d sections and only %2$d turned up. %3$s', 'ai-content-generation'),
                $promised,
                $arrived,
                $advice
            );
        }

        if ($article) {
            return sprintf(
                /* translators: %s: what to do next. */
                __('The article was cut off before it finished. %s', 'ai-content-generation'),
                $advice
            );
        }

        return sprintf(
            /* translators: %s: what to do next. */
            __('The text was cut off before it finished. %s', 'ai-content-generation'),
            $advice
        );
    }

    /**
     * A short answer from a template that was never going to produce an article: no headings, no
     * contents list, a couple of dozen words. Judging it by sentence endings would fail every meta
     * title the plugin writes.
     *
     * @param array<int, array{line: int, level: int, text: string}> $headings
     */
    private static function isShortAnswer(string $markdown, array $headings, int $promised, bool $openFence): bool
    {
        if (!empty($headings) || $promised > 0 || $openFence) {
            return false;
        }

        $words = self::wordCount($markdown);

        return $words > 0 && $words < 60;
    }

    private static function wordCount(string $text): int
    {
        $text = trim($text);

        if ('' === $text) {
            return 0;
        }

        $words = preg_split('/\s+/u', $text);

        return is_array($words) ? count($words) : 0;
    }

    /**
     * Add the legal/safety sentence to a reason when one of the sections that never arrived was
     * carrying that ground. No-op for a complete run, and no-op when nothing flagged.
     *
     * @param array<int, string> $missing
     */
    private static function withSensitiveNote(string $reason, array $missing): string
    {
        $flagged = self::sensitiveMissing($missing);

        if ('' === $reason || empty($flagged)) {
            return $reason;
        }

        $named = [];
        foreach ($flagged as $heading) {
            $named[] = '"' . $heading . '"';
        }

        return $reason . ' ' . sprintf(
            /* translators: %s: quoted, comma-separated headings of the sections that never arrived. */
            __('What is missing covers legal or safety ground: %s. Get that written before this goes anywhere near a published page.', 'ai-content-generation'),
            implode(', ', $named)
        );
    }

    private static function bodylessReason(string $markdown): string
    {
        if ('' === trim($markdown)) {
            return __('Nothing came back. Run it again, and if it keeps happening check the provider key in Settings.', 'ai-content-generation');
        }

        return __('Only the title and outline came back — none of the sections were written. Ask for something shorter, or raise the Longest reply in Settings, then run it again.', 'ai-content-generation');
    }

    /**
     * @param array<int, string> $missing
     *
     * @return array{status: string, reason: string, promised: int, arrived: int, missing: array<int, string>, ends_mid_sentence: bool}
     */
    private static function result(string $status, string $reason, int $promised, int $arrived, array $missing, bool $cut): array
    {
        return [
            'status'            => $status,
            'reason'            => $reason,
            'promised'          => $promised,
            'arrived'           => $arrived,
            'missing'           => array_values($missing),
            'ends_mid_sentence' => $cut,
        ];
    }

    // -------------------------------------------------------------------------------------------
    // Signal 1 — the body's last characters do not close a sentence
    // -------------------------------------------------------------------------------------------

    /**
     * @param array<int, string>                          $lines
     * @param array{inside: array<int, bool>, open: bool} $fence
     */
    private static function endsMidSentence(array $lines, array $fence): bool
    {
        // A code block that never closed is a cut, whatever the last characters happen to be.
        if ($fence['open']) {
            return true;
        }

        $index = self::lastMeaningfulLine($lines, $fence['inside']);
        if (null === $index) {
            return false;
        }

        $line = $lines[$index];

        // A closing fence is a legitimate ending; so is the code inside a balanced one.
        if (self::isFenceDelimiter($line) || !empty($fence['inside'][$index])) {
            return false;
        }

        // A heading with nothing written under it is a cut, not an ending.
        if (self::isHeading($line)) {
            return true;
        }

        $isTable    = self::isTableRow($line);
        $isListItem = self::isListItem($line);

        $text = self::stripLeadingMarkers($line);

        if ('' === $text) {
            return false;
        }

        // A line that is nothing but an image or a link closes the piece the way a picture does.
        if (preg_match('/^!?\[[^\]]*\]\([^)]*\)$/', $text)) {
            return false;
        }

        if (self::closesSentence($text)) {
            return false;
        }

        // No terminal punctuation. A table row or a list item is allowed to end that way — but only
        // if the rest of its own list does too. A list of full sentences whose last item has no
        // full stop stopped in the middle of that item.
        if ($isTable || $isListItem) {
            return self::siblingsAllClosed($lines, $fence['inside'], $index, $isTable);
        }

        // A paragraph that simply stops is the thing we are looking for.
        return true;
    }

    /**
     * True when this line has earlier items in the same list or table and every one of them closes
     * a sentence. Mixed lists, and lists where nothing is punctuated, count as a fine ending — the
     * cost of a false alarm here is a good draft thrown away, so the doubt goes that way.
     *
     * @param array<int, string> $lines
     * @param array<int, bool>   $inside
     */
    private static function siblingsAllClosed(array $lines, array $inside, int $index, bool $isTable): bool
    {
        $siblings = self::siblingEndings(self::precedingBlock($lines, $inside, $index, $isTable), $isTable);

        if (empty($siblings)) {
            return false;
        }

        foreach ($siblings as $sibling) {
            $text = self::stripLeadingMarkers($sibling);

            if ('' === $text || !self::closesSentence($text)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The lines of the list or table that the final line belongs to, in document order, without the
     * final line itself. Walks back until the block ends: a paragraph, a heading, a blank gap wider
     * than the one a loose list leaves, or the top of the document.
     *
     * @param array<int, string> $lines
     * @param array<int, bool>   $inside
     *
     * @return array<int, string>
     */
    private static function precedingBlock(array $lines, array $inside, int $index, bool $isTable): array
    {
        $block = [];
        $blank = 0;

        for ($i = $index - 1; $i >= 0; $i--) {
            $line = $lines[$i];

            if ('' === trim($line)) {
                if (++$blank > 1) {
                    break;
                }
                continue;
            }

            if (!empty($inside[$i])) {
                break;
            }

            $blank = 0;

            if ($isTable) {
                if (!self::isTableRow($line)) {
                    break;
                }

                $block[] = $line;
                continue;
            }

            // A bullet, or an indented line carrying on from the bullet above it.
            if (self::isListItem($line) || preg_match('/^\s+\S/', $line)) {
                $block[] = $line;
                continue;
            }

            break;
        }

        return array_reverse($block);
    }

    /**
     * One line per earlier item: the line the item actually ends on, which for a wrapped bullet is
     * its continuation rather than the bullet itself. Table dividers carry no sentence, so they go.
     *
     * @param array<int, string> $block
     *
     * @return array<int, string>
     */
    private static function siblingEndings(array $block, bool $isTable): array
    {
        $endings = [];

        foreach ($block as $line) {
            if ($isTable) {
                if (!self::isTableDivider($line)) {
                    $endings[] = $line;
                }
                continue;
            }

            if (self::isListItem($line)) {
                $endings[] = $line;
                continue;
            }

            if (!empty($endings)) {
                $endings[count($endings) - 1] = $line;
            }
        }

        return $endings;
    }

    /**
     * Terminal punctuation, optionally wrapped up by closing quotes, brackets or emphasis marks.
     * Includes the danda, which is how a sentence ends in Bangla, Hindi and their neighbours.
     */
    private static function closesSentence(string $text): bool
    {
        return (bool) preg_match('/[.!?\x{2026}\x{3002}\x{FF01}\x{FF1F}\x{0964}\x{0965}]["\'\x{201D}\x{2019}\)\]\}\*_`~]*$/u', $text);
    }

    /**
     * The last line that carries content: blank lines and trailing horizontal rules do not count.
     *
     * @param array<int, string> $lines
     * @param array<int, bool>   $inside
     */
    private static function lastMeaningfulLine(array $lines, array $inside): ?int
    {
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = $lines[$i];

            if ('' === trim($line)) {
                continue;
            }

            if (empty($inside[$i]) && self::isHorizontalRule($line)) {
                continue;
            }

            return $i;
        }

        return null;
    }

    // -------------------------------------------------------------------------------------------
    // Signal 2 — fewer headings than the contents list names
    // -------------------------------------------------------------------------------------------

    /**
     * Every ATX heading outside a code fence.
     *
     * @param array<int, string> $lines
     * @param array<int, bool>   $inside
     *
     * @return array<int, array{line: int, level: int, text: string}>
     */
    private static function headings(array $lines, array $inside): array
    {
        $found = [];

        foreach ($lines as $i => $line) {
            if (!empty($inside[$i])) {
                continue;
            }

            if (preg_match('/^\s{0,3}(#{1,6})\s+(.*?)\s*#*\s*$/u', $line, $m) && '' !== trim($m[2])) {
                $found[] = [
                    'line'  => $i,
                    'level' => strlen($m[1]),
                    'text'  => trim($m[2]),
                ];
            }
        }

        return $found;
    }

    /**
     * The headings that are sections of the article: not the title, not the contents heading.
     *
     * @param array<int, array{line: int, level: int, text: string}> $headings
     *
     * @return array<int, array{line: int, level: int, text: string}>
     */
    private static function bodyHeadings(array $headings): array
    {
        $sections  = [];
        $seenTitle = false;

        foreach ($headings as $heading) {
            if (self::isContentsHeading($heading['text'])) {
                continue;
            }

            if (!$seenTitle && 1 === $heading['level']) {
                $seenTitle = true;
                continue;
            }

            $sections[] = $heading;
        }

        return $sections;
    }

    /**
     * The section titles a contents list names, in order. Empty when the body has no contents list.
     *
     * @param array<int, string>                                     $lines
     * @param array<int, array{line: int, level: int, text: string}> $headings
     *
     * @return array<int, string>
     */
    private static function contentsEntries(array $lines, array $headings): array
    {
        $start = null;
        $stop  = count($lines);

        foreach ($headings as $position => $heading) {
            if (self::isContentsHeading($heading['text'])) {
                $start = $heading['line'] + 1;
                $stop  = isset($headings[$position + 1]) ? $headings[$position + 1]['line'] : count($lines);
                break;
            }
        }

        if (null === $start) {
            return [];
        }

        $entries = [];

        for ($i = $start; $i < $stop; $i++) {
            $line = $lines[$i];

            if ('' === trim($line) || self::isHorizontalRule($line)) {
                continue;
            }

            if (!preg_match('/^\s{0,12}(?:[-*+]|\d+[.)])\s+(.+)$/u', $line, $m)) {
                // Prose inside the contents block means the list is over.
                if (!empty($entries)) {
                    break;
                }
                continue;
            }

            $title = self::entryTitle($m[1]);

            if ('' !== $title) {
                $entries[] = $title;
            }
        }

        return $entries;
    }

    /**
     * `[Why email works](#why-email-works)` is the section title plus its anchor. Keep the title.
     */
    private static function entryTitle(string $raw): string
    {
        $raw = trim($raw);

        if (preg_match('/^\[([^\]]*)\]\([^)]*\)\s*$/u', $raw, $m)) {
            $raw = $m[1];
        }

        return trim((string) preg_replace('/[*_`]+/u', '', $raw));
    }

    /**
     * Promised titles with no matching heading in the body.
     *
     * @param array<int, string>                                     $contents
     * @param array<int, array{line: int, level: int, text: string}> $sections
     *
     * @return array<int, string>
     */
    private static function missing(array $contents, array $sections): array
    {
        if (empty($contents)) {
            return [];
        }

        $present = [];
        foreach ($sections as $section) {
            $key = self::normalize($section['text']);
            if ('' !== $key) {
                $present[$key] = true;
            }
        }

        $missing = [];
        foreach ($contents as $title) {
            $key = self::normalize($title);
            if ('' !== $key && !isset($present[$key])) {
                $missing[] = $title;
            }
        }

        return $missing;
    }

    // -------------------------------------------------------------------------------------------
    // Shared parsing
    // -------------------------------------------------------------------------------------------

    /**
     * True when all that came back is a title and/or a contents list, with no sections under it.
     *
     * @param array<int, string>                                     $lines
     * @param array<int, bool>                                       $inside
     * @param array<int, array{line: int, level: int, text: string}> $headings
     */
    private static function isBodyless(array $lines, array $inside, array $headings): bool
    {
        $contentsRange = self::contentsRange($lines, $headings);

        foreach ($lines as $i => $line) {
            if ('' === trim($line)) {
                continue;
            }

            if (empty($inside[$i]) && self::isHorizontalRule($line)) {
                continue;
            }

            // Anything inside the contents block, and the headings that frame it, are not a body.
            if ($i >= $contentsRange[0] && $i < $contentsRange[1]) {
                continue;
            }

            if (empty($inside[$i]) && preg_match('/^\s{0,3}#{1,6}\s+/u', $line)) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * @param array<int, string>                                     $lines
     * @param array<int, array{line: int, level: int, text: string}> $headings
     *
     * @return array{0: int, 1: int}
     */
    private static function contentsRange(array $lines, array $headings): array
    {
        foreach ($headings as $position => $heading) {
            if (self::isContentsHeading($heading['text'])) {
                return [
                    $heading['line'],
                    isset($headings[$position + 1]) ? $headings[$position + 1]['line'] : count($lines),
                ];
            }
        }

        return [0, 0];
    }

    /**
     * @return array<int, string>
     */
    private static function lines(string $markdown): array
    {
        $markdown = self::utf8($markdown);
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);

        if (0 === strncmp($markdown, "\xEF\xBB\xBF", 3)) {
            $markdown = substr($markdown, 3);
        }

        return explode("\n", $markdown);
    }

    /**
     * A response cut off mid-character leaves a broken byte sequence behind, and every /u pattern
     * below would quietly stop matching on the line that carries it — the very last line. Drop the
     * broken bytes first. The text still ends mid-word, which is the answer we want anyway.
     */
    private static function utf8(string $text): string
    {
        if (1 === preg_match('//u', $text)) {
            return $text;
        }

        if (function_exists('iconv')) {
            $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $text);

            if (is_string($clean)) {
                return $clean;
            }
        }

        return (string) preg_replace('/[\x80-\xFF]/', '', $text);
    }

    /**
     * Which lines sit inside a fenced code block, and whether a fence was left open at the end.
     *
     * @param array<int, string> $lines
     *
     * @return array{inside: array<int, bool>, open: bool}
     */
    private static function fenceMap(array $lines): array
    {
        $inside = [];
        $marker = null;

        foreach ($lines as $i => $line) {
            if (!preg_match('/^\s{0,3}(`{3,}|~{3,})/', $line, $m)) {
                $inside[$i] = null !== $marker;
                continue;
            }

            $char = substr($m[1], 0, 1);

            if (null === $marker) {
                $marker     = $char;
                $inside[$i] = false; // The opening fence itself is not code.
                continue;
            }

            if ($char === $marker) {
                $marker     = null;
                $inside[$i] = false; // Nor is the closing one.
                continue;
            }

            $inside[$i] = true;
        }

        return ['inside' => $inside, 'open' => null !== $marker];
    }

    private static function isFenceDelimiter(string $line): bool
    {
        return (bool) preg_match('/^\s{0,3}(?:`{3,}|~{3,})\s*[\w+-]*\s*$/', $line);
    }

    private static function isHeading(string $line): bool
    {
        return (bool) preg_match('/^\s{0,3}#{1,6}\s+/u', $line);
    }

    private static function isListItem(string $line): bool
    {
        return (bool) preg_match('/^\s{0,8}(?:[-*+]|\d+[.)])\s+/', $line);
    }

    private static function isTableRow(string $line): bool
    {
        return (bool) preg_match('/^\s*\|.*\|\s*$/', $line);
    }

    /** The `| --- | --- |` line under a table's header row. */
    private static function isTableDivider(string $line): bool
    {
        return (bool) preg_match('/^\s*\|(?:\s*:?-{1,}:?\s*\|)+\s*$/', $line);
    }

    private static function isHorizontalRule(string $line): bool
    {
        return (bool) preg_match('/^\s{0,3}([-*_])\s*(?:\1\s*){2,}$/', $line);
    }

    private static function isContentsHeading(string $text): bool
    {
        return (bool) preg_match(self::CONTENTS_HEADING, trim((string) preg_replace('/[*_`#]+/u', '', $text)));
    }

    /**
     * Drop list bullets, numbering, blockquote arrows and table pipes so the text can be read.
     */
    private static function stripLeadingMarkers(string $line): string
    {
        $text = $line;

        for ($pass = 0; $pass < 6; $pass++) {
            $before = $text;
            $text   = (string) preg_replace('/^\s{0,8}(?:>\s?|[-*+]\s+|\d+[.)]\s+|\[[ xX]\]\s+)/u', '', $text);

            if ($text === $before) {
                break;
            }
        }

        // A table row: read the last cell that has anything in it.
        if (preg_match('/^\s*\|(.*)\|\s*$/', $text, $m)) {
            $cells = array_filter(array_map('trim', explode('|', $m[1])), static function ($cell) {
                return '' !== $cell;
            });

            $text = empty($cells) ? '' : (string) end($cells);
        }

        return trim($text);
    }

    /**
     * A comparison key for heading text. Letters and digits in any script survive: sites generate
     * in their own language, and stripping everything outside ASCII would leave every Bangla or
     * Japanese heading as an empty string, so nothing would ever be reported missing.
     */
    private static function normalize(string $text): string
    {
        $text = trim($text);
        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);

        $stripped = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);

        if (null === $stripped) {
            $stripped = (string) preg_replace('/[^a-zA-Z0-9]+/', ' ', $text);
        }

        return trim((string) preg_replace('/\s+/u', ' ', $stripped));
    }
}
