<?php
if (!defined('ABSPATH')) exit;

class KR_LMS_DB {
    public static function install() {
        global $wpdb;
        $table   = $wpdb->prefix . "certificates";
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            course_id BIGINT UNSIGNED NOT NULL,
            certificate_no VARCHAR(100) NOT NULL,
            issued_at DATETIME NOT NULL,
            meta_json LONGTEXT NULL,
            PRIMARY KEY(id),
            KEY user_id (user_id),
            KEY course_id (course_id)
        ) $charset;";

        require_once ABSPATH . "wp-admin/includes/upgrade.php";
        dbDelta($sql);
    }
}
