<?php

if (!defined('ABSPATH')) {
    exit('You are not allowed');
}

function wpwand_frontend_callback()
{

    ob_start(); ?>

    <!-- <button><?php esc_html_e('Generate content', 'wp-wand') ?></button> -->
    <?php if ('side' == wpwand_get_option('toggler_position', 'top')): ?>
        <button class="wpwand-trigger wpwand-open">
            <img src="<?php echo esc_url(wpwand_loago_icon_url());   // phpcs:ignore
                        ?>">
        </button>
    <?php endif; ?>
    <div class="wpwand-floating">
        <button class="wpwand-trigger wpwand-close-button"><svg width="12" height="12" viewBox="0 0 12 12"
                fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M1.5 10.5L10.5 1.5M1.5 1.5L10.5 10.5" stroke="white" stroke-width="1.5"
                    stroke-linecap="round" stroke-linejoin="round" />
            </svg></button>
        <div class="wpwand-floating-wraper">
            <div class="wpwand-floating-header">
                <h4> <img src="<?php echo esc_url(wpwand_loago_icon_url());  // phpcs:ignore
                                ?>">
                    <?php echo esc_html(wpwand_brand_name()) ?> - <?php esc_html_e('Your Personal Content Creator', 'wp-wand'); ?></h4>
            </div>


            <?php if (!WPWAND_OPENAI_KEY && !WPWAND_CLAUDE_KEY && !WPWAND_DEEPSEEK_KEY): ?>
                <!-- wp wand api missing notice  -->

                <div class="wpwand-api-missing-notice-wrap">
                    <div class="wpwand-api-missing-notice">
                        <span><svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M8.21895 5.78105C7.17755 4.73965 5.48911 4.73965 4.44772 5.78105L1.78105 8.44772C0.73965 9.48911 0.73965 11.1776 1.78105 12.219C2.82245 13.2603 4.51089 13.2603 5.55228 12.219L6.28666 11.4846M5.78105 8.21895C6.82245 9.26035 8.51089 9.26035 9.55228 8.21895L12.219 5.55228C13.2603 4.51089 13.2603 2.82245 12.219 1.78105C11.1776 0.73965 9.48911 0.73965 8.44772 1.78105L7.71464 2.51412"
                                    stroke="#EE2626" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <?php esc_html_e('Your API is not connected. Connect now to generate awesome contents.', 'wp-wand'); ?></span>
                    </div>

                    <div class="wpwand-api-missing-form-wrap">
                        <!-- <form action="" class="wpwand-api-missing-form">
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
                        </form> -->
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpwand')); ?>" class="wpwand-big-button"><?php esc_html_e('Setup your API key', 'wp-wand'); ?></a>
                    </div>
                </div>

            <?php else: ?>


                <div class="wpwand-prompts-tabs-wrap">
                    <div class="wpwand-prompts-tabs">
                        <button class="wpwand-tab-item active" data-prompt-id="templates"><?php esc_html_e('Text Generation', 'wp-wand'); ?></button>
                        <button class="wpwand-tab-item " data-prompt-id="wpwand-image-generation"><?php esc_html_e('Image Generation', 'wp-wand'); ?></button>
                        <!-- <button class="wpwand-tab-item" data-prompt-id="prompt-poem">Saved</button> -->
                    </div>
                    <div class="wpwand-template-filter">
                        <input type="text" id="wpwand-search-input" placeholder="<?php esc_html_e('Search for a template...', 'wp-wand'); ?>">
                    </div>
                </div>

                <div class="wpwand-prompt-from-wrap">


                    <div class="wpwand-screen-expander"><i class="dashicons dashicons-editor-expand"></i></div>

                    <div class="wpwand-prompt-item active" id="templates">

                        <div class="wpwand-total-templates-count"><span><strong><?php esc_html_e('Total', 'wp-wand'); ?></strong>
                                <?php echo esc_html(count(wpwand_templates())) ?> <?php esc_html_e('templates', 'wp-wand'); ?>
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
                                            <?php echo esc_html__($template['title'], 'wp-wand') ?>
                                            <?php if (true == $template['is_pro']): ?>
                                                <span class="wpwand-pro-tag">PRO</span>
                                            <?php endif; ?>
                                        </h4>
                                        <p>
                                            <?php echo esc_html__($template['description'], 'wp-wand') ?>
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
                                            </svg> <?php esc_html_e('Back to all templates', 'wp-wand'); ?></span>

                                        <div class="wpwand-template-details">
                                            <h4>
                                                <?php echo esc_html__($template['title'], 'wp-wand') ?>
                                            </h4>
                                            <p>
                                                <?php echo esc_html__($template['description'], 'wp-wand') ?>
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
                                                        <label for="wpwand-topic"><?php esc_html_e('Topic', 'wp-wand'); ?></label>
                                                        <input type="text" id="wpwand-topic" name="wpwand-topic"
                                                            placeholder="<?php esc_html_e('Write in detail about your topic', 'wp-wand'); ?>">
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Product Name -->
                                            <?php if (in_array('Name', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-product-name"><?php esc_html_e('Name', 'wp-wand'); ?></label>
                                                        <input type="text" id="wpwand-product-name" name="wpwand-product-name"
                                                            placeholder="<?php esc_html_e('Write your product name', 'wp-wand'); ?>">
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Product Name -->
                                            <?php if (in_array('Comment', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-comment"><?php esc_html_e('Comment', 'wp-wand'); ?></label>
                                                        <input type="text" id="wpwand-comment" name="wpwand-comment"
                                                            placeholder="<?php esc_html_e('Write your comment', 'wp-wand'); ?>">
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Question -->
                                            <?php if (in_array('Question', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-question"><?php esc_html_e('Question', 'wp-wand'); ?></label>
                                                        <input type="text" id="wpwand-question" name="wpwand-question"
                                                            placeholder="<?php esc_html_e('Write your Question', 'wp-wand'); ?>">
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Subject -->
                                            <?php if (in_array('Subject', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-subject"><?php esc_html_e('Subject', 'wp-wand'); ?></label>
                                                        <input type="text" id="wpwand-subject" name="wpwand-subject"
                                                            placeholder="<?php esc_html_e('Write your Subject', 'wp-wand'); ?>">
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Product Name -->
                                            <?php if (in_array('Comment', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-comment"><?php esc_html_e('Comment', 'wp-wand'); ?></label>
                                                        <input type="text" id="wpwand-comment" name="wpwand-comment"
                                                            placeholder="<?php esc_html_e('Write your comment', 'wp-wand'); ?>">
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Product 1 -->
                                            <?php if (in_array('Product 1', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-product-1"><?php esc_html_e('Product 1', 'wp-wand'); ?></label>
                                                        <input type="text" id="wpwand-product-1" name="wpwand-product-1"
                                                            placeholder="<?php esc_html_e('Product 1', 'wp-wand'); ?>">
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Product 2 -->
                                            <?php if (in_array('Product 2', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-product-2"><?php esc_html_e('Product 2', 'wp-wand'); ?></label>
                                                        <input type="text" id="wpwand-product-2" name="wpwand-product-2"
                                                            placeholder="<?php esc_html_e('Product 2', 'wp-wand'); ?>">
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <!-- description -->
                                            <?php if (in_array('Description', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-description"><?php esc_html_e('Description', 'wp-wand'); ?></label>
                                                        <input type="text" id="wpwand-description" name="wpwand-description"
                                                            placeholder="<?php esc_html_e('Write a meaningful description to generate better result.', 'wp-wand'); ?>">
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- description 1 -->
                                            <?php if (in_array('Product 1 Description', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-description-1"><?php esc_html_e('Product 1 Description', 'wp-wand'); ?></label>
                                                        <input type="text" id="wpwand-description-1" name="wpwand-description-1"
                                                            placeholder="<?php esc_html_e('Write a meaningful description to generate better result.', 'wp-wand'); ?>">
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <!-- description 2 -->
                                            <?php if (in_array('Product 2 Description', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-description-2"><?php esc_html_e('Product 2 Description', 'wp-wand'); ?></label>
                                                        <input type="text" id="wpwand-description-2" name="wpwand-description-2"
                                                            placeholder="<?php esc_html_e('Write a meaningful description to generate better result.', 'wp-wand'); ?>">
                                                    </div>
                                                </div>
                                            <?php endif; ?>


                                            <!-- Content -->
                                            <?php if (in_array('Content', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-content"><?php esc_html_e('Content', 'wp-wand'); ?></label>
                                                        <input name="wpwand-content" id="wpwand-content" placeholder="<?php esc_html_e('Write your content', 'wp-wand'); ?>" />
                                                    </div>
                                                </div>
                                            <?php endif; ?>


                                            <!-- Content Text Area -->
                                            <?php if (in_array('Content Text Area', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-content-textarea"><?php esc_html_e('Content', 'wp-wand'); ?></label>
                                                        <textarea name="wpwand-content-textarea" id="wpwand-content-textarea" cols="30"
                                                            rows="10" placeholder="<?php esc_html_e('Write your content', 'wp-wand'); ?>"></textarea>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <!-- Write anything Text Area -->
                                            <?php if (in_array('custom_textarea', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-custom_textarea"><?php esc_html_e('Write Anything', 'wp-wand'); ?></label>
                                                        <textarea name="wpwand-custom_textarea" id="wpwand-custom_textarea" cols="30"
                                                            rows="10" placeholder="<?php esc_html_e('Write Anything', 'wp-wand'); ?>"></textarea>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (in_array('Keywords', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-keyword"><?php esc_html_e('Keyword to Include', 'wp-wand'); ?> <span
                                                                class="wpwand-optional">(<?php esc_html_e('Optional', 'wp-wand'); ?>)</span></label>
                                                        <input type="text" id="wpwand-keyword" name="wpwand-keyword"
                                                            placeholder="<?php esc_html_e('Write keyword and separate using comma', 'wp-wand'); ?>">
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
                                                        <label for="wpwand-result-number"><?php esc_html_e('Number of Results', 'wp-wand'); ?></label>
                                                        <input type="number" id="wpwand-result-number" min="1" max="10"
                                                            name="wpwand-result-number" value="1">
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (in_array('Tone', $fields)): ?>
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-tone"><?php esc_html_e('Tone', 'wp-wand'); ?></label>
                                                        <select name="wpwand-tone" id="wpwand-tone">
                                                            <option value="friendly"> <?php esc_html_e('Friendly', 'wp-wand'); ?></option>
                                                            <option value="helpful"><?php esc_html_e('Helpful', 'wp-wand'); ?></option>
                                                            <option value="informative"><?php esc_html_e('Informative', 'wp-wand'); ?></option>
                                                            <option value="aggressive"><?php esc_html_e('Aggressive', 'wp-wand'); ?></option>
                                                            <option value="professional"><?php esc_html_e('Professional', 'wp-wand'); ?></option>
                                                            <option value="Formal"><?php esc_html_e('Formal', 'wp-wand'); ?></option>
                                                            <option value="Informal"><?php esc_html_e('Informal', 'wp-wand'); ?></option>
                                                            <option value="Conversational"><?php esc_html_e('Conversational', 'wp-wand'); ?></option>
                                                            <option value="Persuasive"><?php esc_html_e('Persuasive', 'wp-wand'); ?></option>
                                                            <option value="Witty"><?php esc_html_e('Witty', 'wp-wand'); ?></option>
                                                            <option value="Descriptive"><?php esc_html_e('Descriptive', 'wp-wand'); ?></option>
                                                            <option value="Expository"><?php esc_html_e('Expository', 'wp-wand'); ?></option>
                                                            <option value="Humorous"><?php esc_html_e('Humorous', 'wp-wand'); ?></option>
                                                            <option value="Inspirational"><?php esc_html_e('Inspirational', 'wp-wand'); ?></option>
                                                            <option value="Funny"><?php esc_html_e('Funny', 'wp-wand'); ?></option>
                                                            <option value="Poetic"><?php esc_html_e('Poetic', 'wp-wand'); ?></option>
                                                            <option value="Technical"><?php esc_html_e('Technical', 'wp-wand'); ?></option>
                                                            <option value="Argumentative"><?php esc_html_e('Argumentative', 'wp-wand'); ?></option>
                                                            <option value="Instructional"><?php esc_html_e('Instructional', 'wp-wand'); ?></option>
                                                            <option value="Sarcastic"><?php esc_html_e('Sarcastic', 'wp-wand'); ?></option>
                                                            <option value="Urgent"><?php esc_html_e('Urgent', 'wp-wand'); ?></option>
                                                            <option value="Optimistic"><?php esc_html_e('Optimistic', 'wp-wand'); ?></option>
                                                        </select>
                                                    </div>
                                                <?php endif; ?>
                                            </div>


                                            <?php if (in_array('Word Count', $fields)): ?>
                                                <div class="wpwand-form-group">
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-word-limit"><?php esc_html_e('Minimum Word', 'wp-wand'); ?></label>
                                                        <input type="number" id="wpwand-word-limit" name="wpwand-word-limit" value="100">
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($template['point_of_view']): ?>
                                                <div class="wpwand-form-field">
                                                    <label for="wpwand-point-of-view"><?php esc_html_e('Point of View', 'wp-wand'); ?></label>
                                                    <select name="wpwand-point-of-view" id="wpwand-point-of-view">
                                                        <!-- <option value="">Select a</option> -->
                                                        <option value="1st-person"><?php esc_html_e('1st Person', 'wp-wand'); ?></option>
                                                        <option value="2nd-person"><?php esc_html_e('2nd Person', 'wp-wand'); ?></option>
                                                        <option value="3rd-person"><?php esc_html_e('3rd Person', 'wp-wand'); ?></option>
                                                    </select>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Language option  -->

                                            <div class="wpwand-form-group wpwand-col-2">
                                                <div class="wpwand-form-field">
                                                    <label for="wpwand-Language"><?php esc_html_e('Language', 'wp-wand'); ?></label>
                                                    <select name="wpwand-Language" id="wpwand-Language">
                                                        <?php
                                                        if (is_array(wpwand_language_array())) {
                                                            $default_language = wpwand_get_option('wpwand_language', 'en');
                                                            foreach (wpwand_language_array() as $key => $value) {
                                                                printf('<option value="%s" %s >%s</option>', $key, selected($default_language, $key), $key); //phpcs:ignore
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                                <?php
                                                if (function_exists('wpwand_pro_tala_check') && wpwand_pro_tala_check()): ?>
                                                    <div class="wpwand-form-field">
                                                        <label for="wpwand-aichar"><?php esc_html_e('AI Character', 'wp-wand'); ?></label>
                                                        <select name="wpwand-aichar" id="wpwand-aichar">
                                                            <option><?php esc_html_e('No Character', 'wp-wand'); ?></option>
                                                            <?php
                                                            if ($custom_prompt) {

                                                                foreach ($custom_prompt as $value) {
                                                                    printf('<option value="%s">%s</option>', $value['prompt'], $value['title']); //phpcs:ignore
                                                                }
                                                            }
                                                            if (function_exists('wpwand_pro_premad_aichars')) {
                                                                foreach (wpwand_pro_premad_aichars() as $value) {
                                                                    printf('<option value="%s">%s</option>', $value['prompt'], $value['title']); //phpcs:ignore
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
                                                        <h4><?php esc_html_e('Include Info', 'wp-wand'); ?></h4>

                                                        <!-- <label class="wpwand-radio-label" for="wpwand_ai_inf">
                                                        <input type="checkbox" id="wpwand_ai_inf" name="wpwand_ai_inf"
                                                            class="wpwand-radio">
                                                        AI Character
                                                    </label> -->
                                                        <label for="wpwand_biz_inf" class="wpwand-radio-label">
                                                            <input type="checkbox" id="wpwand_biz_inf" name="wpwand_biz_inf"
                                                                class="wpwand-radio">
                                                            <?php esc_html_e('Business Details', 'wp-wand'); ?>
                                                        </label>
                                                        <label for="wpwand_tgdc_inf" class="wpwand-radio-label">
                                                            <input type="checkbox" id="wpwand_tgdc_inf" name="wpwand_tgdc_inf"
                                                                class="wpwand-radio">
                                                            <?php esc_html_e('Targeted Customer', 'wp-wand'); ?>
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
                                                        <?php esc_html_e('Get Pro to Use This Template', 'wp-wand'); ?></a>
                                                <?php else: ?>
                                                    <button class="wpwand-submit-button"><?php esc_html_e('Generate Content', 'wp-wand'); ?></button>
                                                <?php endif; ?>
                                            </div>
                                        </form>

                                        <div class="wpwand-result-box" style="display: none;">
                                            <h4><?php esc_html_e('AI Generated Content', 'wp-wand'); ?></h4>
                                            <div class="wpwand-content-wrap"></div>

                                        </div>
                                    </div>
                            <?php endforeach;
                            endif; ?>
                        </div>

                    </div>
                    <div class="wpwand-prompt-item" id="wpwand-image-generation">


                        <div class="wpwand-template-list">


                            <div class="wpwand-prompt-form-wrap">

                                <div class="wpwand-template-details">
                                    <h4>
                                        <?php echo esc_html__('Generate Image', 'wp-wand'); ?>
                                    </h4>
                                    <p>
                                        <?php echo esc_html__('Get beautiful AI generated images in seconds', 'wp-wand'); ?>
                                    </p>
                                </div>
                                <form action="" class="wpwand-prompt-form">
                                    <input type="hidden" id="wpwand-prompt-id" name="wpwand-prompt-id"
                                        value="<?PHP echo esc_html('wpwand-image-generation') ?>">
                                    <input type="hidden" id="wpwand-prompt" name="wpwand-prompt"
                                        value="<?PHP echo esc_html('wpwand-image-generation') ?>">


                                    <div class="wpwand-form-group">
                                        <div class="wpwand-form-field">
                                            <label for="wpwand-image-prompt"><?php esc_html_e('Image Description', 'wp-wand'); ?></label>
                                            <input type="text" id="wpwand-image-prompt" name="wpwand-image-prompt"
                                                placeholder="<?php esc_html_e('Write in details about your image', 'wp-wand'); ?>">
                                        </div>
                                    </div>
                                    <?php do_action('wpwand_dall_e_frontend_fields') ?>
                                    <div class="wpwand-form-submit">

                                        <button class="wpwand-submit-button"><?php esc_html_e('Generate Image', 'wp-wand'); ?></button>

                                    </div>

                                </form>

                                <div class="wpwand-result-box wpwand-image-result-box" style="display: none;">
                                    <h4><?php esc_html_e('AI Generated Image', 'wp-wand'); ?></h4>
                                    <div class="wpwand-content-wrap"></div>

                                </div>
                            </div>

                        </div>

                    </div>

                </div>
            <?php endif; ?>

        </div>
    </div>


<?php printf("%s", ob_get_clean()); //phpcs:ignore

}

add_action('admin_footer', 'wpwand_frontend_callback');
// add_action( 'wp_footer', 'wpwand_frontend_callback' );