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
    public function register(): void
    {
        add_filter('mce_external_plugins', [$this, 'register_plugin']);
        add_filter('mce_buttons', [$this, 'register_button']);
        add_action('before_wp_tiny_mce', [$this, 'print_config']);
        // Cutover: stop the legacy classic-editor button so there is no duplicate.
        add_action('init', [$this, 'disable_legacy'], 20);
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

        // Pro "Pro" pill on locked menu items (recreated from the legacy .mce-is_pro rule, so it
        // is self-contained and doesn't depend on the legacy admin.css being present).
        echo '<style>'
            . '.mce-menu .mce-container-body{min-width:245px!important}'
            . '.mce-menu-item.mce-is_pro{position:relative;padding-right:56px!important}'
            . '.mce-menu-item.mce-is_pro:after{content:"Pro";position:absolute;right:8px;top:50%;'
            . 'transform:translateY(-50%);background:#EE2626;color:#fff;border-radius:21px;'
            . "font:700 10px/20px 'Inter',sans-serif;padding:2px 9px;text-transform:uppercase}"
            . '.wpwand-mce-loading{color:#3767fb}'
            . '</style>';
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
