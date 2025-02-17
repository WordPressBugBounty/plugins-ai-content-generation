<?php
// restrict direct access

use ElliotJReed\AI\ClaudeAI\Prompt;
use ElliotJReed\AI\Entity\Request;
use Orhanerday\OpenAi\OpenAi;

if (!defined('ABSPATH')) {
    exit('You are not allowed');
}

function wpwand_request()
{

    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wpwand_global_nonce')) {
        wp_send_json_error(__('Nonce verification failed.', 'wp-wand'), 403);
    }

    // Check if prompt parameter exists
    if (isset($_POST['wpwand_image_prompt']) && !empty($_POST['wpwand_image_prompt'])) {
        return wpwand_dall_e_request(sanitize_text_field(wp_unslash($_POST['wpwand_image_prompt'])), $_POST);
    }
    if (empty($_POST['prompt'])) {
        wp_send_json_error(__('error', 'wp-wand'));
    }

    $selected_model = get_option('wpwand_model', 'gpt-3.5-turbo');
    $is_elementor = isset($_POST['is_elementor']) && 'true' == $_POST['is_elementor'] ? '<span class="wpwand-insert-to-widget" >Insert to Elementor</span>' : '';
    $is_gutenberg = isset($_POST['is_gutenberg']) && 'true' == $_POST['is_gutenberg'] ? '<span class="wpwand-insert-to-gutenberg" >Insert to Editor</span>' : '';
    $point_of_view = isset($_POST['point_of_view']) ? sanitize_text_field(wp_unslash($_POST['point_of_view'])) : false;
    $person_cmd = " The content must be written in $point_of_view ";
    $biz_details = '';
    $targated_customer = '';
    $language = isset($_POST['language']) ? wp_kses_post(sanitize_text_field(wp_unslash($_POST['language']))) : '';
    // Sanitize and validate input fields
    $fields = wpwand_api_fields_validate();

    // Replace fields in prompt with values
    $command = preg_replace_callback(
        '/\{([^}]+)\}/',
        function ($matches) use ($fields) {
            $key = trim($matches[1]);
            return isset($fields[$key]) ? $fields[$key] : '';
        },
        sanitize_text_field(wp_unslash($_POST['prompt']))
    );

    $args = [
        'language' => $language,
        'model' => $selected_model
    ];

    // var_dump(wpwand_api_source($selected_model)); 
    // die();

    $content = wpwand_generate_ai_content("$command. $person_cmd ", (int) $fields['no_of_results'], $args);


    $text = '';
    if (isset($content->choices)) {
        foreach ($content->choices as $choice) {
            $reply = isset($choice->message) ? $choice->message->content : $choice->text;
            $reasoning_content = isset($choice->message->reasoning_content) ? $choice->message->reasoning_content : '';

            // <div class="wpwand-ai-reasoning">
            // ' . $reasoning_content . '
            // </div>

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
        $text .= wpwand_ai_error($content->error);
        $text .= '  </div>';
    }
    wp_send_json($text);
}
add_action('wpwand_ajax_api', 'wpwand_request');


