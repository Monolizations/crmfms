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
            // Get all classroom occupancy data
            getClassroomOccupancy($db);
            break;

        case 'building':
            // Get occupancy for specific building
            getBuildingOccupancy($db);
            break;

        case 'room':
            // Get detailed occupancy for specific room
            getRoomOccupancy($db);
            break;

        case 'stats':
            // Get occupancy statistics
            getOccupancyStats($db);
            break;

        case 'update':
            // Update room occupancy (admin only)
            if (!hasRole(['admin', 'dean'])) {
                throw new Exception('Unauthorized access');
            }
            updateRoomOccupancy($db);
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
            break;
    }
} catch (Exception $e) {
    error_log("Classroom Occupancy API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function getClassroomOccupancy($db) {
    try {
        $buildingFilter = $_GET['building'] ?? null;

        $query = "
            SELECT
                r.id,
                r.room_number,
                r.capacity,
                b.name as building_name,
                b.id as building_id,
                f.floor_number,
                COALESCE(ro.occupied_count, 0) as occupied,
                COALESCE(ro.faculty_id, NULL) as faculty_id,
                fac.name as faculty_name,
                COALESCE(ro.subject, '') as subject,
                COALESCE(ro.last_updated, NULL) as last_updated,
                CASE
                    WHEN ro.occupied_count > 0 THEN 'occupied'
                    ELSE 'empty'
                END as status
            FROM rooms r
            LEFT JOIN buildings b ON r.building_id = b.id
            LEFT JOIN floors f ON r.floor_id = f.id
            LEFT JOIN room_occupancy ro ON r.id = ro.room_id
            LEFT JOIN faculties fac ON ro.faculty_id = fac.id
            WHERE r.status = 'active'
        ";

        $params = [];
        if ($buildingFilter) {
            $query .= " AND b.id = :building_id";
            $params[':building_id'] = $buildingFilter;
        }

        $query .= " ORDER BY b.name ASC, r.room_number ASC";

        $stmt = $db->prepare($query);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process rooms data
        $processedRooms = [];
        foreach ($rooms as $room) {
            $processedRooms[] = processRoomData($room);
        }

        echo json_encode([
            'success' => true,
            'data' => $processedRooms
        ]);

    } catch (Exception $e) {
        throw new Exception('Failed to load classroom occupancy data: ' . $e->getMessage());
    }
}

function processRoomData($room) {
    $occupied = (int)($room['occupied'] ?? 0);
    $capacity = (int)($room['capacity'] ?? 0);
    $utilization = $capacity > 0 ? round(($occupied / $capacity) * 100) : 0;

    return [
        'id' => $room['id'],
        'room_number' => $room['room_number'],
        'building_name' => $room['building_name'],
        'building_id' => $room['building_id'],
        'floor_number' => $room['floor_number'],
        'capacity' => $capacity,
        'occupied' => $occupied,
        'available' => max(0, $capacity - $occupied),
        'utilization' => $utilization,
        'status' => $room['status'],
        'faculty_id' => $room['faculty_id'],
        'faculty_name' => $room['faculty_name'],
        'subject' => $room['subject'],
        'last_updated' => $room['last_updated']
    ];
}

function getBuildingOccupancy($db) {
    $buildingId = $_GET['building_id'] ?? null;

    if (!$buildingId) {
        throw new Exception('Building ID is required');
    }

    try {
        $query = "
            SELECT
                b.name as building_name,
                COUNT(r.id) as total_rooms,
                SUM(r.capacity) as total_capacity,
                COALESCE(SUM(ro.occupied_count), 0) as total_occupied,
                ROUND(
                    (COALESCE(SUM(ro.occupied_count), 0) / NULLIF(SUM(r.capacity), 0)) * 100,
                    1
                ) as utilization_percentage
            FROM buildings b
            LEFT JOIN rooms r ON b.id = r.building_id AND r.status = 'active'
            LEFT JOIN room_occupancy ro ON r.id = ro.room_id
            WHERE b.id = :building_id
            GROUP BY b.id, b.name
        ";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':building_id', $buildingId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            throw new Exception('Building not found');
        }

        echo json_encode([
            'success' => true,
            'data' => $result
        ]);

    } catch (Exception $e) {
        throw new Exception('Failed to load building occupancy: ' . $e->getMessage());
    }
}

function getRoomOccupancy($db) {
    $roomId = $_GET['room_id'] ?? null;

    if (!$roomId) {
        throw new Exception('Room ID is required');
    }

    try {
        $query = "
            SELECT
                r.id,
                r.room_number,
                r.capacity,
                r.facilities,
                b.name as building_name,
                f.floor_number,
                COALESCE(ro.occupied_count, 0) as occupied,
                COALESCE(ro.faculty_id, NULL) as faculty_id,
                fac.name as faculty_name,
                fac.email as faculty_email,
                COALESCE(ro.subject, '') as subject,
                COALESCE(ro.class_schedule, '') as schedule,
                COALESCE(ro.last_updated, NULL) as last_updated,
                COALESCE(ro.notes, '') as notes
            FROM rooms r
            LEFT JOIN buildings b ON r.building_id = b.id
            LEFT JOIN floors f ON r.floor_id = f.id
            LEFT JOIN room_occupancy ro ON r.id = ro.room_id
            LEFT JOIN faculties fac ON ro.faculty_id = fac.id
            WHERE r.id = :room_id AND r.status = 'active'
        ";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':room_id', $roomId, PDO::PARAM_INT);
        $stmt->execute();
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            throw new Exception('Room not found');
        }

        $detailedRoom = processRoomData($room);
        $detailedRoom['facilities'] = $room['facilities'];
        $detailedRoom['faculty_email'] = $room['faculty_email'];
        $detailedRoom['schedule'] = $room['schedule'];
        $detailedRoom['notes'] = $room['notes'];

        echo json_encode([
            'success' => true,
            'data' => $detailedRoom
        ]);

    } catch (Exception $e) {
        throw new Exception('Failed to load room occupancy details: ' . $e->getMessage());
    }
}

