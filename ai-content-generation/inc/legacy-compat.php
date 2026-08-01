<?php

/**
 * Legacy back-compat shims.
 *
 * These exist ONLY to keep a site alive during the brief upgrade window where WP Wand
 * (free) has already been updated to 2.0.0 but WP Wand Pro is still on the pre-2.0.0
 * (legacy procedural) codebase. That old Pro `inc/*` code calls a handful of free-owned
 * global helpers that the 2.0.0 rebuild removed. Without these shims those calls hit
 * undefined functions and fatal the site at `plugins_loaded` — BEFORE any admin notice
 * (which fires on `init`/`admin_notices`) could ever render, i.e. a white screen.
 *
 * This file is required directly from the main plugin file (NOT on a hook) so the
 * functions are defined the instant the free plugin loads — before any other plugin's
 * `plugins_loaded` callback (Pro boots at priority 20). Every shim is
 * `function_exists`-guarded so it can never collide with a build that defines the real
 * function, and once Pro is updated to 2.0.0 these names are no longer referenced at all.
 *
 * Pro features are intentionally LOCKED while Pro is outdated: generation requests routed
 * through the legacy entrypoint return an "update required" error instead of running, and
 * {@see wpwand_pro_version_check()} shows a prominent update notice.
 *
 * @package WP_Wand
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('wpwand_legacy_pro_is_outdated')) {
    /**
     * True when WP Wand Pro is present but still on the pre-2.0.0 codebase.
     *
     * Detection is version-constant-free and reliable once all plugins are loaded:
     * the legacy entrypoint `wpwand_pro_init()` is defined by every Pro build, but the
     * namespaced LicenseService class only exists in the 2.0.0+ rebuild.
     */
    function wpwand_legacy_pro_is_outdated()
    {
        return function_exists('wpwand_pro_init')
            && !class_exists('WPWand\\License\\LicenseService');
    }
}

if (!function_exists('wpwand_get_option')) {
    /**
     * Legacy option getter — returns $default for falsy/missing options.
     */
    function wpwand_get_option($opt, $default = '')
    {
        $val = get_option($opt);

        return $val ? $val : $default;
    }
}

if (!function_exists('wpwand_pgs_rate_limi')) {
    /**
     * Legacy bulk rate-limit check, with the same 30-day rolling reset. Verbatim port.
     */
    function wpwand_pgs_rate_limi()
    {
        $usage_limit = (int) get_option('wpwand_pgc_total_bulk_generated', 0);
        $get_limt    = (int) get_option('wpwand_pgc_limit', 10);

        if (-1 === $get_limt) {
            return true;
        }

        if ($usage_limit >= $get_limt) {
            $expiration_date = get_transient('wpwand_pgc_limit_expiration');
            if (!$expiration_date || current_time('timestamp') > $expiration_date) {
                $expiration_date = current_time('timestamp') + 30 * DAY_IN_SECONDS;
                set_transient('wpwand_pgc_limit_expiration', $expiration_date, 30 * DAY_IN_SECONDS);
                update_option('wpwand_pgc_total_bulk_generated', 0);

                return true;
            }

            return false;
        }

        return true;
    }
}

if (!function_exists('wpwand_templates')) {
    /**
     * Legacy template/prompt catalog: free + pro + custom, merged. Side-effect-free
     * (the legacy transient-sync calls were dropped — the 2.0.0 data layer owns seeding).
     */
    function wpwand_templates()
    {
        $all_prompts = get_option('wpwand_data');
        $custom_data = get_option('wpwand_custom_data', []);

        if (is_array($all_prompts) && isset($all_prompts['free'], $all_prompts['pro'])) {
            return array_merge((array) $custom_data, (array) $all_prompts['free'], (array) $all_prompts['pro']);
        }

        return [];
    }
}

