<?php
use Orhanerday\OpenAi\OpenAi;

add_action('admin_enqueue_scripts', 'wpwand_block_editor');

add_action('enqueue_block_editor_assets', 'wpwand_block_editor', 9);
function wpwand_block_editor()
{
    $wpwand_editor_button_menus = apply_filters('wpwand_editor_prompts', wpwand_editor_prompts());
    if (is_admin() && current_user_can('manage_options')) {
        wp_enqueue_script(
            'wpwand-gutenberg-custom-button',
            WPWAND_PLUGIN_URL . 'assets/js/wpwand-gutenberg.js',
            ['wp-editor', 'wp-i18n', 'wp-element', 'wp-compose', 'wp-components'],
            '1.0.0',
            true
        );


        wp_localize_script(
            'wpwand-gutenberg-custom-button',
            'wpwand_gutenberg_editor',
            array(
                'plugin_url' => WPWAND_PLUGIN_URL,
                'editor_ajax_url' => admin_url('admin-ajax.php'),
                'editor_menus' => $wpwand_editor_button_menus,
                'change_action' => 'below',
            )
        );
    }
}





function wpwand_editor_request()
{
    $wpaicg_result = array('status' => 'error', 'msg' => 'Missing request parameters');
    // if ( !wp_verify_nonce( $_POST['nonce'], 'wpaicg-ajax-nonce' ) ) {
    //     $wpaicg_result['status'] = 'error';
    //     $wpaicg_result['msg']    = WPAICG_NONCE_ERROR;
    //     wp_send_json( $wpaicg_result );
    // }
    if (isset($_POST['prompt']) && !empty($_POST['prompt'])) {
        $command = $_POST['prompt'];
        $selected_model = get_option('wpwand_model', 'gpt-3.5-turbo');
        $busines_details = get_option('wpwand_busines_details');
        $targated_customer = get_option('wpwand_targated_customer');
        $language = wpwand_get_option('wpwand_language', 'English');


        $content = wpwand_openAi($command);

        if (!$content->choices) {

            $wpaicg_result['msg'] = 'Something went wrong';
        } else {
            foreach ($content->choices as $choice) {

                $cnt = isset($choice->message) ? $choice->message->content : $choice->text;
                $wpaicg_result['status'] = 'success';
                $wpaicg_result['data'] = str_replace("\n", '<br>', $cnt);
            }
        }
    }
    wp_send_json($wpaicg_result);
}

add_action('wp_ajax_wpwand_editor_request', 'wpwand_editor_request');
add_action('wp_ajax_nopriv_wpwand_editor_request', 'wpwand_editor_request');