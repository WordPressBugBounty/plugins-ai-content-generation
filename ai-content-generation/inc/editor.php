<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_head', 'wpwand_ai_buttons');
function wpwand_ai_buttons()
{
    if (is_admin() && current_user_can('manage_options')) {
        ?>
        <script>
            var wpwand_editor_wp_nonce = '<?php echo wp_create_nonce('wpwand-ajax-nonce') ?>';
        </script>
        <?php
        if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) {
            return;
        }
        if ('true' == get_user_option('rich_editing')) {
            $wpwand_editor_button_menus = apply_filters( 'wpwand_editor_prompts', wpwand_editor_prompts() );
    
            ?>
            <script>
                var wpwand_plugin_url = '<?php echo esc_html(WPWAND_PLUGIN_URL) ?>';
                var wpwand_editor_ajax_url = '<?php echo esc_html(admin_url('admin-ajax.php')) ?>';
                var wpwandTinymceEditorMenus = <?php echo _wp_specialchars(json_encode($wpwand_editor_button_menus, JSON_UNESCAPED_UNICODE), ENT_NOQUOTES, 'UTF-8', true) ?>;
                var wpwandEditorChangeAction = 'below';
            </script>
            <?php
            add_filter('mce_external_plugins', 'wpwand_add_buttons');
            add_filter('mce_buttons', 'wpwand_register_buttons');
            add_filter('mce_css', 'wpwand_classic_mce_css');
        }
    }
}

function wpwand_classic_mce_css($mce_css)
{
    if (!empty($mce_css)) {
        $mce_css .= ',';
    }
    $mce_css .= WPWAND_PLUGIN_URL . 'assets/css/classic-editor.css';

    return $mce_css;

}

function wpwand_add_buttons($plugins)
{
    if (current_user_can('manage_options')) {
        $plugins['wpwandeditor'] = WPWAND_PLUGIN_URL . 'assets/js/classic-editor.js';
    }
    return $plugins;
}

function wpwand_register_buttons($buttons)
{
    array_push($buttons, 'wpwandeditor');
    return $buttons;
}