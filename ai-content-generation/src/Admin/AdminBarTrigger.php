<?php

namespace WPWand\Admin;

use WPWand\Data\Brand;

/**
 * Adds the "AI Assistant" node to the admin bar — the entry point the React assistant panel opens
 * from (its click is wired in assets/src/apps/assistant). Port of the legacy wpwand_admin_bar_menu()
 * (inc/helper-functions.php); only shown when the toggler position is "top", matching legacy.
 *
 * The panel also renders its own floating button, so this is a secondary, convenience entry point.
 */
final class AdminBarTrigger
{
    public function register(): void
    {
        if (is_admin() && get_option('toggler_position', 'top') === 'top') {
            add_action('admin_bar_menu', [$this, 'add_node'], 999);
        }
    }

    /** @param \WP_Admin_Bar $bar */
    public function add_node($bar): void
    {
        $icon = Brand::resolve()['icon'];
        $bar->add_menu([
            'id'    => 'wpwand-trigger',
            'title' => '<img style="width:18px;height:18px;vertical-align:middle;margin-right:4px" src="' . esc_url($icon) . '"> ' . esc_html__('AI Assistant', 'wp-wand'),
            'href'  => '#',
            'meta'  => ['class' => 'wpwand-trigger'],
        ]);
    }
}
