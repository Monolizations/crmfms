<?php
// /api/rooms/rooms.php
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
  // Allow faculty to view rooms for scheduling purposes
  requireAuth($db, ['admin', 'dean', 'faculty']);

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->query("SELECT r.*, q.code_value AS qr_code, f.floor_number, f.name as floor_name, b.name as building_name
                        FROM rooms r
                        JOIN floors f ON r.floor_id = f.floor_id
                        JOIN buildings b ON f.building_id = b.building_id
                        LEFT JOIN qr_codes q ON q.code_type='room' AND q.ref_id=r.room_id
                        ORDER BY b.name, f.floor_number, r.name ASC");
    $items = $stmt->fetchAll();
    
    // Add QR code URL to each item
    foreach ($items as &$item) {
        $item['qr_code_url'] = '/crmfms/api/qr/generate.php?data=' . urlencode($item['qr_code']);
    }
    unset($item); // Break the reference with the last element

    echo json_encode(['items'=>$items]);
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'create') {
      validateRequiredFields($input, ['floor_id', 'room_code', 'name']);
      $stmt = $db->prepare("INSERT INTO rooms(floor_id,room_code,name,status) VALUES(:fid,:rc,:n,'active')");
      $stmt->execute([
        ':fid'=>$input['floor_id'],
        ':rc'=>$input['room_code'],
        ':n'=>$input['name']
      ]);
      $rid = $db->lastInsertId();

      // Auto-generate QR
      $qrVal = "QR-ROOM-".$input['room_code'];
      $stmt = $db->prepare("INSERT INTO qr_codes(code_type,ref_id,code_value) VALUES('room',:id,:c)");
      $stmt->execute([':id'=>$rid, ':c'=>$qrVal]);

      $qr_code_url = '/crmfms/api/qr/generate.php?data=' . urlencode($qrVal);

      echo json_encode(['success'=>true,'message'=>'Room added with QR', 'qr_code_url'=>$qr_code_url]);
      exit;
    }

    if ($action === 'toggle') {
      validateRequiredFields($input, ['room_id']);
      $id = (int)$input['room_id'];
      $stmt = $db->prepare("UPDATE rooms 
                            SET status = CASE WHEN status='active' THEN 'inactive' ELSE 'active' END
                            WHERE room_id=:id");
      $stmt->execute([':id'=>$id]);
      echo json_encode(['success'=>true,'message'=>'Room status updated']);
      exit;
    }

    if ($action === 'update') {
      validateRequiredFields($input, ['room_id', 'floor_id', 'room_code', 'name']);
      $stmt = $db->prepare("UPDATE rooms SET floor_id=:fid, room_code=:rc, name=:n WHERE room_id=:id");
      $stmt->execute([
        ':fid'=>$input['floor_id'],
        ':rc'=>$input['room_code'],
        ':n'=>$input['name'],
        ':id'=>$input['room_id']
      ]);
      echo json_encode(['success'=>true,'message'=>'Room updated successfully']);
      exit;
    }

    if ($action === 'delete') {
      validateRequiredFields($input, ['room_id']);
      $stmt = $db->prepare("DELETE FROM rooms WHERE room_id=:id");
      $stmt->execute([':id'=>$input['room_id']]);
      echo json_encode(['success'=>true,'message'=>'Room deleted successfully']);
      exit;
    }

    if ($action === 'generate_qr_codes') {
      requireAuth($db, ['admin', 'dean']); // Only admin and dean can generate QR codes
      
      // Find rooms without QR codes
      $stmt = $db->query("SELECT r.room_id, r.room_code 
                          FROM rooms r
                          LEFT JOIN qr_codes q ON q.code_type='room' AND q.ref_id=r.room_id
                          WHERE q.code_id IS NULL");
      $roomsWithoutQR = $stmt->fetchAll();
      
      $generatedCount = 0;
      foreach ($roomsWithoutQR as $room) {
        $qrVal = "QR-ROOM-".$room['room_code'];
        $stmt = $db->prepare("INSERT INTO qr_codes(code_type,ref_id,code_value) VALUES('room',:id,:c)");
        $stmt->execute([':id'=>$room['room_id'], ':c'=>$qrVal]);
        $generatedCount++;
      }
      
      echo json_encode(['success'=>true,'message'=>"Generated QR codes for {$generatedCount} rooms"]);
      exit;
    }

    echo json_encode(['success'=>false,'message'=>'Invalid action']);
    exit;
  }

} catch (Throwable $e) {
  http_response_code(500);
  error_log($e->getMessage());
  echo json_encode(['success'=>false,'message'=>'Server error']);
}