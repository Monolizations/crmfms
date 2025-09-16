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
            // Get all faculty presence data
            getFacultyPresence($db);
            break;

        case 'stats':
            // Get attendance statistics
            getAttendanceStats($db);
            break;

        case 'activity':
            // Get recent attendance activity
            getAttendanceActivity($db);
            break;

        case 'update':
            // Update faculty presence (admin only)
            if (!hasRole(['admin', 'dean'])) {
                throw new Exception('Unauthorized access');
            }
            updateFacultyPresence($db);
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
            break;
    }
} catch (Exception $e) {
    error_log("Faculty Presence API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function getFacultyPresence($db) {
    try {
        $query = "
            SELECT
                f.id,
                f.name,
                f.email,
                d.name as department,
                fp.status,
                fp.current_location,
                fp.last_seen,
                fp.checkin_time,
                fp.checkout_time,
                fp.notes
            FROM faculties f
            LEFT JOIN departments d ON f.department_id = d.id
            LEFT JOIN faculty_presence fp ON f.id = fp.faculty_id
            WHERE f.status = 'active'
            ORDER BY f.name ASC
        ";

        $stmt = $db->prepare($query);
        $stmt->execute();
        $faculty = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process faculty data to determine current status
        $processedFaculty = [];
        foreach ($faculty as $fac) {
            $processedFaculty[] = processFacultyStatus($fac);
        }

        echo json_encode([
            'success' => true,
            'data' => $processedFaculty
        ]);

    } catch (Exception $e) {
        throw new Exception('Failed to load faculty presence data: ' . $e->getMessage());
    }
}

function processFacultyStatus($faculty) {
    $now = new DateTime();
    $lastSeen = $faculty['last_seen'] ? new DateTime($faculty['last_seen']) : null;
    $checkinTime = $faculty['checkin_time'] ? new DateTime($faculty['checkin_time']) : null;

    // Determine status based on various factors
    $status = $faculty['status'] ?? 'unknown';

    if ($status === 'present' && $lastSeen) {
        // Check if faculty was seen recently (within last 2 hours)
        $interval = $now->diff($lastSeen);
        $hoursSinceLastSeen = $interval->h + ($interval->days * 24);

        if ($hoursSinceLastSeen > 2) {
            $status = 'absent';
        }
    }

    // Check for scheduled classes to determine expected presence
    if ($status === 'unknown' && $checkinTime) {
        $expectedCheckout = clone $checkinTime;
        $expectedCheckout->modify('+8 hours'); // Assume 8-hour workday

        if ($now > $expectedCheckout) {
            $status = 'checked_out';
        } else {
            $status = 'present';
        }
    }

    return [
        'id' => $faculty['id'],
        'name' => $faculty['name'],
        'email' => $faculty['email'],
        'department' => $faculty['department'],
        'status' => $status,
        'current_location' => $faculty['current_location'] ?? 'Unknown',
        'last_seen' => $faculty['last_seen'],
        'checkin_time' => $faculty['checkin_time'],
        'checkout_time' => $faculty['checkout_time'],
        'notes' => $faculty['notes']
    ];
}

function getAttendanceStats($db) {
    try {
        $query = "
            SELECT
                COUNT(CASE WHEN fp.status = 'present' THEN 1 END) as present,
                COUNT(CASE WHEN fp.status = 'absent' THEN 1 END) as absent,
                COUNT(CASE WHEN fp.status = 'late' THEN 1 END) as late,
                COUNT(CASE WHEN fp.status = 'on_leave' THEN 1 END) as on_leave,
                COUNT(f.id) as total
            FROM faculties f
            LEFT JOIN faculty_presence fp ON f.id = fp.faculty_id
            WHERE f.status = 'active'
        ";

        $stmt = $db->prepare($query);
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);

    } catch (Exception $e) {
        throw new Exception('Failed to load attendance statistics: ' . $e->getMessage());
    }
}

