<?php
if (!defined('ABSPATH')) exit;

class KR_LMS_Shortcode {

    public function __construct() {
        add_shortcode('kr_leaderboard', [$this, 'render_leaderboard']);
        
        // Auto-hook into LearnPress Course (Bottom of page)
        add_action('learn_press_after_single_course', [$this, 'auto_display_on_course']);
    }

    public function auto_display_on_course() {
        echo do_shortcode('[kr_leaderboard title="Course Leaderboard"]');
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
