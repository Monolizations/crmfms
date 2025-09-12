<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Mock data for suggestions
$suggestionsData = [
    'items' => [
        [
            'building' => 'Building A',
            'note' => 'Consider adding more QR codes for better coverage'
        ],
        [
            'building' => 'Building B',
            'note' => 'Peak hours: 9-11 AM, consider additional staff'
        ],
        [
            'building' => 'Main Campus',
            'note' => 'Weather alert: Heavy rain expected, check outdoor facilities'
        ]
    ]
];

echo json_encode($suggestionsData);
?>
