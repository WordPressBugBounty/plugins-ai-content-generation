<?php

namespace WPWand\Admin;

use WPWand\Data\Brand;

/**
 * Post-activation welcome screen (React rebuild of wpwand_welcome_screen()).
 *
 * Registered as a hidden submenu (no menu entry) so it's reachable by slug + via the activation
 * redirect, mirroring the legacy `?welcome_screen` behaviour without cluttering the menu.
 */
final class WelcomePage
{
    public const PAGE_SLUG = 'wpwand-welcome';
    private const HANDLE    = 'wpwand-welcome-app';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'add_menu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function add_menu(): void
    {
        // Hidden page (null parent) — no visible menu item, reachable by slug.
        add_submenu_page(
            '',
            __('Welcome', 'ai-content-generation'),
            __('Welcome', 'ai-content-generation'),
            'edit_posts',
            self::PAGE_SLUG,
            [$this, 'render']
        );
    }

    public function render(): void
    {
        echo '<div class="wrap"><hr class="wp-header-end"><div id="wpwand-welcome-root"></div></div>';
    }

    public function enqueue(string $hook): void
    {
        if (substr($hook, -strlen(self::PAGE_SLUG)) !== self::PAGE_SLUG) {
            return;
        }

        $asset_file = WPWAND_NEW_DIR . 'build/welcome.asset.php';
        if (!is_readable($asset_file)) {
            return;
        }
        $asset = require $asset_file;

        wp_enqueue_script(
            self::HANDLE,
            WPWAND_NEW_URL . 'build/welcome.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        // No webfont request here on purpose: hitting fonts.googleapis.com from wp-admin is a
        // third-party round trip nobody asked for and a wordpress.org review flag. Every rule in the
        // stylesheet reads --wpwand-font, which names Inter first and then the platform UI stack, so
        // a machine without Inter renders one consistent typeface rather than two.
        if (is_readable(WPWAND_NEW_DIR . 'build/style-welcome.css')) {
            wp_enqueue_style(
                self::HANDLE,
                WPWAND_NEW_URL . 'build/style-welcome.css',
                [],
                (string) filemtime(WPWAND_NEW_DIR . 'build/style-welcome.css')
            );
        }

        $brand = Brand::resolve();
        wp_localize_script(self::HANDLE, 'wpwandWelcome', [
            'brand'       => $brand['name'],
            'settingsUrl' => esc_url_raw(admin_url('admin.php?page=wpwand')),
        ]);

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations(self::HANDLE, 'ai-content-generation');
        }
    }
}
