<?php

namespace WPWand\Admin;

/**
 * Shared enqueue for the AI Assistant bundle, used by both the dedicated page and the
 * in-editor injection. Idempotent by script handle.
 */
final class AssistantAssets
{
    public const HANDLE = 'wpwand-assistant-app';

    public static function enqueue(): void
    {
        $asset_file = WPWAND_NEW_DIR . 'build/assistant.asset.php';
        if (!is_readable($asset_file)) {
            return;
        }
        $asset = require $asset_file;

        wp_enqueue_script(
            self::HANDLE,
            WPWAND_NEW_URL . 'build/assistant.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_enqueue_style(
            'wpwand-inter-font',
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
            [],
            $asset['version']
        );
        if (is_readable(WPWAND_NEW_DIR . 'build/style-assistant.css')) {
            wp_enqueue_style(
                self::HANDLE,
                WPWAND_NEW_URL . 'build/style-assistant.css',
                [],
                (string) filemtime(WPWAND_NEW_DIR . 'build/style-assistant.css')
            );
        }

        wp_localize_script(
            self::HANDLE,
            'wpwandApi',
            [
                'root'  => esc_url_raw(rest_url()),
                'nonce' => wp_create_nonce('wp_rest'),
                'brand' => \WPWand\Data\Brand::resolve()['color'],
            ]
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations(self::HANDLE, 'wp-wand');
        }
    }
}
