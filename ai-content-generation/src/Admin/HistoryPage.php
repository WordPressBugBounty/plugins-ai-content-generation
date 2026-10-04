<?php

namespace WPWand\Admin;

/**
 * "History" admin submenu. The menu item always shows; the page renders a locked
 * feature-preview placeholder (History is a Pro feature, not yet wired to data).
 */
final class HistoryPage
{
    private const PARENT_SLUG = 'wpwand';
    private const PAGE_SLUG    = 'wpwand-history-new';
    private const HANDLE       = 'wpwand-history-app';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'add_menu'], 22);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function add_menu(): void
    {
        add_submenu_page(
            self::PARENT_SLUG,
            __('History', 'ai-content-generation'),
            __('History', 'ai-content-generation'),
            'edit_posts',
            self::PAGE_SLUG,
            [$this, 'render'],
            35 // order: after Automation (Pro), before License
        );
    }

    public function render(): void
    {
        echo '<div class="wrap"><hr class="wp-header-end"><div id="wpwand-history-root"></div></div>';
    }

    public function enqueue(string $hook): void
    {
        if (substr($hook, -strlen(self::PAGE_SLUG)) !== self::PAGE_SLUG) {
            return;
        }

        $asset_file = WPWAND_NEW_DIR . 'build/history.asset.php';
        if (!is_readable($asset_file)) {
            return;
        }
        $asset = require $asset_file;

        wp_enqueue_script(
            self::HANDLE,
            WPWAND_NEW_URL . 'build/history.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        // No webfont request here on purpose: hitting fonts.googleapis.com from wp-admin is a
        // third-party round trip nobody asked for and a wordpress.org review flag. Every rule in the
        // stylesheet reads --wpwand-font, which names Inter first and then the platform UI stack, so
        // a machine without Inter renders one consistent typeface rather than two.
        if (is_readable(WPWAND_NEW_DIR . 'build/style-history.css')) {
            wp_enqueue_style(
                self::HANDLE,
                WPWAND_NEW_URL . 'build/style-history.css',
                [],
                (string) filemtime(WPWAND_NEW_DIR . 'build/style-history.css')
            );
        }

        ScriptConfig::merge(self::HANDLE, ScriptConfig::base() + [
            'brand' => \WPWand\Data\Brand::resolve()['color'],
        ]);

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations(self::HANDLE, 'ai-content-generation');
        }
    }
}
