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
            'desc' => 'Upload your logo',
            'placeholder' => 'Add a custom link or click on upload button',
            'default' => WPWAND_PLUGIN_URL . 'assets/img/logo.svg',

        ],
        [
            'type' => 'file',
            'name' => 'logo_icon',
            'label' => 'Logo Icon',
            'desc' => 'Upload your logo icon',
            'placeholder' => 'Add a custom link or click on upload button',
            'default' => WPWAND_PLUGIN_URL . 'assets/img/icon.svg',

        ],

        [
            'type' => 'text',
            'name' => 'brand_name',
            'label' => 'Brand Name',
            'desc' => 'Write your brand name',
            'placeholder' => 'WP Wand',

        ],
        [
            'type' => 'color',
            'name' => 'brand_color',
            'label' => 'Brand Color',
            'default' => '#3767fb',
            'desc' => 'Select your brand color',
            'placeholder' => '',

        ],
        [
            'type' => 'text',
            'name' => 'plugin_name',
            'label' => 'Plugin Name',
            'desc' => 'Write your plugin name',
            'placeholder' => 'WP Wand',
        ],
        [
            'type' => 'text',
            'name' => 'plugin_description',
            'label' => 'Plugin Description',
            'desc' => 'Write your plugin description',
            'placeholder' => 'WP Wand is a AI content generation plugin for WordPress that helps your team create high quality content 10X faster and 50x cheaper. No monthly subscription required.',
        ],
        [
            'type' => 'text',
            'name' => 'author_name',
            'label' => 'Author Name',
            'desc' => 'Write your author name',
            'placeholder' => 'WP Wand',
        ],
        [
            'type' => 'text',
            'name' => 'author_url',
            'label' => 'Author Url',
            'desc' => 'Write your author URL',
            'placeholder' => 'https://wpwand.com',
        ],
    ];
}

function wpwand_add_white_tab()
{
    ?>
    <a href="#white-label" class="wpwand-nav-tab">
        <?php esc_html_e('White Label', 'wpwand'); ?>
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
                <?php esc_html_e('White Label', 'wpwand'); ?>
                <?php echo wpwand_upgrade_to_pro_button('Available in Agency Plan') ?>
            </h4>
            <p class="wpwand-field-desc">You can change all branding and public info of WP Wand to use it as your own on
                client’s website</p>
        </div>
        <table class="form-table">
            <?php if ($all_fields):
                foreach ($all_fields as $field):
                    $field_name = 'wpwand_' . $field['name'];
                    $default_val = isset($field['default']) ? $field['default'] : '';
                    ?>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_html($field_name); ?>"><?php echo esc_html($field['label']); ?>

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
                                        <input type="text" id="<?php echo esc_html($field_name); ?>"
                                            name="<?php echo esc_html($field_name); ?>"
                                            placeholder="<?php echo esc_html($field['placeholder']); ?>" disabled>
                                        <button id="<?php echo esc_html($field_name); ?>-upload-button" class="wpwand-upload-button"
                                            disabled>Upload</button>
                                        <div class="wpwand-upload-preview">
                                            <!-- <span class="wpwand-img-preview-remove">x</span> -->
                                            <img src="<?php echo $default_val ?>" alt="">
                                        </div>
                                    </div>
                                    <?php
                                    break;

                                default:
                                    ?>
                                    <input type="<?php echo esc_html($field['type']); ?>" name="<?php echo esc_html($field_name); ?>"
                                        id="<?php echo esc_html($field_name); ?>"
                                        placeholder="<?php echo esc_html($field['placeholder']); ?>" value="<?php echo $default_val; ?>"
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
                        <?php esc_html_e('Disable White Label Tab', 'wpwand'); ?>


                    </label>
                    <span class="wpwand-field-desc">You can enable White Label tab again after disabling and enabling the
                        Pro plugin.</span>
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