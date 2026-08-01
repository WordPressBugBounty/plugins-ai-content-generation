<?php

namespace WPWand\Data;

/**
 * The "enhance selected text" actions for the Gutenberg toolbar dropdown.
 *
 * Mirrors the legacy wpwand_editor_prompts(): the three free actions carry a prompt with
 * a [text] placeholder (replaced with the selection on the client); the rest are Pro and
 * link to the upgrade page. Prefers the legacy function when loaded, with a built-in
 * fallback so the toolbar is never empty.
 */
final class EditorPrompts
{
    /**
     * @return array<int, array{name: string, prompt: string, is_pro: bool}>
     */
    public static function all(): array
    {
        // The built-in DEFAULTS are the free (locked) base; the Pro plugin hooks the
        // 'wpwand_editor_prompts' filter (WPWand\Data\ProData) to return the fully-unlocked menu
        // when the license is active. No dependency on the legacy wpwand_editor_prompts().
        $list = apply_filters('wpwand_editor_prompts', self::DEFAULTS);
        if (!is_array($list) || empty($list)) {
            $list = self::DEFAULTS;
        }

        return array_map(
            static fn ($p) => [
                'name'   => (string) ($p['name'] ?? ''),
                'prompt' => (string) ($p['prompt'] ?? ''),
                'is_pro' => !empty($p['is_pro']),
            ],
            $list
        );
    }

    /**
     * @var array<int, array{name: string, prompt: string, is_pro: bool}>
     */
    private const DEFAULTS = [
        ['name' => 'Write a paragraph', 'prompt' => 'Write a paragraph: [text]', 'is_pro' => false],
        ['name' => 'Summarize', 'prompt' => 'Summarize this: [text]', 'is_pro' => false],
        ['name' => 'Expand', 'prompt' => 'Expand this: [text]', 'is_pro' => false],
        ['name' => 'Rewrite', 'prompt' => '', 'is_pro' => true],
        ['name' => 'Shorter', 'prompt' => '', 'is_pro' => true],
        ['name' => 'Longer', 'prompt' => '', 'is_pro' => true],
        ['name' => 'Make a bullet list', 'prompt' => '', 'is_pro' => true],
        ['name' => 'Paraphrase', 'prompt' => '', 'is_pro' => true],
        ['name' => 'Generate a call to action', 'prompt' => '', 'is_pro' => true],
        ['name' => 'Correct grammar', 'prompt' => '', 'is_pro' => true],
        ['name' => 'Generate a question', 'prompt' => '', 'is_pro' => true],
        ['name' => 'Suggest a title', 'prompt' => '', 'is_pro' => true],
        ['name' => 'Convert to passive voice', 'prompt' => '', 'is_pro' => true],
        ['name' => 'Convert to active voice', 'prompt' => '', 'is_pro' => true],
        ['name' => 'Write a conclusion', 'prompt' => '', 'is_pro' => true],
        ['name' => 'Provide a counterargument', 'prompt' => '', 'is_pro' => true],
        ['name' => 'Generate a quote', 'prompt' => '', 'is_pro' => true],
    ];
}
