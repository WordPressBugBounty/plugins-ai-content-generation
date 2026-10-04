<?php

namespace WPWand\Admin;

use WPWand\Data\Brand;
use WPWand\Data\EditorPrompts;

/**
 * Classic (TinyMCE) editor integration: adds a "WP Wand" menu button to the toolbar with
 * the enhance actions (Summarize / Expand / … ; Pro → upgrade) that run on the selected
 * text. Rebuilt against REST /editor; registers a TinyMCE plugin via mce_external_plugins.
 */
final class ClassicEditor
{
    public const STYLE_HANDLE = 'wpwand-classic';

    public function register(): void
    {
        add_filter('mce_external_plugins', [$this, 'register_plugin']);
        add_filter('mce_buttons', [$this, 'register_button']);
        add_action('before_wp_tiny_mce', [$this, 'print_config']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_style']);
        // WordPress autosaves while a generation is still running, so the placeholder can be written
        // into the post and left there. Strip it on the way into the database — this covers autosave,
        // revisions and the REST editor, which a client-side guard would not.
        add_filter('content_save_pre', [$this, 'strip_placeholder'], 5);
        // Cutover: stop the legacy classic-editor button so there is no duplicate.
        add_action('init', [$this, 'disable_legacy'], 20);
    }

    /**
     * Remove any leftover "AI is thinking…" placeholder before the content is stored.
     *
     * Matches the element the Classic bundle inserts (assets/src/apps/classic/index.js) and the
     * paragraph the block editor inserts, both carrying the wpwand-mce-loading class.
     */
    public function strip_placeholder(string $content): string
    {
        if (strpos($content, 'wpwand-mce-loading') === false) {
            return $content;
        }

        $cleaned = preg_replace(
            '#<p\b[^>]*\bclass=("|\')[^"\']*\bwpwand-mce-loading\b[^"\']*\1[^>]*>.*?</p>\s*#is',
            '',
            $content
        );

        return is_string($cleaned) ? $cleaned : $content;
    }

    public function disable_legacy(): void
    {
        remove_action('admin_head', 'wpwand_ai_buttons');
    }

    /**
     * @param array<string, string> $plugins
     * @return array<string, string>
     */
    public function register_plugin(array $plugins): array
    {
        if (is_readable(WPWAND_NEW_DIR . 'build/classic.js')) {
            $plugins['wpwandeditor'] = WPWAND_NEW_URL . 'build/classic.js?ver=' . $this->version();
        }
        return $plugins;
    }

    /**
     * @param string[] $buttons
     * @return string[]
     */
    public function register_button(array $buttons): array
    {
        $buttons[] = 'wpwandeditor';
        return $buttons;
    }

    public function print_config(): void
    {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $data = [
            'root'    => esc_url_raw(rest_url()),
            'nonce'   => wp_create_nonce('wp_rest'),
            'prompts' => EditorPrompts::all(),
            'brand'   => Brand::resolve(),
            'upgrade' => 'https://wpwand.com/pro-plugin',
        ];

        echo '<script>window.wpwandClassic=' . wp_json_encode($data) . ';</script>';

        // The Pro pill and the menu width used to be printed here as a <style> block. They live in
        // assets/src/apps/classic/style.scss now and arrive as build/style-classic.css — see
        // enqueue_style(). The :has(.mce-wpwand) scoping moved with them and has to stay: without
        // it the width rule reaches every TinyMCE dropdown on the site, ours or not.
    }

    /**
     * Load the Classic menu's stylesheet.
     *
     * On admin_enqueue_scripts rather than beside the config above, and the reason is a measured
     * one: before_wp_tiny_mce fires from _WP_Editors::editor_js() at admin_print_footer_scripts
     * priority 50, and core prints late styles from _wp_footer_scripts() at priority 10. A style
     * enqueued there is registered after the last chance to print it and never reaches the page —
     * which for these rules means the Pro pill silently loses every rule it has.
     *
     * The cost is that the file loads on any admin screen an author can reach, not only the ones
     * carrying an editor. It is under a kilobyte and every selector needs TinyMCE markup to match
     * anything, so it is inert elsewhere. `redesign-editor-surfaces` task 3.1 owns narrowing this
     * to the screens that actually render an editor.
     */
    public function enqueue_style(): void
    {
        if (!current_user_can('edit_posts')) {
            return;
        }

        if (!is_readable(WPWAND_NEW_DIR . 'build/style-classic.css')) {
            return;
        }

        wp_enqueue_style(
            self::STYLE_HANDLE,
            WPWAND_NEW_URL . 'build/style-classic.css',
            [],
            $this->version()
        );
    }

    private function version(): string
    {
        $asset = WPWAND_NEW_DIR . 'build/classic.asset.php';
        if (is_readable($asset)) {
            $data = require $asset;
            return (string) ($data['version'] ?? '1');
        }
        return '1';
    }
}
