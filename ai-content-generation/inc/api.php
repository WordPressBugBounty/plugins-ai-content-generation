<?php
// restrict direct access

use Orhanerday\OpenAi\OpenAi;

if (!defined('ABSPATH')) {
    exit('You are not allowed');
}

function wpwand_request()
{

    // Check if prompt parameter exists
    if (isset($_POST['wpwand_image_prompt']) && !empty($_POST['wpwand_image_prompt'])) {
        return wpwand_dall_e_request($_POST['wpwand_image_prompt'], $_POST);
    }
    if (empty($_POST['prompt'])) {
        wp_send_json_error('error');
    }

    $selected_model = get_option('wpwand_model', 'gpt-3.5-turbo');
    $is_elementor = 'true' == $_POST['is_elementor'] ? '<span class="wpwand-insert-to-widget" >Insert to Elementor</span>' : '';
    $is_gutenberg = 'true' == $_POST['is_gutenberg'] ? '<span class="wpwand-insert-to-gutenberg" >Insert to Editor</span>' : '';
    $point_of_view = isset($_POST['point_of_view']) ? $_POST['point_of_view'] : false;
    $person_cmd = " The content must be written in $point_of_view ";
    $biz_details = '';
    $targated_customer = '';
    $language = wp_kses_post($_POST['language'] ?? '');
    // Sanitize and validate input fields
    $fields = array(
        'topic' => sanitize_text_field($_POST['topic'] ?? ''),
        'keywords' => sanitize_text_field($_POST['keyword'] ?? ''),
        'no_of_results' => absint($_POST['result_number'] ?? 1),
        'tone' => sanitize_text_field($_POST['tone'] ?? ''),
        // 'writing_style' => sanitize_text_field($_POST['writing_style'] ?? ''),
        'word_count' => isset($_POST['word_limit']) ? intval($_POST['word_limit']) + 1000 : '',
        'product_name' => sanitize_text_field($_POST['product_name'] ?? ''),
        'description' => sanitize_text_field($_POST['description'] ?? ''),
        'content' => wp_kses_post($_POST['content'] ?? ''),
        'content_textarea' => wp_kses_post($_POST['content_textarea'] ?? ''),
        'custom_textarea' => wp_kses_post($_POST['custom_textarea'] ?? ''),
        'product_1' => wp_kses_post($_POST['product_1'] ?? ''),
        'product_2' => wp_kses_post($_POST['product_2'] ?? ''),
        'description_1' => wp_kses_post($_POST['description_1'] ?? ''),
        'description_2' => wp_kses_post($_POST['description_2'] ?? ''),
        'subject' => sanitize_text_field($_POST['subject'] ?? ''),
        'question' => sanitize_text_field($_POST['question'] ?? ''),
        'comment' => sanitize_text_field($_POST['comment'] ?? ''),
    );

    // Replace fields in prompt with values
    $command = preg_replace_callback(
        '/\{([^}]+)\}/',
        function ($matches) use ($fields) {
            $key = trim($matches[1]);
            return isset($fields[$key]) ? $fields[$key] : '';
        },
        sanitize_text_field($_POST['prompt'])
    );

    $args = [
        'language' => $language
    ];

    $content = wpwand_openAi("$command. $person_cmd ", (int) $fields['no_of_results'], $args);

    $text = '';
    if (isset($content->choices)) {
        foreach ($content->choices as $choice) {
            $reply = isset($choice->message) ? $choice->message->content : $choice->text;

            $text .= '<div class="wpwand-content">

            <button class="wpwand-copy-button" >
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M3.66659 3.08333V7.75C3.66659 8.39433 4.18892 8.91667 4.83325 8.91667H8.33325M3.66659 3.08333V1.91667C3.66659 1.27233 4.18892 0.75 4.83325 0.75H7.50829C7.663 0.75 7.81138 0.811458 7.92077 0.920854L10.4957 3.49581C10.6051 3.60521 10.6666 3.75358 10.6666 3.90829V7.75C10.6666 8.39433 10.1443 8.91667 9.49992 8.91667H8.33325M3.66659 3.08333H3.33325C2.22868 3.08333 1.33325 3.97876 1.33325 5.08333V10.0833C1.33325 10.7277 1.85559 11.25 2.49992 11.25H6.33325C7.43782 11.25 8.33325 10.3546 8.33325 9.25V8.91667" stroke="white" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Copy to Clipboard
            </button>
            ' . $is_elementor . $is_gutenberg . '<div class="wpwand-ai-response">' . wpautop($reply) . '
            </div></div>';

        }
    } elseif (isset($content->error)) {
        $text .= '<div class="wpwand-content wpwand-prompt-error">';
        $text .= wpwand_openAi_error($content->error);

        $text .= '  </div>';
    }
    wp_send_json($text);
}
add_action('wpwand_ajax_api', 'wpwand_request');

