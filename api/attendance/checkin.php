<?php
// /api/attendance/checkin.php
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

// Start session (suppress notices)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function checkDuplicateAction($db, $userId, $action, $timeWindowSeconds = 30) {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as action_count
            FROM audit_trail
            WHERE user_id = :user_id
            AND action = :action
            AND created_at >= DATE_SUB(NOW(), INTERVAL :seconds SECOND)
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':action' => $action,
            ':seconds' => $timeWindowSeconds
        ]);
        $result = $stmt->fetch();
        return $result['action_count'] > 0;
    } catch (Exception $e) {
        // If there's an error with the duplicate check, allow the action to proceed
        error_log('Duplicate action check error: ' . $e->getMessage());
        return false;
    }
}

function logAudit($db, $userId, $action, $details, $additionalInfo = null) {
    try {
        $timestamp = date('Y-m-d H:i:s');
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Enhanced details for manual check-ins
        if ($additionalInfo) {
            $enhancedDetails = [
                'timestamp' => $timestamp,
                'action' => $action,
                'details' => $details,
                'method' => 'manual',
                'ip_address' => $ipAddress
            ];

            if (isset($additionalInfo['latitude'])) {
                $enhancedDetails['latitude'] = $additionalInfo['latitude'];
                $enhancedDetails['longitude'] = $additionalInfo['longitude'];
            }

            if (isset($additionalInfo['hours_worked'])) {
                $enhancedDetails['hours_worked'] = $additionalInfo['hours_worked'];
            }

            if (isset($additionalInfo['location'])) {
                $enhancedDetails['location'] = $additionalInfo['location'];
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
   $input = json_decode(file_get_contents('php://input'), true);
   $userId = $_SESSION['uid'];
   $now = date('Y-m-d H:i:s');
   $clientScanTimestamp = $input['scan_timestamp'] ?? $now; // Use client timestamp if provided, otherwise server time

   // Check for duplicate manual check-in actions within 30 seconds
   if (checkDuplicateAction($db, $userId, 'MANUAL_CHECK_IN', 30)) {
       echo json_encode(['success'=>false, 'message'=>'Duplicate check-in detected. Please wait 30 seconds before trying again.']);
       exit;
   }

   // Check if user already has an active check-in today
   $stmt = $db->prepare("SELECT attendance_id FROM attendance
                        WHERE user_id=:u AND DATE(check_in_time)=CURDATE() AND check_out_time IS NULL
                        ORDER BY check_in_time DESC LIMIT 1");
   $stmt->execute([':u'=>$userId]);
   $existing = $stmt->fetch();

   if ($existing) {
       echo json_encode(['success'=>false, 'message'=>'You are already checked in. Please check out first.']);
       exit;
   }

   // Create new check-in record (department/office check-in)
   $stmt = $db->prepare("INSERT INTO attendance(user_id, check_in_time, scan_timestamp, server_timestamp) VALUES(:u, :t, :st, :srt)");
   $stmt->execute([
       ':u'=>$userId,
       ':t'=>$now,
       ':st'=>$clientScanTimestamp,
       ':srt'=>$now
   ]);

   $auditInfo = [
       'location' => 'Department Office (Manual)',
       'method' => 'manual_web_interface',
       'scan_timestamp' => $clientScanTimestamp,
       'server_timestamp' => $now
   ];
   logAudit($db, $userId, 'MANUAL_CHECK_IN', 'Manual check-in recorded at ' . $now, $auditInfo);
   echo json_encode(['success'=>true, 'message'=>'Successfully checked in at ' . date('h:i A', strtotime($now))]);

} catch (Throwable $e) {
   http_response_code(500);
   error_log('Check-in API Error: ' . $e->getMessage());
   echo json_encode(['success'=>false,'message'=>'Server error']);
}
?>