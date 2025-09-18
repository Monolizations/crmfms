<?php
// /api/attendance/checkout.php
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

        // Enhanced details for manual check-outs
        if ($additionalInfo) {
            $enhancedDetails = [
                'timestamp' => $timestamp,
                'action' => $action,
                'details' => $details,
                'method' => 'manual',
                'hours_worked' => $additionalInfo['hours_worked'] ?? null,
                'ip_address' => $ipAddress
            ];

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
   $userId = $_SESSION['uid'];
   $now = date('Y-m-d H:i:s');
   $input = json_decode(file_get_contents('php://input'), true);
   $clientScanTimestamp = $input['scan_timestamp'] ?? $now; // Use client timestamp if provided, otherwise server time

   // Check for duplicate manual check-out actions within 30 seconds
   if (checkDuplicateAction($db, $userId, 'MANUAL_CHECK_OUT', 30)) {
       echo json_encode(['success'=>false, 'message'=>'Duplicate check-out detected. Please wait 30 seconds before trying again.']);
       exit;
   }

   // Find the latest active check-in for today
   $stmt = $db->prepare("SELECT attendance_id, check_in_time FROM attendance
                        WHERE user_id=:u AND DATE(check_in_time)=CURDATE() AND check_out_time IS NULL
                        ORDER BY check_in_time DESC LIMIT 1");
   $stmt->execute([':u'=>$userId]);
   $activeCheckin = $stmt->fetch();

   if (!$activeCheckin) {
       echo json_encode(['success'=>false, 'message'=>'No active check-in found. Please check in first.']);
       exit;
   }

   // Update with check-out time
   $stmt = $db->prepare("UPDATE attendance SET check_out_time=:t WHERE attendance_id=:id");
   $stmt->execute([':t'=>$now, ':id'=>$activeCheckin['attendance_id']]);

   // Calculate hours worked
   $start = strtotime($activeCheckin['check_in_time']);
   $end = strtotime($now);
   $diff = $end - $start;
   $hours = floor($diff / 3600);
   $minutes = floor(($diff % 3600) / 60);
   $hoursWorked = "{$hours}h {$minutes}m";

   $auditInfo = [
       'hours_worked' => $hoursWorked,
       'location' => 'Department Office (Manual)',
       'method' => 'manual_web_interface',
       'scan_timestamp' => $clientScanTimestamp,
       'server_timestamp' => $now
   ];
   logAudit($db, $userId, 'MANUAL_CHECK_OUT', 'Manual check-out recorded at ' . $now . ' (' . $hoursWorked . ')', $auditInfo);
   echo json_encode([
       'success'=>true,
       'message'=>'Successfully checked out at ' . date('h:i A', strtotime($now)),
       'hours_worked'=>$hoursWorked
   ]);

} catch (Throwable $e) {
   http_response_code(500);
   error_log('Check-out API Error: ' . $e->getMessage());
   echo json_encode(['success'=>false,'message'=>'Server error']);
}
?>