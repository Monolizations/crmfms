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

// Mock data for alerts
$alertsData = [
    'items' => [
        [
            'message' => 'Late check-in detected for Room 205'
        ],
        [
            'message' => 'Unusual activity in Building A'
        ],
        [
            'message' => 'System maintenance scheduled for tonight'
        ]
    ]
];

echo json_encode($alertsData);
?>
