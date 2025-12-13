<?php
if (!defined('ABSPATH')) exit;

class KR_LMS_Generator {

    public function __construct() {
        add_action('admin_post_cb_download_certificate', [$this, 'download']);
    }

    public function download() {
        if (!current_user_can('manage_options')) wp_die('Permission denied');
        
        $id = intval($_GET['id']);
        check_admin_referer('cb_download_' . $id);

        global $wpdb;
        $cert = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}certificates WHERE id = %d", $id));
        if (!$cert) wp_die('Not found');

        if (file_exists(KR_LMS_CERT_TEMPLATE_PATH)) {
            $im = imagecreatefrompng(KR_LMS_CERT_TEMPLATE_PATH);
            imagealphablending($im, true);
            imagesavealpha($im, true);
            $width = imagesx($im); $height = imagesy($im);
        } else {
            $width = 1280; $height = 904;
            $im = imagecreatetruecolor($width, $height);
            
            $bg = imagecolorallocate($im, 255, 255, 255);
            imagefilledrectangle($im, 0, 0, $width, $height, $bg);
            
            $accentBlue = imagecolorallocate($im, 37, 99, 235);
            imagefilledrectangle($im, 150, 130, 270, 770, $accentBlue); 
            
            $qrBlack = imagecolorallocate($im, 0, 0, 0);
            imagefilledrectangle($im, 150, 800, 250, 900, $qrBlack);
            imagestring($im, 2, 165, 840, "QR CODE", $bg);
        }

        $black = imagecolorallocate($im, 17, 24, 39);
        $gray  = imagecolorallocate($im, 75, 85, 99);
        $blue  = imagecolorallocate($im, 37, 99, 235);
        
        $user   = get_userdata($cert->user_id);
        $course = get_the_title($cert->course_id);
        $meta   = json_decode($cert->meta_json, true) ?: [];
        
        $student_name = $user ? $user->display_name : 'Student Name';
        $course_name  = $course ?: 'Course Name';
        
        $father = isset($meta['father_name']) && $meta['father_name'] ? $meta['father_name'] : '..................';
        $mother = isset($meta['mother_name']) && $meta['mother_name'] ? $meta['mother_name'] : '..................';
        $batch  = isset($meta['batch']) ? $meta['batch'] : '';
        $date_range = isset($meta['date_range']) ? $meta['date_range'] : '..................';
        $grade  = isset($meta['grade']) ? $meta['grade'] : '-';

        $draw = function($text, $size, $x, $y, $color, $fontRequest = '') use ($im) {
            $font = KR_LMS_CERT_FONT_PATH; 
            if (file_exists($font) && function_exists('imagettftext')) {
                imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
            } else {
                imagestring($im, 5, $x, $y-15, $text, $color);
            }
        };

        $x_base = 340; 
        $y_start = 180;

        $draw("This is certify that", 18, $x_base, $y_start, $gray);
        if(function_exists('imageline')) {
             imageline($im, $x_base, $y_start + 35, $x_base + 120, $y_start + 35, $gray);
        }

        $draw($student_name, 48, $x_base, $y_start + 90, $blue);

        $draw("Son of $father & $mother", 16, $x_base, $y_start + 150, $gray);
        $draw("successfully completed the", 16, $x_base, $y_start + 185, $gray);

        $course_text = $course_name . ($batch ? " " . $batch : "");
        $draw($course_text, 24, $x_base, $y_start + 240, $black);

        $draw("with grade $grade held on $date_range", 16, $x_base, $y_start + 280, $gray);
        $draw("at Visual Center", 16, $x_base, $y_start + 310, $gray);

        $footer_x = 900;
        $footer_y = 650;
        
        $draw("VERIFIED BY", 14, $footer_x, $footer_y, $black);
        
        if(function_exists('imageline')) {
            $line_y = $footer_y + 80;
            imageline($im, $footer_x, $line_y, $footer_x + 200, $line_y, $black);
            imagefilledrectangle($im, $footer_x, $line_y, $footer_x + 200, $line_y + 1, $black);
        }
        $draw("Director", 14, $footer_x, $footer_y + 110, $black);

        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="certificate-' . $id . '.png"');
        imagepng($im);
        imagedestroy($im);
        exit;
    }
}
