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

        // Scheduled runs happen with nobody watching, so this is the screen where "your key stopped
        // working and we finished on the backup" needs to be waiting. See FailoverNotice.
        FailoverNotice::register(self::PAGE_SLUG);
    }

    public function add_menu(): void
    {
        // Menu title carries a "New" badge (HTML is allowed in the menu-title arg).
        $menu_title = __('Automated Posts', 'ai-content-generation')
            . ' <span class="wpwand-menu-new">' . esc_html__('New', 'ai-content-generation') . '</span>';

        add_submenu_page(
            self::PARENT_SLUG,
            __('Automated Posts', 'ai-content-generation'),
            $menu_title,
            'edit_posts',
            self::PAGE_SLUG,
            [$this, 'render'],
            30 // order: after Bulk Posts (25), before History (35)
        );

        add_action('admin_head', static function () {
            echo '<style>#adminmenu .toplevel_page_wpwand .wp-submenu a{white-space:nowrap;}'
                . '#adminmenu .wpwand-menu-new{display:inline-block;margin-left:1px;padding:0 4px;'
                . 'border-radius:9px;background:#3767FB;color:#fff;font-size:8px;font-weight:400;'
                . 'line-height:1.6;text-transform:uppercase;vertical-align:middle;}</style>';
        });
    }

    public function render(): void
    {
        // JS-free skeleton paints instantly; React's createRoot() clears it on mount. See Skeleton.
        // Title mirrors the app's own in-page heading so the header text doesn't change on hand-off.
        echo '<div class="wrap"><hr class="wp-header-end"><div id="wpwand-automation-root">';
        echo Skeleton::panel(__('Automated Posts', 'ai-content-generation'), [], 4); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside Skeleton
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
        // No webfont request here on purpose: hitting fonts.googleapis.com from wp-admin is a
        // third-party round trip nobody asked for and a wordpress.org review flag. Every rule in the
        // stylesheet reads --wpwand-font, which names Inter first and then the platform UI stack, so
        // a machine without Inter renders one consistent typeface rather than two.
        if (is_readable(WPWAND_NEW_DIR . 'build/style-automation.css')) {
            wp_enqueue_style(self::HANDLE, WPWAND_NEW_URL . 'build/style-automation.css', [], (string) filemtime(WPWAND_NEW_DIR . 'build/style-automation.css'));
        }
        ScriptConfig::merge(self::HANDLE, ScriptConfig::base() + [
            'brand' => \WPWand\Data\Brand::resolve()['color'],
            'isPro' => \WPWand\Generation\UsageLimits::is_pro(),
            // The server clamps a saved schedule to this (Schedules::normalize). Ship it so the form
            // cannot offer a number the save will silently reduce.
            'automationPerRun' => \WPWand\Automation\Schedules::max_count(),
        ]);
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations(self::HANDLE, 'ai-content-generation');
        }
    }
}