if (!function_exists('wpwand_ai_error')) {
    /**
     * Legacy provider-error renderer. Verbatim port (self-contained, no removed deps).
     *
     * @param object $error stdClass with ->type and ->message (message may be JSON).
     */
    function wpwand_ai_error($error)
    {
        $source   = str_replace('_error', '', isset($error->type) ? $error->type : '');
        $provider = ucfirst($source);

        if (isset($error->message) && strpos($error->message, 'curl') !== false) {
            return "<h4>{$provider} Error</h4><p>" . esc_html__('Server is not responding. Please try again later.', 'wp-wand') . "</p>";
        }

        $error_details = isset($error->message) ? json_decode($error->message) : null;

        if (json_last_error() === JSON_ERROR_NONE && is_object($error_details)) {
            $message = "<p><strong>{$provider} Error</strong></p>";
            if (isset($error_details->type)) {
                $message .= "<p><strong>Type:</strong> " . ucwords(str_replace('_', ' ', htmlspecialchars($error_details->type))) . "</p>";
            }
            if (isset($error_details->message)) {
                $message_text = htmlspecialchars($error_details->message);
                $message_text = preg_replace(
                    '/(https?:\/\/[^\s]+)/',
                    '<a href="$1" target="_blank" rel="noopener noreferrer" style="color:var(--wpwand-brand-color)">$1</a>',
                    $message_text
                );
                $message .= "<div>" . $message_text . "</div>";
            }

            return $message;
        }

        return "<h4>{$provider} Error</h4><div>" . htmlspecialchars(isset($error->message) ? $error->message : '') . "</div>";
    }
}

if (!function_exists('wpwand_api_fields_validate')) {
    /**
     * Legacy AJAX field sanitizer. Verbatim port.
     *
     * phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce IS checked below.
     */
    function wpwand_api_fields_validate()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wpwand_global_nonce')) {
            wp_send_json_error('Nonce verification failed.', 403);
        }

        return array(
            'topic'            => isset($_POST['topic']) ? sanitize_text_field(wp_unslash($_POST['topic'])) : '',
            'keywords'         => isset($_POST['keyword']) ? sanitize_text_field(wp_unslash($_POST['keyword'])) : '',
            'no_of_results'    => isset($_POST['result_number']) ? absint(sanitize_text_field(wp_unslash($_POST['result_number']))) : 1,
            'tone'             => isset($_POST['tone']) ? sanitize_text_field(wp_unslash($_POST['tone'])) : '',
            'word_count'       => isset($_POST['word_limit']) ? intval(sanitize_text_field(wp_unslash($_POST['word_limit']))) + 1000 : '',
            'product_name'     => isset($_POST['product_name']) ? sanitize_text_field(wp_unslash($_POST['product_name'])) : '',
            'description'      => isset($_POST['description']) ? sanitize_text_field(wp_unslash($_POST['description'])) : '',
            'content'          => isset($_POST['content']) ? wp_kses_post(sanitize_text_field(wp_unslash($_POST['content']))) : '',
            'content_textarea' => isset($_POST['content_textarea']) ? wp_kses_post(sanitize_text_field(wp_unslash($_POST['content_textarea']))) : '',
            'custom_textarea'  => isset($_POST['custom_textarea']) ? wp_kses_post(sanitize_text_field(wp_unslash($_POST['custom_textarea']))) : '',
            'product_1'        => isset($_POST['product_1']) ? wp_kses_post(sanitize_text_field(wp_unslash($_POST['product_1']))) : '',
            'product_2'        => isset($_POST['product_2']) ? wp_kses_post(sanitize_text_field(wp_unslash($_POST['product_2']))) : '',
            'description_1'    => isset($_POST['description_1']) ? wp_kses_post(sanitize_text_field(wp_unslash($_POST['description_1']))) : '',
            'description_2'    => isset($_POST['description_2']) ? wp_kses_post(sanitize_text_field(wp_unslash($_POST['description_2']))) : '',
            'subject'          => isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '',
            'question'         => isset($_POST['question']) ? sanitize_text_field(wp_unslash($_POST['question'])) : '',
            'comment'          => isset($_POST['comment']) ? sanitize_text_field(wp_unslash($_POST['comment'])) : '',
        );
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }
}

