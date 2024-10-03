<?php

if (!defined('ABSPATH')) {
    exit('You are not allowed');
}

// var_dump(get_option('wpwand_pro_tala_key'));
function wpwand_templates()
{

    $all_prompts = get_option('wpwand_data');
    $custom_data = get_option('wpwand_custom_data', []);
    wpwand_sync_transient();
    if (get_option('wpwand_pro_activated') == 'activation') {
        wpwand_sync_date();
        update_option('wpwand_pro_activated', 'data_initialized');
    }
    if (isset($all_prompts['free']) && isset($all_prompts['pro'])) {

        return array_merge($custom_data, $all_prompts['free'], $all_prompts['pro']);
    }
    return [];
}
function wpwand_sync_date()
{
    // Check if the plugin is being activated for the first time
    if (function_exists('wpwand_pro_get_data')) {
        wpwand_pro_get_data();
    } else {
        wpwand_get_data(true);
    }


}
add_action('wp_ajax_wpwand_sync_date', 'wpwand_sync_date');
add_action('wp_ajax_nopriv_wpwand_sync_date', 'wpwand_sync_date');


function wpwand_sync_transient()
{
    if (false === get_transient('wpwand_data_transient')) {
        return set_transient('wpwand_data_transient', wpwand_sync_date(), 12 * HOUR_IN_SECONDS);
    }
    return false;
}

function wpwand_get_data($sync = false)
{

    if (!get_option('wpwand_data') || $sync == true) {

        // Build the request
        $url = "https://updates.finestwp.co/demo-import/wp-wand/import-files.php?fdth";

        $response = wp_remote_get($url);
        $response_body = wp_remote_retrieve_body($response);
        $response_body = json_decode($response_body, true);
        // Send the request with warnings supressed

        return update_option('wpwand_data', $response_body) ? true : false;
    }

    return false;

}



function randomize_array($array)
{
    shuffle($array); // shuffle the outer array

    foreach ($array as $inner_array) {
        shuffle($inner_array); // shuffle each inner array
    }

    return $array;
}

// language set
function wpwand_language_array()
{
    return [
        'English' => 'en',
        'Afrikaans' => 'af',
        'Arabic' => 'ar',
        'Armenian' => 'an',
        'Bosnian' => 'bs',
        'Bulgarian' => 'bg',
        'Chinese' => 'zh',
        'Croatian' => 'hr',
        'Czech' => 'cs',
        'Danish' => 'da',
        'Dutch' => 'nl',
        'Estonian' => 'et',
        'Filipino' => 'fil',
        'Finnish' => 'fi',
        'French' => 'fr',
        'German' => 'de',
        'Greek' => 'el',
        'Hebrew' => 'he',
        'Hindi' => 'hi',
        'Hungarian' => 'hu',
        'Indonesian' => 'id',
        'Italian' => 'it',
        'Japanese' => 'ja',
        'Korean' => 'ko',
        'Latvian' => 'lv',
        'Lithuanian' => 'lt',
        'Malay' => 'ms',
        'Norwegian' => 'no',
        'Persian' => 'fa',
        'Polish' => 'pl',
        'Portuguese' => 'pt',
        'Romanian' => 'ro',
        'Russian' => 'ru',
        'Serbian' => 'sr',
        'Slovak' => 'sk',
        'Slovenian' => 'sl',
        'Spanish' => 'es',
        'Swedish' => 'sv',
        'Thai' => 'th',
        'Turkish' => 'tr',
        'Ukrainian' => 'uk',
        'Urdu' => 'ur',
        'Vietnamese' => 'vi',
    ];

}

function wpwand_editor_prompts($locked = true)
{
    return [
        [
            'name' => 'Write a paragraph',
            'prompt' => ' Write a paragraph: [text]',
            'is_pro' => false,
        ],
        [
            'name' => 'Summarize',
            'prompt' => 'Summarize this: [text]',
            'is_pro' => false,
        ],
        [
            'name' => 'Expand',
            'prompt' => 'Expand this: [text] ',
            'is_pro' => false,
        ],


        // this will be pro 
        [
            'name' => 'Rewrite',
            'prompt' => '',
            'is_pro' => true,
        ],
        [
            'name' => 'Shorter',
            'prompt' => '',
            'is_pro' => true,
        ],
        [
            'name' => 'Longer',
            'prompt' => '',
            'is_pro' => true,
        ],

        [
            'name' => 'Make a bullet list',
            'prompt' => '',
            'is_pro' => true,
        ],
        [
            'name' => 'Paraphrase',
            'prompt' => '',
            'is_pro' => true,
        ],
        [
            'name' => 'Generate a call to action',
            'prompt' => '',
            'is_pro' => true,
        ],
        [
            'name' => 'Correct grammar',
            'prompt' => '',
            'is_pro' => true,
        ],
        [
            'name' => 'Generate a question',
            'prompt' => '',
            'is_pro' => true,
        ],
        [
            'name' => 'Suggest a title',
            'prompt' => '',
            'is_pro' => true,
        ],
        [
            'name' => 'Convert to passive voice',
            'prompt' => '',
            'is_pro' => true,
        ],
        [
            'name' => 'Convert to active voice',
            'prompt' => '',
            'is_pro' => true,
        ],
        [
            'name' => 'Write a conclusion',
            'prompt' => '',
            'is_pro' => true,
        ],
        [
            'name' => 'Provide a counterargument',
            'prompt' => '',
            'is_pro' => true,
        ],
        [
            'name' => 'Generate a quote',
            'prompt' => '',
            'is_pro' => true,
        ],
        [
            'name' => 'Translate to ' . wpwand_get_option('wpwand_language', 'en'),
            'prompt' => '',
            'is_pro' => true,
        ],
    ];
}

// add_filter( 'wpwand_editor_prompts','wpwand_editor_prompt' );