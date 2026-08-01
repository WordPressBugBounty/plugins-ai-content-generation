<?php

namespace WPWand\Admin;

use WPWand\Data\Brand;
use WPWand\Data\EditorPrompts;

/**
 * Registers the WP Wand toolbar in the Gutenberg block editor: an "enhance selected text"
 * dropdown (Summarize / Expand / …) and an "Ask AI to write anything" prompt. The bundle
 * registers a RichText format that renders BlockControls in the block toolbar.
 */
final class GutenbergIntegration
{
    public const HANDLE = 'wpwand-editor';

    public function register(): void
    {
        add_action('enqueue_block_editor_assets', [$this, 'enqueue']);
        // Cutover: stop the legacy block-editor toolbar so there is no duplicate.
        add_action('init', [$this, 'disable_legacy'], 20);
        add_action('enqueue_block_editor_assets', [$this, 'dequeue_legacy'], 100);
    }

    public function disable_legacy(): void
    {
        remove_action('enqueue_block_editor_assets', 'wpwand_block_editor', 9);
        remove_action('admin_enqueue_scripts', 'wpwand_block_editor');
    }

    public function dequeue_legacy(): void
    {
        wp_dequeue_script('wpwand-gutenberg-custom-button');
        wp_deregister_script('wpwand-gutenberg-custom-button');
    }

    public function enqueue(): void
    {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $asset_file = WPWAND_NEW_DIR . 'build/editor.asset.php';
        if (!is_readable($asset_file)) {
            return;
        }
        $asset = require $asset_file;

        wp_enqueue_script(
            self::HANDLE,
            WPWAND_NEW_URL . 'build/editor.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_localize_script(
            self::HANDLE,
            'wpwandEditor',
            [
                'root'    => esc_url_raw(rest_url()),
                'nonce'   => wp_create_nonce('wp_rest'),
                'prompts'  => EditorPrompts::all(),
                'pro'      => \WPWand\Core\Pro::unlocked(),
                'brand'    => Brand::resolve(),
                'upgrade'  => 'https://wpwand.com/pro-plugin',
                'help'     => 'https://wpwand.com/how-ai-assistant-work',
                'hide_bar' => (bool) get_option('wpwand_hide_ai_bar_gutenberg', 0),
            ]
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations(self::HANDLE, 'wp-wand');
        }
    }
}