if (!function_exists('wpwand_api_fields_validate')) {
    function wpwand_api_fields_validate()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wpwand_global_nonce')) {
            wp_send_json_error('Nonce verification failed.', 403);
        }
        return array(
            'topic' => isset($_POST['topic']) ? sanitize_text_field(wp_unslash($_POST['topic'])) : '',
            'keywords' => isset($_POST['keyword']) ? sanitize_text_field(wp_unslash($_POST['keyword'])) : '',
            'no_of_results' => isset($_POST['result_number']) ? absint(sanitize_text_field(wp_unslash($_POST['result_number']))) : 1,
            'tone' => isset($_POST['tone']) ? sanitize_text_field(wp_unslash($_POST['tone'])) : '',
            // 'writing_style' isset($_POST['writing_style') ? => sanitize_text_field(wp_unslash($_POST['writing_style')] ) : '',
            'word_count' => isset($_POST['word_limit']) ? intval(sanitize_text_field(wp_unslash($_POST['word_limit']))) + 1000 : '',
            'product_name' => isset($_POST['product_name']) ? sanitize_text_field(wp_unslash($_POST['product_name'])) : '',
            'description' => isset($_POST['description']) ? sanitize_text_field(wp_unslash($_POST['description'])) : '',
            'content' => isset($_POST['content']) ?  wp_kses_post(sanitize_text_field(wp_unslash($_POST['content']))) : '',
            'content_textarea' => isset($_POST['content_textarea']) ?  wp_kses_post(sanitize_text_field(wp_unslash($_POST['content_textarea']))) : '',
            'custom_textarea' => isset($_POST['custom_textarea']) ?  wp_kses_post(sanitize_text_field(wp_unslash($_POST['custom_textarea']))) : '',
            'product_1' => isset($_POST['product_1']) ?  wp_kses_post(sanitize_text_field(wp_unslash($_POST['product_1']))) : '',
            'product_2' => isset($_POST['product_2']) ?  wp_kses_post(sanitize_text_field(wp_unslash($_POST['product_2']))) : '',
            'description_1' => isset($_POST['description_1']) ?  wp_kses_post(sanitize_text_field(wp_unslash($_POST['description_1']))) : '',
            'description_2' => isset($_POST['description_2']) ?  wp_kses_post(sanitize_text_field(wp_unslash($_POST['description_2']))) : '',
            'subject' => isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '',
            'question' => isset($_POST['question']) ? sanitize_text_field(wp_unslash($_POST['question'])) : '',
            'comment' => isset($_POST['comment']) ? sanitize_text_field(wp_unslash($_POST['comment'])) : '',
        );
    }
}

function wpwand_request_hook()
{


    do_action('wpwand_ajax_api');
}

// Register AJAX action for logged-in and non-logged-in users
add_action('wp_ajax_wpwand_request', 'wpwand_request_hook');
add_action('wp_ajax_nopriv_wpwand_request', 'wpwand_request_hook');

function wpwand_api_set()
{

    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wpwand_global_nonce')) {
        wp_send_json_error('Nonce verification failed.', 403);
    }
    // Check if prompt parameter exists
    if (empty($_POST['api_key'])) {
        wp_send_json_error('Please enter your api key');
    }

    if (!preg_match('/^sk-/', sanitize_text_field(wp_unslash($_POST['api_key'])))) {
        wp_send_json_error('Invalid api key.');
    }


    // Sanitize and validate input fields
    $api_key = sanitize_text_field(wp_unslash($_POST['api_key'])) ?? '';

    $set_api_key = update_option('wpwand_api_key', $api_key);

    if ($set_api_key || get_option('wpwand_api_key') == $_POST['api_key']) {

        $content = wpwand_generate_ai_content('Just check the openai key is valid');
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
    $content = wpwand_generate_ai_content('Just check the openai key is valid');
    if (isset($content->error)) {
        return false;
    }
    return true;
}

function wpwand_only_prompt()
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wpwand_global_nonce')) {
        wp_send_json_error('Nonce verification failed.', 403);
    }

    // Check if prompt parameter exists
    if (empty($_POST['prompt'])) {
        wp_send_json_error('error');
    }

    $selected_model = get_option('wpwand_model', 'gpt-3.5-turbo');
    $biz_details = '';
    $targated_customer = '';
    $language = wpwand_get_option('wpwand_language', 'English');
    // Sanitize and validate input fields
    $prompt = sanitize_text_field(wp_unslash($_POST['prompt'])) ?? '';
    $rawResponse = isset($_POST['rawResponse']) && true == $_POST['rawResponse'] ? true : false;

    $is_table_format_prompt = $rawResponse ? '' : 'You must give output with html tags';



    $content = wpwand_generate_ai_content($prompt . $is_table_format_prompt, 1, ['language' => $language]);

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
        $text .= wpwand_ai_error($content->error);
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
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wpwand_global_nonce')) {
        wp_send_json_error('Nonce verification failed.', 403);
    }

    $image_link = isset($_POST['image_url']) ? sanitize_text_field(wp_unslash($_POST['image_url'])) : false;
    $image_name = isset($_POST['image_name']) ? str_replace(' ', '_', sanitize_text_field(wp_unslash($_POST['image_name']))) : 'image';
    if ($image_link) {

        wp_send_json(wpwand_insert_media($image_link, $image_name));
    }
}

