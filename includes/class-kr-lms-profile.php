<?php
if (!defined('ABSPATH')) exit;

class KR_LMS_Profile {

    public function __construct() {
        // Hook into LearnPress Profile Tabs
        add_filter('learn-press/profile-tabs', [$this, 'add_profile_tabs'], 1000);
        
        // Register Rewrite Endpoints and Flush
        add_action('init', [$this, 'add_rewrite_endpoints']);

        // Fix Icon Styles
        add_action('wp_head', [$this, 'add_custom_styles']);
    }

    public function add_custom_styles() {
        ?>
        <style>
            /* Force Trophy Icon for Leaderboard */
            .lp-profile-nav .lp-icon-trophy:before {
                content: "\f091";
                font-family: "Font Awesome 5 Free";
                font-weight: 900;
            }
        </style>
        <?php
    }

    public function add_rewrite_endpoints() {
        add_rewrite_endpoint('kr-leaderboard', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('kr-certificates', EP_ROOT | EP_PAGES);
        
        // Temporary flush for development (should only run once on activation ideally, but this ensures it works now)
        if (get_option('kr_lms_flush_rewrite') !== 'yes') {
            flush_rewrite_rules();
            update_option('kr_lms_flush_rewrite', 'yes');
        }
    }

    public function add_profile_tabs($tabs) {
        error_log('KR_LMS_Profile: Adding Tabs');

        // Remove default Certificates tab if present
        if (isset($tabs['certificates'])) {
            unset($tabs['certificates']);
        }

        // Custom Certificates Tab
        $tabs['kr-certificates'] = [
            'title'    => esc_html__('My Certificates', 'kr-lms'),
            'slug'     => 'kr-certificates',
            'callback' => [$this, 'tab_content_certificates'],
            'priority' => 12,
            'icon'     => '<i class="fas fa-certificate"></i>' 
        ];

        // Leaderboard Tab
        $tabs['kr-leaderboard'] = [
            'title'    => esc_html__('Leader Board', 'kr-lms'),
            'slug'     => 'kr-leaderboard',
            'callback' => [$this, 'tab_content_leaderboard'],
            'priority' => 11,
            'icon'     => '<i class="fas fa-trophy"></i>'
        ];

        return $tabs;
    }

    public function tab_content_certificates() {
        $user_id = get_current_user_id();
        if (!$user_id) return;

        global $wpdb;
        $table = $wpdb->prefix . "certificates";
        $posts = $wpdb->posts;

        $results = $wpdb->get_results($wpdb->prepare("
            SELECT c.*, p.post_title as course_title 
            FROM $table c
            LEFT JOIN $posts p ON c.course_id = p.ID
            WHERE c.user_id = %d
            ORDER BY c.issued_at DESC
        ", $user_id));

        ?>

            <!-- 1. My Certificates Section (Top Priority) -->
            <h3 class="kr-profile-title"><?php esc_html_e('My Certificates', 'kr-lms'); ?></h3>
            
            <?php if (empty($results)) : ?>
                <div class="learn-press-message message-info" style="margin-bottom: 40px;">
                    <?php esc_html_e('You haven\'t earned any certificates yet.', 'kr-lms'); ?>
                </div>
            <?php else : ?>
                <div class="kr-cert-grid" style="margin-bottom: 40px;">
                    <?php foreach ($results as $row) : 
                        $course_title = $row->course_title ?: 'Unknown Course';
                        $date = date_i18n(get_option('date_format'), strtotime($row->issued_at));
                        
                        $nonce = wp_create_nonce('kr_cert_dl_' . $row->id);
                        $pdf_url = admin_url("admin-post.php?action=cb_download_certificate_png&format=pdf&id={$row->id}&_wpnonce={$nonce}&download=1");
                        $img_url = admin_url("admin-post.php?action=cb_download_certificate_png&format=png&id={$row->id}&_wpnonce={$nonce}&download=1");
                    ?>
                    <div class="kr-cert-item">
                        <div class="kr-cert-icon">
                            <span class="dashicons dashicons-awards"></span>
                        </div>
                        <div class="kr-cert-info">
                            <h4><?php echo esc_html($course_title); ?></h4>
                            
                            <span class="kr-cert-date"><?php echo esc_html($date); ?></span>
                        </div>
                        <div class="kr-cert-actions">
                            <a href="<?php echo esc_url($pdf_url); ?>" class="button button-primary" target="_blank">Download PDF</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>


            <!-- 2. Application Logic & Form (Secondary) -->
            <div class="kr-profile-section kr-apply-section">
                <!-- Header for Application Area -->
                 <h3 class="kr-profile-title"><?php esc_html_e('Certificate Application', 'kr-lms'); ?></h3>

                <?php
                    // Get Completed Courses via LearnPress User API
                    $profile = learn_press_get_profile($user_id);
                    $user = learn_press_get_user($user_id);
                    $completed_courses = [];

                    // Temporary: Fetch all courses user is enrolled in and status is finished
                    // Using direct DB queries for reliability if LP API is complex
                    $finished_courses = $wpdb->get_results($wpdb->prepare("
                        SELECT i.item_id as course_id, p.post_title
                        FROM {$wpdb->prefix}learnpress_user_items i
                        LEFT JOIN $posts p ON i.item_id = p.ID
                        WHERE i.user_id = %d 
                        AND i.item_type = 'lp_course' 
                        AND i.status IN ('enrolled', 'finished', 'passed')
                    ", $user_id));

                    // Filter out existing certificates or pending apps
                    // 1. Existing Certs
                    $existing_cert_ids = $wpdb->get_col($wpdb->prepare("SELECT course_id FROM $table WHERE user_id = %d", $user_id));
                    // 2. Pending/Approved Apps
                    $table_apps = $wpdb->prefix . "kr_cert_apps";
                    $existing_app_ids = $wpdb->get_col($wpdb->prepare("SELECT course_id FROM $table_apps WHERE user_id = %d AND status IN ('pending', 'approved')", $user_id));

                    $ignore_ids = array_merge($existing_cert_ids, $existing_app_ids);
                    
                    $eligible_courses = [];
                    foreach ($finished_courses as $fc) {
                        if (!in_array($fc->course_id, $ignore_ids)) {
                            $eligible_courses[] = $fc;
                        }
                    }
                ?>

                <?php if (empty($eligible_courses)) : ?>
                     <div class="learn-press-message message-success">
                        <?php esc_html_e('You have no eligible courses to apply for.', 'kr-lms'); ?>
                    </div>
                <?php else : ?>
                    <!-- Trigger Button -->
                    <div class="kr-cert-action-area">
                        <button type="button" class="button button-primary" id="kr-open-app-modal">
                            <?php esc_html_e('Apply for New Certificate', 'kr-lms'); ?>
                        </button>
                    </div>

                    <!-- Modal Structure -->
                    <div id="kr-app-modal-overlay" class="kr-modal-overlay" style="display:none;">
                        <div class="kr-modal-content">
                            <span class="kr-modal-close">&times;</span>
                            <h3 class="kr-modal-title"><?php esc_html_e('Certificate Application', 'kr-lms'); ?></h3>
                            
                            <form id="kr-cert-app-form" class="kr-cert-form">
                                <div class="kr-form-row">
                                    <label><?php esc_html_e('Select Course', 'kr-lms'); ?></label>
                                    <select name="course_id" id="kr-app-course" required>
                                        <option value=""><?php esc_html_e('Select a course...', 'kr-lms'); ?></option>
                                        <?php foreach ($eligible_courses as $ec) : ?>
                                            <option value="<?php echo $ec->course_id; ?>"><?php echo esc_html($ec->post_title); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="kr-form-row-group">
                                    <div class="kr-form-col">
                                        <label><?php esc_html_e('Father\'s Name', 'kr-lms'); ?></label>
                                        <input type="text" name="father_name" id="kr-app-father" required placeholder="Enter Father Name">
                                    </div>
                                    <div class="kr-form-col">
                                        <label><?php esc_html_e('Mother\'s Name', 'kr-lms'); ?></label>
                                        <input type="text" name="mother_name" id="kr-app-mother" required placeholder="Enter Mother Name">
                                    </div>
                                </div>

                                <div class="kr-form-row-group">
                                    <div class="kr-form-col">
                                        <label><?php esc_html_e('Course Start Date', 'kr-lms'); ?></label>
                                        <input type="date" name="start_date" id="kr-app-start" required>
                                    </div>
                                    <div class="kr-form-col">
                                        <label><?php esc_html_e('Course End Date', 'kr-lms'); ?></label>
                                        <input type="date" name="end_date" id="kr-app-end" required>
                                    </div>
                                </div>

                                <div class="kr-form-row">
                                    <label><?php esc_html_e('Project / Portfolio URL', 'kr-lms'); ?></label>
                                    <input type="url" name="project_url" id="kr-app-url" required placeholder="https://example.com/my-work">
                                </div>

                                <div class="kr-form-row" style="margin-top:20px; text-align:right;">
                                    <button type="submit" class="button button-primary" id="kr-app-submit">
                                        <?php esc_html_e('Submit Application', 'kr-lms'); ?>
                                    </button>
                                </div>
                                <div id="kr-app-msg"></div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 3. My Applications List -->
                <?php 
                $my_apps = $wpdb->get_results($wpdb->prepare("
                    SELECT a.*, p.post_title as course_title 
                    FROM $table_apps a
                    LEFT JOIN $posts p ON a.course_id = p.ID
                    WHERE a.user_id = %d AND a.status IN ('pending', 'rejected')
                    ORDER BY a.applied_at DESC
                ", $user_id));
                
                if (!empty($my_apps)) : 
                ?>
                    <h4 class="kr-profile-subtitle" style="margin-top:20px;"><?php esc_html_e('My Pending Applications', 'kr-lms'); ?></h4>
                    <div class="kr-app-list">
                        <?php foreach ($my_apps as $app) : 
                            $status_class = 'kr-status-' . $app->status;
                            $date_app = date_i18n(get_option('date_format'), strtotime($app->applied_at));
                            $status_label = ucfirst($app->status);
                        ?>
                        <div class="kr-app-item">
                            <div class="kr-app-info">
                                <h4><?php echo esc_html($app->course_title); ?></h4>
                                <span class="kr-app-date"><?php echo esc_html__('Applied on:', 'kr-lms'); ?> <?php echo esc_html($date_app); ?></span>
                            </div>
                            <div class="kr-app-status">
                                <span class="kr-status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            
            </div>
            
            <style>
                /* Modified Button Color to Blue (Primary) */
                 .kr-cert-actions .button { 
                    width: 100%; 
                    text-align: center; 
                    display: block; 
                    background: #2271b1; /* WordPress Primary Blue */
                    color: #fff; 
                    border: none; 
                    padding: 10px; 
                    border-radius: 4px; 
                    text-decoration: none; 
                    box-sizing: border-box;
                    font-weight: 600;
                    transition: background 0.3s;
                }
                .kr-cert-actions .button:hover {
                    background: #135e96; /* Darker Blue */
                    color: #fff;
                }

                .kr-profile-title { border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; font-size: 20px; }
                .kr-cert-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
                .kr-cert-item { 
                    background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; 
                    padding: 20px; display: flex; flex-direction: column; gap: 15px; 
                    transition: transform 0.2s, box-shadow 0.2s;
                }
                .kr-cert-item:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
                .kr-cert-icon { font-size: 40px; color: #ffb900; }
                .kr-cert-icon .dashicons { font-size: 40px; width: 40px; height: 40px; }
                .kr-cert-info h4 { margin: 0 0 5px; font-size: 16px; line-height: 1.4; color: #333; }
                .kr-cert-no { display: block; font-size: 12px; color: #666; font-family: monospace; background: #f5f5f5; padding: 2px 6px; border-radius: 4px; width: fit-content; margin-bottom: 5px;}
                .kr-cert-date { font-size: 13px; color: #888; }
                .kr-cert-actions { margin-top: auto; }
               
                /* Modal Styles */
                .kr-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center; }
                .kr-modal-content { background: #fff; padding: 30px; width: 90%; max-width: 600px; border-radius: 8px; position: relative; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
                .kr-modal-close { position: absolute; top: 10px; right: 15px; font-size: 28px; cursor: pointer; color: #888; }
                .kr-modal-close:hover { color: #333; }
                .kr-modal-title { margin-top: 0; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
                
                .kr-cert-action-area { margin-bottom: 20px; }

                /* Existing Form Styles Enhanced */
                .kr-cert-form label { display: block; margin-bottom: 5px; font-weight: 500; color: #555; }
                .kr-cert-form input[type="text"], 
                .kr-cert-form input[type="date"],
                .kr-cert-form input[type="url"],
                .kr-cert-form select { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; height: 40px; box-sizing: border-box; }
                
                .kr-form-row { margin-bottom: 15px; }
                .kr-form-row-group { display: flex; gap: 20px; margin-bottom: 15px; }
                .kr-form-col { flex: 1; }
                
                @media (max-width: 600px) {
                    .kr-form-row-group { flex-direction: column; gap: 15px; }
                }

                #kr-app-submit { width: auto; padding: 0 25px; height: 40px; font-size: 14px; }
                
                /* App List Styles */
                .kr-app-list { display: flex; flex-direction: column; gap: 15px; margin-bottom: 30px; }
                .kr-app-item { 
                    display: flex; justify-content: space-between; align-items: center; 
                    padding: 15px 20px; background: #fff; border: 1px solid #eee; border-radius: 8px; 
                    border-left: 4px solid #ddd;
                }
                .kr-app-info h4 { margin: 0 0 5px; font-size: 16px; color: #333; }
                .kr-app-date { font-size: 13px; color: #888; }
                .kr-status-badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
                
                /* Status Colors */
                .kr-status-pending { background: #fff8e1; color: #ffa000; border: 1px solid #ffe082; }
                .kr-app-item:has(.kr-status-pending) { border-left-color: #ffa000; }
                
                .kr-status-rejected { background: #ffebee; color: #d32f2f; border: 1px solid #ffcdd2; }
                .kr-app-item:has(.kr-status-rejected) { border-left-color: #d32f2f; }
            </style>

            <script>
            jQuery(document).ready(function($) {
                // Modal Logic
                $('#kr-open-app-modal').click(function(e) {
                    e.preventDefault();
                    $('#kr-app-modal-overlay').fadeIn(200);
                });
                
                $('.kr-modal-close, #kr-app-modal-overlay').click(function(e) {
                    if (e.target === this) {
                         $('#kr-app-modal-overlay').fadeOut(200);
                    }
                });

                // Form Submit
                $('#kr-cert-app-form').on('submit', function(e) {
                    e.preventDefault();
                    var btn = $('#kr-app-submit');
                    var msg = $('#kr-app-msg');
                    
                    var data = {
                        action: 'kr_submit_cert_app',
                        course_id: $('#kr-app-course').val(),
                        father_name: $('#kr-app-father').val(),
                        mother_name: $('#kr-app-mother').val(),
                        start_date: $('#kr-app-start').val(),
                        end_date: $('#kr-app-end').val(),
                        project_url: $('#kr-app-url').val()
                    };

                    if(!data.course_id || !data.father_name || !data.mother_name || !data.start_date || !data.end_date || !data.project_url) {
                        msg.css('color', 'red').text('Please fill in all required fields.');
                        return;
                    }

                    btn.prop('disabled', true).text('Applying...');
                    msg.text('');

                    $.post('<?php echo admin_url('admin-ajax.php'); ?>', data, function(res) {
                        if(res.success) {
                            msg.css('color', 'green').text(res.data.message);
                            setTimeout(function() { location.reload(); }, 2000);
                        } else {
                            btn.prop('disabled', false).text('Submit Application');
                            msg.css('color', 'red').text(res.data.message || 'Error occurred.');
                        }
                    });
                });
            });
            </script>
        <?php
    }

    public function tab_content_leaderboard() {
        $user_id = get_current_user_id();
        if (!$user_id) return;

        global $wpdb;
        $table_lb = $wpdb->prefix . "kr_leaderboard";
        $table_users = $wpdb->users;
        $posts = $wpdb->posts;

        // 1. Get courses where the current user has activity
        $user_courses = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT course_id, p.post_title as course_title
            FROM $table_lb l
            LEFT JOIN $posts p ON l.course_id = p.ID
            WHERE l.user_id = %d
            ORDER BY p.post_title ASC
        ", $user_id));

        ?>
        <div class="kr-profile-section">
            <h3 class="kr-profile-title"><?php esc_html_e('Class Leaderboard', 'kr-lms'); ?></h3>
            
            <?php if (empty($user_courses)) : ?>
                <div class="learn-press-message message-info">
                    <?php esc_html_e('You haven\'t participated in any exams properly yet to appear on the leaderboard.', 'kr-lms'); ?>
                </div>
            <?php else : ?>
                
                <?php foreach ($user_courses as $course) : 
                    $c_id = $course->course_id;
                    $c_title = $course->course_title ?: 'Unknown Course (' . $c_id . ')';

                    // 2. Get Top 20 for this course
                    $rankings = $wpdb->get_results($wpdb->prepare("
                        SELECT l.*, u.display_name 
                        FROM $table_lb l
                        LEFT JOIN $table_users u ON l.user_id = u.ID
                        WHERE l.course_id = %d
                        ORDER BY l.points DESC, l.date ASC
                        LIMIT 20
                    ", $c_id));
                ?>
                    <div class="kr-course-leaderboard">
                        <h4 class="kr-course-lb-title">
                            <span class="dashicons dashicons-book"></span>
                            <?php echo esc_html($c_title); ?>
                        </h4>
                        
                        <table class="lp-list-table kr-lb-table">
                            <thead>
                                <tr>
                                    <th width="50"><?php esc_html_e('Rank', 'kr-lms'); ?></th>
                                    <th><?php esc_html_e('Student', 'kr-lms'); ?></th>
                                    <th><?php esc_html_e('Exam', 'kr-lms'); ?></th>
                                    <th><?php esc_html_e('Points', 'kr-lms'); ?></th>
                                    <th><?php esc_html_e('Date', 'kr-lms'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $rank = 1;
                                foreach ($rankings as $row) : 
                                    $is_me = ($row->user_id == $user_id);
                                    $row_class = $is_me ? 'kr-lb-me' : '';
                                    if ($rank == 1) $row_class .= ' kr-lb-top-1';
                                    elseif ($rank == 2) $row_class .= ' kr-lb-top-2';
                                    elseif ($rank == 3) $row_class .= ' kr-lb-top-3';

                                    $date = date_i18n(get_option('date_format'), strtotime($row->date));
                                ?>
                                <tr class="<?php echo esc_attr($row_class); ?>">
                                    <td>
                                        <span class="kr-rank-badge"><?php echo $rank; ?></span>
                                    </td>
                                    <td>
                                        <?php echo esc_html($row->display_name ?: 'Unknown User'); ?>
                                        <?php if ($is_me) echo '<span class="kr-you-badge">You</span>'; ?>
                                    </td>
                                    <td><?php echo esc_html($row->exam_name); ?></td>
                                    <td><strong><?php echo floatval($row->points); ?></strong></td>
                                    <td><?php echo esc_html($date); ?></td>
                                </tr>
                                <?php 
                                $rank++;
                                endforeach; 
                                ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>

            <?php endif; ?>
        </div>
        <style>
            .kr-course-leaderboard { margin-bottom: 40px; }
            .kr-course-lb-title { 
                font-size: 18px; color: #444; margin-bottom: 15px; 
                display: flex; align-items: center; gap: 8px; border-left: 4px solid #ffb900; padding-left: 10px;
            }
            .lp-list-table.kr-lb-table { width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid #eee; border-radius: 8px; overflow: hidden; }
            .kr-lb-table th { background: #fdfdfd; padding: 15px; border-bottom: 2px solid #eee; font-weight: 700; color: #666; }
            .kr-lb-table td { padding: 12px 15px; border-bottom: 1px solid #f2f2f2; font-size: 14px; text-align: left;}
            .kr-lb-table tr:last-child td { border-bottom: none; }
            
            /* Highlighting */
            .kr-lb-me { background-color: #f0f9ff; }
            .kr-lb-me td { font-weight: 500; color: #0073aa; }
            .kr-you-badge { font-size: 10px; background: #0073aa; color: #fff; padding: 2px 6px; border-radius: 10px; margin-left: 5px; text-transform: uppercase; vertical-align: middle; }

            /* Rank Badges */
            .kr-rank-badge { display: inline-block; width: 24px; height: 24px; line-height: 24px; text-align: center; border-radius: 50%; background: #eee; font-size: 12px; font-weight: bold; color: #777; }
            .kr-lb-top-1 .kr-rank-badge { background: #ffd700; color: #fff; box-shadow: 0 2px 5px rgba(255, 215, 0, 0.4); }
            .kr-lb-top-2 .kr-rank-badge { background: #c0c0c0; color: #fff; }
            .kr-lb-top-3 .kr-rank-badge { background: #cd7f32; color: #fff; }
        </style>
        <?php
    }
}
