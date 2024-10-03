<?php
namespace WPWAND;

if (!defined('ABSPATH')) {
    exit('You are not allowed');
}

class WooCommerce
{

    function __construct()
    {

        add_action('admin_footer', [$this, 'prompt_form']);

    }


    // Hook to add a custom input field after the post title
    function prompt_form()
    {
        global $post;

        // Check if the post type is 'post'
        if (isset($post->post_type) && $post->post_type === 'product') {
            ?>
            <div class="wpwand-popup-prompt-wrap" style="display:none">
                <div class="wpwand-wc-prompt-wrap">
                    <span class="wpwand-wc-prompt-toggle" href="#"><img src="<?php echo wpwand_loago_icon_url() ?>">Generate Content
                        with
                        AI</span>
                    <div class="wpwand-popup-prompt">
                        <span class="wpwand-wc-prompt-close"><svg width="12" height="12" viewBox="0 0 12 12" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path d="M1.5 10.5L10.5 1.5M1.5 1.5L10.5 10.5" stroke="#0000005e" stroke-width="1.5"
                                    stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg></span>
                        <form action="" class="wpwand-prompt-form" id="wpwand-wc-prompt-form">

                            <div class="wpwand-form-group">
                                <div class="wpwand-form-field">
                                    <label for="wpwand-short_description">Short Description</label>
                                    <textarea name="wpwand-short_description" id="wpwand-short_description" cols="30" rows="10"
                                        placeholder="Write a short description of this product. Add what kind of product is this, how it can help customers etc."></textarea>
                                </div>
                            </div>



                            <div class="wpwand-form-submit">
                                <a href="https://wpwand.com/pro-plugin" target="_blank" class="wpwand-submit-pro"><svg width="14"
                                        height="16" viewBox="0 0 14 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M7 10.25V11.75M2.5 14.75H11.5C12.3284 14.75 13 14.0784 13 13.25V8.75C13 7.92157 12.3284 7.25 11.5 7.25H2.5C1.67157 7.25 1 7.92157 1 8.75V13.25C1 14.0784 1.67157 14.75 2.5 14.75ZM10 7.25V4.25C10 2.59315 8.65685 1.25 7 1.25C5.34315 1.25 4 2.59315 4 4.25V7.25H10Z"
                                            stroke="white" stroke-width="1.5" stroke-linecap="round" />
                                    </svg>
                                    Get Pro to Generate Content</a>
                                <!-- <button class="wpwand-submit-button">Generate Content</button> -->

                            </div>
                        </form>
                        <div class="wpwand-result-box wpwand-" style="display: none;">

                            <div class="wpwand-content-wrap"></div>

                        </div>

                    </div>
                </div>
            </div>

            <?php
        }
    }

}
$license_activated = get_option('wpwand_pro_tala_status') == 'activated' ? true : false;

if (!$license_activated || !defined('WPWAND_PRO_FILE_')) {
    $woocommerce = new WooCommerce();
}