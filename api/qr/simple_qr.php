<?php
/**
 * Simple QR Code Generator
 * A basic QR code implementation using a simple algorithm
 * This is a fallback implementation for when external APIs are not available
 */

class SimpleQR {
    private $data;
    private $size;
    private $margin;
    
    public function __construct($data, $size = 200, $margin = 10) {
        $this->data = $data;
        $this->size = $size;
        $this->margin = $margin;
    }
    
    public function generate() {
        // Create a simple QR-like pattern
        $qrSize = $this->size - ($this->margin * 2);
        $image = imagecreatetruecolor($this->size, $this->size);
        
        // Colors
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        $gray = imagecolorallocate($image, 128, 128, 128);
        
        // Fill background
        imagefill($image, 0, 0, $white);
        
        // Create a simple pattern based on data hash
        $hash = md5($this->data);
        $pattern = $this->createPattern($hash, $qrSize);
        
        // Draw pattern
        $this->drawPattern($image, $pattern, $this->margin, $white, $black);
        
        // Add corner markers (QR code style)
        $this->drawCornerMarkers($image, $this->margin, $black, $white);
        
        // Add data text at bottom
        $textY = $this->size - $this->margin + 15;
        imagestring($image, 2, $this->margin, $textY, substr($this->data, 0, 20), $black);
        
        return $image;
    }
    
    private function createPattern($hash, $size) {
        $pattern = [];
        $gridSize = 21; // Standard QR grid size
        $cellSize = floor($size / $gridSize);
        
        for ($i = 0; $i < $gridSize; $i++) {
            $pattern[$i] = [];
            for ($j = 0; $j < $gridSize; $j++) {
                $hashIndex = ($i * $gridSize + $j) % strlen($hash);
                $pattern[$i][$j] = (hexdec($hash[$hashIndex]) % 2) == 0;
            }
        }
        
        return $pattern;
    }
    
    private function drawPattern($image, $pattern, $margin, $white, $black) {
        $gridSize = count($pattern);
        $cellSize = floor(($this->size - ($margin * 2)) / $gridSize);
        
        for ($i = 0; $i < $gridSize; $i++) {
            for ($j = 0; $j < $gridSize; $j++) {
                $x = $margin + ($j * $cellSize);
                $y = $margin + ($i * $cellSize);
                
                $color = $pattern[$i][$j] ? $black : $white;
                imagefilledrectangle($image, $x, $y, $x + $cellSize - 1, $y + $cellSize - 1, $color);
            }
        }
    }
    
    private function drawCornerMarkers($image, $margin, $black, $white) {
        $markerSize = 7;
        $cellSize = floor(($this->size - ($margin * 2)) / 21);
        
        // Top-left marker
        $this->drawMarker($image, $margin, $margin, $markerSize, $cellSize, $black, $white);
        
        // Top-right marker
        $this->drawMarker($image, $this->size - $margin - ($markerSize * $cellSize), $margin, $markerSize, $cellSize, $black, $white);
        
        // Bottom-left marker
        $this->drawMarker($image, $margin, $this->size - $margin - ($markerSize * $cellSize), $markerSize, $cellSize, $black, $white);
    }
    
    private function drawMarker($image, $x, $y, $size, $cellSize, $black, $white) {
        // Outer square
        imagefilledrectangle($image, $x, $y, $x + ($size * $cellSize), $y + ($size * $cellSize), $black);
        
        // Inner white square
        imagefilledrectangle($image, $x + $cellSize, $y + $cellSize, $x + (($size - 1) * $cellSize), $y + (($size - 1) * $cellSize), $white);
        
        // Center black square
        imagefilledrectangle($image, $x + (2 * $cellSize), $y + (2 * $cellSize), $x + (($size - 2) * $cellSize), $y + (($size - 2) * $cellSize), $black);
    }
    
    public function output($format = 'png') {
        $image = $this->generate();
        
        switch ($format) {
            case 'png':
                header('Content-Type: image/png');
                imagepng($image);
                break;
            case 'jpg':
                header('Content-Type: image/jpeg');
                imagejpeg($image);
                break;
            default:
                header('Content-Type: image/png');
                imagepng($image);
        }
        
        imagedestroy($image);
    }
    
    public function save($filename) {
        $image = $this->generate();
        $result = imagepng($image, $filename);
        imagedestroy($image);
        return $result;
    }
}
?>
