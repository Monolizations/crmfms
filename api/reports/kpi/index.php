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

// Mock KPI data for dashboard
$kpiData = [
    'dept_present' => 15,
    'class_present' => 42,
    'missed_timeouts' => 3,
    'pending_leaves' => 7
];

echo json_encode($kpiData);
?>
