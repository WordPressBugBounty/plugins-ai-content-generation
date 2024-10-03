<?php
namespace WPWAND;

class Config
{
    function __construct()
    {
        add_action('admin_init', [$this, 'create_table']);
    }

    function create_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpwand_generated_post';

        $charset_collate = $wpdb->get_charset_collate();

        $schema_envato = "CREATE TABLE $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            content LONGTEXT DEFAULT NULL,
            post_id BIGINT UNSIGNED DEFAULT NULL,
            status VARCHAR(255) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        dbDelta($schema_envato);
    }
}

// $wpwand_db = new Config();