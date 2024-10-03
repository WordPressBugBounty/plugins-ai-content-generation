<?php

if (!defined('ABSPATH')) {
    exit('You are not allowed');
}

function wpwand_frontend_callback()
{

    ob_start(); ?>

    <!-- <button><?php esc_html_e('Generate content', 'wpwand') ?></button> -->
    <?php if ('side' == wpwand_get_option('toggler_position', 'top')): ?>
        <button class="wpwand-trigger wpwand-open">
            <img src="<?php echo wpwand_loago_icon_url(); ?>">
        </button>
    <?php endif; ?>
    <div class="wpwand-floating">
        <div class="wpwand-floating-wraper">
            <div class="wpwand-floating-header">
                <h4> <img src="<?php echo wpwand_loago_icon_url(); ?>">
                    <?php echo esc_html(wpwand_brand_name()) ?> - Your Personal Content Creator</h4>
            </div>


            <?php if (!WPWAND_OPENAI_KEY): ?>
                <!-- wp wand api missing notice  -->

                <div class="wpwand-api-missing-notice-wrap">
                    <div class="wpwand-api-missing-notice">
                        <span><svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M8.21895 5.78105C7.17755 4.73965 5.48911 4.73965 4.44772 5.78105L1.78105 8.44772C0.73965 9.48911 0.73965 11.1776 1.78105 12.219C2.82245 13.2603 4.51089 13.2603 5.55228 12.219L6.28666 11.4846M5.78105 8.21895C6.82245 9.26035 8.51089 9.26035 9.55228 8.21895L12.219 5.55228C13.2603 4.51089 13.2603 2.82245 12.219 1.78105C11.1776 0.73965 9.48911 0.73965 8.44772 1.78105L7.71464 2.51412"
                                    stroke="#EE2626" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            Your API is not connected. Connect now to generate awesome contents.</span>
                    </div>

                    <div class="wpwand-api-missing-form-wrap">
                        <form action="" class="wpwand-api-missing-form">
                            <div class="wpwand-form-group">
                                <div class="wpwand-form-field">
                                    <label for="wpwand-api-key">OpenAI API Key</label>
                                    <input type="text" id="wpwand-api-key" name="wpwand-api-key"
                                        placeholder="Paste API key here" required>
                                    <a href="https://platform.openai.com/account/api-keys">Get your free OpenAI API key</a>
                                </div>
                            </div>

                            <div class="wpwand-form-submit">
                                <button class="wpwand-submit-button">Connect API</button>
                            </div>
                        </form>
                    </div>
                </div>

            <?php else: ?>


                <div class="wpwand-prompts-tabs-wrap">
                    <div class="wpwand-prompts-tabs">
                        <button class="wpwand-tab-item active" data-prompt-id="templates">Text Generation</button>
                        <button class="wpwand-tab-item " data-prompt-id="wpwand-image-generation">Image Generation</button>
                        <!-- <button class="wpwand-tab-item" data-prompt-id="prompt-poem">Saved</button> -->
                    </div>
                    <div class="wpwand-template-filter">
                        <input type="text" id="wpwand-search-input" placeholder="Search for a template...">
                    </div>
                </div>

                <div class="wpwand-prompt-from-wrap">
                    <button class="wpwand-trigger wpwand-close-button"><svg width="12" height="12" viewBox="0 0 12 12"
                            fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1.5 10.5L10.5 1.5M1.5 1.5L10.5 10.5" stroke="white" stroke-width="1.5"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg></button>

                    <div class="wpwand-screen-expander"><i class="dashicons dashicons-editor-expand"></i></div>

                    <div class="wpwand-prompt-item active" id="templates">

                        <div class="wpwand-total-templates-count"><span>Total
                                <?php echo esc_html(count(wpwand_templates())) ?> templates
                            </span></div>
                        <div class="wpwand-template-list">
                            <?php if (is_array(wpwand_templates())):
                                                                            $custom_prompt = wpwand_get_custom_prpompts('aichar');

                                foreach (wpwand_templates() as $key => $template):
                                    if (!isset($template['number_of_results'])) {
                                        $template['number_of_results'] = true;
                                    }

                                    if (!isset($template['point_of_view'])) {
                                        $template['point_of_view'] = true;
                                    }

                                    if (!isset($template['markdown'])) {
                                        $template['markdown'] = false;
                                    }

                                    $markdown = true == $template['markdown'] ? 1 : 0;
                                    $fields = explode(', ', $template['fields']);
                                    ?>
                                    <div class="wpwand-tiemplate-item">
                                        <h4>
                                            <?php echo esc_html($template['title']) ?>
                                            <?php if (true == $template['is_pro']): ?>
                                                <span class="wpwand-pro-tag">PRO</span>
                                            <?php endif; ?>
                                        </h4>
                                        <p>
                                            <?php echo esc_html($template['description']) ?>
                                        </p>

                                        <svg width="16" height="12" viewBox="0 0 16 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M9.5 0.75L14.75 6M14.75 6L9.5 11.25M14.75 6L1.25 6" stroke="#7C838A"
                                                stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </div>

                                    <div class="wpwand-prompt-form-wrap">
                                        <span class="wpwand-back-button"><svg width="12" height="10" viewBox="0 0 12 10" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path
                                                    d="M4.83333 9.08332L0.75 4.99999M0.75 4.99999L4.83333 0.916656M0.75 4.99999L11.25 4.99999"
                                                    stroke="#7C838A" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg> Back to
                                            all templates</span>

                                        <div class="wpwand-template-details">
                                            <h4>
                                                <?php echo esc_html($template['title']) ?>
                                            </h4>
                                            <p>
                                                <?php echo esc_html($template['description']) ?>
                                            </p>
                                        </div>
                                        <form action="" class="wpwand-prompt-form">
                                            <input type="hidden" id="wpwand-markdown" name="wpwand-markdown"
                                                value="<?PHP echo esc_html($markdown) ?>">
                                            <input type="hidden" id="wpwand-prompt-id" name="wpwand-prompt-id"
                                                value="<?PHP echo esc_html($template['title']) ?>">
                                            <input type="hidden" id="wpwand-prompt" name="wpwand-prompt"
                                                value="<?PHP echo esc_html($template['prompt']) ?>">

                                            <?php if (in_array('Topic', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-topic">Topic</label>
                                                        <input type="text" id="wpwand-topic" name="wpwand-topic"
                                                            placeholder="Write in detail about your topic">
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Product Name -->
                                            <?php if (in_array('Name', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-product-name">Name</label>
                                                        <input type="text" id="wpwand-product-name" name="wpwand-product-name"
                                                            placeholder="Write your product name">
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Product Name -->
                                            <?php if (in_array('Comment', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-comment">Comment</label>
                                                        <input type="text" id="wpwand-comment" name="wpwand-comment"
                                                            placeholder="Write your comment">
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Question -->
                                            <?php if (in_array('Question', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-question">Question</label>
                                                        <input type="text" id="wpwand-question" name="wpwand-question"
                                                            placeholder="Write your Question">
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Subject -->
                                            <?php if (in_array('Subject', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-subject">Subject</label>
                                                        <input type="text" id="wpwand-subject" name="wpwand-subject"
                                                            placeholder="Write your Subject">
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Product Name -->
                                            <?php if (in_array('Comment', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-comment">Comment</label>
                                                        <input type="text" id="wpwand-comment" name="wpwand-comment"
                                                            placeholder="Write your comment">
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Product 1 -->
                                            <?php if (in_array('Product 1', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-product-1">Product 1</label>
                                                        <input type="text" id="wpwand-product-1" name="wpwand-product-1"
                                                            placeholder="Product 1">
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Product 2 -->
                                            <?php if (in_array('Product 2', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-product-2">Product 2</label>
                                                        <input type="text" id="wpwand-product-2" name="wpwand-product-2"
                                                            placeholder="Product 2">
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <!-- description -->
                                            <?php if (in_array('Description', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-description">Description</label>
                                                        <input type="text" id="wpwand-description" name="wpwand-description"
                                                            placeholder="Write a meaningful description to generate better result.">
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- description 1 -->
                                            <?php if (in_array('Product 1 Description', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-description-1">Product 1 Description</label>
                                                        <input type="text" id="wpwand-description-1" name="wpwand-description-1"
                                                            placeholder="Write a meaningful description to generate better result.">
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <!-- description 2 -->
                                            <?php if (in_array('Product 2 Description', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-description-2">Product 2 Description</label>
                                                        <input type="text" id="wpwand-description-2" name="wpwand-description-2"
                                                            placeholder="Write a meaningful description to generate better result.">
                                                    </div>
                                                </div>
                                            <?php endif; ?>


                                            <!-- Content -->
                                            <?php if (in_array('Content', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-content">Content</label>
                                                        <input name="wpwand-content" id="wpwand-content" placeholder="Write your content" />
                                                    </div>
                                                </div>
                                            <?php endif; ?>


                                            <!-- Content Text Area -->
                                            <?php if (in_array('Content Text Area', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-content-textarea">Content</label>
                                                        <textarea name="wpwand-content-textarea" id="wpwand-content-textarea" cols="30"
                                                            rows="10" placeholder="Write your content"></textarea>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <!-- Write anything Text Area -->
                                            <?php if (in_array('custom_textarea', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-custom_textarea">Write Anything</label>
                                                        <textarea name="wpwand-custom_textarea" id="wpwand-custom_textarea" cols="30"
                                                            rows="10" placeholder="Write Anything"></textarea>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (in_array('Keywords', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-keyword">Keyword to Include <span
                                                                class="wpwand-optional">(Optional)</span></label>
                                                        <input type="text" id="wpwand-keyword" name="wpwand-keyword"
                                                            placeholder="Write keyword and separate using comma">
                                                    </div>
                                                </div>

                                            <?php endif; ?>
                                            <div class="wpwand-form-group wpwand-col-2">

                                                <?php /*  if ( 
                                                                                                                                                                                                                                                                                                 
                                                                                                                                                                                                                                                                                                 'Full Blog Post' != $template['title'] 
                                                                                                                                                                                                                                                                                                 && 'Comparison Blog Post Between 2 Products' != $template['title']
                                                                                                                                                                                                                                                                                                 && 'Amazon Product Review' != $template['title']
                                                                                                                                                                                                                                                                                                 && 'Review Blog Post' != $template['title']
                                                                                                                                                                                                                                                                                                 && 'WooCommerce Product Description' != $template['title']
                                                                                                                                                                                                                                                                                                 ):  */

                                                if (true == $template['number_of_results']):
                                                    ?>
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-result-number">Number of Results</label>
                                                        <input type="number" id="wpwand-result-number" min="1" max="10"
                                                            name="wpwand-result-number" value="1">
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (in_array('Tone', $fields)): ?>
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-tone">Tone</label>
                                                        <select name="wpwand-tone" id="wpwand-tone">
                                                            <option value="friendly"> Friendly</option>
                                                            <option value="helpful"> Helpful</option>
                                                            <option value="informative"> Informative</option>
                                                            <option value="aggressive"> Aggressive</option>
                                                            <option value="professional"> Professional</option>
                                                            <option value="Formal"> Formal</option>
                                                            <option value="Informal"> Informal</option>
                                                            <option value="Conversational"> Conversational</option>
                                                            <option value="Persuasive"> Persuasive</option>
                                                            <option value="Witty"> Witty</option>
                                                            <option value="Descriptive"> Descriptive</option>
                                                            <option value="Expository"> Expository</option>
                                                            <option value="Humorous"> Humorous</option>
                                                            <option value="Inspirational"> Inspirational</option>
                                                            <option value="Funny"> Funny</option>
                                                            <option value="Poetic"> Poetic</option>
                                                            <option value="Technical"> Technical</option>
                                                            <option value="Argumentative"> Argumentative</option>
                                                            <option value="Instructional"> Instructional</option>
                                                            <option value="Sarcastic"> Sarcastic</option>
                                                            <option value="Urgent"> Urgent</option>
                                                            <option value="Optimistic"> Optimistic</option>
                                                        </select>
                                                    </div>
                                                <?php endif; ?>
                                            </div>


                                            <?php if (in_array('Word Count', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-word-limit">Minimum Word</label>
                                                        <input type="number" id="wpwand-word-limit" name="wpwand-word-limit" value="100">
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($template['point_of_view']): ?>
                                                <div class="wpwand-form-field">
                                                    <label for="wpwand-point-of-view">Point of View</label>
                                                    <select name="wpwand-point-of-view" id="wpwand-point-of-view">
                                                        <!-- <option value="">Select a</option> -->
                                                        <option value="1st-person">1st Person</option>
                                                        <option value="2nd-person">2nd Person</option>
                                                        <option value="3rd-person">3rd Person</option>
                                                    </select>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Language option  -->

                                            <div class="wpwand-form-group wpwand-col-2">
                                                <div class="wpwand-form-field">
                                                    <label for="wpwand-Language">Language
                                                    </label>
                                                    <select name="wpwand-Language" id="wpwand-Language">
                                                        <?php
                                                        if (is_array(wpwand_language_array())) {
                                                            $default_language = wpwand_get_option('wpwand_language', 'en');
                                                            foreach (wpwand_language_array() as $key => $value) {
                                                                printf('<option value="%s" %s >%s</option>', $key, selected($default_language, $key), $key);
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                                <?php
                                                if (function_exists('wpwand_pro_tala_check') && wpwand_pro_tala_check()): ?>
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-aichar">AI Character</label>
                                                        <select name="wpwand-aichar" id="wpwand-aichar">
                                                            <option>No Character</option>
                                                            <?php
                                                            if ($custom_prompt) {

                                                                foreach ($custom_prompt as $value) {
                                                                    printf('<option value="%s">%s</option>', $value['prompt'], $value['title']);
                                                                }
                                                            }
                                                            if (function_exists('wpwand_pro_premad_aichars')) {
                                                                foreach (wpwand_pro_premad_aichars() as $value) {
                                                                    printf('<option value="%s">%s</option>', $value['prompt'], $value['title']);
                                                                }
                                                            }

                                                            ?>
                                                        </select>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (function_exists('wpwand_pro_tala_check') && wpwand_pro_tala_check()): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field wpwand-radio-field-wrap">
                                                        <h4>Include Info </h4>

                                                        <!-- <label class="wpwand-radio-label" for="wpwand_ai_inf">
                                                        <input type="checkbox" id="wpwand_ai_inf" name="wpwand_ai_inf"
                                                            class="wpwand-radio">
                                                        AI Character
                                                    </label> -->
                                                        <label for="wpwand_biz_inf" class="wpwand-radio-label">
                                                            <input type="checkbox" id="wpwand_biz_inf" name="wpwand_biz_inf"
                                                                class="wpwand-radio">
                                                            Business Details
                                                        </label>
                                                        <label for="wpwand_tgdc_inf" class="wpwand-radio-label">
                                                            <input type="checkbox" id="wpwand_tgdc_inf" name="wpwand_tgdc_inf"
                                                                class="wpwand-radio">
                                                            Targeted Customer
                                                        </label>

                                                    </div>
                                                </div>
                                            <?php endif; ?>


                                            <div class="wpwand-form-submit">
                                                <?php if ($template['is_pro']): ?>
                                                    <a href="https://wpwand.com/pro-plugin" target="_blank" class="wpwand-submit-pro"><svg
                                                            width="14" height="16" viewBox="0 0 14 16" fill="none"
                                                            xmlns="http://www.w3.org/2000/svg">
                                                            <path
                                                                d="M7 10.25V11.75M2.5 14.75H11.5C12.3284 14.75 13 14.0784 13 13.25V8.75C13 7.92157 12.3284 7.25 11.5 7.25H2.5C1.67157 7.25 1 7.92157 1 8.75V13.25C1 14.0784 1.67157 14.75 2.5 14.75ZM10 7.25V4.25C10 2.59315 8.65685 1.25 7 1.25C5.34315 1.25 4 2.59315 4 4.25V7.25H10Z"
                                                                stroke="white" stroke-width="1.5" stroke-linecap="round" />
                                                        </svg>
                                                        Get Pro to Use This Template</a>
                                                <?php else: ?>
                                                    <button class="wpwand-submit-button">Generate Content</button>
                                                <?php endif; ?>
                                            </div>
                                        </form>

                                        <div class="wpwand-result-box" style="display: none;">
                                            <h4>AI Generated Content</h4>
                                            <div class="wpwand-content-wrap"></div>

                                        </div>
                                    </div>
                                <?php endforeach; endif; ?>
                        </div>

                    </div>
                    <div class="wpwand-prompt-item" id="wpwand-image-generation">


                        <div class="wpwand-template-list">


                            <div class="wpwand-prompt-form-wrap">

                                <div class="wpwand-template-details">
                                    <h4>
                                        <?php echo esc_html('Generate Image') ?>
                                    </h4>
                                    <p>
                                        <?php echo esc_html('Get beautiful AI generated images in seconds') ?>
                                    </p>
                                </div>
                                <form action="" class="wpwand-prompt-form">
                                    <input type="hidden" id="wpwand-prompt-id" name="wpwand-prompt-id"
                                        value="<?PHP echo esc_html('wpwand-image-generation') ?>">
                                    <input type="hidden" id="wpwand-prompt" name="wpwand-prompt"
                                        value="<?PHP echo esc_html('wpwand-image-generation') ?>">


                                    <div class="wpwand-form-group">
                                        <div class="wpwand-form-field">
                                            <label for="wpwand-image-prompt">Image Description</label>
                                            <input type="text" id="wpwand-image-prompt" name="wpwand-image-prompt"
                                                placeholder="Write in details about your image">
                                        </div>
                                    </div>
                                    <?php do_action('wpwand_dall_e_frontend_fields') ?>
                                    <div class="wpwand-form-submit">

                                        <button class="wpwand-submit-button">Generate Image</button>

                                    </div>

                                </form>

                                <div class="wpwand-result-box wpwand-image-result-box" style="display: none;">
                                    <h4>AI Generated Image</h4>
                                    <div class="wpwand-content-wrap"></div>

                                </div>
                            </div>

                        </div>

                    </div>

                </div>
            <?php endif; ?>

        </div>
    </div>


    <?php printf("%s", ob_get_clean());

}

add_action('admin_footer', 'wpwand_frontend_callback');
// add_action( 'wp_footer', 'wpwand_frontend_callback' );