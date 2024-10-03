<?php

use Orhanerday\OpenAi\OpenAi;

if ( !defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}


function wdelmtr_api_set() {

    // Check if prompt parameter exists
    if ( empty( $_POST['topic'] ) ) {
        wp_send_json_error( 'Please enter your topic' );
    }

    // Sanitize and validate input fields
    $topic = sanitize_text_field( $_POST['topic'] ?? '' );

    $selected_model = get_option( 'wdelmtr_model', 'gpt-3.5-turbo' );

    // Call OpenAI API to generate content
    $openAI = new OpenAi( WDELMTR_OPENAI_KEY );

    if ( 'gpt-3.5-turbo' == $selected_model ) {

        $complete = $openAI->chat( [
            'model'             => 'gpt-3.5-turbo',
            'messages'          => [
                [
                    'role'    => 'system',
                    'content' => 'you are creative content writer',
                ],
                [
                    'role'    => 'user',
                    'content' => 'write a content about ' . $topic,
                ],
            ],

            'temperature'       => (int) get_option( 'wdelmtr_temperature', 1.0 ),
            'max_tokens'        => (int) get_option( 'wdelmtr_max_tokens', 1000 ),
            'frequency_penalty' => (int) get_option( 'wdelmtr_frequency', 0 ),
            'presence_penalty'  => (int) get_option( 'wdelmtr_presence_penalty', 0 ),
        ] );
    } else {
        $complete = $openAI->completion( [
            'model'             => $selected_model,
            'prompt'            => 'write a content about ' . $topic,
            'temperature'       => (int) get_option( 'wdelmtr_temperature', 1.0 ),
            'max_tokens'        => (int) get_option( 'wdelmtr_max_tokens', 1000 ),
            'frequency_penalty' => (int) get_option( 'wdelmtr_frequency', 0 ),
            'presence_penalty'  => (int) get_option( 'wdelmtr_presence_penalty', 0 ),
        ] );

    }

    $content = json_decode( $complete );

    $text = '';

    // wp_send_json( $content );
    // Build HTML content from OpenAI API response
    foreach ( $content->choices as $choice ) {
        $text .= $choice->message->content;

    }
    wp_send_json( $text );

}

// Register AJAX action for logged-in and non-logged-in users
add_action( 'wp_ajax_wdelmtr_api_set', 'wdelmtr_api_set' );
add_action( 'wp_ajax_nopriv_wdelmtr_api_set', 'wdelmtr_api_set' );


// add_action( 'elementor/editor/footer' , 'wpwand_frontend_callback');