function wpwand_request_hook()
{
    do_action('wpwand_ajax_api');
}

// Register AJAX action for logged-in and non-logged-in users
add_action('wp_ajax_wpwand_request', 'wpwand_request_hook');
add_action('wp_ajax_nopriv_wpwand_request', 'wpwand_request_hook');

function wpwand_api_set()
{

    // Check if prompt parameter exists
    if (empty($_POST['api_key'])) {
        wp_send_json_error('Please enter your api key');
    }

    if (!preg_match('/^sk-/', $_POST['api_key'])) {
        wp_send_json_error('Invalid api key.');
    }


    // Sanitize and validate input fields
    $api_key = sanitize_text_field($_POST['api_key'] ?? '');

    $set_api_key = update_option('wpwand_api_key', $api_key);

    if ($set_api_key || get_option('wpwand_api_key') == $_POST['api_key']) {

        $content = wpwand_openAi('Just check the openai key is valid');
        if (!wpwand_check_api_key()) {
            delete_option('wpwand_api_key');
            // wp_send_json_error($content->error);
            wp_send_json_error('Your OpenAI api key is either invalid or expired.');
        }
        wp_send_json('success');
    }

    wp_send_json_error('Something went wrong.');

}

// Register AJAX action for logged-in and non-logged-in users
add_action('wp_ajax_wpwand_api_set', 'wpwand_api_set');
add_action('wp_ajax_nopriv_wpwand_api_set', 'wpwand_api_set');


function wpwand_check_api_key()
{
    $content = wpwand_openAi('Just check the openai key is valid');
    if (isset($content->error)) {
        return false;
    }
    return true;
}

function wpwand_only_prompt()
{

    // Check if prompt parameter exists
    if (empty($_POST['prompt'])) {
        wp_send_json_error('error');
    }

    $selected_model = get_option('wpwand_model', 'gpt-3.5-turbo');
    $biz_details = '';
    $targated_customer = '';
    $language = wpwand_get_option('wpwand_language', 'English');
    // Sanitize and validate input fields
    $prompt = sanitize_text_field($_POST['prompt'] ?? '');
    $rawResponse = isset($_POST['rawResponse']) && true == $_POST['rawResponse'] ? true : false;

    $is_table_format_prompt = $rawResponse ? '' : 'You must give output with html tags';



    $content = wpwand_openAi($prompt . $is_table_format_prompt, 1, ['language' => $language]);

    $text = '';
    if (isset($content->choices)) {
        foreach ($content->choices as $choice) {
            $reply = isset($choice->message) ? $choice->message->content : $choice->text;

            if (!$rawResponse) {

                $text .= '<div class="wpwand-content">
    
                <div class="wpwand-ai-response">' . wpautop($reply) . '
                </div></div>';
            } else {
                $text .= $reply;
            }

        }
    } elseif (isset($content->error)) {
        $text .= '<div class="wpwand-content wpwand-prompt-error">';
        $text .= wpwand_openAi_error($content->error);
        $text .= '  </div>';
    }
    wp_send_json($text);
}

add_action('wp_ajax_wpwand_only_prompt', 'wpwand_only_prompt');
add_action('wp_ajax_nopriv_wpwand_only_prompt', 'wpwand_only_prompt');

add_action('wp_ajax_wpwand_download_image', 'wpwand_download_image');
add_action('wp_ajax_nopriv_wpwand_download_image', 'wpwand_download_image');

function wpwand_download_image()
{

    $image_link = isset($_POST['image_url']) ? $_POST['image_url'] : false;
    $image_name = isset($_POST['image_name']) ? str_replace(' ', '_', $_POST['image_name']) : 'image';
    if ($image_link) {

        wp_send_json(wpwand_insert_media($image_link, $image_name));

    }
}

