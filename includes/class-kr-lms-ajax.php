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
        
        $meta = [
            'grade'       => $grade,
            'father_name' => sanitize_text_field($_POST['father_name']),
            'mother_name' => sanitize_text_field($_POST['mother_name']),
            'batch'       => sanitize_text_field($_POST['batch']),
            'date_range'  => sanitize_text_field($_POST['date_range']), // Formatted string
            'date_start'  => sanitize_text_field($_POST['date_start']), // Raw YYYY-MM-DD
            'date_end'    => sanitize_text_field($_POST['date_end']),   // Raw YYYY-MM-DD
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
            $data['certificate_no'] = uniqid("CERT-");
            $data['issued_at'] = current_time('mysql');
            $wpdb->insert($wpdb->prefix . "certificates", $data);
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
}
