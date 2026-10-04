<?php

namespace WPWand\Admin;

/**
 * "Bulk Posts (New)" admin submenu — React rebuild of the Pro bulk generator. Pro only.
 */
final class BulkPage
{
    private const PARENT_SLUG = 'wpwand';
    private const PAGE_SLUG    = 'wpwand-bulk-new';
    private const HANDLE       = 'wpwand-bulk-app';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'add_menu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);

        // A bulk run is where the generator quietly changing provider matters most and is hardest
        // to spot, so this screen hosts the notice that says one happened. See FailoverNotice.
        FailoverNotice::register(self::PAGE_SLUG);
    }

    public function add_menu(): void
    {
        add_submenu_page(
            self::PARENT_SLUG,
            __('Bulk Posts', 'ai-content-generation'),
            __('Bulk Posts', 'ai-content-generation'),
            'edit_posts',
            self::PAGE_SLUG,
            [$this, 'render'],
            25 // order: first Pro item, after Settings
        );
    }

    public function render(): void
    {
        // JS-free skeleton paints instantly; React's createRoot() clears it on mount. See Skeleton.
        // Title mirrors the app's own in-page heading so the header text doesn't change on hand-off.
        echo '<div class="wrap"><hr class="wp-header-end"><div id="wpwand-bulk-root">';
        echo Skeleton::panel(__('Bulk Posts', 'ai-content-generation'), [], 5); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside Skeleton
        echo '</div></div>';
    }

    public function enqueue(string $hook): void
    {
        if (substr($hook, -strlen(self::PAGE_SLUG)) !== self::PAGE_SLUG) {
            return;
        }

        $asset_file = WPWAND_NEW_DIR . 'build/bulk.asset.php';
        if (!is_readable($asset_file)) {
            return;
        }
        $asset = require $asset_file;

        wp_enqueue_script(self::HANDLE, WPWAND_NEW_URL . 'build/bulk.js', $asset['dependencies'], $asset['version'], true);
        // No webfont request here on purpose: hitting fonts.googleapis.com from wp-admin is a
        // third-party round trip nobody asked for and a wordpress.org review flag. Every rule in the
        // stylesheet reads --wpwand-font, which names Inter first and then the platform UI stack, so
        // a machine without Inter renders one consistent typeface rather than two.
        if (is_readable(WPWAND_NEW_DIR . 'build/style-bulk.css')) {
            wp_enqueue_style(self::HANDLE, WPWAND_NEW_URL . 'build/style-bulk.css', [], (string) filemtime(WPWAND_NEW_DIR . 'build/style-bulk.css'));
        }
        ScriptConfig::merge(self::HANDLE, ScriptConfig::base() + [
            'brand'          => \WPWand\Data\Brand::resolve()['color'],
            'isPro'          => \WPWand\Generation\UsageLimits::is_pro(),
            'bulkPerRunFree' => \WPWand\Generation\UsageLimits::FREE_BULK_PER_RUN,
        ]);
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations(self::HANDLE, 'ai-content-generation');
        }
    }
}