function wpwand_dall_e_request($prompt, $args = [])
{
    // Call OpenAI API to generate content
    $openAI = new OpenAi(WPWAND_OPENAI_KEY);

    $no_of_result = isset($_POST['result_number']) ? $_POST['result_number'] : 1;
    $image_resulation = isset($_POST['image_resulation']) ? $_POST['image_resulation'] : '256x256';

    $complete = $openAI->image([
        "prompt" => $prompt,
        "n" => (int) $no_of_result,
        "size" => $image_resulation,
        "response_format" => "url",
    ]);

    $content = json_decode($complete);

    $text = '';
    if (isset($content->data)) {
        $count = count($content->data);
        $i = 0;
        foreach ($content->data as $image) {
            $i++;
            // if grater then 1
            $version_info = $count > 1 ? "Version $i of $prompt" : $prompt;
            // $download_url = isset(wpwand_insert_media($image->url)['url']) ? wpwand_insert_media($image->url)['url']: '';

            $text .= '<div class="wpwand-content">

            <div class="wpwand-ai-response wpwand-dall-e">
            <img src="' . $image->url . '" >
            <div class="wpwand-ai-image-content">
            <div class="wpwand-ai-image-result-content">
            <h4> ' . $version_info . ' </h4>
            <p>Resolution: ' . $image_resulation . '</p>
            </div>
            <div class="wpwand-ai-image-actions">
            <button data-name="' . $prompt . '" data-url="' . $image->url . '" class="wpwand-image-action insert">
                <svg width="10" height="10" viewBox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M5 1.5V5M5 5V8.5M5 5H8.5M5 5L1.5 5" stroke="white" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>Add to Media</span>
            </button>
            </div>
            </div>
            </div></div>';

        }
    } elseif (isset($content->error)) {
        $text .= '<div class="wpwand-content wpwand-prompt-error">';
        $text .= wpwand_openAi_error($content->error);
        $text .= '  </div>';
    }
    wp_send_json($text);
}

function wpwand_insert_media($url, $file_name = 'ai-generated-image')
{

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $image_url = 'http://example.com/' . $file_name . '.jpg';

    $tmp = download_url($url);

    $file_array = array(
        'name' => basename($image_url),
        'tmp_name' => $tmp,
    );

    $id = media_handle_sideload($file_array, 0);

    if (is_wp_error($id)) {
        @unlink($file_array['tmp_name']);
        return $id;
    }
    $attachment = array();
    $attachment['id'] = $id;
    $attachment['url'] = wp_get_attachment_url($id);
    return $attachment;

}





function wpwand_openAi($prompt, $number_of_result = 1, $args = [])
{

    $selected_model = isset($args['model']) ? $args['model'] : wpwand_get_option('wpwand_model', 'gpt-3.5-turbo');

    $biz_details = isset($args['biz_details']) && !empty($args['biz_details']) ? "Write this based on our business details, which this: " . $args['biz_details'] : '';
    $targated_customer = isset($args['targated_customer']) && !empty($args['targated_customer']) ? "Write this focusing the benefits of our targeted customer, which this:" . $args['targated_customer'] : '';


    // $ai_character = isset($args['ai_character']) && !empty($args['ai_character']) ?  $args['ai_character'] : '';


    $language = isset($args['language']) && !empty($args['language']) ? $args['language'] : wpwand_get_option('wpwand_language', 'English');

    $davinci_command = "You must write in $language. $prompt $biz_details  $targated_customer";

    $temperature = isset($args['temperature']) ? $args['temperature'] : (int) wpwand_get_option('wpwand_temperature', 1.0);
    $max_tokens = isset($args['max_tokens']) ? $args['max_tokens'] : wpwangd_get_max_token($davinci_command, $selected_model);
    $frequency_penalty = isset($args['frequency_penalty']) ? $args['frequency_penalty'] : (int) wpwand_get_option('wpwand_frequency', 0);
    $presence_penalty = isset($args['presence_penalty']) ? $args['presence_penalty'] : (int) wpwand_get_option('wpwand_presence_penalty', 0);


    // Call OpenAI API to generate content
    $openAI = new OpenAi(get_option('wpwand_api_key'));

    if ('gpt-3.5-turbo' == $selected_model || 'gpt-3.5-turbo-16k' == $selected_model || 'gpt-4' == $selected_model || 'gpt-4o' == $selected_model) {

        $complete = $openAI->chat([
            'model' => $selected_model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => " $prompt You must write in $language. $biz_details $targated_customer"
                ]
            ],
            'n' => $number_of_result < 1 ? 1 : $number_of_result,
            'temperature' => (int) $temperature,
            'max_tokens' => (int) $max_tokens,
            'frequency_penalty' => (int) $frequency_penalty,
            'presence_penalty' => (int) $presence_penalty,
        ]);
    } else {
        $complete = $openAI->completion([
            'n' => $number_of_result < 1 ? 1 : $number_of_result,
            'model' => $selected_model,
            'prompt' => $davinci_command,
            'temperature' => (int) $temperature,
            'max_tokens' => (int) $max_tokens,
            'frequency_penalty' => (int) $frequency_penalty,
            'presence_penalty' => (int) $presence_penalty,
        ]);

    }

    return json_decode($complete);
    // return $davinci_command

}


function wpwand_openAi_error($error)
{
    $text = '';

    $text .= 'OpenAI Error: ' . $error->message;

    return $text;
}