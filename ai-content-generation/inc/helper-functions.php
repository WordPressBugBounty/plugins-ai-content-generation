<?php

function wpwand_admin_scripts()
{
    $screen = get_current_screen();
    $custom_css = '
    .wpwand_editor_icon button {
        background-image: url(' . wpwand_loago_icon_url() . ');
    }
    :root {
        --wpwand-brand-color: ' . wpwand_brand_color() . '
    }
    ';
    wp_enqueue_media();
    wp_enqueue_style('wpwand-inter-font', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
    wp_enqueue_style('jquery-ui', WPWAND_PLUGIN_URL . 'assets/css/jquery-ui.css');
    wp_enqueue_style('sweetalert2', WPWAND_PLUGIN_URL . 'assets/css/sweetalert2.min.css');
    wp_enqueue_style('wpwand-admin', WPWAND_PLUGIN_URL . 'assets/css/admin.css');
    wp_add_inline_style('wpwand-admin', $custom_css);





    // wp_enqueue_script('jquery-ui-slider');
    wp_enqueue_script('jquery-showdown', 'https://cdnjs.cloudflare.com/ajax/libs/showdown/2.1.0/showdown.min.js', ['jquery']);
    wp_enqueue_script('sweetalert2', WPWAND_PLUGIN_URL . 'assets/js/sweetalert2.all.min.js', ['jquery']);
    wp_enqueue_script('wpwand-admin', WPWAND_PLUGIN_URL . 'assets/js/admin.js', ['jquery', 'jquery-ui-slider']);
    wp_localize_script(
        'wpwand-admin',
        'wpwand_glb',
        array(
            'plugin_url' => WPWAND_PLUGIN_URL,
            'ajax_url' => admin_url('admin-ajax.php'),
            'setting_url' => admin_url('admin.php?page=wpwand'),
            'toggler_positions' => wpwand_get_option('toggler_position', 'top'),
            'logo' => wpwand_loago_icon_url(),
            'admin_url' => admin_url(),
            'hide_ai_bar' => wpwand_get_option('wpwand_hide_ai_bar_gutenberg', 0),
            'post_id' => get_the_id(),
            'is_pro' => defined('WPWAND_PRO_FILE_') ?? false,
        )
    );

    wp_enqueue_script('wpwand-seo', WPWAND_PLUGIN_URL . 'assets/js/seo.js', ['wpwand-admin']);

    // Check if the current screen is 'edit-product' (assuming 'product' is the post type)
    if ($screen->id === 'product') {
        wp_enqueue_script('wpwand-wwoocommerce', WPWAND_PLUGIN_URL . 'assets/js/woocommerce.js', ['wpwand-admin']);
    }


    if (strpos($screen->id, "wpwand-post-generator")) {
        // wp_enqueue_script('vue', WPWAND_PLUGIN_URL . 'assets/js/vue.global.js', array('jquery'), null, true);
        wp_enqueue_script('wpwand-wwoocommerce', WPWAND_PLUGIN_URL . 'assets/js/post-generator.js', ['wpwand-admin']);
    }

}

add_action('admin_enqueue_scripts', 'wpwand_admin_scripts');
// add_action( 'wp_enqueue_scripts', 'wpwand_admin_scripts' );

function wpwand_pro_card()
{
    ?>

    <div class="wpwand-pro-card-wrapper">
        <div class="wpwand-pro-card">
            <div class="wpwand-pro-card-header">
                <div class="wpwand-logo"> <svg width="27" height="28" viewBox="0 0 27 28" fill="none"
                        xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M23.4071 8.81326L21.3469 10.8735L17.1987 6.72536L19.259 4.66511C19.8397 4.08437 20.5864 3.80783 21.3331 3.80783C22.0797 3.80783 22.8264 4.08437 23.4071 4.66511C24.5548 5.81277 24.5548 7.66561 23.4071 8.81326Z"
                            fill="white" />
                        <path
                            d="M20.3786 11.8557L5.43138 26.789C4.28372 27.9366 2.43088 27.9366 1.28323 26.789C0.135572 25.6413 0.135572 23.7885 1.28323 22.6408L16.2305 7.70752L20.3786 11.8557Z"
                            fill="white" />
                        <path
                            d="M10.2032 3.28461L10.7701 1.36263C10.8254 1.18288 10.7701 0.989297 10.6457 0.851026C10.5212 0.712754 10.3 0.657445 10.1202 0.712754L8.19827 1.27967L6.2763 0.712754C6.09654 0.657445 5.90296 0.712754 5.76469 0.837198C5.62642 0.97547 5.58494 1.16905 5.64025 1.3488L6.19333 3.28461L5.62642 5.20658C5.57111 5.38632 5.62642 5.57992 5.75086 5.71819C5.88914 5.85646 6.08272 5.89794 6.26247 5.84263L8.19827 5.28955L10.1202 5.85646C10.1756 5.87029 10.217 5.88411 10.2723 5.88411C10.4106 5.88411 10.535 5.8288 10.6457 5.73201C10.7839 5.59374 10.8254 5.40016 10.7701 5.22041L10.2032 3.28461Z"
                            fill="white" />
                        <path
                            d="M4.67233 11.5809L5.23924 9.65891C5.29455 9.47916 5.23924 9.28559 5.1148 9.14732C4.97652 9.00905 4.78294 8.96757 4.60319 9.02288L2.66739 9.57596L0.745399 9.00905C0.565646 8.95374 0.372079 9.00905 0.233807 9.13349C0.0955358 9.27177 0.0540545 9.46534 0.109363 9.6451L0.662449 11.5809L0.095536 13.5029C0.0402273 13.6826 0.0955359 13.8762 0.21998 14.0145C0.358252 14.1527 0.551819 14.1942 0.731572 14.1389L2.65355 13.572L4.57554 14.1389C4.61702 14.1527 4.67233 14.1527 4.72762 14.1527C4.86589 14.1527 4.99035 14.0974 5.10097 14.0006C5.23924 13.8624 5.28072 13.6688 5.22541 13.489L4.67233 11.5809Z"
                            fill="white" />
                        <path
                            d="M25.412 18.4938L25.9789 16.5718C26.0342 16.392 25.9789 16.1985 25.8544 16.0602C25.7161 15.9219 25.5226 15.8804 25.3428 15.9358L23.4208 16.5027L21.4989 15.9358C21.3191 15.8804 21.1255 15.9358 20.9873 16.0602C20.849 16.1985 20.8075 16.392 20.8628 16.5718L21.4297 18.4938L20.8628 20.4158C20.8075 20.5955 20.8628 20.7891 20.9873 20.9274C21.1255 21.0656 21.3191 21.1071 21.4989 21.0518L23.4208 20.4849L25.3428 21.0518C25.3843 21.0656 25.4396 21.0656 25.4949 21.0656C25.6332 21.0656 25.7576 21.0103 25.8682 20.9135C26.0065 20.7753 26.048 20.5817 25.9927 20.4019L25.412 18.4938Z"
                            fill="white" />
                    </svg></div>
                <h3>
                    <?php echo esc_html(wpwand_brand_name()) ?> pro
                </h3>
                <p>Get access to
                    <?php echo esc_html(wpwand_brand_name()) ?> Pro to generate high quality & unique results with lower
                    cost.
                </p>
            </div>
            <div class="wpwand-pro-card-content">
                <ul>
                    <li>
                        <svg width="14" height="11" viewBox="0 0 14 11" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1.16663 6.375L4.49996 9.875L12.8333 1.125" stroke="#3BCB38" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Full Access to
                        <?php echo esc_html(wpwand_brand_name()) ?> Pro
                    </li>
                    <li>
                        <svg width="14" height="11" viewBox="0 0 14 11" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1.16663 6.375L4.49996 9.875L12.8333 1.125" stroke="#3BCB38" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        All 40+ Free & Premium Templates
                    </li>
                    <li>
                        <svg width="14" height="11" viewBox="0 0 14 11" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1.16663 6.375L4.49996 9.875L12.8333 1.125" stroke="#3BCB38" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        High Quality & Better Results
                    </li>
                    <li>
                        <svg width="14" height="11" viewBox="0 0 14 11" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1.16663 6.375L4.49996 9.875L12.8333 1.125" stroke="#3BCB38" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Advanced Data Setup like Character, Business Details & Targeted Customers
                    </li>
                    <li>
                        <svg width="14" height="11" viewBox="0 0 14 11" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1.16663 6.375L4.49996 9.875L12.8333 1.125" stroke="#3BCB38" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Seamless Content Creation Inside Gutenberg Editor
                    </li>
                    <li>
                        <svg width="14" height="11" viewBox="0 0 14 11" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1.16663 6.375L4.49996 9.875L12.8333 1.125" stroke="#3BCB38" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Translate in real-time into over 35 languages
                    </li>
                    <li>
                        <svg width="14" height="11" viewBox="0 0 14 11" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1.16663 6.375L4.49996 9.875L12.8333 1.125" stroke="#3BCB38" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        1 Year Plugin Update
                    </li>
                    <li>
                        <svg width="14" height="11" viewBox="0 0 14 11" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1.16663 6.375L4.49996 9.875L12.8333 1.125" stroke="#3BCB38" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        1 Year Priority Support
                    </li>
                </ul>
            </div>
            <div class="wpwand-pro-card-footer">
                <div class="wpwand-pro-prices">
                    <span class="wpwand-regular-price">$49</span>
                    <span class="wpwand-sale-price">$29</span>
                </div>
                <p>Annual License</p>
                <a href="https://wpwand.com/pro-plugin" target="_blank">Get Pro to Unlock Everything</a>
            </div>
        </div>
    </div>
    <?php
}

function wpwand_model_details_card()
{
    if (!WPWAND_OPENAI_KEY) {
        return false;
    }
    ?>

    <div class="wpwand-model-card-wrapper">
        <div class="wpwand-model-card" id="wpwand-gpt-3-5">
            <div class="wpwand-model-card-header">
                <h3>gpt-3.5-turbo</h3>
                <p>Most capable GPT-3.5 model at 1/10th the cost of text-davinci-003. Will be updated with OpenAI’s latest
                    model iteration.</p>
            </div>
            <div class="wpwand-model-card-content">
                <h4>Cost Estimation</h4>
                <ul>
                    <li>$0.01 for approx. 1000 words</li>
                    <li>$0.1 for approx. 10,000 words</li>
                    <li>$1 for approx. 100,000 words</li>
                </ul>
            </div>
            <div class="wpwand-model-card-footer">
                <h4>Pricing Fact</h4>
                <p>Using this model, you can write approximately 65 blog posts, each containing ~1500 words, for just $1.
                </p>

            </div>
        </div>
        <div class="wpwand-model-card" id="wpwand-text-davinci-003">
            <div class="wpwand-model-card-header">
                <h3>text-davinci-003</h3>
                <p>Most capable GPT-3.5 model at 1/10th the cost of text-davinci-003. Will be updated with OpenAI’s latest
                    model iteration.</p>
            </div>
            <div class="wpwand-model-card-content">
                <h4>Cost Estimation</h4>
                <ul>
                    <li>$0.01 for approx. 1000 words</li>
                    <li>$0.1 for approx. 10,000 words</li>
                    <li>$1 for approx. 100,000 words</li>
                </ul>
            </div>
            <div class="wpwand-model-card-footer">
                <h4>Pricing Fact</h4>
                <p>Using this model, you can write approximately 65 blog posts, each containing ~1500 words, for just $1.
                </p>

            </div>
        </div>
        <div class="wpwand-model-card" id="wpwand-text-curie-001">
            <div class="wpwand-model-card-header">
                <h3>text-curie-001</h3>
                <p>Most capable GPT-3.5 model at 1/10th the cost of text-davinci-003. Will be updated with OpenAI’s latest
                    model iteration.</p>
            </div>
            <div class="wpwand-model-card-content">
                <h4>Cost Estimation</h4>
                <ul>
                    <li>$0.01 for approx. 1000 words</li>
                    <li>$0.1 for approx. 10,000 words</li>
                    <li>$1 for approx. 100,000 words</li>
                </ul>
            </div>
            <div class="wpwand-model-card-footer">
                <h4>Pricing Fact</h4>
                <p>Using this model, you can write approximately 65 blog posts, each containing ~1500 words, for just $1.
                </p>

            </div>
        </div>
        <div class="wpwand-model-card" id="wpwand-text-babbage-001">
            <div class="wpwand-model-card-header">
                <h3>text-babbage-001</h3>
                <p>Most capable GPT-3.5 model at 1/10th the cost of text-davinci-003. Will be updated with OpenAI’s latest
                    model iteration.</p>
            </div>
            <div class="wpwand-model-card-content">
                <h4>Cost Estimation</h4>
                <ul>
                    <li>$0.01 for approx. 1000 words</li>
                    <li>$0.1 for approx. 10,000 words</li>
                    <li>$1 for approx. 100,000 words</li>
                </ul>
            </div>
            <div class="wpwand-model-card-footer">
                <h4>Pricing Fact</h4>
                <p>Using this model, you can write approximately 65 blog posts, each containing ~1500 words, for just $1.
                </p>

            </div>
        </div>
        <div class="wpwand-model-card" id="wpwand-text-ada-001">
            <div class="wpwand-model-card-header">
                <h3>text-ada-001</h3>
                <p>Most capable GPT-3.5 model at 1/10th the cost of text-davinci-003. Will be updated with OpenAI’s latest
                    model iteration.</p>
            </div>
            <div class="wpwand-model-card-content">
                <h4>Cost Estimation</h4>
                <ul>
                    <li>$0.01 for approx. 1000 words</li>
                    <li>$0.1 for approx. 10,000 words</li>
                    <li>$1 for approx. 100,000 words</li>
                </ul>
            </div>
            <div class="wpwand-model-card-footer">
                <h4>Pricing Fact</h4>
                <p>Using this model, you can write approximately 65 blog posts, each containing ~1500 words, for just $1.
                </p>

            </div>
        </div>
    </div>
    <?php
}

function wpwand_get_option($opt, $default = '')
{
    if (get_option($opt)) {
        return get_option($opt);
    } else {
        return $default;
    }
}

function wpwand_add_advanced_tab()
{
    ?>
    <a href="#advanced" class="wpwand-nav-tab">
        <?php esc_html_e('Advanced', 'wpwand'); ?>
    </a>
    <?php
}

add_action('wpwand_add_tab_link', 'wpwand_add_advanced_tab');

function wpwand_add_advanced_tab_content()
{
    ?>
    <div id="advanced" class="tab-panel" style="display:none;">
        <div class="wpwand-tab-header">
            <h4>
                <?php esc_html_e('Advanced Features', 'wpwand'); ?>
            </h4>
            <p class="wpwand-field-desc">Improve your content quality 5x with these premium options. Generate
                targeted, high quality unique content fast.</p>
        </div>
        <table class="form-table">

            <tr>
                <th scope="row">
                    <label for="wpwand_ai_character">
                        <?php esc_html_e('A.I Character', 'wpwand'); ?>
                        <?php echo wpwand_upgrade_to_pro_button() ?>
                    </label>
                    <span class="wpwand-field-desc">Tell your A.I what character it contains while writing for
                        you. It will highly improve your content output.</span>
                </th>
                <td>
                    <textarea disabled id="wpwand_ai_character" name="wpwand_ai_character" rows="5" cols="30"
                        placeholder="Example: You are an expert in SEO copywriting and specializing in Amazon product review article writing."><?php echo esc_attr(wpwand_get_option('wpwand_ai_character', )); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="wpwand_busines_details">
                        <?php esc_html_e('Business Details', 'wpwand'); ?>
                        <?php echo wpwand_upgrade_to_pro_button() ?>

                    </label>
                    <span class="wpwand-field-desc">Write about your business in detail so that AI can
                        understand and create better content based your business. </span>
                </th>
                <td>
                    <textarea disabled id="wpwand_busines_details" name="wpwand_busines_details" rows="5" cols="30"
                        placeholder="Example: We write and share detail reviews of hiking items on our blog. We get most visitors through search engines. Our targeted visitors trust our articles to purchase hiking related products from Amazon."><?php echo esc_attr(wpwand_get_option('wpwand_busines_details')); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="wpwand_targated_customer">
                        <?php esc_html_e('Targated Customer', 'wpwand'); ?>
                        <?php echo wpwand_upgrade_to_pro_button() ?>

                    </label>
                    <span class="wpwand-field-desc">Write about your target customers in details. Give as much
                        information as possible like who are your targeted customers, where are they from, what
                        are there demographic etc.</span>
                </th>
                <td>
                    <textarea disabled id="wpwand_targated_customer" name="wpwand_targated_customer" rows="5" cols="30"
                        placeholder="Example: Our targeted customers are from USA. They are young and energetic. They are interested in tours and mostly in hiking."><?php echo esc_attr(wpwand_get_option('wpwand_targated_customer')); ?></textarea>
                </td>
            </tr>

        </table>
        <a href="https://wpwand.com/pro-plugin" target="_blank" class="wpwand-submit-pro-btn wpwand-get-pro-button">Get Pro
            Version</a>


    </div>
    <?php
}

add_action('wpwand_add_tab_content', 'wpwand_add_advanced_tab_content');

function wpwand_upgrade_to_pro_button($label = 'Upgrade To Pro')
{
    return sprintf('<a class="wpwand-pro-tag" target="_blank" href="%s">%s</a>', esc_url('https://wpwand.com/pro-plugin'), $label);
}
function wpwand_geneeral_locked_content()
{

    ?>


    <tr valign="top">
        <th scope="row">
            <label for="wpwand_frequency">
                <?php esc_html_e('Sync Plugin Data', 'wpwand'); ?>
                <?php echo wpwand_upgrade_to_pro_button() ?>
            </label>
            <span class="wpwand-field-desc">We often update our data for higher quality results.
                By clicking sync button you can get updated data instantly.</span>
        </th>
        <td>
            <div class="wpwand-slider-input-wrap">
                <span class="wpwand-sync-prompt">Sync</span>
            </div>
        </td>
    </tr>
    <?php
}
add_action('wpwand_general_tab_content', 'wpwand_geneeral_locked_content');

function wpwand_welcome_screen()
{
    ?>

    <div class="wpwand-welcome-screen-wrapper">
        <div class="wpwand-welcome-screen">
            <div class="wpwand-welcome-screen-header">
                <div class="wpwand-logo"> <svg width="27" height="28" viewBox="0 0 27 28" fill="none"
                        xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M23.4071 8.81326L21.3469 10.8735L17.1987 6.72536L19.259 4.66511C19.8397 4.08437 20.5864 3.80783 21.3331 3.80783C22.0797 3.80783 22.8264 4.08437 23.4071 4.66511C24.5548 5.81277 24.5548 7.66561 23.4071 8.81326Z"
                            fill="white" />
                        <path
                            d="M20.3786 11.8557L5.43138 26.789C4.28372 27.9366 2.43088 27.9366 1.28323 26.789C0.135572 25.6413 0.135572 23.7885 1.28323 22.6408L16.2305 7.70752L20.3786 11.8557Z"
                            fill="white" />
                        <path
                            d="M10.2032 3.28461L10.7701 1.36263C10.8254 1.18288 10.7701 0.989297 10.6457 0.851026C10.5212 0.712754 10.3 0.657445 10.1202 0.712754L8.19827 1.27967L6.2763 0.712754C6.09654 0.657445 5.90296 0.712754 5.76469 0.837198C5.62642 0.97547 5.58494 1.16905 5.64025 1.3488L6.19333 3.28461L5.62642 5.20658C5.57111 5.38632 5.62642 5.57992 5.75086 5.71819C5.88914 5.85646 6.08272 5.89794 6.26247 5.84263L8.19827 5.28955L10.1202 5.85646C10.1756 5.87029 10.217 5.88411 10.2723 5.88411C10.4106 5.88411 10.535 5.8288 10.6457 5.73201C10.7839 5.59374 10.8254 5.40016 10.7701 5.22041L10.2032 3.28461Z"
                            fill="white" />
                        <path
                            d="M4.67233 11.5809L5.23924 9.65891C5.29455 9.47916 5.23924 9.28559 5.1148 9.14732C4.97652 9.00905 4.78294 8.96757 4.60319 9.02288L2.66739 9.57596L0.745399 9.00905C0.565646 8.95374 0.372079 9.00905 0.233807 9.13349C0.0955358 9.27177 0.0540545 9.46534 0.109363 9.6451L0.662449 11.5809L0.095536 13.5029C0.0402273 13.6826 0.0955359 13.8762 0.21998 14.0145C0.358252 14.1527 0.551819 14.1942 0.731572 14.1389L2.65355 13.572L4.57554 14.1389C4.61702 14.1527 4.67233 14.1527 4.72762 14.1527C4.86589 14.1527 4.99035 14.0974 5.10097 14.0006C5.23924 13.8624 5.28072 13.6688 5.22541 13.489L4.67233 11.5809Z"
                            fill="white" />
                        <path
                            d="M25.412 18.4938L25.9789 16.5718C26.0342 16.392 25.9789 16.1985 25.8544 16.0602C25.7161 15.9219 25.5226 15.8804 25.3428 15.9358L23.4208 16.5027L21.4989 15.9358C21.3191 15.8804 21.1255 15.9358 20.9873 16.0602C20.849 16.1985 20.8075 16.392 20.8628 16.5718L21.4297 18.4938L20.8628 20.4158C20.8075 20.5955 20.8628 20.7891 20.9873 20.9274C21.1255 21.0656 21.3191 21.1071 21.4989 21.0518L23.4208 20.4849L25.3428 21.0518C25.3843 21.0656 25.4396 21.0656 25.4949 21.0656C25.6332 21.0656 25.7576 21.0103 25.8682 20.9135C26.0065 20.7753 26.048 20.5817 25.9927 20.4019L25.412 18.4938Z"
                            fill="white" />
                    </svg></div>
                <h3>Welcome to
                    <?php echo esc_html(wpwand_brand_name()) ?>
                </h3>
                <p>Your ultimate AI content generation assistant.</p>
                <!-- <img src="<?php echo WPWAND_PLUGIN_URL . 'assets/img/video.png' ?>" alt=""> -->
                <iframe width="560" src="https://www.youtube.com/embed/CJkraHhSsZ8" title="YouTube video player"
                    frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    allowfullscreen></iframe>
            </div>
            <div class="wpwand-welcome-screen-content">
                <!-- <div class="wpwand-welcome-promo-card">
                    <div class="wpwand-promo-content">
                        <span>LIMITED TIME DEAL</span>
                        <h4>Get
                            <?php echo esc_html(wpwand_brand_name()) ?> Pro for Lifetime with limited 50% discount.
                        </h4>
                    </div>
                    <div class="wpwand-promo-acton">
                        <a target="_blank" href="https://wpwand.com/pricing-plan/">Get Lifetime Access</a>
                    </div>
                </div> -->
                <div class="wpwand-doc-button">
                    <a href="https://wpwand.com/docs/knowledge-base/getting-started/" target="_blank">
                        <svg width="21" height="18" viewBox="0 0 21 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M17.5 17H3.5C2.39543 17 1.5 16.1046 1.5 15L1.5 3C1.5 1.89543 2.39543 1 3.5 1L13.5 1C14.6046 1 15.5 1.89543 15.5 3V4M17.5 17C16.3954 17 15.5 16.1046 15.5 15L15.5 4M17.5 17C18.6046 17 19.5 16.1046 19.5 15V6C19.5 4.89543 18.6046 4 17.5 4L15.5 4M11.5 1L7.5 1M5.5 13H11.5M5.5 5H11.5V9H5.5V5Z"
                                stroke="#080E13" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Read Our Documentation
                    </a>
                </div>
                <h4>What are you getting for free?</h4>
                <ul>
                    <li>
                        <svg width="14" height="11" viewBox="0 0 14 11" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1.16663 6.375L4.49996 9.875L12.8333 1.125" stroke="#3BCB38" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        No Monthly or Yearly Subscription Required
                    </li>
                    <li>
                        <svg width="14" height="11" viewBox="0 0 14 11" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1.16663 6.375L4.49996 9.875L12.8333 1.125" stroke="#3BCB38" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        No Limit on Content Generation
                    </li>
                    <li>
                        <svg width="14" height="11" viewBox="0 0 14 11" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1.16663 6.375L4.49996 9.875L12.8333 1.125" stroke="#3BCB38" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        12+ Free Prompt Templates
                    </li>
                    <li>
                        <svg width="14" height="11" viewBox="0 0 14 11" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1.16663 6.375L4.49996 9.875L12.8333 1.125" stroke="#3BCB38" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Access to AI Assistant (Limited for Free Version)
                    </li>
                    <li>
                        <svg width="14" height="11" viewBox="0 0 14 11" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1.16663 6.375L4.49996 9.875L12.8333 1.125" stroke="#3BCB38" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Free Plugin Update
                    </li>
                    <li>
                        <svg width="14" height="11" viewBox="0 0 14 11" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1.16663 6.375L4.49996 9.875L12.8333 1.125" stroke="#3BCB38" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Free Support
                    </li>

                </ul>
            </div>
            <?php // if ( !WPWAND_OPENAI_KEY ): ?>
            <div class="wpwand-welcome-screen-footer">

                <h4>Start by connecting your free OpenAI Key</h4>
                <!-- wp wand api missing notice  -->

                <div class="wpwand-api-missing-notice-wrap">

                    <div class="wpwand-api-missing-form-wrap">
                        <form action="" class="wpwand-api-missing-form">
                            <div class="wpwand-form-group">
                                <div class="wpwand-form-field">
                                    <input type="text" id="wpwand-api-key" name="wpwand-api-key"
                                        placeholder="Paste API key here" value="<?php echo esc_html(WPWAND_OPENAI_KEY) ?>"
                                        required>

                                    <div class="wpwand-error"></div>
                                    <a href="https://platform.openai.com/account/api-keys">Get your free OpenAI API key</a>
                                </div>
                            </div>

                            <div class="wpwand-form-submit">
                                <button class="wpwand-submit-button">
                                    <?php echo WPWAND_OPENAI_KEY ? 'Connected!' : 'Connect API'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
            <?php //ndif; ?>
        </div>
    </div>
    <?php
}

function wpwand_promo_notice()
{
    // if (true == get_option('wpwand_promo_notice_dismiss')) {
    //     return false;
    // }
    $notice_id = rand(5555, 444444);
    ?>

    <div class="wpwand-promo-notice notice wpwand-notice-<?php echo esc_attr($notice_id); ?>"">
            <div class=" wpwand-promo-notice-content">
        <span>DEAL ALERT</span>
        <h4>Get
            <?php echo esc_html(wpwand_brand_name()) ?> Pro for Lifetime with limited 50% discount
        </h4>
    </div>
    <div class="wpwand-promo-notice-action">
        <a target="_blank" href="https://wpwand.com/pricing-plan/" class="wpwand-promo-notice-link">Get Lifetime Access</a>
        <a href="" class="wpwand-promo-notice-Hide" data-notice-id="<?php echo esc_attr($notice_id); ?>"
            data-nonce="<?php echo wp_create_nonce('dismiss-notice-' . $notice_id); ?>">Hide</a>
    </div>
    </div>

    <?php
}

if (!function_exists('wpwand_pro_init')) {
    // add_action('admin_notices', 'wpwand_promo_notice');

}

function wpwand_dismiss_notice()
{
    $notice_id = isset($_POST['notice_id']) ? $_POST['notice_id'] : '';

    if (!$notice_id) {
        wp_send_json_error('Something went wrong');
    }

    $dismiss = update_option('wpwand_promo_notice_dismiss', true);
    wp_send_json($dismiss);
}
add_action('wp_ajax_wpwand_dismiss_notice', 'wpwand_dismiss_notice');
add_action('wp_ajax_nopriv_wpwand_dismiss_notice', 'wpwand_dismiss_notice');

function wpwand_frontend_language()
{
    ?>
    <div class="wpwand-form-group">
        <div class="wpwand-form-field">
            <label for="wpwand-Language">Language
                <?php echo wpwand_upgrade_to_pro_button() ?>
            </label>
            <select name="wpwand-Language" id="wpwand-Language" disabled>
                <option value="English">English</option>
            </select>
        </div>
    </div>
    <?php
}
// add_action('wpwand_frontend_language', 'wpwand_frontend_language');

// Callback function to add menu item
function wpwand_admin_bar_menu($wp_admin_bar)
{
    // Add a parent menu item
    $wp_admin_bar->add_menu(
        array(
            'id' => 'wpwand-trigger',
            'title' => '<img style="width:25px" src="' . wpwand_loago_icon_url() . '" > AI Assistant',
            'href' => '#',
            'meta' => array(
                'class' => 'wpwand-trigger',
            ),
        )
    );

}
if ('top' == wpwand_get_option('toggler_position', 'top') && is_admin()) {
    add_action('admin_bar_menu', 'wpwand_admin_bar_menu', 999);
}

function wpwand_dall_e_image_field()
{
    ?>
    <div class="wpwand-form-group wpwand-col-2">
        <div class="wpwand-form-field">
            <label for="wpwand-image-resulation">Select Resolution</label>
            <select name="wpwand-image-resulation" id="wpwand-image-resulation">
                <option value="256x256">256x256</option>
                <option value="" disabled>512x512 [Available in pro]</option>
                <option value="" disabled>1024x1024 [Available in pro]</option>
            </select>
        </div>
        <div class="wpwand-form-field">
            <label for="wpwand-result-number">Number of Results</label>
            <input type="number" id="wpwand-result-number" min="1" max="3" name="wpwand-result-number" value="1">
        </div>
    </div>
    <?php
}

add_action('wpwand_dall_e_frontend_fields', 'wpwand_dall_e_image_field');

function wpwand_loago_url()
{
    $white_label = wpwand_get_option('wpwand_white_label_disable', 1);
    $is_agency = wpwand_get_option('wpwand_pro_tala_agency');

    $logo = wpwand_get_option('wpwand_logo');
    $default_logo = WPWAND_PLUGIN_URL . 'assets/img/logo.svg';

    if (!empty($logo) && 1 == $is_agency && function_exists('wpwand_pro_init')) {
        return $logo;
    }
    return $default_logo;
}


function wpwand_loago_icon_url()
{
    $white_label = wpwand_get_option('wpwand_white_label_disable');
    $is_agency = wpwand_get_option('wpwand_pro_tala_agency');

    $logo = wpwand_get_option('wpwand_logo_icon');
    $default_logo = WPWAND_PLUGIN_URL . 'assets/img/icon.svg';

    if (!empty($logo) && $is_agency && function_exists('wpwand_pro_init')) {
        return $logo;
    }
    return $default_logo;
}

function wpwand_brand_name()
{
    $white_label = wpwand_get_option('wpwand_white_label_disable');
    $is_agency = wpwand_get_option('wpwand_pro_tala_agency');

    $brand_name = wpwand_get_option('wpwand_brand_name', 'WP Wand');
    $default_name = 'WP Wand';

    if (!empty($brand_name) && $is_agency && function_exists('wpwand_pro_init')) {
        return $brand_name;
    }
    return $default_name;
}

function wpwand_brand_color()
{
    $white_label = wpwand_get_option('wpwand_white_label_disable');
    $is_agency = wpwand_get_option('wpwand_pro_tala_agency');

    $brand_color = wpwand_get_option('wpwand_brand_color', '#3767fb');
    $default_color = '#3767fb';

    if (!empty($brand_color) && $is_agency && function_exists('wpwand_pro_init')) {
        return $brand_color;
    }
    return $default_color;
}

function wpwand_pgs_rate_limi()
{
    // Get the usage limit option and default to 0 if not set
    $usage_limit = get_option('wpwand_pgc_total_bulk_generated', 0);

    $get_limt = (int) get_option('wpwand_pgc_limit', 10);

    if (-1 == $get_limt) {
        return true;
    }
    // Check if the usage limit is already exceeded
    if ($usage_limit >= $get_limt) {
        // Check if the transient exists and if the 30-day period has passed
        $expiration_date = get_transient('wpwand_pgc_limit_expiration');
        if (!$expiration_date || current_time('timestamp') > $expiration_date) {
            // Reset the usage limit and set a new 30-day period
            $usage_limit = 0;
            $expiration_date = current_time('timestamp') + 30 * DAY_IN_SECONDS;
            set_transient('wpwand_pgc_limit_expiration', $expiration_date, 30 * DAY_IN_SECONDS);
            update_option('wpwand_pgc_total_bulk_generated', $usage_limit);

            return true;
        }
        return false;
    }

    return true;

}


function wpwangd_get_max_token($command, $selected_model = '')
{
    // Skip calculation for specific model
    if ($selected_model === 'gpt-3.5-turbo-16k') {
        return wpwand_get_option('wpwand_max_token', 3600);
    }

    $total_word = str_word_count($command);
    $total_token = $total_word * 1.2;
    $max_token = wpwand_get_option('wpwand_max_token', 3600);

    $sum_of_tokens = $total_token + $max_token;
    $excess = $sum_of_tokens - 4000;

    if ($excess > 0) {
        // Subtract the excess from max_token
        $max_token -= $excess;
    }

    // Return the adjusted max_token value
    return $max_token;
}

function wpwand_get_custom_prpompts($type = '')
{
    global $wpdb;

    $where = !empty($type) ? "WHERE type = '" . $type . "'" : "";
    $query = "SELECT * FROM {$wpdb->prefix}wpwand_custom_prompts $where";
    $results = $wpdb->get_results($query, ARRAY_A);

    if ($results) {
        return $results;
    }

    return false;
}