if (!function_exists('wpwand_generate_ai_content')) {
    /**
     * Legacy generation entrypoint. The free 2.0.0 code never calls this — only legacy Pro
     * does — so it doubles as the Pro feature lock: while Pro is outdated it returns an
     * "update required" error (in the new Generator's error shape) instead of generating.
     * Once Pro is on 2.0.0 it calls the Generator directly and this shim is never hit.
     *
     * @return object stdClass with ->choices or ->error (legacy-identical shape).
     */
    function wpwand_generate_ai_content($prompt, $number = 1, $args = [])
    {
        if (wpwand_legacy_pro_is_outdated()) {
            return (object) [
                'error' => (object) [
                    'message' => wp_json_encode([
                        'type'    => 'update_required',
                        'message' => __('Please update WP Wand Pro to version 2.0.0 or higher to keep generating content. Your Pro features are paused until the update completes.', 'wp-wand'),
                    ]),
                    'type'    => 'wpwand_error',
                    'code'    => 426,
                ],
            ];
        }

        if (class_exists('WPWand\\Generation\\Generator')) {
            return \WPWand\Generation\Generator::generate((string) $prompt, (int) $number, (array) $args);
        }

        return (object) [
            'error' => (object) [
                'message' => wp_json_encode(['message' => __('The generation engine is unavailable.', 'wp-wand')]),
                'type'    => 'wpwand_error',
                'code'    => 500,
            ],
        ];
    }
}

if (!function_exists('wpwand_loago_icon_url')) {
    /**
     * Legacy brand/logo icon URL. Faithful port (depends only on the shimmed option getter).
     */
    function wpwand_loago_icon_url()
    {
        $logo      = wpwand_get_option('wpwand_logo_icon');
        $is_agency = wpwand_get_option('wpwand_pro_tala_agency');

        if (!empty($logo) && $is_agency && function_exists('wpwand_pro_init')) {
            return $logo;
        }

        return defined('WPWAND_PLUGIN_URL')
            ? WPWAND_PLUGIN_URL . 'assets/img/icon.svg'
            : plugins_url('assets/img/icon.svg', dirname(__DIR__) . '/wp-wand.php');
    }
}

if (!function_exists('wpwand_get_data')) {
    /**
     * Legacy template-data (re)seed. Only the legacy Pro license-deactivate handler calls
     * this; it re-seeds the free catalog (the legacy wpwand_dummy_datas() source is gone, so
     * this routes through the 2.0.0 data layer instead).
     */
    function wpwand_get_data($sync = false)
    {
        if (!get_option('wpwand_data') || true === $sync) {
            if (class_exists('WPWand\\Data\\Templates')) {
                \WPWand\Data\Templates::seed(true);

                return true;
            }

            return false;
        }

        return [];
    }
}

if (!function_exists('wpwand_dall_e_request')) {
    /**
     * Legacy DALL·E image generation. Only legacy Pro calls this; image generation is a Pro
     * feature, so while Pro is outdated it is locked: respond with an update notice (matching
     * the legacy wp_send_json HTML-string contract) instead of calling the image API.
     */
    function wpwand_dall_e_request($prompt, $args = [])
    {
        $message = '<div class="wpwand-content wpwand-prompt-error"><p>'
            . esc_html__('Image generation is paused until WP Wand Pro is updated to version 2.0.0 or higher.', 'wp-wand')
            . '</p></div>';

        wp_send_json($message);
    }
}

if (!function_exists('wpwand_pro_card')) {
    /**
     * Legacy Pro upsell card. Every legacy call site is commented out, so this is a
     * defensive no-op kept only so an uncommented call can never fatal.
     */
    function wpwand_pro_card()
    {
        return '';
    }
}
