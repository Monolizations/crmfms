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

require_once __DIR__ . '/../../config/database.php';

try {
    $db = (new Database())->getConnection();
    $suggestions = [];

    // 1. Buildings not monitored in the last 7 days
    $stmt = $db->prepare("
        SELECT b.name as building_name, b.building_id,
               MAX(mr.round_time) as last_monitoring
        FROM buildings b
        LEFT JOIN monitoring_rounds mr ON b.building_id = mr.building_id
        GROUP BY b.building_id, b.name
        HAVING last_monitoring IS NULL OR last_monitoring < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY last_monitoring ASC
        LIMIT 3
    ");
    $stmt->execute();
    $unmonitoredBuildings = $stmt->fetchAll();

    foreach ($unmonitoredBuildings as $building) {
        $daysSince = $building['last_monitoring']
            ? floor((strtotime('now') - strtotime($building['last_monitoring'])) / (60*60*24))
            : 'never';

        $suggestions[] = [
            'building' => $building['building_name'],
            'note' => "Not monitored for {$daysSince} days - consider scheduling a round",
            'type' => 'monitoring_gap',
            'priority' => 'high'
        ];
    }

    // 2. Active system alerts
    $stmt = $db->prepare("
        SELECT message, type, priority
        FROM system_alerts
        WHERE is_active = 1
        AND (expires_at IS NULL OR expires_at > NOW())
        ORDER BY priority DESC, created_at DESC
        LIMIT 3
    ");
    $stmt->execute();
    $activeAlerts = $stmt->fetchAll();

    foreach ($activeAlerts as $alert) {
        $suggestions[] = [
            'building' => 'System Wide',
            'note' => $alert['message'],
            'type' => 'system_alert',
            'priority' => $alert['priority'] == 3 ? 'high' : ($alert['priority'] == 2 ? 'medium' : 'low')
        ];
    }

    // 3. Peak attendance times analysis
    $stmt = $db->prepare("
        SELECT
            HOUR(a.check_in_time) as hour,
            COUNT(*) as checkin_count,
            b.name as building_name
        FROM attendance a
        JOIN rooms r ON a.room_id = r.room_id
        JOIN floors f ON r.floor_id = f.floor_id
        JOIN buildings b ON f.building_id = b.building_id
        WHERE DATE(a.check_in_time) = CURDATE()
        GROUP BY HOUR(a.check_in_time), b.name
        ORDER BY checkin_count DESC
        LIMIT 3
    ");
    $stmt->execute();
    $peakHours = $stmt->fetchAll();

    foreach ($peakHours as $peak) {
        if ($peak['checkin_count'] > 10) { // Only suggest for high activity
            $suggestions[] = [
                'building' => $peak['building_name'],
                'note' => "High activity at {$peak['hour']}:00 ({$peak['checkin_count']} check-ins) - consider monitoring",
                'type' => 'peak_activity',
                'priority' => 'medium'
            ];
        }
    }

    // 4. Rooms with frequent check-ins today
    $stmt = $db->prepare("
        SELECT
            r.name as room_name,
            b.name as building_name,
            COUNT(a.attendance_id) as activity_count
        FROM attendance a
        JOIN rooms r ON a.room_id = r.room_id
        JOIN floors f ON r.floor_id = f.floor_id
        JOIN buildings b ON f.building_id = b.building_id
        WHERE DATE(a.check_in_time) = CURDATE()
        GROUP BY r.room_id, r.name, b.name
        HAVING activity_count > 5
        ORDER BY activity_count DESC
        LIMIT 3
    ");
    $stmt->execute();
    $activeRooms = $stmt->fetchAll();

    foreach ($activeRooms as $room) {
        $suggestions[] = [
            'building' => $room['building_name'],
            'note' => "Room {$room['room_name']} has high activity ({$room['activity_count']} visits today)",
            'type' => 'room_activity',
            'priority' => 'medium'
        ];
    }

    // 5. Pending leave requests count
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_pending_leaves
        FROM leave_requests
        WHERE status = 'pending'
    ");
    $stmt->execute();
    $pendingLeaves = $stmt->fetch();

    if ($pendingLeaves['total_pending_leaves'] > 0) {
        $suggestions[] = [
            'building' => 'System Wide',
            'note' => "{$pendingLeaves['total_pending_leaves']} pending leave requests - review faculty availability",
            'type' => 'leave_requests',
            'priority' => 'low'
        ];
    }

    // If no automated suggestions, provide some general tips
    if (empty($suggestions)) {
        $suggestions = [
            [
                'building' => 'General',
                'note' => 'All buildings monitored recently - great job maintaining coverage!',
                'type' => 'general',
                'priority' => 'low'
            ],
            [
                'building' => 'General',
                'note' => 'Consider monitoring during peak hours (9-11 AM) for optimal coverage',
                'type' => 'general',
                'priority' => 'low'
            ]
        ];
    }

    // Limit to top 10 suggestions and sort by priority
    $priorityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
    usort($suggestions, function($a, $b) use ($priorityOrder) {
        return $priorityOrder[$b['priority']] <=> $priorityOrder[$a['priority']];
    });

    $suggestions = array_slice($suggestions, 0, 10);

    echo json_encode(['items' => $suggestions]);

} catch (Exception $e) {
    // Fallback to basic suggestions if database error
    $fallbackSuggestions = [
        'items' => [
            [
                'building' => 'System',
                'note' => 'Unable to load automated suggestions - check database connection',
                'type' => 'error',
                'priority' => 'high'
            ]
        ]
    ];
    echo json_encode($fallbackSuggestions);
}
?>
