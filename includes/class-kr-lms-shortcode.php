<?php
if (!defined('ABSPATH')) exit;

class KR_LMS_Shortcode {

    public function __construct() {
        add_shortcode('kr_leaderboard', [$this, 'render_leaderboard']);
        
        // Auto-hook into LearnPress Course (Bottom of page)
        add_action('learn_press_after_single_course', [$this, 'auto_display_on_course']);
        
        // Certificate Search Shortcode
        add_shortcode('kr_certificate_search', [$this, 'render_certificate_search']);
    }

    public function auto_display_on_course() {
        echo do_shortcode('[kr_leaderboard title="Course Leaderboard"]');
    }

    public function render_certificate_search($atts) {
        ob_start();
        ?>
        <div class="kr-lms-cert-search">
            <style>
                .kr-lms-cert-search { 
                    max-width: 850px; margin: 60px auto; 
                    font-family: 'Inter', system-ui, -apple-system, sans-serif;
                    background: #ffffff;
                    border-radius: 20px;
                    box-shadow: 0 20px 40px -5px rgba(0,0,0,0.08);
                    padding: 50px;
                    border: 1px solid #f1f5f9;
                    text-align: center; /* Center initial content */
                }
                
                .kr-cs-header { margin-bottom: 30px; }
                .kr-cs-header h2 { margin: 0 0 10px; font-size: 28px; color: #1e293b; font-weight: 800; }
                .kr-cs-header p { margin: 0; color: #64748b; font-size: 16px; }

                /* Search Form */
                .kr-cs-form { 
                    display: flex; 
                    border: 2px solid #e2e8f0;
                    border-radius: 12px; overflow: hidden;
                    margin-bottom: 40px;
                    background: #fff;
                    transition: all 0.2s ease;
                }
                .kr-cs-form:focus-within {
                    border-color: #3b82f6;
                    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
                }
                .kr-cs-input { 
                    flex: 1; padding: 18px 24px; border: none; 
                    font-size: 16px; outline: none; color: #1e293b;
                    background: transparent; text-align: left;
                }
                .kr-cs-btn { 
                    padding: 0 40px; background: #2563eb; color: #fff; 
                    border: none; cursor: pointer; font-size: 16px; font-weight: 600;
                    transition: background 0.2s;
                }
                .kr-cs-btn:hover { background: #1d4ed8; }

                /* Result Card (Left Aligned Content) */
                .kr-cs-result { 
                    background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; 
                    margin-bottom: 20px; border-radius: 16px; 
                    display: flex; align-items: start; gap: 20px; 
                    text-align: left;
                    transition: all 0.2s;
                }
                .kr-cs-result:hover { 
                    border-color: #cbd5e1; transform: translateY(-2px);
                    box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.05);
                    background: #fff;
                }
                .kr-cs-icon {
                    width: 50px; height: 50px; background: #fff; color: #3b82f6;
                    border-radius: 12px; display: flex; align-items: center; justify-content: center;
                    flex-shrink: 0; border: 1px solid #e2e8f0;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
                }
                .kr-cs-icon svg { width: 26px; height: 26px; fill: currentColor; }
                
                .kr-cs-info { flex: 1; min-width: 0; padding-top: 2px; }
                .kr-cs-title { 
                    font-size: 18px; font-weight: 700; color: #0f172a; 
                    margin-bottom: 8px; line-height: 1.4;
                }
                .kr-cs-meta { 
                    font-size: 14px; color: #64748b; 
                    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
                }
                .kr-cs-pill {
                    background: #fff; padding: 4px 10px; border-radius: 6px; 
                    border: 1px solid #e2e8f0;
                    font-size: 12px; font-weight: 600; color: #475569;
                }

                .kr-cs-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 5px; }
                .kr-cs-download { 
                    text-decoration: none; background: #fff; color: #334155; 
                    padding: 10px 18px; border-radius: 10px; font-size: 14px; font-weight: 600;
                    border: 1px solid #cbd5e1; display: inline-flex; align-items: center; gap: 8px;
                    transition: all 0.2s; white-space: nowrap; cursor: pointer;
                    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
                }
                .kr-cs-download:hover { 
                    background: #f1f5f9; border-color: #94a3b8; transform: translateY(-1px);
                    color: #0f172a;
                }
                .kr-cs-download.primary {
                    background: #2563eb; border-color: #2563eb; color: #fff;
                    box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
                }
                .kr-cs-download.primary:hover {
                    background: #1d4ed8; border-color: #1d4ed8;
                }
                .kr-cs-download svg { width: 18px; height: 18px; fill: currentColor; }
                
                .kr-cs-no-res { 
                    padding: 40px; color: #64748b; 
                    background: #f8fafc; border-radius: 16px; border: 1px dashed #cbd5e1; 
                }

                @media (max-width: 600px) {
                    .kr-lms-cert-search { padding: 30px 20px; }
                    .kr-cs-result { flex-direction: column; text-align: center; gap: 15px; align-items: center; }
                    .kr-cs-meta { justify-content: center; }
                    .kr-cs-actions { width: 100%; justify-content: center; }
                }
            </style>
            
            <div class="kr-cs-header">
                <h2>Certificate Search</h2>
                <p>Verify and download your achievements</p>
            </div>
            
            <form method="post" class="kr-cs-form">
                <input type="email" name="kr_cert_email" class="kr-cs-input" placeholder="Enter your email to search..." required value="<?php echo isset($_POST['kr_cert_email']) ? esc_attr($_POST['kr_cert_email']) : ''; ?>">
                <button type="submit" class="kr-cs-btn">Search</button>
            </form>

            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['kr_cert_email'])) {
                $email = sanitize_email($_POST['kr_cert_email']);
                global $wpdb;
                $table = $wpdb->prefix . "certificates";
                $users = $wpdb->users;

                // Query Certificates
                $results = $wpdb->get_results($wpdb->prepare("
                    SELECT c.*, u.display_name, c.course_id 
                    FROM $table c
                    LEFT JOIN $users u ON c.user_id = u.ID
                    WHERE u.user_email = %s
                    ORDER BY c.issued_at DESC
                ", $email));

                if ($results) {
                    foreach ($results as $row) {
                        $meta = json_decode($row->meta_json, true);
                        $course_title = get_the_title($row->course_id) ?: 'Deleted Course';
                        $date = date('M d, Y', strtotime($row->issued_at));
                        
                        $nonce = wp_create_nonce('kr_cert_dl_' . $row->id);
                        $pdf_url = admin_url("admin-post.php?action=cb_download_certificate_png&format=pdf&id={$row->id}&_wpnonce={$nonce}");
                        $img_url = admin_url("admin-post.php?action=cb_download_certificate_png&format=png&download=1&id={$row->id}&_wpnonce={$nonce}");
                        
                        echo '<div class="kr-cs-result">';
                            echo '<div class="kr-cs-icon">';
                                echo '<svg viewBox="0 0 24 24"><path d="M12,1L3,5V11C3,16.55 6.84,21.74 12,23C17.16,21.74 21,16.55 21,11V5L12,1M12,11.99H7V10.29H12V11.99M17,8.29H7V6.59H17V8.29M17,5.59H7V3.89H17V5.59Z" transform="scale(0.8) translate(3,3)"/></svg>'; 
                            echo '</div>';
                            echo '<div class="kr-cs-info">';
                                echo '<div class="kr-cs-title">' . esc_html($course_title) . '</div>';
                                echo '<div class="kr-cs-meta">';
                                    echo '<span class="kr-cs-pill">CERT: ' . esc_html($row->certificate_no) . '</span>';
                                    echo '<span>Issued: ' . $date . '</span>';
                                echo '</div>';
                            echo '</div>';
                            
                            echo '<div class="kr-cs-actions">';
                                // PDF Download
                                echo '<a href="' . esc_url($pdf_url) . '" class="kr-cs-download primary">';
                                    echo '<svg viewBox="0 0 24 24"><path d="M5,20H19V18H5M19,9H15V3H9V9H5L12,16L19,9Z" fill="currentColor"/></svg>';
                                    echo 'PDF';
                                echo '</a>';
                                // Image Download
                                echo '<a href="' . esc_url($img_url) . '" class="kr-cs-download">';
                                    echo '<svg viewBox="0 0 24 24"><path d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z" fill="currentColor"/></svg>';
                                    echo 'Image';
                                echo '</a>';
                            echo '</div>';
                            
                        echo '</div>';
                    }
                } else {
                    echo '<div class="kr-cs-no-res">';
                    echo '<h4 style="margin:0 0 10px; color:#475569;">No Records Found</h4>';
                    echo '<p style="margin:0;">We couldn\'t find any certificates linked to <strong>'.esc_html($email).'</strong>.</p>';
                    echo '</div>';
                }
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_leaderboard($atts) {
        $atts = shortcode_atts([
            'course_id' => 0,
            'limit'     => 10,
            'title'     => 'Leaderboard',
            'type'      => 'course' // 'course' or 'global'
        ], $atts);

        global $wpdb;
        $table = $wpdb->prefix . "kr_leaderboard";
        $users = $wpdb->users;
        $results = [];

        if ($atts['type'] === 'global') {
            // Global Leaderboard (Sum of points)
            $results = $wpdb->get_results($wpdb->prepare("
                SELECT l.user_id, u.display_name, SUM(l.points) as points, 'All Exams' as exam_name
                FROM $table l
                LEFT JOIN $users u ON l.user_id = u.ID
                GROUP BY l.user_id
                ORDER BY points DESC
                LIMIT %d
            ", $atts['limit']));
        } else {
            // Course Specific
            $course_id = intval($atts['course_id']);
            if (!$course_id) $course_id = get_the_ID();

            if ($course_id) {
                $results = $wpdb->get_results($wpdb->prepare("
                    SELECT l.*, u.display_name 
                    FROM $table l
                    LEFT JOIN $users u ON l.user_id = u.ID
                    WHERE l.course_id = %d
                    ORDER BY l.points DESC, l.date ASC
                    LIMIT %d
                ", $course_id, $atts['limit']));
            }
        }

        if (empty($results)) return '';

        ob_start();
        ?>
        <div class="kr-lms-leaderboard-wrapper">
            <style>
                .kr-lms-leaderboard-wrapper {
                    background: #fff;
                    border: 1px solid #eee;
                    border-radius: 8px;
                    padding: 20px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
                    margin: 20px 0;
                    font-family: inherit;
                }
                .kr-lms-lb-title {
                    font-size: 18px;
                    font-weight: 600;
                    margin-bottom: 15px;
                    color: #333;
                    border-bottom: 1px solid #f0f0f0;
                    padding-bottom: 10px;
                }
                .kr-lms-lb-table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .kr-lms-lb-table th {
                    text-align: left;
                    padding: 10px;
                    color: #666;
                    font-size: 13px;
                    text-transform: uppercase;
                    background: #f9f9f9;
                    border-radius: 4px;
                }
                .kr-lms-lb-table td {
                    padding: 12px 10px;
                    border-bottom: 1px solid #f0f0f0;
                    vertical-align: middle;
                }
                .kr-lms-lb-table tr:last-child td {
                    border-bottom: none;
                }
                .kr-lb-rank {
                    font-weight: 700;
                    color: #888;
                    width: 40px;
                }
                .kr-lb-rank-1 { color: #d4af37; } /* Gold */
                .kr-lb-rank-2 { color: #c0c0c0; } /* Silver */
                .kr-lb-rank-3 { color: #cd7f32; } /* Bronze */
                
                .kr-lb-user {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    font-weight: 500;
                    color: #333;
                }
                .kr-lb-avatar {
                    width: 32px;
                    height: 32px;
                    background: #e2e8f0;
                    color: #475569;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 12px;
                    font-weight: bold;
                }
                .kr-lb-points {
                    font-weight: 700;
                    color: #2271b1;
                    text-align: right;
                }
                .kr-lb-exam {
                    color: #666;
                    font-size: 13px;
                }
            </style>
            
            <?php if (!empty($atts['title'])): ?>
                <div class="kr-lms-lb-title"><?php echo esc_html($atts['title']); ?></div>
            <?php endif; ?>

            <table class="kr-lms-lb-table">
                <thead>
                    <tr>
                        <th width="10%">#</th>
                        <th>Student</th>
                        <th><?php echo ($atts['type'] === 'global') ? 'Total' : 'Exam'; ?></th>
                        <th style="text-align:right">Points</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rank = 1;
                    foreach ($results as $row): 
                        $initial = strtoupper(substr($row->display_name, 0, 1));
                        $rankClass = ($rank <= 3) ? 'kr-lb-rank-' . $rank : '';
                        $examDisplay = ($atts['type'] === 'global') ? 'All Courses' : $row->exam_name;
                    ?>
                    <tr>
                        <td class="kr-lb-rank <?php echo $rankClass; ?>">
                            <?php 
                                if($rank == 1) echo '🥇';
                                elseif($rank == 2) echo '🥈';
                                elseif($rank == 3) echo '🥉';
                                else echo $rank;
                            ?>
                        </td>
                        <td>
                            <div class="kr-lb-user">
                                <div class="kr-lb-avatar"><?php echo $initial; ?></div>
                                <span><?php echo esc_html($row->display_name); ?></span>
                            </div>
                        </td>
                        <td class="kr-lb-exam"><?php echo esc_html($examDisplay); ?></td>
                        <td class="kr-lb-points"><?php echo number_format($row->points, 1); ?></td>
                    </tr>
                    <?php $rank++; endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
}
