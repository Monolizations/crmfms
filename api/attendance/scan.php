<?php
// /api/attendance/scan.php
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

function validateRequiredFields($input, $requiredFields) {
  foreach ($requiredFields as $field) {
    if (!isset($input[$field]) || (is_string($input[$field]) && trim($input[$field]) === '') || (is_array($input[$field]) && empty($input[$field]))) {
      http_response_code(400);
      echo json_encode(['success'=>false, 'message'=> ucfirst(str_replace('_', ' ', $field)) . ' is required.']);
      exit;
    }
  }
}

try {
  $db = (new Database())->getConnection();
  requireAuth($db); // User must be logged in to scan

  $input = json_decode(file_get_contents('php://input'), true);
  validateRequiredFields($input, ['code_value']);

  $scanned_code_value = $input['code_value'];
  $user_id = $_SESSION['uid']; // The user who is scanning

  // 1. Validate QR code and get its type and ref_id
  $stmt = $db->prepare("SELECT code_type, ref_id FROM qr_codes WHERE code_value = :cv LIMIT 1");
  $stmt->execute([':cv' => $scanned_code_value]);
  $qr_info = $stmt->fetch();

  if (!$qr_info) {
    http_response_code(404);
    echo json_encode(['success'=>false, 'message'=>'Invalid QR Code.']);
    exit;
  }

  $code_type = $qr_info['code_type'];
  $ref_id = $qr_info['ref_id'];

  // 2. Determine attendance action (check-in or check-out)
  $message = '';
  $current_time = date('Y-m-d H:i:s');

  if ($code_type === 'room') {
    // Scanning a room QR code means checking into/out of a room
    // Find if the user has an active check-in for this room
    $stmt = $db->prepare("SELECT attendance_id FROM attendance WHERE user_id = :uid AND room_id = :rid AND check_out_time IS NULL LIMIT 1");
    $stmt->execute([':uid' => $user_id, ':rid' => $ref_id]);
    $active_attendance = $stmt->fetch();

    if ($active_attendance) {
      // Check-out
      $stmt = $db->prepare("UPDATE attendance SET check_out_time = :cot WHERE attendance_id = :aid");
      $stmt->execute([':cot' => $current_time, ':aid' => $active_attendance['attendance_id']]);
      $message = 'Checked out from room.';
    } else {
      // Check-in
      $stmt = $db->prepare("INSERT INTO attendance (user_id, room_id, check_in_time) VALUES (:uid, :rid, :cit)");
      $stmt->execute([':uid' => $user_id, ':rid' => $ref_id, ':cit' => $current_time]);
      $message = 'Checked into room.';
    }
  } else if ($code_type === 'faculty') {
    // Scanning a faculty QR code means checking in/out for the day (or general attendance)
    // Find if the faculty has an active check-in for today
    $today = date('Y-m-d');
    $stmt = $db->prepare("SELECT attendance_id FROM attendance WHERE user_id = :uid AND DATE(check_in_time) = :today AND check_out_time IS NULL LIMIT 1");
    $stmt->execute([':uid' => $user_id, ':today' => $today]);
    $active_attendance = $stmt->fetch();

    if ($active_attendance) {
      // Check-out
      $stmt = $db->prepare("UPDATE attendance SET check_out_time = :cot WHERE attendance_id = :aid");
      $stmt->execute([':cot' => $current_time, ':aid' => $active_attendance['attendance_id']]);
      $message = 'Checked out for the day.';
    } else {
      // Check-in
      $stmt = $db->prepare("INSERT INTO attendance (user_id, check_in_time) VALUES (:uid, :cit)");
      $stmt->execute([':uid' => $user_id, ':cit' => $current_time]);
      $message = 'Checked in for the day.';
    }
  } else {
    http_response_code(400);
    echo json_encode(['success'=>false, 'message'=>'Unsupported QR Code type.']);
    exit;
  }

  echo json_encode(['success'=>true, 'message'=>$message]);

} catch (Throwable $e) {
  http_response_code(500);
  error_log($e->getMessage());
  echo json_encode(['success'=>false,'message'=>'Server error.']);
}