function getOccupancyStats($db) {
    try {
        $query = "
            SELECT
                COUNT(CASE WHEN COALESCE(ro.occupied_count, 0) > 0 THEN 1 END) as occupied_rooms,
                COUNT(CASE WHEN COALESCE(ro.occupied_count, 0) = 0 THEN 1 END) as empty_rooms,
                COUNT(r.id) as total_rooms,
                SUM(r.capacity) as total_capacity,
                COALESCE(SUM(ro.occupied_count), 0) as total_occupied,
                ROUND(
                    (COALESCE(SUM(ro.occupied_count), 0) / NULLIF(SUM(r.capacity), 0)) * 100,
                    1
                ) as overall_utilization,
                COUNT(DISTINCT CASE WHEN ro.faculty_id IS NOT NULL THEN ro.faculty_id END) as active_faculty
            FROM rooms r
            LEFT JOIN room_occupancy ro ON r.id = ro.room_id
            WHERE r.status = 'active'
        ";

        $stmt = $db->prepare($query);
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get utilization by building
        $buildingQuery = "
            SELECT
                b.name as building,
                COUNT(r.id) as rooms,
                ROUND(
                    (COALESCE(SUM(ro.occupied_count), 0) / NULLIF(SUM(r.capacity), 0)) * 100,
                    1
                ) as utilization
            FROM buildings b
            LEFT JOIN rooms r ON b.id = r.building_id AND r.status = 'active'
            LEFT JOIN room_occupancy ro ON r.id = ro.room_id
            GROUP BY b.id, b.name
            ORDER BY utilization DESC
        ";

        $buildingStmt = $db->prepare($buildingQuery);
        $buildingStmt->execute();
        $buildingStats = $buildingStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'summary' => $stats,
                'by_building' => $buildingStats
            ]
        ]);

    } catch (Exception $e) {
        throw new Exception('Failed to load occupancy statistics: ' . $e->getMessage());
    }
}

function updateRoomOccupancy($db) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['room_id'])) {
        throw new Exception('Room ID is required');
    }

    $roomId = (int)$input['room_id'];
    $occupiedCount = (int)($input['occupied_count'] ?? 0);
    $facultyId = $input['faculty_id'] ?? null;
    $subject = $input['subject'] ?? null;
    $schedule = $input['schedule'] ?? null;
    $notes = $input['notes'] ?? null;

    try {
        // Check if room exists and get capacity
        $checkQuery = "SELECT capacity FROM rooms WHERE id = :room_id AND status = 'active'";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':room_id', $roomId, PDO::PARAM_INT);
        $checkStmt->execute();
        $room = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            throw new Exception('Room not found or inactive');
        }

        // Validate occupied count
        if ($occupiedCount < 0 || $occupiedCount > $room['capacity']) {
            throw new Exception('Invalid occupied count. Must be between 0 and ' . $room['capacity']);
        }

        // Update or insert room occupancy
        $query = "
            INSERT INTO room_occupancy
            (room_id, occupied_count, faculty_id, subject, class_schedule, notes, last_updated)
            VALUES (:room_id, :occupied_count, :faculty_id, :subject, :schedule, :notes, NOW())
            ON DUPLICATE KEY UPDATE
            occupied_count = :occupied_count,
            faculty_id = :faculty_id,
            subject = :subject,
            class_schedule = :schedule,
            notes = :notes,
            last_updated = NOW()
        ";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':room_id', $roomId, PDO::PARAM_INT);
        $stmt->bindParam(':occupied_count', $occupiedCount, PDO::PARAM_INT);
        $stmt->bindParam(':faculty_id', $facultyId, PDO::PARAM_INT);
        $stmt->bindParam(':subject', $subject, PDO::PARAM_STR);
        $stmt->bindParam(':schedule', $schedule, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $notes, PDO::PARAM_STR);
        $stmt->execute();

        // Log activity if this is a significant change
        if ($occupiedCount > 0 && $facultyId) {
            logOccupancyActivity($db, $roomId, 'occupancy_update',
                "Room occupancy updated: $occupiedCount students", $facultyId);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Room occupancy updated successfully'
        ]);

    } catch (Exception $e) {
        throw new Exception('Failed to update room occupancy: ' . $e->getMessage());
    }
}

function logOccupancyActivity($db, $roomId, $type, $description, $facultyId = null) {
    try {
        $query = "
            INSERT INTO attendance_activity
            (faculty_id, activity_type, description, location, timestamp, metadata)
            VALUES (:faculty_id, :type, :description,
                    (SELECT CONCAT(b.name, ' - ', r.room_number)
                     FROM rooms r
                     LEFT JOIN buildings b ON r.building_id = b.id
                     WHERE r.id = :room_id),
                    NOW(), :metadata)
        ";

        $metadata = json_encode(['room_id' => $roomId]);

        $stmt = $db->prepare($query);
        $stmt->bindParam(':faculty_id', $facultyId, PDO::PARAM_INT);
        $stmt->bindParam(':type', $type, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':room_id', $roomId, PDO::PARAM_INT);
        $stmt->bindParam(':metadata', $metadata, PDO::PARAM_STR);
        $stmt->execute();

    } catch (Exception $e) {
        error_log("Failed to log occupancy activity: " . $e->getMessage());
        // Don't throw exception for logging failures
    }
}
?>