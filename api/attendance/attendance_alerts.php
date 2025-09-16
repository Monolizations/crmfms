<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Require authentication
requireAuth();

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            // Get all attendance alerts
            getAttendanceAlerts($db);
            break;

        case 'active':
            // Get only active attendance alerts
            getActiveAttendanceAlerts($db);
            break;

        case 'acknowledge':
            // Acknowledge an alert
            acknowledgeAttendanceAlert($db);
            break;

        case 'resolve':
            // Resolve an alert
            resolveAttendanceAlert($db);
            break;

        case 'create':
            // Create new attendance alert (admin only)
            if (!hasRole(['admin', 'dean'])) {
                throw new Exception('Unauthorized access');
            }
            createAttendanceAlert($db);
            break;

        case 'stats':
            // Get alert statistics
            getAttendanceAlertStats($db);
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
            break;
    }
} catch (Exception $e) {
    error_log("Attendance Alerts API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function getAttendanceAlerts($db) {
    try {
        $status = $_GET['status'] ?? 'all';
        $limit = (int)($_GET['limit'] ?? 50);
        $limit = min($limit, 100); // Max 100 records

        $query = "
            SELECT
                aa.id,
                aa.alert_type,
                aa.severity,
                aa.message,
                aa.faculty_id,
                f.name as faculty_name,
                f.email as faculty_email,
                aa.room_id,
                r.room_number,
                b.name as building_name,
                aa.status,
                aa.triggered_at,
                aa.acknowledged_at,
                aa.resolved_at,
                aa.resolved_by,
                aa.notes,
                aa.metadata
            FROM attendance_alerts aa
            LEFT JOIN faculties f ON aa.faculty_id = f.id
            LEFT JOIN rooms r ON aa.room_id = r.id
            LEFT JOIN buildings b ON r.building_id = b.id
        ";

        $params = [];
        if ($status !== 'all') {
            $query .= " WHERE aa.status = :status";
            $params[':status'] = $status;
        }

        $query .= " ORDER BY aa.triggered_at DESC LIMIT :limit";

        $stmt = $db->prepare($query);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process alerts for better display
        $processedAlerts = [];
        foreach ($alerts as $alert) {
            $processedAlerts[] = processAlertData($alert);
        }

        echo json_encode([
            'success' => true,
            'data' => $processedAlerts
        ]);

    } catch (Exception $e) {
        throw new Exception('Failed to load attendance alerts: ' . $e->getMessage());
    }
}

function getActiveAttendanceAlerts($db) {
    try {
        $query = "
            SELECT
                aa.id,
                aa.alert_type,
                aa.severity,
                aa.message,
                aa.faculty_id,
                f.name as faculty_name,
                aa.room_id,
                r.room_number,
                b.name as building_name,
                aa.triggered_at,
                aa.metadata
            FROM attendance_alerts aa
            LEFT JOIN faculties f ON aa.faculty_id = f.id
            LEFT JOIN rooms r ON aa.room_id = r.id
            LEFT JOIN buildings b ON r.building_id = b.id
            WHERE aa.status = 'active'
            ORDER BY
                CASE aa.severity
                    WHEN 'critical' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'medium' THEN 3
                    WHEN 'low' THEN 4
                    ELSE 5
                END,
                aa.triggered_at DESC
        ";

        $stmt = $db->prepare($query);
        $stmt->execute();
        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $processedAlerts = [];
        foreach ($alerts as $alert) {
            $processedAlerts[] = processAlertData($alert);
        }

        echo json_encode([
            'success' => true,
            'data' => $processedAlerts
        ]);

    } catch (Exception $e) {
        throw new Exception('Failed to load active attendance alerts: ' . $e->getMessage());
    }
}

function processAlertData($alert) {
    return [
        'id' => $alert['id'],
        'alert_type' => $alert['alert_type'],
        'severity' => $alert['severity'],
        'message' => $alert['message'],
        'faculty_id' => $alert['faculty_id'],
        'faculty_name' => $alert['faculty_name'],
        'faculty_email' => $alert['faculty_email'],
        'room_id' => $alert['room_id'],
        'room_number' => $alert['room_number'],
        'building_name' => $alert['building_name'],
        'status' => $alert['status'] ?? 'active',
        'triggered_at' => $alert['triggered_at'],
        'acknowledged_at' => $alert['acknowledged_at'],
        'resolved_at' => $alert['resolved_at'],
        'resolved_by' => $alert['resolved_by'],
        'notes' => $alert['notes'],
        'metadata' => $alert['metadata'] ? json_decode($alert['metadata'], true) : null
    ];
}

function acknowledgeAttendanceAlert($db) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['alert_id'])) {
        throw new Exception('Alert ID is required');
    }

    $alertId = (int)$input['alert_id'];
    $userId = $_SESSION['user_id'] ?? null;

    try {
        // Check if alert exists and is active
        $checkQuery = "SELECT id, status FROM attendance_alerts WHERE id = :alert_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':alert_id', $alertId, PDO::PARAM_INT);
        $checkStmt->execute();
        $alert = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$alert) {
            throw new Exception('Alert not found');
        }

        if ($alert['status'] !== 'active') {
            throw new Exception('Alert is not active');
        }

        // Update alert status
        $query = "
            UPDATE attendance_alerts
            SET status = 'acknowledged',
                acknowledged_at = NOW(),
                acknowledged_by = :user_id
            WHERE id = :alert_id
        ";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':alert_id', $alertId, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // Log acknowledgment activity
        logAlertActivity($db, $alertId, 'acknowledged', 'Alert acknowledged by user', $userId);

        echo json_encode([
            'success' => true,
            'message' => 'Alert acknowledged successfully'
        ]);

    } catch (Exception $e) {
        throw new Exception('Failed to acknowledge alert: ' . $e->getMessage());
    }
}

