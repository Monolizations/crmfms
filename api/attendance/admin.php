<?php
// /api/attendance/admin.php
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

try {
    requireAuth(['admin']); // Only admins can access this
    $db = (new Database())->getConnection();

    $action = $_GET['action'] ?? 'list';

    switch ($action) {
        case 'list':
            // Get all attendance records with pagination and filters
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 50);
            $offset = ($page - 1) * $limit;

            $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $_GET['end_date'] ?? date('Y-m-d');
            $userId = $_GET['user_id'] ?? null;
            $status = $_GET['status'] ?? null; // 'present', 'checked_out', 'all'

            $whereConditions = ["DATE(a.check_in_time) BETWEEN :start AND :end"];
            $params = [':start' => $startDate, ':end' => $endDate];

            if ($userId) {
                $whereConditions[] = "a.user_id = :user_id";
                $params[':user_id'] = $userId;
            }

            if ($status === 'present') {
                $whereConditions[] = "a.check_out_time IS NULL";
            } elseif ($status === 'checked_out') {
                $whereConditions[] = "a.check_out_time IS NOT NULL";
            }

            $whereClause = implode(' AND ', $whereConditions);

            // Get total count
            $countStmt = $db->prepare("
                SELECT COUNT(*) as total
                FROM attendance a
                JOIN users u ON a.user_id = u.user_id
                WHERE $whereClause AND u.status = 'active'
            ");
            $countStmt->execute($params);
            $countResult = $countStmt->fetch();
            $total = $countResult ? (int)$countResult['total'] : 0;

            // Get records
            $stmt = $db->prepare("
                SELECT
                    a.attendance_id,
                    a.user_id,
                    a.room_id,
                    a.check_in_time,
                    a.check_out_time,
                    a.scan_timestamp,
                    a.server_timestamp,
                    DATE(a.scan_timestamp) as scan_date,
                    a.latitude,
                    a.longitude,
                    u.first_name,
                    u.last_name,
                    u.employee_id,
                    r.name as room_name
                FROM attendance a
                JOIN users u ON a.user_id = u.user_id
                LEFT JOIN rooms r ON a.room_id = r.room_id
                WHERE $whereClause AND u.status = 'active'
                ORDER BY a.check_in_time DESC
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute($params);
            $records = $stmt->fetchAll();

            $items = [];
            foreach ($records as $record) {
                $checkInTime = strtotime($record['check_in_time']);
                $checkOutTime = $record['check_out_time'] ? strtotime($record['check_out_time']) : null;

                $hoursWorked = null;
                if ($checkOutTime) {
                    $diff = $checkOutTime - $checkInTime;
                    $hours = floor($diff / 3600);
                    $minutes = floor(($diff % 3600) / 60);
                    $hoursWorked = "{$hours}h {$minutes}m";
                }

        $location = 'Department';
        if ($record['room_id'] && isset($record['room_name']) && $record['room_name']) {
            $location = "Room {$record['room_name']}";
        }

                $items[] = [
                    'attendance_id' => $record['attendance_id'],
                    'user_id' => $record['user_id'],
                    'employee_id' => $record['employee_id'],
                    'name' => $record['first_name'] . ' ' . $record['last_name'],
                    'department' => 'N/A', // Departments table not implemented
                    'location' => $location,
                    'check_in_time' => date('Y-m-d H:i:s', $checkInTime),
                    'check_out_time' => $checkOutTime ? date('Y-m-d H:i:s', $checkOutTime) : null,
                    'scan_timestamp' => $record['scan_timestamp'] ? date('Y-m-d H:i:s', strtotime($record['scan_timestamp'])) : null,
                    'scan_date' => $record['scan_date'],
                    'server_timestamp' => $record['server_timestamp'] ? date('Y-m-d H:i:s', strtotime($record['server_timestamp'])) : null,
                    'hours_worked' => $hoursWorked,
                    'status' => $checkOutTime ? 'Checked Out' : 'Present',
                    'latitude' => $record['latitude'],
                    'longitude' => $record['longitude']
                ];
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'items' => $items,
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => ceil($total / $limit)
                ]
            ]);
            break;

        case 'stats':
            // Get attendance statistics
            $today = date('Y-m-d');

            $stmt = $db->prepare("
                SELECT
                    COUNT(DISTINCT CASE WHEN DATE(a.check_in_time) = :today THEN a.user_id END) as present_today,
                    COUNT(DISTINCT CASE WHEN DATE(a.check_in_time) = :today AND a.check_out_time IS NULL THEN a.user_id END) as currently_present,
                    COUNT(DISTINCT CASE WHEN DATE(a.check_in_time) = :today AND a.check_out_time IS NOT NULL THEN a.user_id END) as checked_out_today,
                    COUNT(DISTINCT u.user_id) as total_users
                FROM users u
                LEFT JOIN attendance a ON u.user_id = a.user_id AND DATE(a.check_in_time) = :today
                WHERE u.status = 'active'
            ");
            $stmt->execute([':today' => $today]);
            $stats = $stmt->fetch();

            echo json_encode([
                'success' => true,
                'data' => [
                    'present_today' => (int)$stats['present_today'],
                    'currently_present' => (int)$stats['currently_present'],
                    'checked_out_today' => (int)$stats['checked_out_today'],
                    'total_users' => (int)$stats['total_users']
                ]
            ]);
            break;

        case 'delete':
            // Delete an attendance record (admin only)
            $attendanceId = $_POST['attendance_id'] ?? null;

            if (!$attendanceId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Attendance ID required']);
                exit;
            }

            // Get attendance details before deletion for audit log
            $stmt = $db->prepare("SELECT a.*, u.first_name, u.last_name FROM attendance a JOIN users u ON a.user_id = u.user_id WHERE a.attendance_id = :id");
            $stmt->execute([':id' => $attendanceId]);
            $attendanceRecord = $stmt->fetch();

            if (!$attendanceRecord) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Attendance record not found']);
                exit;
            }

            $stmt = $db->prepare("DELETE FROM attendance WHERE attendance_id = :id");
            $stmt->execute([':id' => $attendanceId]);

            // Log the deletion
            $auditDetails = json_encode([
                'timestamp' => date('Y-m-d H:i:s'),
                'action' => 'ADMIN_ATTENDANCE_DELETE',
                'attendance_id' => $attendanceId,
                'user_name' => $attendanceRecord['first_name'] . ' ' . $attendanceRecord['last_name'],
                'check_in_time' => $attendanceRecord['check_in_time'],
                'check_out_time' => $attendanceRecord['check_out_time'],
                'room_id' => $attendanceRecord['room_id'],
                'method' => 'admin_web_interface'
            ]);

            $auditStmt = $db->prepare("INSERT INTO audit_trail (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $auditStmt->execute([$_SESSION['uid'], 'ADMIN_ATTENDANCE_DELETE', $auditDetails, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);

            echo json_encode(['success' => true, 'message' => 'Attendance record deleted']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    error_log('Admin attendance API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>