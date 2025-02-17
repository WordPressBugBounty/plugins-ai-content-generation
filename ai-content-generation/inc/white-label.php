<?php

if (!defined('ABSPATH')) {
    exit('You are not allowed');
}

add_action('wpwand_add_tab_link', 'wpwand_add_white_tab', 100);
add_action('wpwand_add_tab_content', 'wpwand_add_white_tab_content', 100);

function wpwand_white_label_fields()
{
    return [
        [
            'type' => 'file',
            'name' => 'logo',
            'label' => 'Upload Logo',
            'desc' => __('Upload your logo', 'wp-wand'),
            'placeholder' => __('Add a custom link or click on upload button', 'wp-wand'),
            'default' => WPWAND_PLUGIN_URL . 'assets/img/logo.svg',

        ],
        [
            'type' => 'file',
            'name' => 'logo_icon',
            'label' => 'Logo Icon',
            'desc' => __('Upload your logo icon', 'wp-wand'),
            'placeholder' => __('Add a custom link or click on upload button', 'wp-wand'),
            'default' => WPWAND_PLUGIN_URL . 'assets/img/icon.svg',

        ],

        [
            'type' => 'text',
            'name' => 'brand_name',
            'label' => __('Brand Name', 'wp-wand'),
            'desc' => __('Write your brand name', 'wp-wand'),
            'placeholder' => __('WP Wand', 'wp-wand'),

        ],
        [
            'type' => 'color',
            'name' => 'brand_color',
            'label' => __('Brand Color', 'wp-wand'),
            'default' => '#3767fb',
            'desc' => __('Select your brand color', 'wp-wand'),
            'placeholder' => '',

        ],
        [
            'type' => 'text',
            'name' => 'plugin_name',
            'label' => __('Plugin Name', 'wp-wand'),
            'desc' => __('Write your plugin name', 'wp-wand'),
            'placeholder' => __('WP Wand', 'wp-wand'),
        ],
        [
            'type' => 'text',
            'name' => 'plugin_description',
            'label' => __('Plugin Description', 'wp-wand'),
            'desc' => __('Write your plugin description', 'wp-wand'),
            'placeholder' => __('WP Wand is a AI content generation plugin for WordPress that helps your team create high quality content 10X faster and 50x cheaper. No monthly subscription required.', 'wp-wand'),
        ],
        [
            'type' => 'text',
            'name' => 'author_name',
            'label' => __('Author Name', 'wp-wand'),
            'desc' => __('Write your author name', 'wp-wand'),
            'placeholder' => __('WP Wand', 'wp-wand'),
        ],
        [
            'type' => 'text',
            'name' => 'author_url',
            'label' => __('Author Url', 'wp-wand'),
            'desc' => __('Write your author URL', 'wp-wand'),
            'placeholder' => 'https://wpwand.com',
        ],
    ];
}

function wpwand_add_white_tab()
{
    ?>
    <a href="#white-label" class="wpwand-nav-tab">
        <?php esc_html_e('White Label', 'wp-wand'); ?>
    </a>
    <?php
}

function wpwand_add_white_tab_content()
{
    $all_fields = is_array(wpwand_white_label_fields()) ? wpwand_white_label_fields() : false;

    ?>

    <div id="white-label" class="tab-panel" style="display:none;">
        <div class="wpwand-tab-header">
            <h4>
                <?php esc_html_e('White Label', 'wp-wand'); ?>
                <?php  wpwand_upgrade_to_pro_button('Available in Agency Plan') ?>
            </h4>
            <p class="wpwand-field-desc"><?php esc_html_e('You can change all branding and public info of WP Wand to use it as your own on client’s website', 'wp-wand'); ?></p>
        </div>
        <table class="form-table">
            <?php if ($all_fields):
                foreach ($all_fields as $field):
                    $field_name = 'wpwand_' . $field['name'];
                    $default_val = isset($field['default']) ? $field['default'] : '';
                    ?>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr($field_name); ?>"><?php echo esc_html($field['label']); ?>

                            </label>
                            <span class="wpwand-field-desc">
                                <?php echo esc_html($field['desc']); ?>
                            </span>
                        </th>
                        <td>
                            <?php switch ($field['type']) {
                                case 'file':
                                    # code...
                                    ?>
                                    <div class="wpwand-upload-field-wrap">
                                        <input type="text" id="<?php echo esc_attr($field_name); ?>"
                                            name="<?php echo esc_attr($field_name); ?>"
                                            placeholder="<?php echo esc_attr($field['placeholder']); ?>" disabled>
                                        <button id="<?php echo esc_attr($field_name); ?>-upload-button" class="wpwand-upload-button"
                                            disabled><?php esc_html_e('Upload', 'wp-wand'); ?></button>
                                        <div class="wpwand-upload-preview">
                                            <!-- <span class="wpwand-img-preview-remove">x</span> -->
                                            <img src="<?php echo esc_url($default_val);  // phpcs:ignore?>" alt="">
                                        </div>
                                    </div>
                                    <?php
                                    break;

                                default:
                                    ?>
                                    <input type="<?php echo esc_attr($field['type']); ?>" name="<?php echo esc_attr($field_name); ?>"
                                        id="<?php echo esc_attr($field_name); ?>"
                                        placeholder="<?php echo esc_attr($field['placeholder']); ?>" value="<?php echo esc_attr( $default_val ); ?>"
                                        disabled>

                                    <?php
                                    break;
                            } ?>



                        </td>
                    </tr>
                <?php endforeach; endif; ?>

            <tr valign="top">
                <th scope="row">
                    <label for="wpwand_white_label_disable">
                        <?php esc_html_e('Disable White Label Tab', 'wp-wand'); ?>


                    </label>
                    <span class="wpwand-field-desc"><?php esc_html_e('You can enable White Label tab again after disabling and enabling the Pro plugin.', 'wp-wand'); ?></span>
                </th>

                <td class="wpwand-field">
                    <input type="checkbox" id="wpwand_white_label_disable" name="wpwand_white_label_disable" value="1"
                        class="wpwand_white_label_disable" disabled />

                </td>
            </tr>

        </table>
        <!-- <a href="" class="wpwand-submit-pro-btn wpwand-pro-button">Get Pro Version</a> -->

        <?php // wpwand_card()?>
    </div>
    <?php
}