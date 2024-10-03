<?php
/**
 * Plugin Name: WP Wand Elmentor
 * Plugin URI: https://wdelmtr.com/
 * Description: WP Wand is the generative AI platform for business that helps your team create content tailored for your brand 10X faster
 * Version: 1.0.0
 * Author: WP Wand
 * Author URI: https://wdelmtr.com/
 * Text Domain: wdelmtr
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Define constants
define('WDELMTR_VERSION', '1.0.0');
define('WDELMTR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WDELMTR_PLUGIN_URL', plugin_dir_url(__FILE__));



if (!defined('WDELMTR_OPENAI_KEY')) {
    define('WDELMTR_OPENAI_KEY', get_option('wpwand_api_key', false));
}
require __DIR__ . '/inc/elementor.php';

function wdelmtr_init()
{
    if (!is_admin()) {
        return false;
    }


}


final class WDELMTR_Extension
{

    const VERSION = '1.0.0';
    const MINIMUM_ELEMENTOR_VERSION = '2.6.0';
    const MINIMUM_PHP_VERSION = '5.6';

    private static $_instance = null;

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct()
    {

        add_action('init', [$this, 'i18n']);
        $this->init();
    }

    public function i18n()
    {
        load_plugin_textdomain('wdelmtr');
    }

    public function init()
    {

        // Check if Elementor installed and activated
        if (!has_action('init', 'wpwand_init')) {
            add_action('admin_notices', [$this, 'admin_notice_missing_main_plugin']);
            return;
        }
        // Check for required Elementor version
        if (!version_compare(ELEMENTOR_VERSION, self::MINIMUM_ELEMENTOR_VERSION, '>=')) {
            add_action('admin_notices', [$this, 'admin_notice_minimum_elementor_version']);
            return;
        }

        // Check for required PHP version
        if (version_compare(PHP_VERSION, self::MINIMUM_PHP_VERSION, '<')) {
            add_action('admin_notices', [$this, 'admin_notice_minimum_php_version']);
            return;
        }

        add_action('elementor/widgets/register', [$this, 'init_widgets']);
        add_action('elementor/editor/after_enqueue_scripts', [$this, 'wdelmtr_editor_scripts'], 100);
        add_action('wp_enqueue_scripts', array($this, 'wdelmtr_register_frontend_styles'), 10);

    }

    public function wdelmtr_editor_scripts()
    {

        wp_enqueue_style('wpwand-inter-font', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
        wp_enqueue_style('jquery-ui', WPWAND_PLUGIN_URL . 'assets/css/jquery-ui.css');
        wp_enqueue_style('wpwand-admin', WPWAND_PLUGIN_URL . 'assets/css/admin.css', ['elementor-editor']);
        $custom_css = '
        .wpwand_editor_icon button {
            background-image: url(' . wpwand_loago_icon_url() . ');
        }
        :root {
            --wpwand-brand-color: ' . wpwand_brand_color() . '
        }
        ';
        wp_add_inline_style('wpwand-admin', $custom_css);

        wp_enqueue_script('jquery-showdown', 'https://cdnjs.cloudflare.com/ajax/libs/showdown/1.9.1/showdown.min.js', ['jquery']);
        wp_enqueue_script('jquery-ui-slider');
        wp_enqueue_script('wpwand-admin', WPWAND_PLUGIN_URL . 'assets/js/admin.js', ['jquery']);

        wp_enqueue_style(
            'wdelmtr-editor',
            WDELMTR_PLUGIN_URL . 'assets/css/editor.css',
            null,
            WDELMTR_VERSION
        );
        // wp_enqueue_script(
        //     'wdelmtr-editor',
        //     WDELMTR_PLUGIN_URL . 'assets/js/editor.js',
        //     array( 'jquery' ),
        //     WDELMTR_VERSION,
        //     true
        // );

        wp_localize_script('wpwand-admin', 'wpwand_glb', array(
            'plugin_url' => WDELMTR_PLUGIN_URL,
            'ajax_url' => admin_url('admin-ajax.php'),
            'setting_url' => admin_url('admin.php?page=wdelmtr'),

        )
        );
    }

    /**
     * Load Frontend Styles
     *
     */
    public function wdelmtr_register_frontend_styles()
    {
        wp_enqueue_style(
            'themify-icons',
            WDELMTR_PLUGIN_URL . '/vendor/themify-icons/themify-icons.css',
            null,
            WDELMTR_VERSION
        );


    }

    public function admin_notice_minimum_php_version()
    {

        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }

        $message = sprintf(
            /* translators: 1: Plugin name 2: PHP 3: Required PHP version */
            esc_html__('"%1$s" requires "%2$s" version %3$s or greater.', 'wdelmtr'),
            '<strong>' . esc_html__('WP Wand Extension', 'wdelmtr') . '</strong>',
            '<strong>' . esc_html__('PHP', 'wdelmtr') . '</strong>',
            self::MINIMUM_PHP_VERSION
        );

        printf('<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message);
    }

    public function admin_notice_missing_main_plugin()
    {

        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }

        $message = sprintf(
            /* translators: 1: Plugin name 2: Elementor */
            esc_html__('"%1$s" requires "%2$s" to be installed and activated.', 'wdelmtr'),
            '<strong>' . esc_html__('WP Wand Extension', 'wdelmtr') . '</strong>',
            '<strong>' . esc_html__('Elementor', 'wdelmtr') . '</strong>'
        );

        printf('<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message);
    }

    public function admin_notice_minimum_elementor_version()
    {

        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }

        $message = sprintf(
            /* translators: 1: Plugin name 2: Elementor 3: Required Elementor version */
            esc_html__('"%1$s" requires "%2$s" version %3$s or greater.', 'wdelmtr'),
            '<strong>' . esc_html__('WP Wand Extension', 'wdelmtr') . '</strong>',
            '<strong>' . esc_html__('WP Wand', 'wdelmtr') . '</strong>',
            self::MINIMUM_ELEMENTOR_VERSION
        );

        printf('<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message);
    }

    public function init_widgets($widgets_manager)
    {

        /*
         * Extensions Include
         */

    }
}


function register_currency_control($controls_manager)
{

    require_once(WDELMTR_PLUGIN_DIR . 'inc/controls/text.php');
    require_once(WDELMTR_PLUGIN_DIR . 'inc/controls/textarea.php');
    require_once(WDELMTR_PLUGIN_DIR . 'inc/controls/wysiwyg.php');

    $controls_manager->register(new Elementor\fdwltControl_Text());
    $controls_manager->register(new Elementor\FDWELT_Control_Textarea());
    $controls_manager->register(new Elementor\FDWSELT_Control_Wysiwyg());

}
add_action('elementor/controls/register', 'register_currency_control');