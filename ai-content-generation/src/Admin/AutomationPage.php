<?php

namespace WPWand\Admin;

/**
 * "Automation (New)" admin submenu — React UI for scheduled recurring post generation. Pro only.
 */
final class AutomationPage
{
    private const PARENT_SLUG = 'wpwand';
    private const PAGE_SLUG    = 'wpwand-automation-new';
    private const HANDLE       = 'wpwand-automation-app';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'add_menu'], 21);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function add_menu(): void
    {
        // Menu title carries a "New" badge (HTML is allowed in the menu-title arg).
        $menu_title = __('Automated Posts', 'wp-wand-pro')
            . ' <span class="wpwand-menu-new">' . esc_html__('New', 'wp-wand-pro') . '</span>';

        add_submenu_page(
            self::PARENT_SLUG,
            __('Automated Posts', 'wp-wand-pro'),
            $menu_title,
            'edit_posts',
            self::PAGE_SLUG,
            [$this, 'render'],
            30 // order: after Bulk Posts (25), before History (35)
        );

        add_action('admin_head', static function () {
            echo '<style>#adminmenu .toplevel_page_wpwand .wp-submenu a{white-space:nowrap;}'
                . '#adminmenu .wpwand-menu-new{display:inline-block;margin-left:1px;padding:0 4px;'
                . 'border-radius:9px;background:#d63638;color:#fff;font-size:8px;font-weight:400;'
                . 'line-height:1.6;text-transform:uppercase;vertical-align:middle;}</style>';
        });
    }

    public function render(): void
    {
        // JS-free skeleton paints instantly; React's createRoot() clears it on mount. See Skeleton.
        // Title mirrors the app's own in-page heading so the header text doesn't change on hand-off.
        echo '<div class="wrap"><div id="wpwand-automation-root">';
        echo Skeleton::panel(__('Automation', 'wp-wand'), [], 4); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside Skeleton
        echo '</div></div>';
    }

    public function enqueue(string $hook): void
    {
        if (substr($hook, -strlen(self::PAGE_SLUG)) !== self::PAGE_SLUG) {
            return;
        }

        $asset_file = WPWAND_NEW_DIR . 'build/automation.asset.php';
        if (!is_readable($asset_file)) {
            return;
        }
        $asset = require $asset_file;

        wp_enqueue_script(self::HANDLE, WPWAND_NEW_URL . 'build/automation.js', $asset['dependencies'], $asset['version'], true);
        wp_enqueue_style('wpwand-inter-font', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap', [], $asset['version']);
        if (is_readable(WPWAND_NEW_DIR . 'build/style-automation.css')) {
            wp_enqueue_style(self::HANDLE, WPWAND_NEW_URL . 'build/style-automation.css', [], (string) filemtime(WPWAND_NEW_DIR . 'build/style-automation.css'));
        }
        wp_localize_script(self::HANDLE, 'wpwandApi', [
            'root'  => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'brand' => \WPWand\Data\Brand::resolve()['color'],
        ]);
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations(self::HANDLE, 'wp-wand-pro');
        }
    }
}