function resolveAttendanceAlert($db) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['alert_id'])) {
        throw new Exception('Alert ID is required');
    }

    $alertId = (int)$input['alert_id'];
    $notes = $input['notes'] ?? null;
    $userId = $_SESSION['user_id'] ?? null;

    try {
        // Check if alert exists
        $checkQuery = "SELECT id, status FROM attendance_alerts WHERE id = :alert_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':alert_id', $alertId, PDO::PARAM_INT);
        $checkStmt->execute();
        $alert = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$alert) {
            throw new Exception('Alert not found');
        }

        // Update alert status
        $query = "
            UPDATE attendance_alerts
            SET status = 'resolved',
                resolved_at = NOW(),
                resolved_by = :user_id,
                notes = :notes
            WHERE id = :alert_id
        ";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':alert_id', $alertId, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':notes', $notes, PDO::PARAM_STR);
        $stmt->execute();

        // Log resolution activity
        logAlertActivity($db, $alertId, 'resolved', 'Alert resolved by user', $userId);

        echo json_encode([
            'success' => true,
            'message' => 'Alert resolved successfully'
        ]);

    } catch (Exception $e) {
        throw new Exception('Failed to resolve alert: ' . $e->getMessage());
    }
}

function createAttendanceAlert($db) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Invalid input data');
    }

    $alertType = $input['alert_type'] ?? '';
    $severity = $input['severity'] ?? 'medium';
    $message = $input['message'] ?? '';
    $facultyId = $input['faculty_id'] ?? null;
    $roomId = $input['room_id'] ?? null;
    $metadata = $input['metadata'] ?? null;

    // Validate required fields
    if (empty($alertType) || empty($message)) {
        throw new Exception('Alert type and message are required');
    }

    // Validate severity
    $validSeverities = ['low', 'medium', 'high', 'critical'];
    if (!in_array($severity, $validSeverities)) {
        throw new Exception('Invalid severity level');
    }

    try {
        $query = "
            INSERT INTO attendance_alerts
            (alert_type, severity, message, faculty_id, room_id, status, triggered_at, metadata)
            VALUES (:alert_type, :severity, :message, :faculty_id, :room_id, 'active', NOW(), :metadata)
        ";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':alert_type', $alertType, PDO::PARAM_STR);
        $stmt->bindParam(':severity', $severity, PDO::PARAM_STR);
        $stmt->bindParam(':message', $message, PDO::PARAM_STR);
        $stmt->bindParam(':faculty_id', $facultyId, PDO::PARAM_INT);
        $stmt->bindParam(':room_id', $roomId, PDO::PARAM_INT);
        $stmt->bindParam(':metadata', $metadata ? json_encode($metadata) : null, PDO::PARAM_STR);
        $stmt->execute();

        $alertId = $db->lastInsertId();

        // Log alert creation
        logAlertActivity($db, $alertId, 'created', 'Alert created by administrator');

        echo json_encode([
            'success' => true,
            'message' => 'Attendance alert created successfully',
            'alert_id' => $alertId
        ]);

    } catch (Exception $e) {
        throw new Exception('Failed to create attendance alert: ' . $e->getMessage());
    }
}

