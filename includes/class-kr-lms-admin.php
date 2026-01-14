<?php
if (!defined('ABSPATH')) exit;

class KR_LMS_Admin {

    public function __construct() {
        add_action('admin_menu',            [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'scripts']);
    }

    public function menu() {
        // Main Menu
        add_menu_page(
            "KR LMS",
            "KR LMS",
            "manage_options",
            "kr-lms",
            [$this, 'page'],
            "dashicons-welcome-learn-more",
            56
        );
        // Submenu: Certificates (First submenu usually repeats main menu to rename it, but here we keep it)
        add_submenu_page(
            "kr-lms",
            "Certificates",
            "Certificates",
            "manage_options",
            "kr-lms",
            [$this, 'page']
        );
        // Submenu: Leader Board
        add_submenu_page(
            "kr-lms",
            "Leader Board",
            "Leader Board",
            "manage_options",
            "kr-lms-leaderboard",
            [$this, 'page_leaderboard']
        );
        // Submenu: Applications
        global $wpdb;
        $count = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}kr_cert_apps WHERE status='pending'");
        $menu_title = "Applications";
        if($count > 0) {
            $menu_title .= " <span class='kr-pending-count'>{$count}</span>";
        }

        add_submenu_page(
            "kr-lms",
            "Applications",
            $menu_title,
            "manage_options",
            "kr-lms-applications",
            [$this, 'page_applications']
        );
        // Submenu: Shortcodes
        add_submenu_page(
            "kr-lms",
            "Shortcodes",
            "Shortcodes",
            "manage_options",
            "kr-lms-help",
            [$this, 'page_help']
        );
    }

    public function scripts($hook) {
            if (strpos($hook, 'kr-lms') === false) return;
        
        wp_enqueue_script('jquery');
        wp_enqueue_style('cb-admin-css', plugin_dir_url(__DIR__) . 'assets/css/admin.css', [], time());
        wp_enqueue_script('cb-admin-js', plugin_dir_url(__DIR__) . 'assets/js/admin.js', ['jquery'], time(), true);
        
        // SweetAlert2 (CDN for simplicity)
        wp_enqueue_style('swal2-css', 'https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css');
        wp_enqueue_script('swal2-js', 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js', [], null, true);
        
        // CSS for Menu Badge
        wp_add_inline_style('admin-menu', '
            .kr-pending-count {
                display: inline-block;
                vertical-align: top;
                margin: 1px 0 0 2px;
                padding: 0 6px;
                min-width: 18px;
                height: 18px;
                border-radius: 9px;
                background-color: #d63638;
                color: #fff;
                font-size: 11px;
                line-height: 18px;
                text-align: center;
                font-weight: 600;
            }
        ');
    }

    public function page() {
        global $wpdb;
        $table = $wpdb->prefix . "certificates";
        $users = $wpdb->users;
        $posts = $wpdb->posts;

        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $paged  = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $limit  = 10;
        $offset = ($paged - 1) * $limit;

        $where_sql = "WHERE 1=1";
        if (!empty($search)) {
            $where_sql .= $wpdb->prepare(
                " AND (
                    c.certificate_no LIKE %s OR
                    u.display_name LIKE %s OR
                    u.user_email LIKE %s OR
                    p.post_title LIKE %s
                )",
                '%' . $search . '%', '%' . $search . '%', '%' . $search . '%', '%' . $search . '%'
            );
        }

        $total_sql = "
            SELECT COUNT(c.id)
            FROM $table c
            LEFT JOIN $users u ON c.user_id = u.ID
            LEFT JOIN $posts p ON c.course_id = p.ID
            $where_sql
        ";
        $total_items = $wpdb->get_var($total_sql);
        $total_pages = ceil($total_items / $limit);

        $data_sql = "
            SELECT c.*, u.display_name, u.user_email, p.post_title as course_title
            FROM $table c
            LEFT JOIN $users u ON c.user_id = u.ID
            LEFT JOIN $posts p ON c.course_id = p.ID
            $where_sql
            ORDER BY c.id DESC
            LIMIT %d OFFSET %d
        ";
        $certs = $wpdb->get_results($wpdb->prepare($data_sql, $limit, $offset));
        
        // Fix for base url in pagination
        $base_url = menu_page_url('kr-lms', false);
        ?>

        <div class="wrap cb-wrapper">
            <div class="cb-header">
                <div>
                    <h1 class="wp-heading-inline">KR LMS Certificates</h1>
                    <p class="cb-subtitle">Manage and generate student certificates.</p>
                </div>
            </div>

            <div class="cb-toolbar">
                <form method="get" action="">
                    <input type="hidden" name="page" value="kr-lms">
                    <div class="cb-search-box">
                        <span class="dashicons dashicons-search cb-search-icon"></span>
                        <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search student, course or email..." class="cb-search-input">
                        <button type="submit" class="cb-search-btn">Search</button>
                    </div>
                </form>
                <button id="cb-add-new" class="cb-btn cb-btn-primary cb-btn-glow">
                    <span class="dashicons dashicons-plus" style="margin-top:3px;"></span> New Certificate
                </button>
            </div>

            <div class="cb-card">
                <?php if (empty($certs) && $total_items == 0): ?>
                    <div class="cb-empty-state">
                        <span class="dashicons dashicons-awards" style="font-size:48px; height:48px; width:48px; color:#cbd5e1;"></span>
                        <h3>No certificates found</h3>
                        <?php if ($search): ?>
                            <p>Try adjusting your search terms.</p>
                            <a href="<?php echo $base_url; ?>" class="cb-btn cb-btn-outline">Clear Search</a>
                        <?php else: ?>
                            <p>Get started by generating your first certificate.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <table class="cb-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Student</th>
                                <th>Course</th>
                                <th>Grade</th>
                                <th>Date</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($certs as $c): ?>
                            <?php
                                $meta  = json_decode($c->meta_json, true) ?: [];
                                $grade = isset($meta['grade']) ? $meta['grade'] : '-';
                                $s_name  = $c->display_name ?: 'Unknown User';
                                $s_email = $c->user_email ?: 'No Email';
                                $c_title = $c->course_title ?: '(Deleted Course)';

                                $download_url = wp_nonce_url(
                                    admin_url('admin-post.php?action=cb_download_certificate&id=' . $c->id),
                                    'cb_download_' . $c->id
                                );
                                
                                $view_url = wp_nonce_url(
                                    admin_url('admin-post.php?action=cb_view_certificate&id=' . $c->id),
                                    'cb_view_' . $c->id
                                );

                                // Extract Dates (Logic for OLD entries compatibility)
                                $d_start = isset($meta['date_start']) ? $meta['date_start'] : '';
                                $d_end   = isset($meta['date_end'])   ? $meta['date_end']   : '';
                                
                                if (empty($d_start) && !empty($meta['date_range'])) {
                                    $parts = explode(' to ', $meta['date_range']);
                                    if (count($parts) == 2) {
                                        $d_start = date('Y-m-d', strtotime($parts[0]));
                                        $d_end   = date('Y-m-d', strtotime($parts[1]));
                                    }
                                }
                            ?>
                            <tr id="cb-row-<?php echo $c->id; ?>">
                                <td class="cb-id">#<?php echo $c->id; ?></td>
                                <td>
                                    <div class="cb-user-cell">
                                        <div class="cb-avatar"><?php echo strtoupper(substr($s_name, 0, 1)); ?></div>
                                        <div>
                                            <div class="cb-user-name"><?php echo esc_html($s_name); ?></div>
                                            <div class="cb-user-email"><?php echo esc_html($s_email); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="cb-course-name"><?php echo esc_html($c_title); ?></div>
                                    <div class="cb-cert-no"><?php echo esc_html($c->certificate_no); ?></div>
                                </td>
                                <td><span class="cb-badge"><?php echo esc_html($grade); ?></span></td>
                                <td><?php echo date('M j, Y', strtotime($c->issued_at)); ?></td>
                                <td style="text-align:right;">
                                    <a href="<?php echo esc_url($view_url); ?>" class="cb-action-btn cb-tooltip" title="Download Certificate" target="_blank">
                                        <span class="dashicons dashicons-download"></span>
                                    </a>
                                    <button class="cb-action-btn cb-edit-btn cb-tooltip" title="Edit"
                                        data-id="<?php echo $c->id; ?>"
                                        data-user-id="<?php echo $c->user_id; ?>"
                                        data-user-text="<?php echo esc_attr($s_name . ' (' . $s_email . ')'); ?>"
                                        data-course-id="<?php echo $c->course_id; ?>"
                                        data-course-text="<?php echo esc_attr($c_title); ?>"
                                        data-father="<?php echo esc_attr(isset($meta['father_name']) ? $meta['father_name'] : ''); ?>"
                                        data-mother="<?php echo esc_attr(isset($meta['mother_name']) ? $meta['mother_name'] : ''); ?>"
                                        data-batch="<?php echo esc_attr(isset($meta['batch']) ? $meta['batch'] : ''); ?>"
                                        data-grade="<?php echo esc_attr($grade); ?>"
                                        data-start="<?php echo esc_attr($d_start); ?>"
                                        data-end="<?php echo esc_attr($d_end); ?>"
                                    >
                                        <span class="dashicons dashicons-edit"></span>
                                    </button>
                                    <button class="cb-action-btn cb-delete-btn cb-tooltip" title="Delete" data-id="<?php echo $c->id; ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="cb-footer">
                <div class="cb-stats">
                    Showing <strong><?php echo count($certs); ?></strong> of <strong><?php echo $total_items; ?></strong>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="cb-pagination">
                        <?php
                        echo paginate_links([
                            'base'      => add_query_arg('paged', '%#%'),
                            'format'    => '',
                            'prev_text' => '&laquo; Prev',
                            'next_text' => 'Next &raquo;',
                            'total'     => $total_pages,
                            'current'   => $paged
                        ]);
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- MODAL (Create Certificate) -->
        <div id="cb-modal">
            <div class="cb-modal-backdrop"></div>
            <div class="cb-modal-content">
                <div class="cb-modal-header">
                    <h2>New Certificate</h2>
                    <button id="cb-close" class="cb-close-icon">&times;</button>
                </div>
                <div class="cb-modal-body">
                    <input type="hidden" id="cb-id" value="">
                    <div class="cb-grid-2">
                        <div class="cb-form-group cb-full-width">
                            <label>Student</label>
                            <div class="cb-autocomplete">
                                <input type="text" id="cb-user-search" class="cb-input" placeholder="Type pattern name or email..." autocomplete="off">
                                <div id="cb-user-dropdown" class="cb-dropdown"></div>
                                <input type="hidden" id="cb-user-selected">
                            </div>
                        </div>
                        <div class="cb-form-group">
                            <label>Father's Name</label>
                            <input type="text" id="cb-father-name" class="cb-input" placeholder="e.g. Md. Father Name">
                        </div>
                        <div class="cb-form-group">
                            <label>Mother's Name</label>
                            <input type="text" id="cb-mother-name" class="cb-input" placeholder="e.g. Mrs. Mother Name">
                        </div>
                        <div class="cb-form-group cb-full-width">
                            <label>Course</label>
                            <div class="cb-autocomplete">
                                <input type="text" id="cb-course-search" class="cb-input" placeholder="Search course title..." autocomplete="off">
                                <div id="cb-course-dropdown" class="cb-dropdown"></div>
                                <input type="hidden" id="cb-course-selected">
                            </div>
                        </div>
                        <div class="cb-form-group">
                            <label>Batch</label>
                            <input type="text" id="cb-batch" class="cb-input" placeholder="e.g. Batch 2">
                        </div>
                        <div class="cb-form-group">
                            <label>Grade</label>
                            <input type="text" id="cb-grade" class="cb-input" placeholder="e.g. A+">
                        </div>
                        <div class="cb-grid-2 cb-full-width" style="grid-column: 1 / -1; display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                            <div class="cb-form-group">
                                <label>Start Date</label>
                                <input type="date" id="cb-date-start" class="cb-input">
                            </div>
                            <div class="cb-form-group">
                                <label>End Date</label>
                                <input type="date" id="cb-date-end" class="cb-input">
                            </div>
                        </div>
                        <input type="hidden" id="cb-date-range">
                    </div>
                </div>
                <div class="cb-modal-footer">
                    <button id="cb-cancel" class="cb-btn cb-btn-outline">Cancel</button>
                    <button id="cb-generate" class="cb-btn cb-btn-primary">Generate</button>
                </div>
            </div>
        </div>
        <?php
    }

    public function page_leaderboard() {
        global $wpdb;
        $table = $wpdb->prefix . "kr_leaderboard";
        $users = $wpdb->users;
        $posts = $wpdb->posts;

        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $paged  = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $limit  = 10;
        $offset = ($paged - 1) * $limit;

        $where_sql = "WHERE 1=1";
        if (!empty($search)) {
            $where_sql .= $wpdb->prepare(
                " AND (
                    l.exam_name LIKE %s OR
                    u.display_name LIKE %s OR
                    p.post_title LIKE %s
                )",
                '%' . $search . '%', '%' . $search . '%', '%' . $search . '%'
            );
        }

        $total_items = $wpdb->get_var("
            SELECT COUNT(l.id) FROM $table l
            LEFT JOIN $users u ON l.user_id = u.ID
            LEFT JOIN $posts p ON l.course_id = p.ID
            $where_sql
        ");
        $total_pages = ceil($total_items / $limit);

        $items = $wpdb->get_results($wpdb->prepare("
            SELECT l.*, u.display_name, u.user_email, p.post_title as course_title
            FROM $table l
            LEFT JOIN $users u ON l.user_id = u.ID
            LEFT JOIN $posts p ON l.course_id = p.ID
            $where_sql
            ORDER BY l.points DESC, l.date DESC
            LIMIT %d OFFSET %d
        ", $limit, $offset));

        $base_url = menu_page_url('kr-lms-leaderboard', false);
        ?>
        <div class="wrap cb-wrapper">
            <div class="cb-header">
                <div>
                    <h1 class="wp-heading-inline">Leader Board</h1>
                    <p class="cb-subtitle">Manage student exam rankings.</p>
                </div>
            </div>

            <div class="cb-toolbar">
                <form method="get" action="">
                    <input type="hidden" name="page" value="kr-lms-leaderboard">
                    <div class="cb-search-box">
                        <span class="dashicons dashicons-search cb-search-icon"></span>
                        <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search..." class="cb-search-input">
                        <button type="submit" class="cb-search-btn">Search</button>
                    </div>
                </form>
                <button id="lb-add-new" class="cb-btn cb-btn-primary cb-btn-glow">
                    <span class="dashicons dashicons-plus" style="margin-top:3px;"></span> Add Entry
                </button>
            </div>

            <div class="cb-card">
                 <?php if (empty($items) && $total_items == 0): ?>
                    <div class="cb-empty-state">
                        <span class="dashicons dashicons-chart-bar" style="font-size:48px; color:#cbd5e1;"></span>
                        <h3>No records found</h3>
                    </div>
                <?php else: ?>
                    <table class="cb-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Course</th>
                                <th>Exam Name</th>
                                <th>Points</th>
                                <th>Date</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr id="lb-row-<?php echo $item->id; ?>">
                                <td>
                                    <div class="cb-user-cell">
                                        <div class="cb-avatar"><?php echo strtoupper(substr($item->display_name, 0, 1)); ?></div>
                                        <div>
                                            <div class="cb-user-name"><?php echo esc_html($item->display_name); ?></div>
                                            <div class="cb-user-email"><?php echo esc_html($item->user_email); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo esc_html($item->course_title); ?></td>
                                <td><?php echo esc_html($item->exam_name); ?></td>
                                <td><span class="cb-badge"><?php echo $item->points; ?></span></td>
                                <td><?php echo $item->date; ?></td>
                                <td style="text-align:right;">
                                    <button class="cb-action-btn lb-edit-btn" 
                                        data-id="<?php echo $item->id; ?>"
                                        data-user-id="<?php echo $item->user_id; ?>"
                                        data-user-text="<?php echo esc_attr($item->display_name . ' (' . $item->user_email . ')'); ?>"
                                        data-course-id="<?php echo $item->course_id; ?>"
                                        data-course-text="<?php echo esc_attr($item->course_title); ?>"
                                        data-exam="<?php echo esc_attr($item->exam_name); ?>"
                                        data-points="<?php echo $item->points; ?>"
                                        data-date="<?php echo $item->date; ?>"
                                    >
                                        <span class="dashicons dashicons-edit"></span>
                                    </button>
                                    <button class="cb-action-btn lb-delete-btn" data-id="<?php echo $item->id; ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <div class="cb-footer">
                <div class="cb-stats">
                    Showing <strong><?php echo count($items); ?></strong> of <strong><?php echo $total_items; ?></strong>
                </div>
                <?php if ($total_pages > 1): ?>
                    <div class="cb-pagination">
                        <?php echo paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'total' => $total_pages,
                            'current' => $paged
                        ]); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- MODAL (Leaderboard) -->
        <div id="lb-modal">
            <div class="cb-modal-backdrop"></div>
            <div class="cb-modal-content">
                <div class="cb-modal-header">
                    <h2>Add Leaderboard Entry</h2>
                    <button id="lb-close" class="cb-close-icon">&times;</button>
                </div>
                <div class="cb-modal-body">
                    <input type="hidden" id="lb-id" value="">
                    <div class="cb-grid-2">
                        <div class="cb-form-group cb-full-width">
                            <label>Student</label>
                            <div class="cb-autocomplete">
                                <input type="text" id="lb-user-search" class="cb-input" placeholder="Search student..." autocomplete="off">
                                <div id="lb-user-dropdown" class="cb-dropdown"></div>
                                <input type="hidden" id="lb-user-selected">
                            </div>
                        </div>
                        <div class="cb-form-group cb-full-width">
                            <label>Course</label>
                            <div class="cb-autocomplete">
                                <input type="text" id="lb-course-search" class="cb-input" placeholder="Search course..." autocomplete="off">
                                <div id="lb-course-dropdown" class="cb-dropdown"></div>
                                <input type="hidden" id="lb-course-selected">
                            </div>
                        </div>
                        <div class="cb-form-group cb-full-width">
                            <label>Exam Name (Lesson/Quiz)</label>
                            <div class="cb-autocomplete">
                                <input type="text" id="lb-exam-name" class="cb-input" placeholder="Search lesson or type name..." autocomplete="off">
                                <div id="lb-exam-dropdown" class="cb-dropdown"></div>
                                <input type="hidden" id="lb-exam-id"> <!-- Optional ID storage -->
                            </div>
                        </div>
                        <div class="cb-form-group">
                            <label>Points</label>
                            <input type="number" step="0.01" id="lb-points" class="cb-input" placeholder="e.g. 95.5">
                        </div>
                        <div class="cb-form-group">
                            <label>Date</label>
                            <input type="date" id="lb-date" class="cb-input" value="<?php echo current_time('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>
                <div class="cb-modal-footer">
                    <button id="lb-cancel" class="cb-btn cb-btn-outline">Cancel</button>
                    <button id="lb-save" class="cb-btn cb-btn-primary">Save Entry</button>
                </div>
            </div>
        </div>
        <?php
    }

    public function page_applications() {
        global $wpdb;
        $table_apps = $wpdb->prefix . "kr_cert_apps";
        $table_cert = $wpdb->prefix . "certificates";
        $users = $wpdb->users;
        $posts = $wpdb->posts;

        // --- Handle GET Actions (Legacy/Simple interactions) ---
        if (isset($_GET['action'], $_GET['id'], $_GET['_wpnonce'])) {
            $action = $_GET['action']; // 'kr_reject' only (Approve handled via AJAX now)
            $id = intval($_GET['id']);
            
            if (wp_verify_nonce($_GET['_wpnonce'], $action . '_' . $id)) {
                if ($action === 'kr_reject') {
                    $wpdb->update($table_apps, ['status' => 'rejected', 'updated_at' => current_time('mysql')], ['id' => $id]);
                    echo '<div class="notice notice-info is-dismissible"><p>Application rejected.</p></div>';
                }
            }
        }

        // --- Search & Pagination ---
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $limit = 10;
        $offset = ($paged - 1) * $limit;

        $where_sql = "WHERE 1=1";
        if (!empty($search)) {
            $where_sql .= $wpdb->prepare(
                " AND (
                    u.display_name LIKE %s OR
                    u.user_email LIKE %s OR
                    p.post_title LIKE %s
                )",
                '%' . $search . '%', '%' . $search . '%', '%' . $search . '%'
            );
        }

        $total_items = $wpdb->get_var("
            SELECT COUNT(a.id) FROM $table_apps a
            LEFT JOIN $users u ON a.user_id = u.ID
            LEFT JOIN $posts p ON a.course_id = p.ID
            $where_sql
        ");
        
        $items = $wpdb->get_results($wpdb->prepare("
            SELECT a.*, u.display_name, u.user_email, p.post_title as course_title
            FROM $table_apps a
            LEFT JOIN $users u ON a.user_id = u.ID
            LEFT JOIN $posts p ON a.course_id = p.ID
            $where_sql
            ORDER BY FIELD(a.status, 'pending', 'approved', 'rejected'), a.applied_at ASC
            LIMIT %d OFFSET %d
        ", $limit, $offset));

        $total_pages = ceil($total_items / $limit);
        $base_url = menu_page_url('kr-lms-applications', false);
        ?>
        <div class="wrap cb-wrapper">
             <div class="cb-header">
                <div>
                    <h1 class="wp-heading-inline">Certificate Applications</h1>
                    <p class="cb-subtitle">Review and manage student applications.</p>
                </div>
            </div>

            <div class="cb-toolbar">
                <form method="get" action="">
                    <input type="hidden" name="page" value="kr-lms-applications">
                    <div class="cb-search-box">
                        <span class="dashicons dashicons-search cb-search-icon"></span>
                        <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search student or course..." class="cb-search-input">
                        <button type="submit" class="cb-search-btn">Search</button>
                    </div>
                </form>
            </div>
            
            <div class="cb-card">
                 <?php if (empty($items)): ?>
                    <div class="cb-empty-state">
                        <span class="dashicons dashicons-clipboard" style="font-size:48px; color:#cbd5e1;"></span>
                        <h3>No applications found.</h3>
                        <?php if ($search): ?>
                             <a href="<?php echo $base_url; ?>" class="cb-btn cb-btn-outline">Clear Search</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <table class="cb-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Course</th>
                                <th>Data</th>
                                <th>Status</th>
                                <th>Applied At</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $item): 
                            $app_data = json_decode($item->application_data, true) ?: [];
                            $has_data = !empty($app_data);
                            $json_attr = esc_attr(json_encode($app_data));
                        ?>
                            <tr>
                                <td>
                                    <div class="cb-user-cell">
                                        <div class="cb-avatar"><?php echo strtoupper(substr($item->display_name, 0, 1)); ?></div>
                                        <div>
                                            <div class="cb-user-name"><?php echo esc_html($item->display_name); ?></div>
                                            <div class="cb-user-email"><?php echo esc_html($item->user_email); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo esc_html($item->course_title); ?></td>
                                <td>
                                    <?php if($has_data): ?>
                                        <button type="button" class="button button-small app-view-btn" 
                                            data-father="<?php echo esc_attr($app_data['father_name'] ?? ''); ?>"
                                            data-mother="<?php echo esc_attr($app_data['mother_name'] ?? ''); ?>"
                                            data-start="<?php echo esc_attr($app_data['start_date'] ?? ''); ?>"
                                            data-end="<?php echo esc_attr($app_data['end_date'] ?? ''); ?>"
                                            data-url="<?php echo esc_url($app_data['project_url'] ?? ''); ?>"
                                        >
                                            <span class="dashicons dashicons-visibility" style="margin-top:3px; font-size:16px;"></span> View Details
                                        </button>
                                    <?php else: ?>
                                        <span style="color:#ccc;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $color = 'gray';
                                    if($item->status=='approved') $color='green';
                                    if($item->status=='rejected') $color='red';
                                    if($item->status=='pending') $color='#d97706';
                                    ?>
                                    <span class="cb-badge" style="background:<?php echo $color; ?>; color:#fff; text-transform:capitalize;">
                                        <?php echo $item->status; ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($item->applied_at)); ?></td>
                                <td style="text-align:right;">
                                    <div style="display:flex; gap:5px; justify-content:flex-end; align-items:center;">
                                        
                                        <?php if($item->status == 'pending' || $item->status == 'rejected'): ?>
                                            <button type="button" class="button button-primary app-approve-btn"
                                                data-appid="<?php echo $item->id; ?>"
                                                data-uid="<?php echo $item->user_id; ?>"
                                                data-uname="<?php echo esc_attr($item->display_name); ?>"
                                                data-cid="<?php echo $item->course_id; ?>"
                                                data-cname="<?php echo esc_attr($item->course_title); ?>"
                                                
                                                data-father="<?php echo esc_attr($app_data['father_name'] ?? ''); ?>"
                                                data-mother="<?php echo esc_attr($app_data['mother_name'] ?? ''); ?>"
                                                data-start="<?php echo esc_attr($app_data['start_date'] ?? ''); ?>"
                                                data-end="<?php echo esc_attr($app_data['end_date'] ?? ''); ?>"
                                                data-url="<?php echo esc_url($app_data['project_url'] ?? ''); ?>"
                                            >Approve</button>
                                        <?php endif; ?>

                                        <?php if($item->status == 'pending'): ?>
                                            <a href="<?php echo wp_nonce_url(add_query_arg(['action'=>'kr_reject', 'id'=>$item->id]), 'kr_reject_'.$item->id); ?>" 
                                            class="button button-secondary" 
                                            onclick="return confirm('Reject this application?');" 
                                            style="color:#d63638; border-color:#d63638;">Reject</a>
                                        <?php endif; ?>

                                        <!-- Delete Action (Always available) -->
                                        <button type="button" class="button button-link app-delete-btn" 
                                            data-id="<?php echo $item->id; ?>" 
                                            style="color:#a0a0a0; margin-left:5px;" title="Delete Application">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>

                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
             <div class="cb-footer">
                <div class="cb-stats">
                    Showing <strong><?php echo count($items); ?></strong> of <strong><?php echo $total_items; ?></strong>
                </div>
                <?php if ($total_pages > 1): ?>
                    <div class="cb-pagination">
                        <?php echo paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'total' => $total_pages,
                            'current' => $paged
                        ]); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- MODAL 1: VIEW DETAILS -->
        <div id="app-view-modal" style="display:none;">
            <div class="cb-modal-backdrop"></div>
            <div class="cb-modal-content" style="max-width:500px;">
                <div class="cb-modal-header">
                    <h2>Application Details</h2>
                    <button id="app-view-close" class="cb-close-icon">&times;</button>
                </div>
                <div class="cb-modal-body" id="app-view-content" style="font-size:14px; line-height:1.6;">
                    <!-- Content via JS -->
                </div>
            </div>
        </div>

        <!-- MODAL 2: ISSUE CERTIFICATE (APPROVE) -->
        <div id="app-issue-modal" style="display:none;">
            <div class="cb-modal-backdrop"></div>
            <div class="cb-modal-content">
                <div class="cb-modal-header">
                    <h2>Issue Certificate</h2>
                    <button id="issue-close" class="cb-close-icon">&times;</button>
                </div>
                <div class="cb-modal-body">
                    <p style="margin-bottom:20px; color:#666;">Review details before approving the application.</p>
                    
                    <input type="hidden" id="issue-app-id">
                    <input type="hidden" id="issue-user-id">
                    <input type="hidden" id="issue-course-id">

                    <div class="cb-grid-2">
                        <div class="cb-form-group cb-full-width">
                            <label>Student</label>
                            <input type="text" id="issue-user-display" class="cb-input" readonly style="background:#f0f0f1;">
                        </div>
                        <div class="cb-form-group cb-full-width">
                            <label>Course</label>
                            <input type="text" id="issue-course-display" class="cb-input" readonly style="background:#f0f0f1;">
                        </div>

                        <div class="cb-form-group">
                            <label>Father's Name</label>
                            <input type="text" id="issue-father" class="cb-input">
                        </div>
                        <div class="cb-form-group">
                            <label>Mother's Name</label>
                            <input type="text" id="issue-mother" class="cb-input">
                        </div>

                        <div class="cb-form-group">
                            <label>Batch</label>
                            <input type="text" id="issue-batch" class="cb-input" value="Batch 1">
                        </div>
                        <div class="cb-form-group">
                            <label>Grade</label>
                            <input type="text" id="issue-grade" class="cb-input" value="A">
                        </div>

                        <div class="cb-grid-2 cb-full-width" style="grid-column: 1 / -1; display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                            <div class="cb-form-group">
                                <label>Start Date</label>
                                <input type="date" id="issue-start" class="cb-input">
                            </div>
                            <div class="cb-form-group">
                                <label>End Date</label>
                                <input type="date" id="issue-end" class="cb-input">
                            </div>
                        </div>

                        <div class="cb-form-group cb-full-width">
                            <label>Project / Portfolio URL</label>
                            <input type="url" id="issue-url" class="cb-input">
                        </div>
                    </div>
                </div>
                <div class="cb-modal-footer">
                    <button id="issue-cancel" class="cb-btn cb-btn-outline">Cancel</button>
                    <button id="issue-save" class="cb-btn cb-btn-primary">Generate & Approve</button>
                </div>
            </div>
        </div>
        <?php
    }

    public function page_help() {
        ?>
        <div class="wrap cb-wrapper">
            <div class="cb-header">
                <h2>Shortcode Guide</h2>
            </div>
            
            <div class="cb-card" style="padding: 0; max-width: 900px; overflow: hidden;">
                <!-- Tabs -->
                <div style="display:flex; border-bottom:1px solid #e2e8f0; background:#f8fafc;">
                    <div class="kr-tab active" onclick="openTab(event, 'tab-leaderboard')" style="padding:15px 25px; cursor:pointer; font-weight:600; color:#334155; border-bottom:2px solid transparent;">Leaderboard</div>
                    <div class="kr-tab" onclick="openTab(event, 'tab-certificate')" style="padding:15px 25px; cursor:pointer; font-weight:600; color:#64748b; border-bottom:2px solid transparent;">Certificates</div>
                </div>

                <!-- Tab Content: Leaderboard -->
                <div id="tab-leaderboard" class="kr-tab-content" style="padding:40px;">
                    <h3 style="margin-top:0;">Leaderboard System</h3>
                    <p class="cb-subtitle" style="margin-bottom: 30px;">
                        The <code>[kr_leaderboard]</code> shortcode allows you to display student rankings.
                    </p>

                    <div style="background:#f0f9ff; border:1px solid #e0f2fe; padding:20px; border-radius:8px; margin-bottom:30px;">
                        <h4 style="margin-top:0; color:#0369a1;">1. Automatic Display</h4>
                        <p style="margin-bottom:0; color:#0c4a6e;">
                            Users do not need to do anything for Courses. The leaderboard for a specific course is 
                            automatically displayed at the bottom of every <strong>Single Course Page</strong>.
                        </p>
                    </div>

                    <h4 style="margin-bottom:10px;">2. Global Leaderboard (Hall of Fame)</h4>
                    <p>Show top students across <strong>ALL</strong> courses (Total Points):</p>
                    <code style="display:block; background:#1e293b; color:#fff; padding:15px; border-radius:6px; margin:10px 0;">[kr_leaderboard type="global" title="Top Students of All Time"]</code>

                    <h4 style="margin-top:30px; margin-bottom:10px;">3. Specific Course Leaderboard</h4>
                    <p>Show a specific course's ranking on a custom page:</p>
                    <code style="display:block; background:#1e293b; color:#fff; padding:15px; border-radius:6px; margin:10px 0;">[kr_leaderboard course_id="123"]</code>
                </div>

                <!-- Tab Content: Certificate -->
                <div id="tab-certificate" class="kr-tab-content" style="display:none; padding:40px;">
                    <h3 style="margin-top:0;">Certificate Verification</h3>
                    <p class="cb-subtitle" style="margin-bottom: 30px;">
                        Allow users to search and download their certificates publicly.
                    </p>

                    <div style="background:#f0fdf4; border:1px solid #dcfce7; padding:20px; border-radius:8px; margin-bottom:30px;">
                        <h4 style="margin-top:0; color:#15803d;">Public Search Form</h4>
                        <p style="margin-bottom:0; color:#14532d;">
                            This shortcode displays a search box where users can enter their <strong>Email Address</strong>.
                            If found, they can instantly download their certificates as PDF.
                        </p>
                    </div>

                    <h4 style="margin-bottom:10px;">Usage</h4>
                    <p>Paste this shortcode on any public page (e.g. "Verify Certificate"):</p>
                    <code style="display:block; background:#1e293b; color:#fff; padding:15px; border-radius:6px; margin:10px 0;">[kr_certificate_search]</code>
                </div>
            </div>
        </div>

        <script>
        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("kr-tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
            }
            tablinks = document.getElementsByClassName("kr-tab");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].style.color = "#64748b";
                tablinks[i].style.borderBottomColor = "transparent";
            }
            document.getElementById(tabName).style.display = "block";
            evt.currentTarget.style.color = "#334155";
            evt.currentTarget.style.borderBottomColor = "#2563eb";
        }
        // Initialize style for active tab (JS fallback if CSS classes aren't enough)
        document.querySelector('.kr-tab.active').style.color = "#334155";
        document.querySelector('.kr-tab.active').style.borderBottomColor = "#2563eb";
        </script>
        <?php
    }
}
