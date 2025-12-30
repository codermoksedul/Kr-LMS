<?php
// Standalone debug script for KR LMS Font Loading
// No WP dependency needed for pure GD test

$baseDir = __DIR__; // c:\Users\moksedul\Local Sites\sayeedfahadcom\app\public\wp-content\plugins\kr-lms
$fontDir = $baseDir . '/assets/img';
// Ensure forward slashes
$fontDir = str_replace('\\', '/', $fontDir);
$fontPath = $fontDir . '/certificate-font.ttf';
$fontPathBold = $fontDir . '/certificate-font-bold.ttf';

header('Content-Type: text/plain');

echo "PHP Version: " . phpversion() . "\n";
echo "GD Info:\n";
print_r(gd_info());

echo "\nChecking Font Paths:\n";
echo "Font Dir: " . $fontDir . "\n";
echo "Regular Font: " . $fontPath . " | Exists: " . (file_exists($fontPath) ? 'YES' : 'NO') . "\n";
echo "Bold Font:    " . $fontPathBold . " | Exists: " . (file_exists($fontPathBold) ? 'YES' : 'NO') . "\n";

echo "\nTesting imagettftext:\n";
$im = imagecreatetruecolor(500, 200);
$white = imagecolorallocate($im, 255, 255, 255);
$black = imagecolorallocate($im, 0, 0, 0);
imagefilledrectangle($im, 0, 0, 499, 199, $white);

// TEST 1: Absolute Path
echo "Test 1 (Absolute Path): ";
try {
    $bbox = imagettftext($im, 20, 0, 10, 50, $black, $fontPath, "Test Absolute");
    if ($bbox) echo "SUCCESS\n";
    else echo "FAILED (returned false)\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// TEST 2: Realpath
echo "Test 2 (Realpath): ";
$realPath = realpath($fontPath);
echo "($realPath) ";
try {
    $bbox = imagettftext($im, 20, 0, 10, 100, $black, $realPath, "Test Realpath");
    if ($bbox) echo "SUCCESS\n";
    else echo "FAILED\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// TEST 3: System Arial Bold
echo "Test 3 (System Arial Bold): ";
try {
    $bbox = imagettftext($im, 20, 0, 10, 150, $black, 'C:/Windows/Fonts/arialbd.ttf', "Test Arial Bold");
    if ($bbox) echo "SUCCESS\n";
    else echo "FAILED\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// TEST 4: Copy to Temp (Skipped)
echo "Test 4 (System Arial Regular): ";
try {
    $bbox = imagettftext($im, 20, 0, 10, 180, $black, 'C:/Windows/Fonts/arial.ttf', "Test Arial Reg");
    if ($bbox) echo "SUCCESS\n";
    else echo "FAILED\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

imagedestroy($im);
echo "\nDone.\n";
