<?php
if (!defined('ABSPATH')) exit;

class KR_LMS_Profile {

    public function __construct() {
        // Debug
        error_log('KR_LMS_Profile Initialized');

        // Verification Marker
        add_action('wp_footer', function() { echo '<!-- KR LMS ACTIVE -->'; });

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
        <div class="kr-profile-section">
            <h3 class="kr-profile-title"><?php esc_html_e('My Certificates', 'kr-lms'); ?></h3>
            
            <?php if (empty($results)) : ?>
                <div class="learn-press-message message-info">
                    <?php esc_html_e('You haven\'t earned any certificates yet. Complete a course to get one!', 'kr-lms'); ?>
                </div>
            <?php else : ?>
                <div class="kr-cert-grid">
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
                            <span class="kr-cert-no"><?php echo esc_html($row->certificate_no); ?></span>
                            <span class="kr-cert-date"><?php echo esc_html($date); ?></span>
                        </div>
                        <div class="kr-cert-actions">
                            <a href="<?php echo esc_url($pdf_url); ?>" class="button button-primary" target="_blank">Download PDF</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
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
            .kr-cert-actions .button { width: 100%; text-align: center; }
        </style>
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
