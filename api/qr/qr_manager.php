<?php
// /api/qr/qr_manager.php
// QR Code Management API - Provides additional QR code functionality
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
  requireAuth($db, ['admin', 'dean', 'secretary']);

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'list') {
      // List all QR codes with their associated data
      $stmt = $db->query("SELECT q.*, 
                          CASE 
                            WHEN q.code_type = 'faculty' THEN CONCAT(u.first_name, ' ', u.last_name, ' (', u.employee_id, ')')
                            WHEN q.code_type = 'room' THEN CONCAT(r.name, ' (', r.room_code, ')')
                            ELSE q.code_type
                          END as display_name,
                          CASE 
                            WHEN q.code_type = 'faculty' THEN u.employee_id
                            WHEN q.code_type = 'room' THEN r.room_code
                            ELSE ''
                          END as reference_code
                          FROM qr_codes q
                          LEFT JOIN users u ON q.code_type = 'faculty' AND q.ref_id = u.user_id
                          LEFT JOIN rooms r ON q.code_type = 'room' AND q.ref_id = r.room_id
                          ORDER BY q.created_at DESC");
      $items = $stmt->fetchAll();
      
      // Add QR code URL to each item
      foreach ($items as &$item) {
        $item['qr_code_url'] = '/crmfms/api/qr/generate.php?data=' . urlencode($item['code_value']);
        $item['download_url'] = '/crmfms/api/qr/qr_manager.php?action=download&code_id=' . $item['code_id'];
      }
      unset($item);

      echo json_encode(['items'=>$items]);
      exit;
    }
    
    if ($action === 'download') {
      $code_id = $_GET['code_id'] ?? '';
      
      if (empty($code_id)) {
        http_response_code(400);
        echo json_encode(['success'=>false, 'message'=>'Code ID is required']);
        exit;
      }
      
      // Get QR code data
      $stmt = $db->prepare("SELECT * FROM qr_codes WHERE code_id = :id LIMIT 1");
      $stmt->execute([':id'=>$code_id]);
      $qr = $stmt->fetch();
      
      if (!$qr) {
        http_response_code(404);
        echo json_encode(['success'=>false, 'message'=>'QR code not found']);
        exit;
      }
      
      // Generate filename based on type and reference
      $filename = $qr['code_type'] . '_' . $qr['ref_id'] . '_qr.png';
      
      // Set headers for download
      header('Content-Type: image/png');
      header('Content-Disposition: attachment; filename="' . $filename . '"');
      header('Cache-Control: no-cache, no-store, must-revalidate');
      header('Pragma: no-cache');
      header('Expires: 0');
      
      // Generate and output QR code using local implementation
      require_once __DIR__ . '/simple_qr.php';
      
      try {
          $qr = new SimpleQR($qr['code_value'], 300, 20);
          $qr->output('png');
      } catch (Exception $e) {
          // Fallback error image
          $im = imagecreatetruecolor(300, 300);
          $bg = imagecolorallocate($im, 255, 255, 255);
          $text_color = imagecolorallocate($im, 0, 0, 0);
          imagefill($im, 0, 0, $bg);
          imagestring($im, 3, 50, 140, 'QR Code Error', $text_color);
          imagepng($im);
          imagedestroy($im);
      }
      exit;
    }
    
    if ($action === 'preview') {
      $code_id = $_GET['code_id'] ?? '';
      
      if (empty($code_id)) {
        http_response_code(400);
        echo json_encode(['success'=>false, 'message'=>'Code ID is required']);
        exit;
      }
      
      // Get QR code data
      $stmt = $db->prepare("SELECT * FROM qr_codes WHERE code_id = :id LIMIT 1");
      $stmt->execute([':id'=>$code_id]);
      $qr = $stmt->fetch();
      
      if (!$qr) {
        http_response_code(404);
        echo json_encode(['success'=>false, 'message'=>'QR code not found']);
        exit;
      }
      
      // Set content type to PNG image
      header('Content-Type: image/png');
      
      // Generate QR code with larger size for preview using local implementation
      require_once __DIR__ . '/simple_qr.php';
      
      try {
          $qr = new SimpleQR($qr['code_value'], 400, 30);
          $qr->output('png');
      } catch (Exception $e) {
          // Fallback error image
          $im = imagecreatetruecolor(400, 400);
          $bg = imagecolorallocate($im, 255, 255, 255);
          $text_color = imagecolorallocate($im, 0, 0, 0);
          imagefill($im, 0, 0, $bg);
          imagestring($im, 4, 100, 190, 'QR Code Error', $text_color);
          imagepng($im);
          imagedestroy($im);
      }
      exit;
    }
    
    echo json_encode(['success'=>false, 'message'=>'Invalid action']);
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'regenerate') {
      validateRequiredFields($input, ['code_id']);
      requireAuth($db, ['admin', 'dean']); // Only admin and dean can regenerate
      
      $code_id = (int)$input['code_id'];
      
      // Get existing QR code
      $stmt = $db->prepare("SELECT * FROM qr_codes WHERE code_id = :id LIMIT 1");
      $stmt->execute([':id'=>$code_id]);
      $qr = $stmt->fetch();
      
      if (!$qr) {
        http_response_code(404);
        echo json_encode(['success'=>false, 'message'=>'QR code not found']);
        exit;
      }
      
      // Generate new QR code value with timestamp for uniqueness
      $timestamp = time();
      $newQrValue = $qr['code_value'] . '_' . $timestamp;
      
      // Update QR code
      $stmt = $db->prepare("UPDATE qr_codes SET code_value = :new_value, updated_at = NOW() WHERE code_id = :id");
      $stmt->execute([':new_value'=>$newQrValue, ':id'=>$code_id]);
      
      $qr_code_url = '/crmfms/api/qr/generate.php?data=' . urlencode($newQrValue);
      
      echo json_encode(['success'=>true,'message'=>'QR code regenerated successfully', 'qr_code_url'=>$qr_code_url]);
      exit;
    }
    
    if ($action === 'bulk_generate') {
      requireAuth($db, ['admin', 'dean']); // Only admin and dean can bulk generate
      
      $type = $input['type'] ?? 'all'; // 'faculty', 'room', or 'all'
      $generatedCount = 0;
      
      if ($type === 'faculty' || $type === 'all') {
        // Generate QR codes for faculty without them
        $stmt = $db->query("SELECT u.user_id, u.employee_id 
                            FROM users u
                            LEFT JOIN qr_codes q ON q.code_type='faculty' AND q.ref_id=u.user_id
                            WHERE q.code_id IS NULL");
        $facultyWithoutQR = $stmt->fetchAll();
        
        foreach ($facultyWithoutQR as $faculty) {
          $qrVal = "QR-FACULTY-".$faculty['employee_id'];
          $stmt = $db->prepare("INSERT INTO qr_codes(code_type,ref_id,code_value) VALUES('faculty',:id,:c)");
          $stmt->execute([':id'=>$faculty['user_id'], ':c'=>$qrVal]);
          $generatedCount++;
        }
      }
      
      if ($type === 'room' || $type === 'all') {
        // Generate QR codes for rooms without them
        $stmt = $db->query("SELECT r.room_id, r.room_code 
                            FROM rooms r
                            LEFT JOIN qr_codes q ON q.code_type='room' AND q.ref_id=r.room_id
                            WHERE q.code_id IS NULL");
        $roomsWithoutQR = $stmt->fetchAll();
        
        foreach ($roomsWithoutQR as $room) {
          $qrVal = "QR-ROOM-".$room['room_code'];
          $stmt = $db->prepare("INSERT INTO qr_codes(code_type,ref_id,code_value) VALUES('room',:id,:c)");
          $stmt->execute([':id'=>$room['room_id'], ':c'=>$qrVal]);
          $generatedCount++;
        }
      }
      
      echo json_encode(['success'=>true,'message'=>"Generated {$generatedCount} QR codes"]);
      exit;
    }
    
    if ($action === 'delete') {
      validateRequiredFields($input, ['code_id']);
      requireAuth($db, ['admin', 'dean']); // Only admin and dean can delete
      
      $code_id = (int)$input['code_id'];
      $stmt = $db->prepare("DELETE FROM qr_codes WHERE code_id = :id");
      $stmt->execute([':id'=>$code_id]);
      
      echo json_encode(['success'=>true,'message'=>'QR code deleted successfully']);
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
