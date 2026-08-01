<?php

/**
 * WP Wand — new architecture bootstrap.
 *
 * Part of the React + REST rebuild (see REBUILD-PLAN.md). This file loads the new
 * namespaced (PSR-4) code ALONGSIDE the legacy procedural code in inc/, without
 * altering any existing behavior. Legacy admin-ajax handlers and the jQuery UI keep
 * working; the new REST API (wpwand/v1) and migration runner are added on top.
 *
 * @package WPWand
 */

if (!defined('ABSPATH')) {
    exit('You are not allowed');
}

if (!defined('WPWAND_NEW_DIR')) {
    define('WPWAND_NEW_DIR', plugin_dir_path(__FILE__));
}
if (!defined('WPWAND_NEW_URL')) {
    define('WPWAND_NEW_URL', plugin_dir_url(__FILE__));
}

/*
 * Prefer the Composer autoloader when present (after `composer install`), otherwise fall
 * back to a lightweight PSR-4 autoloader so the plugin always works on user sites even if
 * Composer was never run. Both map the WPWand\ namespace to src/.
 */
if (file_exists(WPWAND_NEW_DIR . 'vendor/autoload.php')) {
    require_once WPWAND_NEW_DIR . 'vendor/autoload.php';
}

spl_autoload_register(static function ($class) {
    $prefix   = 'WPWand\\';
    $base_dir = WPWAND_NEW_DIR . 'src/';
    $len      = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative = substr($class, $len);
    $file     = $base_dir . str_replace('\\', '/', $relative) . '.php';

    if (is_readable($file)) {
        require_once $file;
    }
});

\WPWand\Core\Plugin::instance()->boot();
