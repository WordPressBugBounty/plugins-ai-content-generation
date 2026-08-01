<?php

namespace WPWand\Admin;

use WPWand\Data\Brand;

/**
 * Elementor editor integration (rebuild of the legacy inc/modules/elementor module).
 *
 * Mounts the React assistant inside the Elementor editor and loads a small glue script that
 * adds a WP Wand button to each text / textarea / WYSIWYG control. Clicking it opens the
 * assistant and remembers the field; the panel's Insert writes back into it. The legacy module
 * (custom control overrides + its own panel JS) is disabled so there is no duplicate.
 */
final class ElementorIntegration
{
    public const HANDLE = 'wpwand-elementor';

    public function register(): void
    {
        // Don't register the legacy Elementor control overrides (they injected the old panel).
        add_action('init', [$this, 'disable_legacy'], 20);

        add_action('elementor/editor/after_enqueue_scripts', [$this, 'enqueue']);
        add_action('elementor/editor/after_enqueue_scripts', [$this, 'dequeue_legacy'], 200);
        add_action('elementor/editor/footer', [$this, 'mount']);
    }

    public function disable_legacy(): void
    {
        // Legacy module registered its text/textarea/wysiwyg control overrides here.
        remove_action('elementor/controls/register', 'register_currency_control');
    }

    public function dequeue_legacy(): void
    {
        // The legacy editor JS (handle 'wpwand-admin') rendered the old floating panel.
        wp_dequeue_script('wpwand-admin');
        wp_dequeue_script('jquery-showdown');
    }

    public function enqueue(): void
    {
        if (!current_user_can('edit_posts')) {
            return;
        }

        // The same React assistant used everywhere else.
        AssistantAssets::enqueue();

        $asset_file = WPWAND_NEW_DIR . 'build/elementor.asset.php';
        if (!is_readable($asset_file)) {
            return;
        }
        $asset = require $asset_file;

        wp_enqueue_script(
            self::HANDLE,
            WPWAND_NEW_URL . 'build/elementor.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_localize_script(self::HANDLE, 'wpwandElementor', [
            'brand' => Brand::resolve(),
        ]);
    }

    public function mount(): void
    {
        echo '<div id="wpwand-assistant-root"></div>';
    }
}
