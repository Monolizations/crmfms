<?php
// /api/attendance/checkout.php
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

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
  $timestamp = $input['timestamp'] ?? date('Y-m-d H:i:s');

  $uid = $_SESSION['uid'];

  // Find the latest check-in without check-out
  $stmt = $db->prepare("SELECT * FROM attendance
                        WHERE user_id=:u AND DATE(check_in_time)=CURDATE() AND check_out_time IS NULL
                        ORDER BY check_in_time DESC LIMIT 1");
  $stmt->execute([':u'=>$uid]);
  $checkin = $stmt->fetch();

  if (!$checkin) {
    echo json_encode(['success'=>false, 'message'=>'No active check-in found']);
    exit;
  }

  // Update with check-out time
  $stmt = $db->prepare("UPDATE attendance SET check_out_time=:t WHERE attendance_id=:id");
  $stmt->execute([':t'=>$timestamp, ':id'=>$checkin['attendance_id']]);

  echo json_encode(['success'=>true, 'message'=>'Check-out recorded successfully']);
} catch (Throwable $e) {
  http_response_code(500);
  error_log($e->getMessage());
  echo json_encode(['success'=>false, 'message'=>'Server error']);
}
?>