function wpwand_dall_e_request($prompt, $args = [])
{

    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wpwand_global_nonce')) {
        wp_send_json_error('Nonce verification failed.', 403);
    }
    // Call OpenAI API to generate content
    $openAI = new OpenAi(WPWAND_OPENAI_KEY);

    $no_of_result = isset($_POST['result_number']) ? sanitize_text_field(wp_unslash($_POST['result_number'])) : 1;
    $image_resulation = isset($_POST['image_resulation']) ? sanitize_text_field(wp_unslash($_POST['image_resulation'])) : '256x256';

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
            <img src="' . $image->url /* // phpcs:ignore */ . '" > 
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
            </div></div>';  // phpcs:ignore
        }
    } elseif (isset($content->error)) {
        $text .= '<div class="wpwand-content wpwand-prompt-error">';
        $text .= wpwand_ai_error($content->error);
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
        wp_delete_file($file_array['tmp_name']);
        return $id;
    }
    $attachment = array();
    $attachment['id'] = $id;
    $attachment['url'] = wp_get_attachment_url($id);
    return $attachment;
}


function wpwand_api_source($model = '')
{
    if ($model && strpos($model, 'claude') !== false) {
        return 'claude';
    }
    if ($model && strpos($model, 'deepseek') !== false) {
        return 'deepseek';
    }
    return 'openai';
}

function wpwand_ai_error($error)
{
    $source = isset($error->type) && strpos($error->type, 'claude') !== false ? 'claude' : (isset($error->type) && strpos($error->type, 'deepseek') !== false ? 'deepseek' : 'openai');
    $provider = ucfirst($source);
    // if curl error then add server error / server is not responding
    if (isset($error->message) && strpos($error->message, 'curl') !== false) {
        return "{$provider} Error: Server is not responding";
    }
    return "{$provider} Error: " . $error->message;
}

/* 
// deprecated 
function wpwand_claude($prompt, $number_of_result = 1, $args = []) 
{
    try {
        $selected_model = isset($args['model']) ? $args['model'] : wpwand_get_option('wpwand_model', 'gpt-3.5-turbo');

        $biz_details = isset($args['biz_details']) && !empty($args['biz_details']) ? "Write this based on our business details, which this: " . $args['biz_details'] : '';
        $targated_customer = isset($args['targated_customer']) && !empty($args['targated_customer']) ? "Write this focusing the benefits of our targeted customer, which this:" . $args['targated_customer'] : '';

        $language = isset($args['language']) && !empty($args['language']) ? $args['language'] : wpwand_get_option('wpwand_language', 'English');

        $temperature = isset($args['temperature']) ? $args['temperature'] : (int) wpwand_get_option('wpwand_temperature', 1.0);
        $max_tokens = isset($args['max_tokens']) ? $args['max_tokens'] : wpwangd_get_max_token($prompt, $selected_model);

        if ('claude' == wpwand_api_source($selected_model)) {
            $wpwand_api = new Prompt(WPWAND_CLAUDE_KEY, 'claude-3-haiku-20240307');
        } else {
            $wpwand_api = new Prompt(WPWAND_OPENAI_KEY, $selected_model);
        }

        $choices = [];
        for ($i = 0; $i < $number_of_result; $i++) {
            $request = (new Request())
                ->setRole('system')
                ->setInstructions("You must write in $language. $biz_details $targated_customer")
                ->setInput("$prompt")
                ->setTemperature($temperature)
                ->setMaximumTokens($max_tokens);

            $response = $wpwand_api->send($request);

            $choices[] = (object) [
                'message' => (object) [
                    'content' => $response->getContent() . \PHP_EOL
                ]
            ];
        }

        return (object) [
            'choices' => $choices
        ];
    } catch (\Exception $e) {
        return (object) [
            'error' => (object) [
                'message' => $e->getMessage(),
                'type' => 'claude_error',
                'code' => $e->getCode()
            ]
        ];
    }
}

 */

function wpwand_openAi($prompt, $number_of_result = 1, $args = [])
{
    // return json_decode($complete);
    return wpwand_generate_ai_content($prompt, $number_of_result, $args);
    // return $davinci_command

}


function wpwand_openAi_error($error)
{
    $text = '';

    $text .= 'OpenAI Error: ' . $error->message;

    return $text;
}

