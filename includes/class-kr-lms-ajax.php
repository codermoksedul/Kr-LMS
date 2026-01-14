<?php
if (!defined('ABSPATH')) exit;

class KR_LMS_AJAX {

    public function __construct() {
        add_action('wp_ajax_cb_search_users',         [$this, 'search_users']);
        add_action('wp_ajax_cb_search_courses',       [$this, 'search_courses']);
        add_action('wp_ajax_cb_search_exams',         [$this, 'search_exams']);
        add_action('wp_ajax_cb_generate_certificate', [$this, 'generate_certificate']);
        add_action('wp_ajax_cb_delete_certificate',   [$this, 'delete_certificate']);
        
        // Leaderboard
        add_action('wp_ajax_kb_lb_save',   [$this, 'save_leaderboard']);
        add_action('wp_ajax_kb_lb_delete', [$this, 'delete_leaderboard']);

        // Certificate Application
        add_action('wp_ajax_kr_submit_cert_app', [$this, 'submit_cert_app']);
        add_action('wp_ajax_kr_delete_app',      [$this, 'delete_application']);
    }

    public function delete_application() {
        if (!current_user_can('manage_options')) wp_send_json_error();
        
        global $wpdb;
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $wpdb->delete($wpdb->prefix . "kr_cert_apps", ['id' => $id]);
        
        wp_send_json_success();
    }

