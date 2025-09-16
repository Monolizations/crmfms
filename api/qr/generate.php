<?php
// /api/qr/generate.php
// This script generates a QR code image based on the 'data' GET parameter.
// Uses the Endroid QR Code library for high-quality QR code generation.

// Include Composer autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Label\Font\OpenSans;
use Endroid\QrCode\Label\LabelAlignment;
use Endroid\QrCode\Label\Margin\Margin;

// Get the data parameter
$data = $_GET['data'] ?? '';

if (empty($data)) {
    http_response_code(400);
    echo "Error: 'data' parameter is missing.";
    exit;
}

// Optional parameters with defaults
$size = isset($_GET['size']) ? (int)$_GET['size'] : 300;
$margin = isset($_GET['margin']) ? (int)$_GET['margin'] : 10;

// Validate size and margin
$size = max(100, min(800, $size)); // Limit between 100-800
$margin = max(5, min(50, $margin)); // Limit between 5-50

try {
    // Create QR code instance with all parameters in constructor
    $qrCode = new QrCode(
        data: $data,
        errorCorrectionLevel: ErrorCorrectionLevel::High,
        size: $size,
        margin: $margin,
        roundBlockSizeMode: RoundBlockSizeMode::Margin,
        foregroundColor: new Color(0, 0, 0),
        backgroundColor: new Color(255, 255, 255)
    );

    // Try to parse data for room information to add label
    $label = null;
    $decoded = json_decode($data, true);
    if ($decoded && isset($decoded['type']) && $decoded['type'] === 'room') {
        // Add room information as label
        $roomLabel = $decoded['building'] . ' F' . $decoded['floor'] . ' ' . $decoded['room_code'];
        $font = new OpenSans(12);
        $label = new Label(
            text: $roomLabel,
            font: $font,
            alignment: LabelAlignment::Center,
            margin: new Margin(0, 0, 0, 0),
            textColor: new Color(0, 0, 0)
        );
    }

    // Create writer and output
    $writer = new PngWriter();
    $result = $writer->write($qrCode, null, $label);
    
    // Set headers and output
    header('Content-Type: ' . $result->getMimeType());
    echo $result->getString();
    
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
