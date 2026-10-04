<?php

/**
 * Plugin Name: WP Wand
 * Plugin URI: https://wpwand.com/
 * Description: Write posts, product copy and marketing text with AI, inside WordPress. Bring your own OpenAI, Claude, DeepSeek or OpenRouter key — no monthly subscription.
 * Version: 2.1.1
 * Author: WP Wand
 * Author URI: https://wpwand.com/
 * Text Domain: ai-content-generation
 * License: GPL-2.0+
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

if (!defined('ABSPATH')) {
    exit; // No direct access.
}

/*
 * New architecture (React + REST). bootstrap.php registers the PSR-4 autoloader, the wpwand/v1
 * REST API, the React admin screens, and the schema migration runner.
 */
require_once plugin_dir_path(__FILE__) . 'bootstrap.php';

/*
 * Back-compat shims for the upgrade window where this free plugin is already 2.0.0 but
 * WP Wand Pro is still on the legacy codebase. Required at load time (not on a hook) so the
 * shimmed helpers exist before Pro boots — otherwise legacy Pro would fatal on undefined
 * functions before any "please update Pro" notice could render. @see inc/legacy-compat.php
 */
require_once plugin_dir_path(__FILE__) . 'inc/legacy-compat.php';

/*
 * Installed copies of WP Wand Pro still call the licence server at its old address, which no
 * longer exists. This sends those calls to the new one. @see inc/legacy-host.php
 */
require_once plugin_dir_path(__FILE__) . 'inc/legacy-host.php';

/*
 * Bring the schema up to date the moment the plugin is switched on, rather than waiting for the
 * next wp-admin page load. `wp plugin activate` and a re-activation after an update both land here
 * with nobody in the admin; Plugin::boot() covers the cron and WP-CLI routes for the rest.
 */
register_activation_hook(__FILE__, static function () {
    (new WPWand\Data\Migrations\MigrationRunner())->run();
});

/**
 * Boot the legacy procedural side of the plugin (constants, provider keys, and the inc/* includes).
 * The new React + REST architecture is bootstrapped separately from bootstrap.php (loaded above).
 */
