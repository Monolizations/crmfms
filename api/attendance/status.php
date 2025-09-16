<?php
// /api/attendance/status.php
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

// Start session
session_start();

if (!isset($_SESSION['uid'])) {
  http_response_code(401);
  echo json_encode(['success'=>false, 'message'=>'Not logged in']);
  exit;
}

try {
  $db = (new Database())->getConnection();
  $uid = $_SESSION['uid'];

  // Get today's attendance records
  $stmt = $db->prepare("SELECT * FROM attendance
                        WHERE user_id=:u AND DATE(check_in_time)=CURDATE()
                        ORDER BY check_in_time DESC");
  $stmt->execute([':u'=>$uid]);
  $records = $stmt->fetchAll();

  $checkedIn = false;
  $checkInTime = null;
  $checkOutTime = null;
  $totalHours = '0h 0m';

  if (!empty($records)) {
    $latest = $records[0];
    $checkedIn = $latest['check_out_time'] === null;
    $checkInTime = date('h:i A', strtotime($latest['check_in_time']));

    if ($latest['check_out_time']) {
      $checkOutTime = date('h:i A', strtotime($latest['check_out_time']));

      // Calculate total hours
      $start = strtotime($latest['check_in_time']);
      $end = strtotime($latest['check_out_time']);
      $diff = $end - $start;
      $hours = floor($diff / 3600);
      $minutes = floor(($diff % 3600) / 60);
      $totalHours = "{$hours}h {$minutes}m";
    }
  }

  echo json_encode([
    'success' => true,
    'checkedIn' => $checkedIn,
    'checkInTime' => $checkInTime,
    'checkOutTime' => $checkOutTime,
    'totalHours' => $totalHours
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  error_log($e->getMessage());
  echo json_encode(['success'=>false, 'message'=>'Server error']);
}
?>