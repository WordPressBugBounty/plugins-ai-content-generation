<?php

function wpwand_settings_page()
{
    $activate_text = WPWAND_OPENAI_KEY ? 'Active' : 'Not active <a href="https://platform.openai.com/account/api-keys">Get your free OpenAI API key</a>';
    ?>
    <div class="wrap">

        <?php

        if (isset($_GET['welcome_screen'])) {

            wpwand_welcome_screen();
            return true;
        }
        ?>

        <div class="wpwand-setting-page-wrap">

            <div class="wpwand-logo-full">
                <img src="<?php echo wpwand_loago_url(); ?>">

            </div>
            <div class="wpwand-settings">
                <?php settings_errors(); ?>

                <h2 class="wpwand-nav-tab-wrapper">
                    <a href="#general" class="wpwand-nav-tab nav-tab-active">
                        <?php esc_html_e('General', 'wpwand'); ?>
                    </a>
                    <?php do_action('wpwand_add_tab_link') ?>
                </h2>
                <a href="https://wpwand.com/pro-plugin" target="_blank" class="wpwand-get-pro-button">Get Pro Version</a>
                <form method="post" action="options.php">
                    <?php settings_fields('wpwand_settings_group'); ?>
                    <?php do_settings_sections('wpwand_settings_group'); ?>
                    <div id="general" class="tab-panel">
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">
                                    <label for="wpwand_api_key">
                                        <?php esc_html_e('OpenAI API Key', 'wpwand'); ?>
                                        <span class="wpwand-field-desc">Add your OpenAI API key to activate
                                            <?php echo esc_html(wpwand_brand_name()) ?>
                                        </span>
                                    </label>
                                </th>
                                <td class="wpwand-field">
                                    <input type="text" id="wpwand_api_key" name="wpwand_api_key" class="regular-text"
                                        value="<?php echo esc_attr(wpwand_get_option('wpwand_api_key')); ?>" />
                                    <div class="wpwand_api_key_status">

                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <path fill-rule="evenodd" clip-rule="evenodd"
                                                d="M8 16C12.4183 16 16 12.4183 16 8C16 3.58172 12.4183 0 8 0C3.58172 0 0 3.58172 0 8C0 12.4183 3.58172 16 8 16ZM11.7071 6.70711C12.0976 6.31658 12.0976 5.68342 11.7071 5.29289C11.3166 4.90237 10.6834 4.90237 10.2929 5.29289L7 8.58579L5.70711 7.29289C5.31658 6.90237 4.68342 6.90237 4.29289 7.29289C3.90237 7.68342 3.90237 8.31658 4.29289 8.70711L6.29289 10.7071C6.68342 11.0976 7.31658 11.0976 7.70711 10.7071L11.7071 6.70711Z"
                                                fill="<?php echo esc_attr(WPWAND_OPENAI_KEY) ? '#3BCB38' : '#D1D6DB' ?>" />
                                        </svg>

                                        <span>
                                            <?php printf($activate_text) ?>
                                        </span>
                                    </div>
                                </td>
                            </tr>

                            <?php if (WPWAND_OPENAI_KEY): ?>
                                <tr>
                                    <th scope="row">
                                        <label for="wpwand_model">
                                            <?php esc_html_e('Model', 'wpwand'); ?>
                                            <span class="wpwand-field-desc">Add your OpenAI API key to activate
                                                <?php echo esc_html(wpwand_brand_name()) ?>
                                            </span>
                                        </label>
                                    </th>
                                    <td>
                                        <select id="wpwand_model" name="wpwand_model">
                                            <option value="gpt-4o" <?php selected(wpwand_get_option('wpwand_model', 'gpt-3.5-turbo'), 'gpt-4o'); ?>>
                                                <?php esc_html_e('gpt-4o', 'wpwand'); ?></option>
                                            <option value="gpt-4" <?php selected(wpwand_get_option('wpwand_model', 'gpt-3.5-turbo'), 'gpt-4'); ?>>
                                                <?php esc_html_e('gpt-4', 'wpwand'); ?></option>
                                            <option value="gpt-3.5-turbo" <?php selected(wpwand_get_option('wpwand_model', 'gpt-3.5-turbo'), 'gpt-3.5-turbo'); ?>>
                                                <?php esc_html_e('gpt-3.5-turbo', 'wpwand'); ?></option>
                                            <option value="gpt-3.5-turbo-16k" <?php selected(wpwand_get_option('wpwand_model', 'gpt-3.5-turbo'), 'gpt-3.5-turbo-16k'); ?>>
                                                <?php esc_html_e('gpt-3.5-turbo-16k', 'wpwand'); ?></option>
                                            <option value="text-davinci-003" <?php selected(wpwand_get_option('wpwand_model', 'gpt-3.5-turbo'), 'text-davinci-003'); ?>>
                                                <?php esc_html_e('text-davinci-003', 'wpwand'); ?></option>
                                            <option value="text-curie-001" <?php selected(wpwand_get_option('wpwand_model', 'gpt-3.5-turbo'), 'text-curie-001'); ?>>
                                                <?php esc_html_e('text-curie-001', 'wpwand'); ?></option>
                                            <option value="text-babbage-001" <?php selected(wpwand_get_option('wpwand_model', 'gpt-3.5-turbo'), 'text-babbage-001'); ?>>
                                                <?php esc_html_e('text-babbage-001', 'wpwand'); ?></option>
                                            <option value="text-ada-001" <?php selected(wpwand_get_option('wpwand_model', 'gpt-3.5-turbo'), 'text-ada-001'); ?>>
                                                <?php esc_html_e('text-ada-001', 'wpwand'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="wpwand_language">
                                            <?php esc_html_e('Default Content Language', 'wpwand'); ?>
                                            <span class="wpwand-field-desc">Select your language</span>
                                        </label>
                                    </th>
                                    <td>
                                        <select id="wpwand_language" name="wpwand_language">
                                            <?php
                                            if (is_array(wpwand_language_array())) {
                                                $default_language = wpwand_get_option('wpwand_language', 'en');
                                                foreach (wpwand_language_array() as $key => $value) {
                                                    printf('<option value="%s" %s >%s</option>', $key, selected($default_language, $key), $key);
                                                }
                                            }
                                            ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">
                                        <label for="wpwand_temperature">
                                            <?php esc_html_e('Temperature', 'wpwand'); ?>
                                            <span class="wpwand-field-desc">Controls randomness: If you lower the number, the
                                                result will be repetitive & the output quality might gets lower.</span>
                                        </label>
                                    </th>
                                    <td>

                                        <div class="wpwand-slider-input-wrap">
                                            <input type="number" id="wpwand_temperature" name="wpwand_temperature"
                                                class="wpwand_slider_input small-text" min="0" max="1" step="0.1"
                                                value="<?php echo esc_attr(wpwand_get_option('wpwand_temperature', 1)); ?>" />
                                        </div>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">
                                        <label for="wpwand_max_tokens">
                                            <?php esc_html_e('Max Token', 'wpwand'); ?>
                                            <span class="wpwand-field-desc">The maximum number of tokens to generate. One token
                                                is roughly 4 characters for normal English text.</span>
                                        </label>
                                    </th>
                                    <td>
                                        <div class="wpwand-slider-input-wrap">
                                            <input type="number" id="wpwand_max_tokens" name="wpwand_max_tokens" min="0"
                                                max="3600" step="1" class="wpwand_slider_input small-text"
                                                value="<?php echo esc_attr(wpwand_get_option('wpwand_max_tokens', 3450)); ?>" />
                                        </div>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">
                                        <label for="wpwand_presence_penalty">
                                            <?php esc_html_e('Presence Penalty', 'wpwand'); ?>
                                            <span class="wpwand-field-desc"></span>
                                        </label>
                                    </th>
                                    <td>
                                        <div class="wpwand-slider-input-wrap">
                                            <input type="number" id="wpwand_presence_penalty" name="wpwand_presence_penalty"
                                                min="0" max="2" step="0.1" class="wpwand_slider_input small-text"
                                                value="<?php echo esc_attr(wpwand_get_option('wpwand_presence_penalty', 0)); ?>" />
                                        </div>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">
                                        <label for="wpwand_frequency">
                                            <?php esc_html_e('Frequency', 'wpwand'); ?>
                                            <span class="wpwand-field-desc"></span>
                                        </label>
                                    </th>
                                    <td>
                                        <div class="wpwand-slider-input-wrap">
                                            <input type="number" id="wpwand_frequency" name="wpwand_frequency" min="0" max="2"
                                                step="0.1" class="wpwand_slider_input small-text"
                                                value="<?php echo esc_attr(wpwand_get_option('wpwand_frequency', 0)); ?>" />
                                        </div>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">
                                        <label for="wpwand_frequency">
                                            <?php esc_html_e('Hide ChatGPT Assistant inside gutenberg', 'wpwand'); ?>
                                            <span class="wpwand-field-desc"></span>
                                        </label>
                                    </th>

                                    <td class="wpwand-field">
                                        <input type="checkbox" id="wpwand_hide_ai_bar_gutenberg"
                                            name="wpwand_hide_ai_bar_gutenberg" value="1" class="wpwand_hide_ai_bar_gutenberg"
                                            <?php checked(wpwand_get_option('wpwand_hide_ai_bar_gutenberg', 0)) ?> />

                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="toggler_position">
                                            <?php esc_html_e('AI Button Position', 'wpwand'); ?>
                                            <span class="wpwand-field-desc">Change WP Wand’s AI button position based on your
                                                preference</span>
                                        </label>
                                    </th>
                                    <td>
                                        <select id="toggler_position" name="toggler_position">
                                            <option value="top" <?php selected(wpwand_get_option('toggler_position', 'top'), 'top'); ?>>
                                                <?php esc_html_e('Top', 'wpwand'); ?></option>
                                            <option value="side" <?php selected(wpwand_get_option('toggler_position', 'top'), 'side'); ?>>
                                                <?php esc_html_e('Side', 'wpwand'); ?></option>
                                        </select>
                                    </td>
                                </tr>

                                <?php do_action('wpwand_general_tab_content') ?>

                            <?php endif; ?>

                        </table>
                        <?php wpwand_model_details_card() ?>

                    </div>
                    <?php do_action('wpwand_add_tab_content') ?>
                    <?php submit_button(esc_html__('Update', 'wpwand')); ?>

                </form>



            </div>

        </div>
    </div>
    <?php
}

function wpwand_register_menu()
{
    add_menu_page(wpwand_brand_name(), wpwand_brand_name(), 'manage_options', 'wpwand', '', wpwand_loago_icon_url());
    add_submenu_page('wpwand', wpwand_brand_name(), 'Settings', 'manage_options', 'wpwand', 'wpwand_settings_page');


    if (!defined('WPWAND_PRO_FILE_')) {

        add_submenu_page(
            'wpwand',
            '',
            '',
            'manage_options',
            '',
            ''
        );
    }

}

add_action('admin_menu', 'wpwand_register_menu');
add_action('admin_init', 'wpwand_register_settings');
// Register WP Wand settings
function wpwand_register_settings()
{
    register_setting(
        'wpwand_settings_group',
        'wpwand_api_key',
        array(
            'type' => 'string',
            'description' => esc_html__('OpenAI API Key', 'wpwand'),
            'sanitize_callback' => 'sanitize_text_field',
            'validate_callback' => 'wpwand_validate_api_key',
        )
    );

    register_setting(
        'wpwand_settings_group',
        'wpwand_model',
        array(
            'type' => 'string',
            'description' => esc_html__('Model', 'wpwand'),
            'sanitize_callback' => 'sanitize_text_field',
            'validate_callback' => 'wpwand_validate_model',
        )
    );

    register_setting(
        'wpwand_settings_group',
        'wpwand_language',
        array(
            'type' => 'string',
            'description' => esc_html__('Language', 'wpwand'),
            'sanitize_callback' => 'sanitize_text_field',
            'validate_callback' => 'wpwand_validate_model',
        )
    );
    register_setting(
        'wpwand_settings_group',
        'toggler_position',
        array(
            'type' => 'string',
            'description' => esc_html__('Toggler Position', 'wpwand'),
            'sanitize_callback' => 'sanitize_text_field',
            'validate_callback' => 'wpwand_validate_model',
        )
    );

    register_setting(
        'wpwand_settings_group',
        'wpwand_temperature',
        array(
            'type' => 'string',
            'description' => esc_html__('Temperature', 'wpwand'),
            'sanitize_callback' => 'sanitize_text_field',
            'validate_callback' => 'wpwand_validate_input_field',
        )
    );
    register_setting(
        'wpwand_settings_group',
        'wpwand_frequency',
        array(
            'type' => 'string',
            'description' => esc_html__('Frequency', 'wpwand'),
            'sanitize_callback' => 'sanitize_text_field',
            'validate_callback' => 'wpwand_validate_input_field',
        )
    );
    register_setting(
        'wpwand_settings_group',
        'wpwand_max_tokens',
        array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'validate_callback' => 'wpwand_validate_input_field',
        )
    );
    register_setting(
        'wpwand_settings_group',
        'wpwand_presence_penalty',
        array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'validate_callback' => 'wpwand_validate_input_field',
        )
    );
    register_setting(
        'wpwand_settings_group',
        'wpwand_hide_ai_bar_gutenberg',
        array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'validate_callback' => 'wpwand_validate_input_field',
        )
    );

    do_action('wpwand_register_settings');

}

// Validate WP Wand API key
function wpwand_validate_api_key($input)
{
    $error = false;

    // Check if the input is empty
    if (empty($input)) {
        add_settings_error('wpwand_api_key', 'wpwand_api_key_empty', esc_html__('Please enter an OpenAI API key.', 'wpwand'));
        $error = true;
    } else {
        // Check if the input matches the expected format of an OpenAI secret key
        if (!preg_match('/^sk-\w+$/', $input)) {
            add_settings_error('wpwand_api_key', 'wpwand_api_key_invalid', esc_html__('Invalid OpenAI API key format.', 'wpwand'));
            $error = true;
        }
    }


    // If there is an error, return the old value
    if ($error) {
        return wpwand_get_option('wpwand_api_key');
    }

    return $input;
}

// Validate WP Wand AI character
function wpwand_validate_input_field($input)
{
    // Perform any additional validation here
    return $input;
}

function wpwand_validate_model($input)
{
    $allowed_models = array('davinci', 'curie', 'babbage');

    if (!in_array($input, $allowed_models)) {
        add_settings_error('wpwand_model', 'wpwand_model_invalid', esc_html__('Invalid model selected.', 'wpwand'));
        return wpwand_get_option('wpwand_model');
    }

    return $input;
}