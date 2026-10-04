<?php

/**
 * The licence server moved, and installed copies of WP Wand Pro still call the old address.
 *
 * finestwp.co lapsed on 2026-09-18 and was not renewed. Every Pro build up to and including
 * 2.0.0 has https://tala.finestwp.co written into it for licence activation, deactivation,
 * the template catalogue and its own update check. Those copies can no longer reach the
 * server, which also means they can never be offered the Pro release that carries the new
 * address. Pro cannot run without this plugin, so this plugin is the one place that reaches
 * every one of them.
 *
 * What it does: when Pro is present, a request addressed to the old host is repeated against
 * the new one, byte for byte the same path, query and arguments, and the answer is handed
 * back to Pro. Nothing is downloaded or installed from here. Pro makes the call and Pro
 * handles the reply, exactly as before.
 *
 * Both Pro code paths go through wp_safe_remote_get(), measured on 1.3.05 (inc/tala.php) and
 * on 2.0.0 (src/License/TalaClient.php), so pre_http_request sees all of them.
 *
 * Old Pro caches a failed update check for a day (2.0.0 for an hour), so on its own the Pro
 * update can take up to a day to appear after this lands. The "Update now" link in the version
 * notice clears that cache (wpwand_pro_version_check() in wp-wand.php) and shows it at once.
 *
 * Remove once no supported Pro release still carries the old address.
 *
 * @package WP_Wand
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WPWAND_LEGACY_LICENSE_HOST')) {
    define('WPWAND_LEGACY_LICENSE_HOST', 'tala.finestwp.co');
}

if (!defined('WPWAND_LICENSE_HOST')) {
    define('WPWAND_LICENSE_HOST', 'tala.thefarhan.com');
}

if (!function_exists('wpwand_reroute_legacy_license_host')) {
    /**
     * Repeat a request for the old licence host against the new one.
     *
     * @param false|array|WP_Error $pre  Short-circuit value; false means nobody has answered yet.
     * @param array                $args Request arguments, already parsed by WP_Http.
     * @param string               $url  Request URL.
     *
     * @return false|array|WP_Error
     */
    function wpwand_reroute_legacy_license_host($pre, $args, $url)
    {
        // The cheap test first: this runs for every outgoing request on the site.
        if (false !== $pre || !is_string($url) || false === stripos($url, WPWAND_LEGACY_LICENSE_HOST)) {
            return $pre;
        }

        // The host itself must match, not merely appear somewhere in the URL.
        $host = wp_parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || 0 !== strcasecmp($host, WPWAND_LEGACY_LICENSE_HOST)) {
            return $pre;
        }

        $target = preg_replace(
            '#^https?://' . preg_quote(WPWAND_LEGACY_LICENSE_HOST, '#') . '#i',
            'https://' . WPWAND_LICENSE_HOST,
            $url,
            1
        );

        if (!is_string($target) || $target === $url) {
            return $pre;
        }

        return wp_safe_remote_request($target, is_array($args) ? $args : array());
    }
}

/*
 * Only where Pro is installed: nothing else on a site calls that host. Every plugin file has
 * been included by plugins_loaded, so the check is reliable here, and priority 1 puts the
 * filter in place before Pro boots at 20.
 */
add_action('plugins_loaded', static function () {
    if (function_exists('wpwand_pro_init')) {
        add_filter('pre_http_request', 'wpwand_reroute_legacy_license_host', 10, 3);
    }
}, 1);
