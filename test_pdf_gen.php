<?php
// Test actual PDF generation
if (!class_exists('Imagick')) {
    die("Imagick Missing");
}

try {
    $im = new Imagick();
    $im->newImage(100, 100, "white");
    $im->setImageFormat("png");
    
    // Convert to PDF
    $pdf = new Imagick();
    $pdf->readImageBlob($im->getImageBlob());
    $pdf->setImageFormat("pdf");
    
    // Setup for output
    $blob = $pdf->getImageBlob();
    if (strlen($blob) > 0 && strpos($blob, '%PDF') === 0) {
        echo "SUCCESS: PDF Generated (" . strlen($blob) . " bytes)";
    } else {
        echo "FAILURE: Output is not PDF";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