function wpwand_generate_claude_content($prompt, $number_of_result, $args, $request_config)
{

    $endpoint = 'https://api.anthropic.com/v1/messages';
    $request_config['headers'] = array_merge($request_config['headers'], array(
        'x-api-key' => WPWAND_CLAUDE_KEY,
        'anthropic-version' => '2023-06-01'
    ));

    // Base variations for multiple results
    $variations = array(
        "Provide a unique perspective on this: ",
        "Give a different take on this topic: ",
        "Approach this from another angle: ",
        "Offer an alternative view on this: ",
        "Present a fresh perspective on this: "
    );

    // Make multiple requests for multiple results
    $responses = array();
    for ($i = 0; $i < $number_of_result; $i++) {
        // Add variation prefix for multiple results
        $variation_prefix = $number_of_result > 1 ? ($variations[$i % count($variations)] ?? '') : '';

        $body = array(
            'model' => $args['model'],
            'max_tokens' => $args['max_tokens'],
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => "{$variation_prefix}{$prompt} You must write in {$args['language']}. {$args['biz_details']} {$args['targated_customer']}"
                )
            ),
            // Slightly vary temperature for each request to increase diversity
            'temperature' => min(1.0, $args['temperature'] + ($i * 0.1))
        );

        $response = wp_safe_remote_post($endpoint, array_merge(
            $request_config,
            array('body' => json_encode($body))
        ));

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $response_body = json_decode(wp_remote_retrieve_body($response));

        if (isset($response_body->error)) {
            throw new Exception($response_body->error->message);
        }

        $responses[] = $response_body;
    }

    // Format responses to match OpenAI structure
    $combined_response = new stdClass();
    $combined_response->choices = array();
    foreach ($responses as $resp) {
        $combined_response->choices[] = (object) [
            'message' => (object) [
                'content' => isset($resp->content[0]->text) ? $resp->content[0]->text : ''
            ]
        ];
    }

    return $combined_response;
}

function wpwand_generate_deepseek_content($prompt, $number_of_result, $args, $request_config)
{
    $endpoint = 'https://api.deepseek.com/v1/chat/completions';


    return wpwand_generate_common_ai_content($endpoint, $prompt, $number_of_result, $args, $request_config);
}

function wpwand_generate_openai_content($prompt, $number_of_result, $args, $request_config)
{
    $endpoint = 'https://api.openai.com/v1/chat/completions';



    /*     $model = isset($args['model']) && !empty($args['model']) ? $args['model'] :  'gpt-3.5-turbo';
    $body = array(
        'model' => $model,
        'messages' => array(
            array(
                'role' => 'system',
                'content' => "{$prompt} You must write in {$args['language']}. {$args['biz_details']} {$args['targated_customer']}"
            )
        ),
        'n' => max(1, $number_of_result),
        'temperature' => $args['temperature'],
        'max_tokens' => $args['max_tokens'],
        'frequency_penalty' => (float) wpwand_get_option('wpwand_frequency', 0),
        'presence_penalty' => (float) wpwand_get_option('wpwand_presence_penalty', 0)
    );

    $response = wp_safe_remote_post($endpoint, array_merge(
        $request_config,
        array('body' => json_encode($body))
    ));

    if (is_wp_error($response)) {
        throw new Exception($response->get_error_message());
    }

    $response_body = json_decode(wp_remote_retrieve_body($response));

    if (isset($response_body->error)) {
        throw new Exception($response_body->error->message);
    } */

    return wpwand_generate_common_ai_content($endpoint, $prompt, $number_of_result, $args, $request_config);
}