function getAttendanceAlertStats($db) {
    try {
        $query = "
            SELECT
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_alerts,
                COUNT(CASE WHEN status = 'acknowledged' THEN 1 END) as acknowledged_alerts,
                COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_alerts,
                COUNT(*) as total_alerts,
                COUNT(CASE WHEN severity = 'critical' THEN 1 END) as critical_alerts,
                COUNT(CASE WHEN severity = 'high' THEN 1 END) as high_alerts,
                COUNT(CASE WHEN severity = 'medium' THEN 1 END) as medium_alerts,
                COUNT(CASE WHEN severity = 'low' THEN 1 END) as low_alerts
            FROM attendance_alerts
            WHERE triggered_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ";

        $stmt = $db->prepare($query);
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get alerts by type
        $typeQuery = "
            SELECT
                alert_type,
                COUNT(*) as count,
                MAX(triggered_at) as latest_triggered
            FROM attendance_alerts
            WHERE triggered_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY alert_type
            ORDER BY count DESC
        ";

        $typeStmt = $db->prepare($typeQuery);
        $typeStmt->execute();
        $alertsByType = $typeStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'summary' => $stats,
                'by_type' => $alertsByType
            ]
        ]);

    } catch (Exception $e) {
        throw new Exception('Failed to load attendance alert statistics: ' . $e->getMessage());
    }
}

function logAlertActivity($db, $alertId, $action, $description, $userId = null) {
    try {
        $query = "
            INSERT INTO alert_activity_log
            (alert_id, action, description, user_id, timestamp)
            VALUES (:alert_id, :action, :description, :user_id, NOW())
        ";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':alert_id', $alertId, PDO::PARAM_INT);
        $stmt->bindParam(':action', $action, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

    } catch (Exception $e) {
        error_log("Failed to log alert activity: " . $e->getMessage());
        // Don't throw exception for logging failures
    }
}

// Auto-generate attendance alerts based on system conditions
function generateAttendanceAlerts($db) {
    try {
        // Alert for faculty not checked in by 9 AM
        $lateCheckinQuery = "
            INSERT INTO attendance_alerts
            (alert_type, severity, message, faculty_id, status, triggered_at)
            SELECT
                'late_checkin',
                'medium',
                CONCAT(f.name, ' has not checked in by 9:00 AM'),
                f.id,
                'active',
                NOW()
            FROM faculties f
            LEFT JOIN faculty_presence fp ON f.id = fp.faculty_id
            WHERE f.status = 'active'
            AND (
                fp.checkin_time IS NULL
                OR TIME(fp.checkin_time) > '09:00:00'
            )
            AND DATE(fp.checkin_time) = CURDATE()
            AND NOT EXISTS (
                SELECT 1 FROM attendance_alerts aa
                WHERE aa.faculty_id = f.id
                AND aa.alert_type = 'late_checkin'
                AND DATE(aa.triggered_at) = CURDATE()
            )
        ";

        $db->exec($lateCheckinQuery);

        // Alert for empty classrooms during scheduled class time
        $emptyClassroomQuery = "
            INSERT INTO attendance_alerts
            (alert_type, severity, message, room_id, status, triggered_at)
            SELECT
                'empty_classroom',
                'low',
                CONCAT('Room ', r.room_number, ' in ', b.name, ' is empty during class hours'),
                r.id,
                'active',
                NOW()
            FROM rooms r
            LEFT JOIN buildings b ON r.building_id = b.id
            LEFT JOIN room_occupancy ro ON r.id = ro.room_id
            WHERE r.status = 'active'
            AND HOUR(NOW()) BETWEEN 8 AND 17
            AND (ro.occupied_count IS NULL OR ro.occupied_count = 0)
            AND NOT EXISTS (
                SELECT 1 FROM attendance_alerts aa
                WHERE aa.room_id = r.id
                AND aa.alert_type = 'empty_classroom'
                AND DATE(aa.triggered_at) = CURDATE()
            )
        ";

        $db->exec($emptyClassroomQuery);

        // Alert for overcrowded classrooms
        $overcrowdedQuery = "
            INSERT INTO attendance_alerts
            (alert_type, severity, message, room_id, status, triggered_at, metadata)
            SELECT
                'overcrowded_classroom',
                'high',
                CONCAT('Room ', r.room_number, ' is overcrowded (', ro.occupied_count, '/', r.capacity, ' students)'),
                r.id,
                'active',
                NOW(),
                JSON_OBJECT('occupied', ro.occupied_count, 'capacity', r.capacity)
            FROM rooms r
            LEFT JOIN room_occupancy ro ON r.id = ro.room_id
            WHERE r.status = 'active'
            AND ro.occupied_count > r.capacity * 1.1
            AND NOT EXISTS (
                SELECT 1 FROM attendance_alerts aa
                WHERE aa.room_id = r.id
                AND aa.alert_type = 'overcrowded_classroom'
                AND DATE(aa.triggered_at) = CURDATE()
            )
        ";

        $db->exec($overcrowdedQuery);

    } catch (Exception $e) {
        error_log("Failed to generate attendance alerts: " . $e->getMessage());
    }
}

// Generate alerts when this endpoint is called with action=generate
if ($action === 'generate') {
    if (!hasRole(['admin', 'dean'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized access'
        ]);
        exit;
    }

    generateAttendanceAlerts($db);
    echo json_encode([
        'success' => true,
        'message' => 'Attendance alerts generated successfully'
    ]);
}
?>