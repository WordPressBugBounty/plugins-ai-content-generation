<?php

namespace WPWand\Admin;

/**
 * Registers the new React-based "Settings (New)" admin screen.
 *
 * Phase 1 rollout is side-by-side: the legacy Settings page is left fully intact; this
 * adds a separate submenu so the new UI can be compared against the old one with zero
 * risk (REBUILD-PLAN.md §8). Once parity is confirmed it will take over the main slug.
 */
final class SettingsPage
{
    // Post-cutover: the React Settings screen IS the top-level WP Wand menu (slug 'wpwand'),
    // replacing the legacy settings page (whose menu registration is removed in Plugin::boot_modules).
    private const PAGE_SLUG = 'wpwand';
    private const HANDLE     = 'wpwand-settings-app';

    public function register(): void
    {
        // Priority 9 so this top-level menu exists before the submenus other modules attach to it.
        add_action('admin_menu', [$this, 'add_menu'], 9);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('admin_head', [$this, 'icon_css']);
    }

    /**
     * Constrain the brand SVG used as the top-level menu icon (and the admin-bar trigger icon) to a
     * normal icon size — the raw SVG otherwise renders at its intrinsic size and overlaps the menu.
     */
    public function icon_css(): void
    {
        echo '<style>'
            . '#adminmenu #toplevel_page_wpwand .wp-menu-image img{width:20px;height:20px;padding-top:7px;}'
            . '#wpadminbar #wp-admin-bar-wpwand-trigger .ab-item img{width:18px;height:18px;vertical-align:middle;margin-right:4px;}'
            . '</style>';
    }

    public function add_menu(): void
    {
        $brand = \WPWand\Data\Brand::resolve();
        $label = $brand['name'];
        $icon  = $brand['icon'] ?: 'dashicons-edit';

        add_menu_page($label, $label, 'edit_posts', self::PAGE_SLUG, [$this, 'render'], $icon);
        // Rename the auto-created first submenu (which defaults to the brand name) to "Settings".
        add_submenu_page(
            self::PAGE_SLUG,
            __('Settings', 'wp-wand'),
            __('Settings', 'wp-wand'),
            'edit_posts',
            self::PAGE_SLUG,
            [$this, 'render']
        );
    }

