<?php
if (!defined('ABSPATH')) exit;

class KR_LMS_DB {
    public static function install() {
        global $wpdb;
        $table_cert = $wpdb->prefix . "certificates";
        $table_lb   = $wpdb->prefix . "kr_leaderboard";
        $charset    = $wpdb->get_charset_collate();

        $sql1 = "CREATE TABLE $table_cert (
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

        $sql2 = "CREATE TABLE $table_lb (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            course_id BIGINT UNSIGNED NOT NULL,
            exam_name VARCHAR(255) NOT NULL,
            points DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            date DATE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            KEY user_id (user_id),
            KEY course_id (course_id)
        ) $charset;";

        $table_apps = $wpdb->prefix . "kr_cert_apps";
        $sql3 = "CREATE TABLE $table_apps (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            course_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            application_data LONGTEXT NULL,
            applied_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY(id),
            KEY user_id (user_id),
            KEY course_id (course_id),
            KEY status (status)
        ) $charset;";

        require_once ABSPATH . "wp-admin/includes/upgrade.php";
        
        // Output Buffering to prevent any unexpected output during activation
        ob_start();
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        ob_end_clean();
    }
}
