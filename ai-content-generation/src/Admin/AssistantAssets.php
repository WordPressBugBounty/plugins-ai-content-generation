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

        // No webfont request here on purpose: this runs on every admin page, and hitting
        // fonts.googleapis.com from wp-admin is both a third-party round trip nobody asked for and a
        // wordpress.org review flag. Every rule reads --wpwand-font, which names Inter first and then
        // the platform UI stack, so a machine without Inter renders one consistent typeface.
        if (is_readable(WPWAND_NEW_DIR . 'build/style-assistant.css')) {
            wp_enqueue_style(
                self::HANDLE,
                WPWAND_NEW_URL . 'build/style-assistant.css',
                [],
                (string) filemtime(WPWAND_NEW_DIR . 'build/style-assistant.css')
            );
        }

        ScriptConfig::merge(self::HANDLE, ScriptConfig::base() + [
            'brand' => \WPWand\Data\Brand::resolve()['color'],
            // Where the user asked for the trigger. 'top' means the admin-bar node is the trigger,
            // so the floating edge button must NOT also render — the setting moves the button, it
            // does not add a second one. See AdminBarTrigger.
            // 'hidden' means neither: no admin-bar node, no edge button. Only Settings brings it back.
            'togglerPosition' => (string) get_option('toggler_position', 'top'),
        ]);

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations(self::HANDLE, 'ai-content-generation');
        }
    }
}