    /**
     * The container carries a static, JS-free loading skeleton so the page paints the REAL settings
     * layout the moment WordPress prints it — before settings.js has even downloaded. It reuses the
     * app's actual markup/classes (wpwand-app header, wpws-card/tabs/panel/rows), which are styled by
     * style-settings.css (enqueued in <head>, independent of JS), blurred under a spinner overlay.
     * React's createRoot() clears these children on first mount, so the skeleton is replaced by the
     * live UI with no teardown code. Skeleton-only CSS is scoped under #wpwand-settings-root.
     */
    public function render(): void
    {
        echo '<div class="wrap"><div id="wpwand-settings-root">';
        echo $this->skeleton(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup, brand name escaped below
        echo '</div></div>';
    }

    private function skeleton(): string
    {
        $brand = \WPWand\Data\Brand::resolve();
        $name  = $brand['name'] !== '' ? $brand['name'] : 'WP Wand';

        // Mirrors the General tab: the four provider key rows exactly as the app renders them.
        $labels = [
            __('OpenAI API Key', 'wp-wand'),
            __('Claude API Key', 'wp-wand'),
            __('DeepSeek API Key', 'wp-wand'),
            __('OpenRouter API Key', 'wp-wand'),
        ];
        $desc = esc_html__('Add your API key to activate.', 'wp-wand');
        $rows = '';
        foreach ($labels as $label) {
            $rows .= '<div class="wpws-row">'
                . '<div class="wpws-row__label"><span class="wpws-row__label-line"><label>' . esc_html($label) . '</label></span>'
                . '<span class="wpws-row__desc">' . $desc . '</span></div>'
                . '<div class="wpws-row__field"><span class="wpwand-skel-input"></span></div>'
                . '</div>';
        }

        // Only the form panel blurs behind the spinner; the brand header + tab rail + Pro button
        // stay crisp so the page reads as "loaded, fetching your settings" — not a blurred mess.
        return '<style>'
            . '#wpwand-settings-root .wpwand-skel-panel{position:relative;min-height:320px}'
            . '#wpwand-settings-root .wpwand-skel-blur{filter:blur(3px);opacity:.6;pointer-events:none;user-select:none}'
            . '#wpwand-settings-root .wpwand-skel-input{display:block;height:38px;max-width:420px;border:1px solid #e5e7eb;'
            . 'border-radius:6px;background:linear-gradient(90deg,#f3f4f6 25%,#fafafa 37%,#f3f4f6 63%);'
            . 'background-size:400% 100%;animation:wpwand-skel-shine 1.4s ease infinite}'
            . '#wpwand-settings-root .wpwand-skel-spin{position:absolute;inset:0;display:flex;flex-direction:column;'
            . 'align-items:center;justify-content:center;gap:14px;color:#6b7280;font-size:13px;'
            . 'font-family:Inter,-apple-system,sans-serif;z-index:2}'
            . '#wpwand-settings-root .wpwand-skel-spin i{width:26px;height:26px;border:3px solid #e5e7eb;border-top-color:#2563eb;'
            . 'border-radius:50%;animation:wpwand-skel-spin .8s linear infinite;display:block}'
            . '@keyframes wpwand-skel-shine{0%{background-position:100% 0}100%{background-position:-100% 0}}'
            . '@keyframes wpwand-skel-spin{to{transform:rotate(360deg)}}'
            . '</style>'
            . '<div class="wpwand-app" role="status" aria-live="polite">'
            . '<div class="wpwand-app__header"><h1 class="wpwand-app__title">' . esc_html($name) . '</h1></div>'
            . '<div class="wpws-card">'
            . '<div class="wpws-tabs">'
            . '<button type="button" class="wpws-tab is-active">' . esc_html__('General', 'wp-wand') . '</button>'
            . '<button type="button" class="wpws-tab">' . esc_html__('Advanced', 'wp-wand') . '</button>'
            . '<span class="wpws-getpro">' . esc_html__('Get Pro Version', 'wp-wand') . '</span>'
            . '</div>'
            . '<div class="wpwand-skel-panel">'
            . '<div class="wpws-panel wpwand-skel-blur">' . $rows . '</div>'
            . '<div class="wpwand-skel-spin"><i></i><span>' . esc_html__('Loading settings…', 'wp-wand') . '</span></div>'
            . '</div>'
            . '</div></div>';
    }

    public function enqueue(string $hook): void
    {
        // Only load on our page. Submenu hooks look like "{parent}_page_{slug}".
        if (substr($hook, -strlen(self::PAGE_SLUG)) !== self::PAGE_SLUG) {
            return;
        }

        $asset_file = WPWAND_NEW_DIR . 'build/settings.asset.php';
        if (!is_readable($asset_file)) {
            return; // build not present (dev forgot `npm run build`)
        }
        $asset = require $asset_file;

        // The White Label tab picks the logo/icon from the media library (window.wp.media).
        wp_enqueue_media();

        wp_enqueue_script(
            self::HANDLE,
            WPWAND_NEW_URL . 'build/settings.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        // Inter font (same as the legacy UI) + the app's compiled stylesheet.
        wp_enqueue_style(
            'wpwand-inter-font',
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
            [],
            $asset['version']
        );
        // @wordpress/scripts emits the entry's stylesheet as style-{entry}.css.
        if (is_readable(WPWAND_NEW_DIR . 'build/style-settings.css')) {
            wp_enqueue_style(
                self::HANDLE,
                WPWAND_NEW_URL . 'build/style-settings.css',
                [],
                (string) filemtime(WPWAND_NEW_DIR . 'build/style-settings.css')
            );
        }

        wp_localize_script(
            self::HANDLE,
            'wpwandApi',
            [
                'root'  => esc_url_raw(rest_url()),
                'nonce' => wp_create_nonce('wp_rest'),
            ]
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations(self::HANDLE, 'wp-wand');
        }
    }
}
