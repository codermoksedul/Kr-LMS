<?php
/**
 * Plugin Name: KR LMS
 * Description: KR LMS Certificate System with search, AJAX actions, and high-quality PNG download.
 * Version: 3.2
 * Author: Moksedul
 */

if (!defined('ABSPATH')) exit;

// Constants
define('KR_LMS_VERSION', '3.1');
define('KR_LMS_PATH', plugin_dir_path(__FILE__));
define('KR_LMS_URL', plugin_dir_url(__FILE__));

// Asset Paths
define('KR_LMS_ASSETS', KR_LMS_URL . 'assets/');
define('KR_LMS_CERT_TEMPLATE_PATH', KR_LMS_PATH . 'assets/img/certificate-template.png');
define('KR_LMS_CERT_FONT_PATH',     KR_LMS_PATH . 'assets/img/certificate-font.ttf');
define('KR_LMS_CERT_FONT_BOLD_PATH', KR_LMS_PATH . 'assets/img/certificate-font-bold.ttf');
define('KR_LMS_CERT_LOGO_PATH',     KR_LMS_PATH . 'assets/img/logo.png');
define('KR_LMS_CERT_BADGE_PATH',    KR_LMS_PATH . 'assets/img/badge.png');
define('KR_LMS_CERT_SIGNATURE_PATH', KR_LMS_PATH . 'assets/img/signature.png');

// Includes
require_once KR_LMS_PATH . 'includes/class-kr-lms-db.php';
require_once KR_LMS_PATH . 'includes/class-kr-lms-admin.php';
require_once KR_LMS_PATH . 'includes/class-kr-lms-ajax.php';
require_once KR_LMS_PATH . 'includes/class-kr-lms-generator.php';
require_once KR_LMS_PATH . 'includes/class-kr-lms-shortcode.php';

// Main Class
class KR_LMS {

    public function __construct() {
        // DB Installation Hook
        register_activation_hook(__FILE__, ['KR_LMS_DB', 'install']);

        // Initialize Components
        new KR_LMS_Admin();
        new KR_LMS_AJAX();
        new KR_LMS_Generator();
        new KR_LMS_Shortcode();
    }
}

// Start
new KR_LMS();
