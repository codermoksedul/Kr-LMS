<?php
if (!defined('ABSPATH')) exit;

class KR_LMS_Generator {

    public function __construct() {
        add_action('admin_post_cb_view_certificate', [$this, 'view_page']);
        add_action('admin_post_cb_download_certificate_png', [$this, 'generate_png']);
        add_action('admin_post_nopriv_cb_download_certificate_png', [$this, 'generate_png']);
    }

    public function view_page() {
        if (!current_user_can('manage_options')) wp_die('Permission denied');
        
        $id = intval($_GET['id']);
        check_admin_referer('cb_view_' . $id);
        
        // URLs for PNG (Preview) and PDF (Download)
        $base_url = admin_url('admin-post.php?action=cb_download_certificate_png&id=' . $id . '&_wpnonce=' . wp_create_nonce('cb_download_' . $id));
        $img_url = $base_url . '&format=png';
        $pdf_url = $base_url . '&format=pdf';
        
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Certificate Preview</title>
            <style>
                body {
                    margin: 0;
                    padding: 20px;
                    background: #f0f0f1;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    min-height: 100vh;
                }
                .toolbar {
                    margin-bottom: 20px;
                    display: flex;
                    gap: 15px;
                    background: white;
                    padding: 15px 25px;
                    border-radius: 8px;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                }
                .btn {
                    text-decoration: none;
                    padding: 10px 20px;
                    border-radius: 5px;
                    font-weight: 500;
                    font-size: 14px;
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    cursor: pointer;
                    border: none;
                    transition: all 0.2s;
                }
                .btn-pdf {
                    background-color: #2271b1;
                    color: white;
                }
                .btn-pdf:hover {
                    background-color: #135e96;
                }
                .btn-close {
                    background-color: #d63638;
                    color: white;
                }
                .btn-close:hover {
                    background-color: #a32b2d;
                }
                .preview-container {
                    max-width: 100%;
                    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                    background: white;
                }
                .cert-image {
                    display: block;
                    max-width: 100%;
                    height: auto;
                    max-height: 85vh;
                }
            </style>
        </head>
        <body>
            <div class="toolbar">
                <a href="<?php echo esc_url($pdf_url); ?>" download="Certificate-<?php echo $id; ?>.pdf" class="btn btn-pdf">
                    ⬇️ Download PDF
                </a>
                <!-- Optional PNG Download -->
                <a href="<?php echo esc_url($img_url); ?>" download="Certificate-<?php echo $id; ?>.png" class="btn" style="background:#eee; color:#333;">
                    ⬇️ PNG
                </a>
                <button onclick="window.close()" class="btn btn-close">
                    ✕ Close
                </button>
            </div>
            
            <div class="preview-container">
                <img src="<?php echo esc_url($img_url); ?>" alt="Certificate Preview" class="cert-image">
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    public function generate_png() {
        $id = intval($_GET['id']);
        $nonce = isset($_REQUEST['_wpnonce']) ? $_REQUEST['_wpnonce'] : '';
        
        // Security Check: Allow Admin OR Public with Search Token
        // Note: check_admin_referer dies on failure, so we use wp_verify_nonce
        $isAdmin  = current_user_can('manage_options') && wp_verify_nonce($nonce, 'cb_download_' . $id);
        $isPublic = wp_verify_nonce($nonce, 'kr_cert_dl_' . $id);

        if (!$isAdmin && !$isPublic) {
            wp_die('Permission denied or link expired.');
        }

        global $wpdb;
        $cert = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}certificates WHERE id = %d", $id));
        if (!$cert) wp_die('Not found');

        // Load the background template
        $bgPath = KR_LMS_PATH . 'certificate-bg.png';
        
        if (!file_exists($bgPath)) {
            wp_die('Certificate background not found');
        }
        
        $im = @imagecreatefrompng($bgPath);
        if ($im === false) {
            wp_die('Failed to load certificate background');
        }
        
        // Force True Color to ensure text colors work correctly
        if (!imageistruecolor($im)) {
            $temp = imagecreatetruecolor($width, $height);
            imagecopy($temp, $im, 0, 0, 0, 0, $width, $height);
            imagedestroy($im);
            $im = $temp;
        }
        
        imagealphablending($im, true);
        imagesavealpha($im, true);
        
        // Define colors
        $black = imagecolorallocate($im, 17, 24, 39);
        $gray = imagecolorallocate($im, 102, 102, 102);
        $royalBlue = imagecolorallocate($im, 30, 94, 235);
        $red = imagecolorallocate($im, 255, 0, 0); // For error/fallback
        
        // Get certificate data
        $user = get_userdata($cert->user_id);
        $course = get_the_title($cert->course_id);
        $meta = json_decode($cert->meta_json, true) ?: [];
        
        $student_name = $user ? $user->display_name : 'Student Name';
        $course_name = $course ?: 'Course Name';
        $father = isset($meta['father_name']) && $meta['father_name'] ? $meta['father_name'] : 'Md Mollbor Rahman';
        $mother = isset($meta['mother_name']) && $meta['mother_name'] ? $meta['mother_name'] : 'Afiya Khanomhas';
        $batch = isset($meta['batch']) ? $meta['batch'] : 'Batch 2';
        $date_range = isset($meta['date_range']) ? $meta['date_range'] : '28 May, 2024 to 05 December, 2024';
        $grade = isset($meta['grade']) ? $meta['grade'] : 'A';
        
        // Font paths - Fix for Windows/GD
        // Using System Fonts because custom fonts are incompatible with this PHP/GD setup
        $fontRegular = 'C:/Windows/Fonts/arial.ttf';
        $fontBold    = 'C:/Windows/Fonts/arialbd.ttf';
        
        // Helper function to draw text with fallback
        $drawText = function($im, $size, $x, $y, $color, $font, $text) use ($red) {
            // Check if font exists
            if (file_exists($font)) {
                try {
                    $bbox = @imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
                    if ($bbox !== false) return;
                } catch (Exception $e) {
                    error_log("KR LMS Font Exception: " . $e->getMessage());
                }
            }
            
            // Fallback to built-in font (1-5) if TTF fails
            $builtinFont = 5; 
            $y_adj = $y - ($size * 1.2); 
            imagestring($im, $builtinFont, $x, $y_adj, $text, $color);
        };
        
        // Text positioning - Calibrated for 3508x2480px A4 Landscape
        // Blue bar width is approx 15% -> start text at ~28%
        // Adjusted left to 950 to align with "This is certify that" line
        $startX = 950; 
        $startY = 600;
        
        // 1. "This is certify that" - Removed (in BG)
        
        // 3. Student Name - Large Blue
        // SWITCHED TO REGULAR FONT for "Semi-Bold" look (Bold was too thick)
        $drawText($im, 100, $startX, $startY + 150, $royalBlue, $fontRegular, $student_name);
        
        // 4. Parent details
        // Increased font size to 40pt as requested
        $parentText = "Son of " . $father . " & " . $mother;
        $drawText($im, 40, $startX, $startY + 280, $gray, $fontRegular, $parentText);
        $drawText($im, 40, $startX, $startY + 340, $gray, $fontRegular, "successfully completed the");
        
        // 5. Course Name - Large Black Bold
        $courseDisplay = $course_name;
        if (strpos($courseDisplay, $batch) === false) {
             $courseDisplay .= " " . $batch;
        }

        $fontSize = 55;
        if (strlen($courseDisplay) > 50) $fontSize = 45;
        
        // Moved down to +700 (from 650) - "More Down"
        $drawText($im, $fontSize, $startX, $startY + 850, $black, $fontBold, $courseDisplay);
        
        // 6. Grade details
        // Gap reduced (110px diff vs 150px previously) - "Text gap decrease"
        $gradeText = "with grade " . $grade . " held on " . $date_range;
        $drawText($im, 40, $startX, $startY + 950, $gray, $fontRegular, $gradeText);
        
        // 7. Center location
        // Tight gap maintained
        $drawText($im, 40, $startX, $startY + 1020, $gray, $fontRegular, "at Visual Center");

        // Output
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Capture GD output to buffer
        ob_start();
        imagepng($im, null, 6);
        $pngData = ob_get_clean();
        imagedestroy($im);

        $filenameBase = 'Certificate-' . sanitize_file_name($student_name);
        $format = isset($_GET['format']) ? $_GET['format'] : 'png';
        
        // Try PDF generation via Imagick ONLY if requested
        if ($format === 'pdf' && class_exists('Imagick')) {
            try {
                $pdf = new Imagick();
                $pdf->readImageBlob($pngData);
                $pdf->setImageFormat('pdf');
                
                // Set page size to A4 Landscape (roughly matching our ratio)
                // 3508x2480 matches A4 @ 300dpi directly
                
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filenameBase . '.pdf"');
                header('Cache-Control: no-cache, must-revalidate');
                header('Expires: 0');
                
                echo $pdf->getImageBlob();
                $pdf->clear();
                $pdf->destroy();
                exit;
                
            } catch (Exception $e) {
                error_log("KR LMS PDF Generation Failed: " . $e->getMessage());
                // Fallback to PNG below if PDF fails
            }
        }
        
        // Default: PNG output (for preview or fallback)
        header('Content-Type: image/png');
        if ($format === 'pdf') {
             // If they asked for PDF but we failed, force download as PNG
             header('Content-Disposition: attachment; filename="' . $filenameBase . '.png"');
        } else {
             // For preview/img tag, inline is better, or attachment if direct
             // Let's use inline for preview usage (format=png)
             header('Content-Disposition: inline; filename="' . $filenameBase . '.png"');
        }
        
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        echo $pngData;
        exit;
    }
}
