<?php

namespace WPWand\Generation;

/**
 * Converts the Markdown the engine produces into WordPress block markup, so a generated post reads
 * as proper headings/paragraphs/lists instead of showing raw `##`, `**`, etc. in the published
 * content. Scoped to the subset the AI actually emits (ATX headings, bold/italic/code, links,
 * ordered/unordered lists, blockquotes, rules, paragraphs) — not a full CommonMark parser.
 */
final class Markdown
{
    /**
     * Convert assembled post Markdown to block markup, dropping the leading H1 (it's the title,
     * already stored as the post title — keeping it would duplicate the heading on the post).
     */
    public static function to_post_content(string $md): string
    {
        $md = (string) preg_replace('/\A\s*#\s+.*(?:\r?\n|$)/', '', $md, 1);

        return self::to_blocks($md);
    }

    public static function to_blocks(string $md): string
    {
        $lines  = preg_split('/\r\n|\r|\n/', trim($md)) ?: [];
        $blocks = [];
        $para   = [];
        $list   = [];
        $listType = null;

        $flushPara = static function () use (&$para, &$blocks) {
            if ($para) {
                $text = self::inline(trim(implode(' ', $para)));
                $blocks[] = "<!-- wp:paragraph --><p>{$text}</p><!-- /wp:paragraph -->";
                $para = [];
            }
        };
        $flushList = static function () use (&$list, &$listType, &$blocks) {
            if ($list) {
                $tag      = $listType === 'ol' ? 'ol' : 'ul';
                $attr     = $listType === 'ol' ? ' {"ordered":true}' : '';
                $items    = '';
                foreach ($list as $i) {
                    $items .= '<li>' . self::inline($i) . '</li>';
                }
                $blocks[] = "<!-- wp:list{$attr} --><{$tag}>{$items}</{$tag}><!-- /wp:list -->";
                $list     = [];
                $listType = null;
            }
        };

        foreach ($lines as $line) {
            $t = rtrim($line);

            if (trim($t) === '') {
                $flushPara();
                $flushList();
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.*)$/', $t, $m)) {
                $flushPara();
                $flushList();
                $level    = strlen($m[1]);
                $text     = self::inline(trim($m[2]));
                $blocks[] = "<!-- wp:heading {\"level\":{$level}} --><h{$level}>{$text}</h{$level}><!-- /wp:heading -->";
                continue;
            }

            if (preg_match('/^(\*\*\*+|---+|___+)\s*$/', $t)) {
                $flushPara();
                $flushList();
                $blocks[] = '<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->';
                continue;
            }

            if (preg_match('/^>\s?(.*)$/', $t, $m)) {
                $flushPara();
                $flushList();
                $blocks[] = '<!-- wp:quote --><blockquote class="wp-block-quote"><p>' . self::inline($m[1]) . '</p></blockquote><!-- /wp:quote -->';
                continue;
            }

            if (preg_match('/^[-*+]\s+(.*)$/', $t, $m)) {
                $flushPara();
                if ($listType === 'ol') {
                    $flushList();
                }
                $listType = 'ul';
                $list[]   = $m[1];
                continue;
            }

            if (preg_match('/^\d+[.)]\s+(.*)$/', $t, $m)) {
                $flushPara();
                if ($listType === 'ul') {
                    $flushList();
                }
                $listType = 'ol';
                $list[]   = $m[1];
                continue;
            }

            $flushList();
            $para[] = trim($t);
        }

        $flushPara();
        $flushList();

        return implode("\n\n", $blocks);
    }

    /** Inline formatting: escape, then bold / italic / code / links. */
    private static function inline(string $s): string
    {
        $s = esc_html($s);

        $s = (string) preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s);
        $s = (string) preg_replace('/__(.+?)__/s', '<strong>$1</strong>', $s);
        $s = (string) preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/s', '<em>$1</em>', $s);
        $s = (string) preg_replace('/(?<![\w_])_(?!\s)(.+?)(?<!\s)_(?![\w_])/s', '<em>$1</em>', $s);
        $s = (string) preg_replace('/`(.+?)`/s', '<code>$1</code>', $s);

        $s = (string) preg_replace_callback(
            '/\[([^\]]+)\]\(([^)]+)\)/',
            static function ($m) {
                return '<a href="' . esc_url(html_entity_decode($m[2])) . '">' . $m[1] . '</a>';
            },
            $s
        );

        return $s;
    }
}
