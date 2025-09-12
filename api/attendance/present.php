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

// Mock data for present attendance
$presentData = [
    'items' => [
        [
            'name' => 'John Smith',
            'location_type' => 'Classroom',
            'location_label' => 'Room 101',
            'time_in_human' => '8:30 AM',
            'status' => 'Present'
        ],
        [
            'name' => 'Emily Davis',
            'location_type' => 'Office',
            'location_label' => 'Admin Office',
            'time_in_human' => '9:00 AM',
            'status' => 'Present'
        ],
        [
            'name' => 'Admin User',
            'location_type' => 'Office',
            'location_label' => 'Main Office',
            'time_in_human' => '8:00 AM',
            'status' => 'Present'
        ]
    ]
];

echo json_encode($presentData);
?>