    public function submit_cert_app() {
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'Please login first.']);

        global $wpdb;
        $user_id = get_current_user_id();
        $course_id = intval($_POST['course_id']);

        if (!$course_id) wp_send_json_error(['message' => 'Invalid course.']);

        // Check again if already applied or certified
        $table_cert = $wpdb->prefix . "certificates";
        $table_apps = $wpdb->prefix . "kr_cert_apps";

        $exists_cert = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_cert WHERE user_id=%d AND course_id=%d", $user_id, $course_id));
        if ($exists_cert) wp_send_json_error(['message' => 'You already have a certificate for this course.']);

        $exists_app = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_apps WHERE user_id=%d AND course_id=%d AND status IN ('pending', 'approved')", $user_id, $course_id));
        if ($exists_app) wp_send_json_error(['message' => 'You already have a pending application for this course.']);

        $res = $wpdb->insert($table_apps, [
            'user_id'    => $user_id,
            'course_id'  => $course_id,
            'status'     => 'pending',
            'application_data' => wp_json_encode([
                'father_name' => sanitize_text_field($_POST['father_name']),
                'mother_name' => sanitize_text_field($_POST['mother_name']),
                'start_date'  => sanitize_text_field($_POST['start_date']),
                'end_date'    => sanitize_text_field($_POST['end_date']),
                'project_url' => esc_url_raw($_POST['project_url'])
            ]),
            'applied_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ]);

        if ($res) {
            wp_send_json_success(['message' => 'Application submitted successfully!']);
        } else {
            wp_send_json_error(['message' => 'Database error. Please try again.']);
        }
    }

    public function search_users() {
        $term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';
        $users = get_users(['search' => '*'.$term.'*', 'number' => 10, 'fields' => ['ID', 'display_name', 'user_email']]);
        $out = [];
        foreach ($users as $u) {
            $out[] = ['id' => $u->ID, 'text' => $u->display_name . ' (' . $u->user_email . ')'];
        }
        wp_send_json($out);
    }

    public function search_courses() {
        $term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';
        $posts = get_posts(['post_type' => 'lp_course', 's' => $term, 'posts_per_page' => 10]);
        $out = [];
        foreach ($posts as $p) {
            $out[] = ['id' => $p->ID, 'text' => $p->post_title];
        }
        wp_send_json($out);
    }

    public function search_exams() {
        $term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';
        // Search Lessons and Quizzes
        $posts = get_posts(['post_type' => ['lp_lesson', 'lp_quiz'], 's' => $term, 'posts_per_page' => 10]);
        $out = [];
        foreach ($posts as $p) {
            $type = ($p->post_type === 'lp_lesson') ? 'Lesson' : 'Quiz';
            $out[] = ['id' => $p->ID, 'text' => $p->post_title];
        }
        wp_send_json($out);
    }

    public function generate_certificate() {
        global $wpdb;
        $user_id   = intval($_POST['user_id']);
        $course_id = intval($_POST['course_id']);
        $grade     = sanitize_text_field($_POST['grade']);
        $id        = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $app_id    = isset($_POST['app_id']) ? intval($_POST['app_id']) : 0; // New: Application ID
        
        $meta = [
            'grade'       => $grade,
            'father_name' => sanitize_text_field($_POST['father_name']),
            'mother_name' => sanitize_text_field($_POST['mother_name']),
            'batch'       => sanitize_text_field($_POST['batch']),
            'date_range'  => sanitize_text_field($_POST['date_range']), // Formatted string
            'date_start'  => sanitize_text_field($_POST['date_start']), // Raw YYYY-MM-DD
            'date_end'    => sanitize_text_field($_POST['date_end']),   // Raw YYYY-MM-DD
            'project_url' => isset($_POST['project_url']) ? esc_url_raw($_POST['project_url']) : ''
        ];

        /*
         * Start and End dates are saved in meta to easily repopulate the
         * edit form later. date_range is used for display.
         */

        $data = [
            'user_id' => $user_id,
            'course_id' => $course_id,
            'meta_json' => wp_json_encode($meta),
        ];

        if ($id > 0) {
            $wpdb->update($wpdb->prefix . "certificates", $data, ['id' => $id]);
        } else {
            // Check if exists first to prevent duplicates (though frontend checks too)
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}certificates WHERE user_id=%d AND course_id=%d", $user_id, $course_id));
            if (!$exists) {
                $data['certificate_no'] = uniqid("CERT-");
                $data['issued_at'] = current_time('mysql');
                $res = $wpdb->insert($wpdb->prefix . "certificates", $data);
                
                if ($res) {
                    $this->send_certificate_email($user_id, $course_id);
                }
            }
        }

        // AUTO-APPROVE APPLICATION IF LINKED
        if ($app_id > 0) {
            $wpdb->update($wpdb->prefix . "kr_cert_apps", 
                ['status' => 'approved', 'updated_at' => current_time('mysql')], 
                ['id' => $app_id]
            );
        }

        wp_send_json(['success' => true]);
    }

    public function delete_certificate() {
        if (!current_user_can('manage_options')) wp_send_json_error();
        
        global $wpdb;
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $wpdb->delete($wpdb->prefix . "certificates", ['id' => $id]);
        
        wp_send_json_success();
    }

    public function save_leaderboard() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);

        global $wpdb;
        $table = $wpdb->prefix . "kr_leaderboard";

        $user_id   = intval($_POST['user_id']);
        $course_id = intval($_POST['course_id']);
        $exam_name = sanitize_text_field($_POST['exam_name']);
        $points    = floatval($_POST['points']);
        $date      = sanitize_text_field($_POST['date']);

        if (!$user_id || !$course_id || !$exam_name || !$date) {
            wp_send_json_error(['message' => 'Missing required fields']);
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        $data = [
            'user_id'   => $user_id,
            'course_id' => $course_id,
            'exam_name' => $exam_name,
            'points'    => $points,
            'date'      => $date
        ];

        if ($id > 0) {
            $res = $wpdb->update($table, $data, ['id' => $id]);
        } else {
            $res = $wpdb->insert($table, $data);
        }

        if ($res) wp_send_json_success();
        else wp_send_json_error(['message' => 'Database error']);
    }

    public function delete_leaderboard() {
        if (!current_user_can('manage_options')) wp_send_json_error();
        
        global $wpdb;
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $wpdb->delete($wpdb->prefix . "kr_leaderboard", ['id' => $id]);
        
        wp_send_json_success();
    }

    private function send_certificate_email($user_id, $course_id) {
        $user = get_userdata($user_id);
        if (!$user) return;

        $course_title = get_the_title($course_id);
        $site_name = get_bloginfo('name');
        
        // Construct Profile Link
        $profile_link = site_url(); 
        if (function_exists('learn_press_get_page_link')) {
             $profile_url = learn_press_get_page_link('profile');
             $user_nicename = $user->user_nicename;
             $profile_link = rtrim($profile_url, '/') . '/' . $user_nicename . '/kr-certificates/';
        } else {
             $profile_link = home_url('/profile/' . $user->user_nicename . '/kr-certificates/');
        }

        $subject = "Your Certificate is Ready! - $course_title";
        
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Certificate Ready</title>
        </head>
        <body style='font-family: Arial, sans-serif; background-color: #f3f4f6; margin: 0; padding: 0;'>
            <div style='width: 100%; background-color: #f3f4f6; padding: 40px 0;'>
                <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05);'>
                    <div style='background-color: #2271b1; padding: 30px; text-align: center;'>
                        <h1 style='margin: 0; color: #ffffff; font-size: 24px;'>$site_name</h1>
                    </div>
                    <div style='padding: 40px 30px; color: #334155; line-height: 1.6; font-size: 16px;'>
                        <p>Hi <strong>{$user->display_name}</strong>,</p>
                        <p>Congratulations! We are pleased to inform you that your certificate for <strong style='color: #2271b1;'>$course_title</strong> has been successfully generated.</p>
                        <p>You can now view and download your certificate directly from your profile.</p>
                        <div style='text-align: center; margin-top: 35px; margin-bottom: 20px;'>
                            <a href='$profile_link' style='display: inline-block; background-color: #2271b1; color: #ffffff; padding: 14px 30px; text-decoration: none; border-radius: 50px; font-weight: bold;'>Download Certificate</a>
                        </div>
                    </div>
                    <div style='background-color: #f8fafc; padding: 20px; text-align: center; font-size: 13px; color: #94a3b8; border-top: 1px solid #e2e8f0;'>
                        <p>&copy; " . date('Y') . " $site_name. All rights reserved.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";

        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($user->user_email, $subject, $message, $headers);
    }
}