function wpwand_init()
{
    // No load_plugin_textdomain() call on purpose. Since WordPress 4.6 a wordpress.org plugin whose
    // text domain matches its directory slug gets its language pack loaded automatically, and this
    // one ships no bundled .mo files of its own — only the .pot. Calling it anyway is what Plugin
    // Check flags as discouraged.

    if (!function_exists('get_plugin_data')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    // Define constants
    define('WPWAND_PLUGIN_DIR', plugin_dir_path(__FILE__));
    define('WPWAND_PLUGIN_URL', plugin_dir_url(__FILE__));
    if (!defined('WPWAND_OPENAI_KEY')) {
        define('WPWAND_OPENAI_KEY', get_option('wpwand_api_key', false));
    }
    if (!defined('WPWAND_CLAUDE_KEY')) {
        define('WPWAND_CLAUDE_KEY', get_option('wpwand_claude_api_key', false));
    }
    if (!defined('WPWAND_DEEPSEEK_KEY')) {
        define('WPWAND_DEEPSEEK_KEY', get_option('wpwand_deepseek_api_key', false));
    }
    if (!defined('WPWAND_OPENROUTER_KEY')) {
        define('WPWAND_OPENROUTER_KEY', get_option('wpwand_openrouter_api_key', false));
    }
    define('WPWAND_AI_CHARACTER', '');

    if (!current_user_can('edit_posts')) {
        return false;
    }

    if (version_compare(phpversion(), '7.4', '<')) {
        add_action('admin_notices', 'wpwand_php_version_notice');

        return false;
    }
    // Only the version is needed; skip header markup/translation ($markup=false, $translate=false)
    // so this never triggers WP 6.7+'s just-in-time textdomain notice.
    define('WPWAND_VERSION', get_plugin_data(__FILE__, false, false)['Version']);

    // Usage insights (opt-in telemetry).
    if (!class_exists('Finestics\Client')) {
        require_once WPWAND_PLUGIN_DIR . 'inc/Finestics/Client.php';
    }

    $init_finestics = new Finestics\Client('wpwand', 'WP Wand', __FILE__);
    $init_finestics->insights()->init();

    // The legacy procedural inc/* code has been fully removed — the new React + REST architecture
    // (bootstrap.php) now provides every feature: settings, the AI Assistant + editor integrations
    // (Classic/Gutenberg/Elementor), generation, templates, WooCommerce, bulk and white-label.

    // Signal readiness — the Pro plugin waits on this (did_action('wpwand_init')) before booting.
    do_action('wpwand_init');
}

add_action('plugins_loaded', 'wpwand_init', 10);


function wpwand_pro_version_check()
{
    if (!is_admin()) {
        return;
    }

    // Namespaced on purpose. `force-check` belongs to core: wp-admin/update-core.php renders
    // "Check again" as update-core.php?force-check=1 with no nonce of ours, and this runs on init
    // for every admin request — so matching the bare parameter meant core's own link, and any
    // rollback/downgrade plugin that reuses it, died on check_admin_referer()'s wp_die() with
    // "The link you followed has expired." wp_verify_nonce() returns false instead of dying, so a
    // request that is not ours simply falls through.
    if (isset($_GET['wpwand-force-check'])) {
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (current_user_can('update_plugins') && wp_verify_nonce($nonce, 'wpwand_pro_force_update_check')) {
            // Pro keeps its last update answer in this transient — a failed one too, for a day in
            // 1.x and an hour in 2.0.0 — and answers from it without asking again. Clearing core's
            // cache alone left this link doing nothing on a site whose last check had failed.
            delete_transient('wpwand_pro_update');
            wp_clean_plugins_cache();
            wp_update_plugins();
            wp_safe_redirect(admin_url('plugins.php'));
            exit;
        }
    }

    // Determine the installed Pro version straight from its header (works even when the
    // legacy Pro build never defined a version constant), and require a 2.0.0+ match.
    $pro_file = WP_PLUGIN_DIR . '/wp-wand-pro/wp-wand-pro.php';
    if (!file_exists($pro_file)) {
        return;
    }
    if (!function_exists('get_plugin_data')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $pro_version = get_plugin_data($pro_file, false, false)['Version'];

    if ($pro_version && version_compare($pro_version, '2.0.0', '<')) {
        add_action('admin_notices', function () {
            $force_update_url = wp_nonce_url(admin_url('admin.php?page=wpwand&wpwand-force-check=1'), 'wpwand_pro_force_update_check');

            echo '<div class="notice notice-error"><p>';
            printf(
                /* translators: %s: URL to trigger a plugin update check */
                wp_kses(__('<strong>Action required:</strong> WP Wand Pro must be updated to version 2.0.0 or higher to match WP Wand 2.0.0. Your Pro features are paused until the update completes. <a href="%s">Update now</a>', 'ai-content-generation'), ['a' => ['href' => []], 'strong' => []]),
                esc_url($force_update_url)
            );
            echo '</p></div>';
        });
    }
}
add_action('init', 'wpwand_pro_version_check');

// Hook into the 'admin_init' action
add_action('admin_init', 'wpwand_activation_redirect');

// Activation redirect function
function wpwand_activation_redirect()
{

    if (get_option('wpwand_activation_redirect', false)) {
        // Redirect to a specific page or URL after activation
        delete_option('wpwand_activation_redirect');
        // New React welcome screen (replaces the legacy ?welcome_screen page).
        wp_safe_redirect(admin_url('admin.php?page=wpwand-welcome'));
        exit;
    }
}

// Hook into the 'activated_plugin' action — show the welcome screen on first activation.
add_action('activated_plugin', 'wpwand_set_activation_redirect');

// Set activation redirect flag
function wpwand_set_activation_redirect($plugin)
{
    if ($plugin === plugin_basename(__FILE__)) {
        // Set the option to redirect after activation
        update_option('wpwand_activation_redirect', true);
    }
}


// write a wpwand_php_version_notice function
function wpwand_php_version_notice()
{
    echo '<div class="error"><p>' . esc_html__('WP Wand needs PHP 7.4 or newer. Ask your host to update PHP, then activate WP Wand again.', 'ai-content-generation') . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