function getAttendanceActivity($db) {
    try {
        $limit = (int)($_GET['limit'] ?? 10);
        $limit = min($limit, 50); // Max 50 records

        $query = "
            SELECT
                aa.id,
                aa.faculty_id,
                f.name as faculty_name,
                aa.activity_type,
                aa.description,
                aa.location,
                aa.timestamp,
                aa.metadata
            FROM attendance_activity aa
            LEFT JOIN faculties f ON aa.faculty_id = f.id
            ORDER BY aa.timestamp DESC
            LIMIT :limit
        ";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process activities for better display
        $processedActivities = [];
        foreach ($activities as $activity) {
            $processedActivities[] = [
                'id' => $activity['id'],
                'faculty_id' => $activity['faculty_id'],
                'faculty_name' => $activity['faculty_name'],
                'type' => $activity['activity_type'],
                'description' => formatActivityDescription($activity),
                'location' => $activity['location'],
                'timestamp' => $activity['timestamp']
            ];
        }

        echo json_encode([
            'success' => true,
            'data' => $processedActivities
        ]);

    } catch (Exception $e) {
        throw new Exception('Failed to load attendance activity: ' . $e->getMessage());
    }
}

function formatActivityDescription($activity) {
    $type = $activity['activity_type'];
    $faculty = $activity['faculty_name'] ?? 'Unknown Faculty';
    $location = $activity['location'] ?? 'Unknown Location';

    switch ($type) {
        case 'checkin':
            return "$faculty checked in at $location";
        case 'checkout':
            return "$faculty checked out from $location";
        case 'late':
            return "$faculty arrived late at $location";
        case 'absent':
            return "$faculty marked as absent";
        case 'leave':
            return "$faculty started leave";
        case 'location_change':
            return "$faculty moved to $location";
        default:
            return $activity['description'] ?? "$faculty performed activity: $type";
    }
}

function updateFacultyPresence($db) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['faculty_id'])) {
        throw new Exception('Faculty ID is required');
    }

    $facultyId = (int)$input['faculty_id'];
    $status = $input['status'] ?? 'present';
    $location = $input['location'] ?? null;
    $notes = $input['notes'] ?? null;

    try {
        // Check if faculty exists
        $checkQuery = "SELECT id FROM faculties WHERE id = :faculty_id AND status = 'active'";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':faculty_id', $facultyId, PDO::PARAM_INT);
        $checkStmt->execute();

        if ($checkStmt->rowCount() === 0) {
            throw new Exception('Faculty not found or inactive');
        }

        // Update or insert faculty presence
        $query = "
            INSERT INTO faculty_presence
            (faculty_id, status, current_location, last_seen, notes, updated_at)
            VALUES (:faculty_id, :status, :location, NOW(), :notes, NOW())
            ON DUPLICATE KEY UPDATE
            status = :status,
            current_location = :location,
            last_seen = NOW(),
            notes = :notes,
            updated_at = NOW()
        ";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':faculty_id', $facultyId, PDO::PARAM_INT);
        $stmt->bindParam(':status', $status, PDO::PARAM_STR);
        $stmt->bindParam(':location', $location, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $notes, PDO::PARAM_STR);
        $stmt->execute();

        // Log activity
        logAttendanceActivity($db, $facultyId, 'status_update', "Status updated to $status", $location);

        echo json_encode([
            'success' => true,
            'message' => 'Faculty presence updated successfully'
        ]);

    } catch (Exception $e) {
        throw new Exception('Failed to update faculty presence: ' . $e->getMessage());
    }
}

function logAttendanceActivity($db, $facultyId, $type, $description, $location = null) {
    try {
        $query = "
            INSERT INTO attendance_activity
            (faculty_id, activity_type, description, location, timestamp)
            VALUES (:faculty_id, :type, :description, :location, NOW())
        ";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':faculty_id', $facultyId, PDO::PARAM_INT);
        $stmt->bindParam(':type', $type, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':location', $location, PDO::PARAM_STR);
        $stmt->execute();

    } catch (Exception $e) {
        error_log("Failed to log attendance activity: " . $e->getMessage());
        // Don't throw exception for logging failures
    }
}
?>