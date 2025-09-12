<?php
// /api/attendance/attendance.php
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
  $code = sanitize($input['code_value'] ?? '');

  if ($code === '') {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Invalid QR code']);
    exit;
  }

  // Lookup QR
  $stmt = $db->prepare("SELECT * FROM qr_codes WHERE code_value = :c LIMIT 1");
  $stmt->execute([':c'=>$code]);
  $qr = $stmt->fetch();

  if (!$qr) {
    echo json_encode(['success'=>false,'message'=>'QR not recognized']);
    exit;
  }

  $uid = $_SESSION['uid'];
  $now = date('Y-m-d H:i:s');

  if ($qr['code_type'] === 'faculty') {
    // Not used for scan logging (faculty QR is mostly ID). Just demo.
    echo json_encode(['success'=>false,'message'=>'Faculty QR is not valid for attendance scan.']);
    exit;
  }

  if ($qr['code_type'] === 'department') {
    // Check if user already logged in today without timeout
    $stmt = $db->prepare("SELECT * FROM attendance_logs 
                          WHERE user_id=:u AND type='department' AND DATE(time_in)=CURDATE() AND time_out IS NULL");
    $stmt->execute([':u'=>$uid]);
    $log = $stmt->fetch();

    if ($log) {
      // time-out
      $stmt = $db->prepare("UPDATE attendance_logs SET time_out=:t WHERE log_id=:id");
      $stmt->execute([':t'=>$now, ':id'=>$log['log_id']]);
      echo json_encode(['success'=>true,'message'=>'Department time-out recorded']);
    } else {
      // time-in
      $stmt = $db->prepare("INSERT INTO attendance_logs(user_id,type,location_id,time_in) VALUES(:u,'department',:loc,:t)");
      $stmt->execute([':u'=>$uid, ':loc'=>$qr['ref_id'], ':t'=>$now]);
      echo json_encode(['success'=>true,'message'=>'Department time-in recorded']);
    }
    exit;
  }

  if ($qr['code_type'] === 'room') {
    // Check schedule match (simplified: check day + time)
    $dow = date('D'); // Mon, Tue, ...
    $stmt = $db->prepare("SELECT * FROM schedules WHERE faculty_id=:u AND room_id=:r AND day_of_week=:d 
                          AND :now BETWEEN start_time AND end_time LIMIT 1");
    $stmt->execute([':u'=>$uid, ':r'=>$qr['ref_id'], ':d'=>$dow, ':now'=>date('H:i:s')]);
    $sched = $stmt->fetch();

    if (!$sched) {
      echo json_encode(['success'=>false,'message'=>'No active schedule for this room/time']);
      exit;
    }

    // Check if already time-in
    $stmt = $db->prepare("SELECT * FROM attendance_logs 
                          WHERE user_id=:u AND type='class' AND room_id=:r AND DATE(time_in)=CURDATE() AND time_out IS NULL");
    $stmt->execute([':u'=>$uid, ':r'=>$qr['ref_id']]);
    $log = $stmt->fetch();

    if ($log) {
      $stmt = $db->prepare("UPDATE attendance_logs SET time_out=:t WHERE log_id=:id");
      $stmt->execute([':t'=>$now, ':id'=>$log['log_id']]);
      echo json_encode(['success'=>true,'message'=>'Class time-out recorded']);
    } else {
      $stmt = $db->prepare("INSERT INTO attendance_logs(user_id,type,location_id,time_in) VALUES(:u,'class',:loc,:t)");
      $stmt->execute([':u'=>$uid, ':loc'=>$qr['ref_id'], ':t'=>$now]);
      echo json_encode(['success'=>true,'message'=>'Class time-in recorded']);
    }
    exit;
  }

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Server error']);
}
