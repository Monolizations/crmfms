<?php
// /api/attendance/attendance.php
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

// Start session
session_start();

function checkDuplicateScan($db, $userId, $qrCode, $timeWindowSeconds = 30) {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as scan_count
            FROM audit_trail
            WHERE user_id = :user_id
            AND JSON_EXTRACT(details, '$.qr_code_value') = :qr_code
            AND created_at >= DATE_SUB(NOW(), INTERVAL :seconds SECOND)
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':qr_code' => $qrCode,
            ':seconds' => $timeWindowSeconds
        ]);
        $result = $stmt->fetch();
        return $result['scan_count'] > 0;
    } catch (Exception $e) {
        // If there's an error with the duplicate check, allow the scan to proceed
        error_log('Duplicate scan check error: ' . $e->getMessage());
        return false;
    }
}

function logAudit($db, $userId, $action, $details, $qrInfo = null) {
    try {
        $timestamp = date('Y-m-d H:i:s');
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Enhanced details for QR scans
        if ($qrInfo) {
            $enhancedDetails = [
                'timestamp' => $timestamp,
                'action' => $action,
                'details' => $details,
                'qr_code_type' => $qrInfo['code_type'] ?? 'unknown',
                'qr_ref_id' => $qrInfo['ref_id'] ?? 'unknown',
                'qr_code_value' => $qrInfo['code_value'] ?? 'unknown',
                'location' => $qrInfo['location'] ?? null,
                'ip_address' => $ipAddress
            ];

            if (isset($qrInfo['latitude'])) {
                $enhancedDetails['latitude'] = $qrInfo['latitude'];
                $enhancedDetails['longitude'] = $qrInfo['longitude'];
            }

            if (isset($qrInfo['session_type'])) {
                $enhancedDetails['session_type'] = $qrInfo['session_type'];
            }

            $details = json_encode($enhancedDetails);
        }

        $stmt = $db->prepare("INSERT INTO audit_trail (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $details, $ipAddress]);
    } catch (Exception $e) {
        error_log('Audit log error: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success'=>false, 'message'=>'Method not allowed']);
  exit;
}

if (!isset($_SESSION['uid'])) {
  http_response_code(401);
  echo json_encode(['success'=>false, 'message'=>'Not logged in']);
  exit;
}

try {
  $db = (new Database())->getConnection();

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $userId = $_SESSION['uid'];

    if ($action === 'recent') {
      // Get recent check-ins for the current user
      $stmt = $db->prepare("SELECT a.*,
                           CASE
                             WHEN a.room_id IS NOT NULL THEN 'room'
                             ELSE 'department'
                           END as location_type,
                            CASE
                              WHEN a.room_id IS NOT NULL THEN r.name
                              ELSE 'Department Office'
                            END as location_label,
                           TIME(a.check_in_time) as time_in_human
                           FROM attendance a
                           LEFT JOIN rooms r ON a.room_id = r.room_id
                           WHERE a.user_id = :user_id
                           ORDER BY a.check_in_time DESC
                           LIMIT 10");
      $stmt->execute([':user_id' => $userId]);
      $recentCheckins = $stmt->fetchAll();

      echo json_encode(['items' => $recentCheckins]);
      exit;
    }

    if ($action === 'stats') {
      // Get attendance statistics for current user
      $stmt = $db->prepare("SELECT
                           COUNT(*) as total_checkins,
                           COUNT(DISTINCT DATE(check_in_time)) as active_days,
                           ROUND(AVG(CASE WHEN TIME(check_out_time) > '17:00:00' THEN 1 ELSE 0 END) * 100, 1) as overtime_percentage
                           FROM attendance
                           WHERE user_id = :user_id
                           AND check_in_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
      $stmt->execute([':user_id' => $userId]);
      $stats = $stmt->fetch();

      // Calculate attendance rate (simplified)
      $totalDays = 30; // Last 30 days
      $attendanceRate = round(($stats['active_days'] / $totalDays) * 100, 1);

      echo json_encode([
        'rate' => $attendanceRate,
        'total_checkins' => $stats['total_checkins'] ?? 0,
        'active_days' => $stats['active_days'] ?? 0,
        'overtime_percentage' => $stats['overtime_percentage'] ?? 0
      ]);
      exit;
    }

    // Default: return user's attendance records
    $stmt = $db->prepare("SELECT a.*,
                         CASE
                           WHEN a.room_id IS NOT NULL THEN 'room'
                           ELSE 'department'
                         END as location_type,
                          CASE
                            WHEN a.room_id IS NOT NULL THEN r.name
                            ELSE 'Department Office'
                          END as location_label
                         FROM attendance a
                         LEFT JOIN rooms r ON a.room_id = r.room_id
                         WHERE a.user_id = :user_id
                         ORDER BY a.check_in_time DESC
                         LIMIT 50");
    $stmt->execute([':user_id' => $userId]);
    $attendanceRecords = $stmt->fetchAll();

    echo json_encode(['items' => $attendanceRecords]);
    exit;
  }

  $input = json_decode(file_get_contents('php://input'), true);
  $code = sanitize($input['code_value'] ?? '');
  $latitude = $input['latitude'] ?? null;
  $longitude = $input['longitude'] ?? null;
  $clientScanTimestamp = $input['scan_timestamp'] ?? null;

    // Convert ISO timestamp to MySQL format if provided
    if ($clientScanTimestamp) {
        $clientScanTimestamp = date('Y-m-d H:i:s', strtotime($clientScanTimestamp));
    }


  if ($code === '') {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Invalid QR code']);
    exit;
  }

    // Lookup QR - handle multiple formats
    $qr = null;

    // First try direct lookup (for any format stored in database)
    $stmt = $db->prepare("SELECT * FROM qr_codes WHERE code_value = :c LIMIT 1");
    $stmt->execute([':c'=>$code]);
    $qr = $stmt->fetch();

    // If not found, try parsing as JSON (new format)
    if (!$qr) {
      $decoded = json_decode($code, true);
      if ($decoded && isset($decoded['type'])) {
        if ($decoded['type'] === 'room' && isset($decoded['room_id'])) {
          $roomId = (int)$decoded['room_id'];
          $stmt = $db->prepare("SELECT * FROM qr_codes WHERE code_type = 'room' AND ref_id = :rid LIMIT 1");
          $stmt->execute([':rid' => $roomId]);
          $qr = $stmt->fetch();
        } elseif ($decoded['type'] === 'faculty' && isset($decoded['user_id'])) {
          $userId = (int)$decoded['user_id'];
          $stmt = $db->prepare("SELECT * FROM qr_codes WHERE code_type = 'faculty' AND ref_id = :uid LIMIT 1");
          $stmt->execute([':uid' => $userId]);
          $qr = $stmt->fetch();
        }
      }
    }

    // If still not found, try parsing old format QR-ROOM-{code} or QR-FACULTY-{id}
    if (!$qr) {
      if (strpos($code, 'QR-ROOM-') === 0) {
        $roomCode = str_replace('QR-ROOM-', '', $code);
        $stmt = $db->prepare("SELECT q.* FROM qr_codes q
                             JOIN rooms r ON q.ref_id = r.room_id AND q.code_type = 'room'
                             WHERE r.room_code = :rc LIMIT 1");
        $stmt->execute([':rc' => $roomCode]);
        $qr = $stmt->fetch();
      } elseif (strpos($code, 'QR-FACULTY-') === 0) {
        $employeeId = str_replace('QR-FACULTY-', '', $code);
        $stmt = $db->prepare("SELECT q.* FROM qr_codes q
                             JOIN users u ON q.ref_id = u.user_id AND q.code_type = 'faculty'
                             WHERE u.employee_id = :eid LIMIT 1");
        $stmt->execute([':eid' => $employeeId]);
        $qr = $stmt->fetch();
      }
    }

    if (!$qr) {
      error_log("QR not recognized: '$code'");
      // For debugging, try to find any QR codes that might be similar
      $stmt = $db->query("SELECT code_value FROM qr_codes LIMIT 5");
      $sampleCodes = $stmt->fetchAll(PDO::FETCH_COLUMN);
      error_log("Sample QR codes in DB: " . implode(', ', $sampleCodes));
      echo json_encode(['success'=>false,'message'=>'QR not recognized. Code: ' . substr($code, 0, 50)]);
      exit;
    }

    error_log("QR recognized: type={$qr['code_type']}, ref_id={$qr['ref_id']}, code_value={$qr['code_value']}");

    $uid = $_SESSION['uid'];
    $now = date('Y-m-d H:i:s');

    // Check for duplicate scans within the last 30 seconds to prevent accidental duplicates
    if (checkDuplicateScan($db, $uid, $code, 30)) {
      echo json_encode(['success'=>false,'message'=>'Duplicate scan detected. Please wait 30 seconds before scanning again.']);
      exit;
    }

   if ($qr['code_type'] === 'faculty') {
     // Not used for scan logging (faculty QR is mostly ID). Just demo.
     echo json_encode(['success'=>false,'message'=>'Faculty QR is not valid for attendance scan.']);
     exit;
   }

   if ($qr['code_type'] === 'department') {
     // Check if user already logged in today without timeout
     $stmt = $db->prepare("SELECT * FROM attendance
                           WHERE user_id=:u AND DATE(check_in_time)=CURDATE() AND check_out_time IS NULL AND room_id IS NULL");
     $stmt->execute([':u'=>$uid]);
     $log = $stmt->fetch();

      if ($log) {
        // time-out
        $stmt = $db->prepare("UPDATE attendance SET check_out_time=:t WHERE attendance_id=:id");
        $stmt->execute([':t'=>$now, ':id'=>$log['attendance_id']]);

        $qrInfo = [
            'code_type' => 'department',
            'code_value' => $code,
            'location' => 'Department Office'
        ];
        logAudit($db, $uid, 'DEPARTMENT_CHECK_OUT', 'Department time-out recorded', $qrInfo);
        echo json_encode(['success'=>true,'message'=>'Department time-out recorded']);
      } else {
        // time-in
        $stmt = $db->prepare("INSERT INTO attendance(user_id, check_in_time, scan_timestamp, server_timestamp, latitude, longitude) VALUES(:u, :t, :st, :srt, :lat, :lng)");
        $stmt->execute([
            ':u'=>$uid,
            ':t'=>$now,
            ':st'=>$clientScanTimestamp,
            ':srt'=>$now,
            ':lat'=>$latitude,
            ':lng'=>$longitude
        ]);

        $qrInfo = [
            'code_type' => 'department',
            'code_value' => $code,
            'location' => 'Department Office',
            'scan_timestamp' => $clientScanTimestamp,
            'server_timestamp' => $now,
            'latitude' => $latitude,
            'longitude' => $longitude
        ];
        logAudit($db, $uid, 'DEPARTMENT_CHECK_IN', 'Department time-in recorded', $qrInfo);
        echo json_encode(['success'=>true,'message'=>'Department time-in recorded']);
      }
     exit;
   }

    if ($qr['code_type'] === 'room') {
      error_log("Processing room QR: room_id={$qr['ref_id']}, user_id=$uid");
      // Schedule validation removed - schedules functionality has been disabled

      // Check if already checked in to this room today (classroom check-ins are permanent for the session)
      $stmt = $db->prepare("SELECT * FROM attendance
                            WHERE user_id=:u AND room_id=:r AND DATE(check_in_time)=CURDATE()");
      $stmt->execute([':u'=>$uid, ':r'=>$qr['ref_id']]);
      $existingCheckin = $stmt->fetch();

      error_log("Classroom check: user=$uid, room={$qr['ref_id']}, existing=" . ($existingCheckin ? 'yes' : 'no'));

      if ($existingCheckin) {
        // Already checked in to this room today - no check-out allowed for classrooms
        echo json_encode(['success'=>false,'message'=>'You are already checked in to this classroom for today. Classroom sessions do not require check-out.']);
        exit;
      } else {
        // Get room details for better logging and location
        $roomDetails = null;
        $stmt = $db->prepare("SELECT room_code, name FROM rooms WHERE room_id = :rid LIMIT 1");
        $stmt->execute([':rid' => $qr['ref_id']]);
        $roomDetails = $stmt->fetch();

        $roomName = $roomDetails ? ($roomDetails['name'] ?: $roomDetails['room_code'] ?: 'Room ' . $qr['ref_id']) : 'Room ' . $qr['ref_id'];
        $roomCode = $roomDetails ? $roomDetails['room_code'] : null;

        // Debug logging
        error_log("Classroom check-in: user_id=$uid, room_id={$qr['ref_id']}, room_name=$roomName, time=$now, lat=$latitude, lng=$longitude");

        // Create new classroom check-in (no check-out will be recorded for classrooms)
        $stmt = $db->prepare("INSERT INTO attendance(user_id, room_id, check_in_time, scan_timestamp, server_timestamp, latitude, longitude) VALUES(:u, :r, :t, :st, :srt, :lat, :lng)");
        $result = $stmt->execute([
            ':u'=>$uid,
            ':r'=>$qr['ref_id'],
            ':t'=>$now,
            ':st'=>$clientScanTimestamp,
            ':srt'=>$now,
            ':lat'=>$latitude,
            ':lng'=>$longitude
        ]);

        if (!$result) {
          error_log("Failed to insert classroom attendance record");
          echo json_encode(['success'=>false,'message'=>'Failed to record classroom attendance']);
          exit;
        }

        $qrInfo = [
            'code_type' => 'room',
            'ref_id' => $qr['ref_id'],
            'code_value' => $code,
            'location' => $roomName,
            'room_code' => $roomCode,
            'scan_timestamp' => $clientScanTimestamp,
            'server_timestamp' => $now,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'session_type' => 'classroom_permanent'
        ];
        logAudit($db, $uid, 'CLASSROOM_CHECK_IN', 'Classroom check-in recorded for ' . $roomName . ' (permanent session)', $qrInfo);
        echo json_encode(['success'=>true,'message'=>'Classroom check-in recorded. You are now marked present for this class session.']);
      }
     exit;
  }

} catch (Throwable $e) {
   http_response_code(500);
   error_log('Attendance API Error: ' . $e->getMessage());
   echo json_encode(['success'=>false,'message'=>'Server error']);
}