function wpwand_generate_common_ai_content($endpoint, $prompt, $number_of_result, $args, $request_config)
{
    $model = isset($args['model']) && !empty($args['model']) ? $args['model'] : 'gpt-3.5-turbo';
    $request_config['headers']['Authorization'] = wpwand_api_source($model) == 'deepseek' ? 'Bearer ' . WPWAND_DEEPSEEK_KEY : 'Bearer ' . WPWAND_OPENAI_KEY;
    
    // Base variations for multiple results
    $variations = array(
        "Provide a unique perspective on this: ",
        "Give a different take on this topic: ",
        "Approach this from another angle: ",
        "Offer an alternative view on this: ",
        "Present a fresh perspective on this: "
    );

    $responses = array();
    
    // Make multiple requests for multiple results
    for ($i = 0; $i < $number_of_result; $i++) {
        // Add variation prefix for multiple results
        $variation_prefix = $number_of_result > 1 ? ($variations[$i % count($variations)] ?? '') : '';
        
        $body = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "You are a professional content writer. You must follow the system instructions strictly."
                ],
                [
                    'role' => 'system',
                    'content' => "You must write in {$args['language']}. {$args['biz_details']} {$args['targated_customer']}"
                ],
                [
                    'role' => 'user',
                    'content' => "{$variation_prefix}{$prompt} . You must follow this instructions strictly. Don't add any other text/explanation/multiple results. Just write the content."
                ]
            ],
            'temperature' => $args['temperature'] + ($i * 0.1), // Slightly vary temperature for diversity
            'max_tokens' => $args['max_tokens'],
            'frequency_penalty' => (float) wpwand_get_option('wpwand_frequency', 0),
            'presence_penalty' => (float) wpwand_get_option('wpwand_presence_penalty', 0)
        ];

        $response = wp_safe_remote_post($endpoint, array_merge(
            $request_config,
            array('body' => json_encode($body))
        ));

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $response_body = json_decode(wp_remote_retrieve_body($response));

        if (isset($response_body->error)) {
            throw new Exception($response_body->error->message);
        }

        $responses[] = $response_body;
    }

    // Combine all responses into a single response object
    $combined_response = new stdClass();
    $combined_response->choices = array();
    
    foreach ($responses as $resp) {
        if (isset($resp->choices) && !empty($resp->choices)) {
            $combined_response->choices[] = $resp->choices[0];
        }
    }

    return $combined_response;
}

function wpwand_generate_ai_content($prompt, $number_of_result = 1, $args = [])
{

    try {
        // Prepare and normalize arguments
        $args = wp_parse_args($args, array(
            'model' => wpwand_get_option('wpwand_model', 'gpt-3.5-turbo'),
            'language' => wpwand_get_option('wpwand_language', 'English'),
            'biz_details' => '',
            'targated_customer' => '',
            'temperature' => (float) wpwand_get_option('wpwand_temperature', 1.0),
            'max_tokens' => null
        ));

        $model = isset($args['model']) && !empty($args['model']) ? $args['model'] :  'claude-3-5-sonnet-20241022';

        // Set max tokens if not provided
        if (null === $args['max_tokens']) {
            $args['max_tokens'] = wpwangd_get_max_token($prompt, $model);
        }

        // Format business details and target customer if provided
        $args['biz_details'] = !empty($args['biz_details']) ? "Write this based on our business details, which this: {$args['biz_details']}" : '';
        $args['targated_customer'] = !empty($args['targated_customer']) ? "Write this focusing the benefits of our targeted customer, which this: {$args['targated_customer']}" : '';

        // Base request configuration
        $request_config = array(
            'timeout' => 60,
            'data_format' => 'body',
            'headers' => array(
                'Content-Type' => 'application/json'
            )
        );

        $is_claude = 'claude' === wpwand_api_source($args['model']);
        $is_deepseek = 'deepseek' === wpwand_api_source($args['model']);

        // Generate content based on the model type
        if ($is_claude) {
            if (WPWAND_CLAUDE_KEY && !empty(WPWAND_CLAUDE_KEY)) {
                $response = wpwand_generate_claude_content($prompt, $number_of_result, $args, $request_config);
            } else {
                throw new Exception(__('Claude API key is missing', 'wp-wand'));
            }
        } else if ($is_deepseek) {
            if (WPWAND_DEEPSEEK_KEY && !empty(WPWAND_DEEPSEEK_KEY)) {
                $response = wpwand_generate_deepseek_content($prompt, $number_of_result, $args, $request_config);
            } else {
                throw new Exception(__('DeepSeek API key is missing', 'wp-wand'));
            }
        } else {
            if (WPWAND_OPENAI_KEY && !empty(WPWAND_OPENAI_KEY)) {
                $response = wpwand_generate_openai_content($prompt, $number_of_result, $args, $request_config);
            } else {
                throw new Exception(__('OpenAI API key is missing', 'wp-wand'));
            }
        }

        return apply_filters('wpwand_api_response', $response, $is_claude);
    } catch (Exception $e) {
        return (object) [
            'error' => (object) [
                'message' => $e->getMessage(),
                'type' => $is_claude ? 'claude_error' : ($is_deepseek ? 'deepseek_error' : 'openai_error'),
                'code' => 500
            ]
        ];
    }
}
