<?php
// /api/attendance/checkin.php
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

  // Check if already checked in today
  $stmt = $db->prepare("SELECT * FROM attendance
                        WHERE user_id=:u AND DATE(check_in_time)=CURDATE() AND check_out_time IS NULL
                        ORDER BY check_in_time DESC LIMIT 1");
  $stmt->execute([':u'=>$uid]);
  $existing = $stmt->fetch();

  if ($existing) {
    echo json_encode(['success'=>false, 'message'=>'Already checked in today']);
    exit;
  }

  // Insert check-in record
  $stmt = $db->prepare("INSERT INTO attendance(user_id, check_in_time) VALUES(:u, :t)");
  $stmt->execute([':u'=>$uid, ':t'=>$timestamp]);

  echo json_encode(['success'=>true, 'message'=>'Check-in recorded successfully']);
} catch (Throwable $e) {
  http_response_code(500);
  error_log($e->getMessage());
  echo json_encode(['success'=>false, 'message'=>'Server error']);
}
?>