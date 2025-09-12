<?php
// /api/qr/generate.php
// This script generates a QR code image based on the 'data' GET parameter.
// Uses a local QR code generation implementation.

// Include the simple QR code generator
require_once __DIR__ . '/simple_qr.php';

// Get the data parameter
$data = $_GET['data'] ?? '';

if (empty($data)) {
    http_response_code(400);
    echo "Error: 'data' parameter is missing.";
    exit;
}

// Optional parameters with defaults
$size = isset($_GET['size']) ? (int)$_GET['size'] : 200;
$margin = isset($_GET['margin']) ? (int)$_GET['margin'] : 10;

// Validate size and margin
$size = max(100, min(600, $size)); // Limit between 100-600
$margin = max(5, min(50, $margin)); // Limit between 5-50

try {
    // Create and output QR code
    $qr = new SimpleQR($data, $size, $margin);
    $qr->output('png');
} catch (Exception $e) {
    // Fallback: Create a simple error image
    header('Content-Type: image/png');
    $im = imagecreatetruecolor($size, $size);
    $bg = imagecolorallocate($im, 255, 255, 255);
    $text_color = imagecolorallocate($im, 0, 0, 0);
    imagefill($im, 0, 0, $bg);
    
    // Center the error text
    $text = 'QR Code Error';
    $font = 3;
    $textWidth = imagefontwidth($font) * strlen($text);
    $textHeight = imagefontheight($font);
    $x = ($size - $textWidth) / 2;
    $y = ($size - $textHeight) / 2;
    
    imagestring($im, $font, $x, $y, $text, $text_color);
    imagestring($im, 2, $x, $y + 20, 'Unable to generate', $text_color);
    
    imagepng($im);
    imagedestroy($im);
}
