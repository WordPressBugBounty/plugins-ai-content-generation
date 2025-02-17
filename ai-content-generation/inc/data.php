<?php

if (!defined('ABSPATH')) {
    exit('You are not allowed');
}

// Add this at the beginning of the file, after the ABSPATH check
add_action('plugins_loaded', 'wpwand_load_template_strings', 0);

function wpwand_load_template_strings()
{
    // Hardcode all possible template strings that could come from the API
    $template_strings = array(
        // Free Templates
        __('Headline Generation', 'wp-wand'),
        __('Create attention-grabbing headlines for website or blog post with a specific topic.', 'wp-wand'),
        __('Paragraph Related to Headline', 'wp-wand'),
        __('Quickly generate compelling content for your headlines.', 'wp-wand'),
        __('One Click Blog Post', 'wp-wand'),
        __('Generate complete blog posts that engage your readers.', 'wp-wand'),
        __('Blog Title', 'wp-wand'),
        __('Generate titles that grab attention and increase clicks.', 'wp-wand'),
        __('Blog Outline', 'wp-wand'),
        __('Create detailed outlines that make writing blog posts a breeze.', 'wp-wand'),
        __('Blog Intro', 'wp-wand'),
        __('Quickly write a highly engaging intro for your blog post.', 'wp-wand'),
        __('Blog Paragraph', 'wp-wand'),
        __('Generate high-quality individual paragraph for your blog posts.', 'wp-wand'),
        __('Blog Post Writer', 'wp-wand'),
        __('Generate a SEO friendly blog post with your basic instructions', 'wp-wand'),
        __('Job Post', 'wp-wand'),
        __('Create job descriptions that attract top talent.', 'wp-wand'),
        __('Product Description', 'wp-wand'),
        __('Generate compelling product descriptions for your online store.', 'wp-wand'),
        __('Linkedin Post', 'wp-wand'),
        __('Generate a highly engaging post for Linkedin', 'wp-wand'),
        __('Facebook Post', 'wp-wand'),
        __('Generate a highly engaging post for Facebook', 'wp-wand'),

        // Pro Templates
        __('Meta Title', 'wp-wand'),
        __('Optimize your content for search engines with effective meta titles.', 'wp-wand'),
        __('Meta Description', 'wp-wand'),
        __('Create meta descriptions that increase click-through rates.', 'wp-wand'),
        __('Meta Keywords', 'wp-wand'),
        __('Generate relevant keywords to improve SEO.', 'wp-wand'),
        __('Sales Page Headlines', 'wp-wand'),
        __('Create effective headlines for your sales pages.', 'wp-wand'),
        __('Sentence Expander', 'wp-wand'),
        __('Expand short sentences into detailed paragraphs.', 'wp-wand'),
        __('Button Call to Action Text', 'wp-wand'),
        __('Generate effective call-to-action text for your buttons.', 'wp-wand'),
        __('Review Blog Post', 'wp-wand'),
        __('Create informative and engaging reviews of products or services.', 'wp-wand'),
        __('Comparison Blog Post Between 2 Products', 'wp-wand'),
        __('Generate a complete blog post based on a given topic or keywords', 'wp-wand'),
        __('WooCommerce Product Description', 'wp-wand'),
        __('Optimize your content for search engines with effective meta titles.', 'wp-wand'),
        __('Amazon Product Review', 'wp-wand'),
        __('Create engaging reviews for Amazon products.', 'wp-wand'),

        // Email Templates
        __('Email Subject Line', 'wp-wand'),
        __('Create subject lines that increase email open rates.', 'wp-wand'),
        __('Email Content', 'wp-wand'),
        __('Generate compelling email content that drives engagement.', 'wp-wand'),

        // FAQ and Support
        __('FAQs Writer', 'wp-wand'),
        __('Quickly create informative FAQs for your website or product.', 'wp-wand'),
        __('Grammar Correction', 'wp-wand'),
        __('Ensure your content is error-free with Grammar Correction.', 'wp-wand'),
        __('Features to Benefits', 'wp-wand'),
        __('Highlight the benefits of your products or services.', 'wp-wand'),

        // Copywriting Formulas
        __('HSO Copywriting Formula', 'wp-wand'),
        __('Use the Headline, Story, Offer formula to create effective copy', 'wp-wand'),
        __('AIDA Copywriting Formula', 'wp-wand'),
        __('Use the Attention, Interest, Desire, Action formula to write persuasive copy.', 'wp-wand'),
        __('PAS Copywriting Formula', 'wp-wand'),
        __('Use the Problem, Agitate, Solve formula to write compelling copy.', 'wp-wand'),

        // Marketing Content
        __('Offer Ideas', 'wp-wand'),
        __('Generate new ideas for offers and promotions.', 'wp-wand'),
        __('Press Release', 'wp-wand'),
        __('Write effective press releases that get your message out.', 'wp-wand'),
        __('Social Media Post Ideas', 'wp-wand'),
        __('Generate ideas for engaging social media posts.', 'wp-wand'),

        // Website Content
        __('Website Tagline', 'wp-wand'),
        __('Create memorable taglines for your website.', 'wp-wand'),
        __('Website About Us', 'wp-wand'),
        __('Quickly create compelling About Us pages.', 'wp-wand'),

        // Social and Community
        __('Quora Answers', 'wp-wand'),
        __('Generate informative answers to common questions on Quora.', 'wp-wand'),
        __('Comment Reply', 'wp-wand'),
        __('Quickly respond to comments on your blog or social media.', 'wp-wand'),

        // Course Content
        __('Course Name', 'wp-wand'),
        __('Generate catchy names for your online courses.', 'wp-wand'),
        __('Course Description', 'wp-wand'),
        __('Create compelling descriptions for your online courses.', 'wp-wand'),

        // Feature and Product Content
        __('Feature List', 'wp-wand'),
        __('Generate comprehensive lists of product or service features.', 'wp-wand'),
        __('Feature Details', 'wp-wand'),
        __('Quickly describe the features of your products or services.', 'wp-wand'),

        // SEO and Content Tools
        __('Keyword Generator', 'wp-wand'),
        __('Generate relevant keywords to improve SEO.', 'wp-wand'),
        __('Content Rewriter', 'wp-wand'),
        __('Quickly rewrite existing content to improve readability and SEO.', 'wp-wand'),
        __('Magic Headlines', 'wp-wand'),
        __('Generate hundreds of headline ideas in seconds.', 'wp-wand'),
    );
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
    if (defined('DOING_AJAX')) {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wpwand_global_nonce')) {
            wp_send_json_error('Nonce verification failed.', 403);
        }
    }
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

        $response = wp_safe_remote_get($url);
        $response_body = wp_remote_retrieve_body($response);
        $response_body = json_decode($response_body, true);
        // Send the request with warnings supressed

        return update_option('wpwand_data', $response_body) ? true : false;
    }

    return false;
}

// Add a helper function to retrieve and translate the stored strings
function wpwand_translate_template_string($string)
{
    if (empty($string)) {
        return $string;
    }

    // Check if string is in our special format
    if (strpos($string, '_x:') === 0) {
        $parts = explode(':', trim($string, ':'));
        if (count($parts) === 3) {
            return _x($parts[1], $parts[2], 'wp-wand');
        }
    }

    return $string;
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

// Add this new function
function wpwand_register_template_strings()
{
    $templates = get_option('wpwand_data');

    if (!empty($templates) && is_array($templates)) {
        foreach ($templates as $type => $type_templates) {
            if (is_array($type_templates)) {
                foreach ($type_templates as $template) {
                    if (!empty($template['title'])) {
                        __($template['title'], 'wp-wand');
                    }
                    if (!empty($template['description'])) {
                        __($template['description'], 'wp-wand');
                    }
                    if (!empty($template['prompt'])) {
                        __($template['prompt'], 'wp-wand');
                    }
                }
            }
        }
    }


}

// Add this action hook at the end of the file
add_action('init', 'wpwand_register_template_strings');
