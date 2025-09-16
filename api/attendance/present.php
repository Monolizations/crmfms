<?php
// /api/attendance/present.php
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $db = (new Database())->getConnection();

    // Get all users who are currently checked in (no check-out time) for today
    $stmt = $db->prepare("
        SELECT
            u.first_name,
            u.last_name,
            a.check_in_time,
            a.room_id,
            r.name as room_name,
            b.building_name,
            f.floor_number,
            CASE
                WHEN a.room_id IS NOT NULL THEN 'Classroom'
                ELSE 'Department'
            END as location_type,
            CASE
                WHEN a.room_id IS NOT NULL THEN CONCAT('Room ', r.name, ' (', b.building_name, ' Floor ', f.floor_number, ')')
                ELSE 'Department Office'
            END as location_label
        FROM attendance a
        JOIN users u ON a.user_id = u.user_id
        LEFT JOIN rooms r ON a.room_id = r.room_id
        LEFT JOIN floors f ON r.floor_id = f.floor_id
        LEFT JOIN buildings b ON f.building_id = b.building_id
        WHERE DATE(a.check_in_time) = CURDATE()
        AND a.check_out_time IS NULL
        AND u.status = 'active'
        ORDER BY a.check_in_time ASC
    ");

    $stmt->execute();
    $presentRecords = $stmt->fetchAll();

    $items = [];
    foreach ($presentRecords as $record) {
        $items[] = [
            'name' => $record['first_name'] . ' ' . $record['last_name'],
            'location_type' => $record['location_type'],
            'location_label' => $record['location_label'],
            'time_in_human' => date('g:i A', strtotime($record['check_in_time'])),
            'status' => 'Present'
        ];
    }

    echo json_encode(['items' => $items]);

} catch (Throwable $e) {
    error_log('Present attendance API Error: ' . $e->getMessage());
    // Return empty array on error to prevent dashboard crashes
    echo json_encode(['items' => []]);
}
?>
