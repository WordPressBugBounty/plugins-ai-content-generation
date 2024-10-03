<?php
/**
 * Plugin Name: WP Wand
 * Plugin URI: https://wpwand.com/
 * Description: WP Wand is a AI content generation plugin for WordPress that helps your team create high quality content 10X faster and 50x cheaper. No monthly subscription required.
 * Version: 1.2.5
 * Author: WP Wand
 * Author URI: https://wpwand.com/
 * Text Domain: wpwand
 * License: GPL-2.0+
 * Requires PHP: 7.4
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */
if (!function_exists('get_plugin_data')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
// Define constants
define('WPWAND_VERSION', get_plugin_data(__FILE__)['Version']);

define('WPWAND_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPWAND_PLUGIN_URL', plugin_dir_url(__FILE__));
if (!defined('WPWAND_OPENAI_KEY')) {
    define('WPWAND_OPENAI_KEY', get_option('wpwand_api_key', false));
}
define('WPWAND_AI_CHARACTER', '');

function wpwand_init()
{
    if (!current_user_can('manage_options')) {
        return false;
    }


    // Vendor Autoload
    if (!class_exists('Orhanerday\OpenAi\OpenAi')) {
        require __DIR__ . '/vendor/orhanerday/open-ai/src/Url.php';
        require __DIR__ . '/vendor/orhanerday/open-ai/src/OpenAi.php';
    }


    // global $sdk_license;
    if (!class_exists('Finestics\Client')) {
        require_once WPWAND_PLUGIN_DIR . 'inc/Finestics/Client.php';
    }

    $init_finestics = new Finestics\Client('wp-wand', 'WP Wand', __FILE__);
    $init_finestics->insights()->init();


    // Include required files
    require_once WPWAND_PLUGIN_DIR . 'inc/config.php';
    require_once WPWAND_PLUGIN_DIR . 'inc/editor.php';
    require_once WPWAND_PLUGIN_DIR . 'inc/admin.php';
    require_once WPWAND_PLUGIN_DIR . 'inc/data.php';
    require_once WPWAND_PLUGIN_DIR . 'inc/helper-functions.php';
    require_once WPWAND_PLUGIN_DIR . 'inc/frontend.php';
    require_once WPWAND_PLUGIN_DIR . 'inc/api.php';
    require_once WPWAND_PLUGIN_DIR . 'inc/WooCommerce.php';

    if (!function_exists('wpwand_pro_init')) {
        require_once WPWAND_PLUGIN_DIR . 'inc/post-generator.php';
    }


    $is_agency = wpwand_get_option('wpwand_pro_tala_agency');

    if (!$is_agency) {
        require_once WPWAND_PLUGIN_DIR . 'inc/white-label.php';
    }

    require_once WPWAND_PLUGIN_DIR . 'inc/gutenberg.php';
    if (defined('ELEMENTOR_VERSION')) {
        require_once WPWAND_PLUGIN_DIR . 'inc/modules/elementor/wp-wand-elementor.php';
        WDELMTR_Extension::instance();
    }
    do_action('wpwand_init');
}

add_action('init', 'wpwand_init', 10);

/**
 * Load plugin textdomain.
 */
function wpwand_load_plugin_textdomain()
{
    load_plugin_textdomain('wpwand', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'wpwand_load_plugin_textdomain');

// Hook into the 'admin_init' action
add_action('admin_init', 'wpwand_activation_redirect');

// Activation redirect function
function wpwand_activation_redirect()
{

    if (get_option('wpwand_activation_redirect', false)) {
        // Redirect to a specific page or URL after activation
        delete_option('wpwand_activation_redirect');
        wp_safe_redirect(admin_url('admin.php?page=wpwand&welcome_screen'));
        exit;
    }
}

// Hook into the 'activated_plugin' action
add_action('activated_plugin', 'wpwand_set_activation_redirect');

// Set activation redirect flag
function wpwand_set_activation_redirect($plugin)
{
    if ($plugin === plugin_basename(__FILE__)) {
        // Set the option to redirect after activation
        update_option('wpwand_activation_redirect', true);
    }
}