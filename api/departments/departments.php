<?php
// /api/departments/departments.php
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $db = (new Database())->getConnection();
  requireAuth(['admin', 'dean', 'faculty']);

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get department QR codes
    $stmt = $db->query("SELECT q.*, 'Department Check-in' as name
                        FROM qr_codes q
                        WHERE q.code_type='department'
                        ORDER BY q.created_at ASC");
    $items = $stmt->fetchAll();

    // Add QR code URL to each item
    foreach ($items as &$item) {
        $item['qr_code_url'] = '/crmfms/api/qr/generate.php?data=' . urlencode($item['code_value']);
    }
    unset($item); // Break the reference with the last element

    echo json_encode(['items'=>$items]);
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'create') {
      requireAuth(['admin', 'dean']); // Only admin and dean can create department QR codes

      $name = sanitize($input['name'] ?? 'Department Check-in');
      
      // Check if a permanent department QR code already exists
      $stmt = $db->prepare("SELECT * FROM qr_codes WHERE code_type='department' AND code_value LIKE 'QR-DEPT-PERMANENT%' LIMIT 1");
      $stmt->execute();
      $existing = $stmt->fetch();
      
      if ($existing) {
        echo json_encode(['success'=>false, 'message'=>'Permanent department QR code already exists']);
        exit;
      }
      
      // Create a permanent department QR code with structured data
      $deptData = [
        'type' => 'department',
        'department_id' => 1,
        'department_name' => 'Main Department',
        'purpose' => 'Time In/Time Out',
        'status' => 'active',
        'created' => date('Y-m-d H:i:s')
      ];
      
      $code_value = json_encode($deptData);

      $stmt = $db->prepare("INSERT INTO qr_codes(code_type, ref_id, code_value) VALUES('department', 1, :c)");
      $stmt->execute([':c'=>$code_value]);

      $qr_code_url = '/crmfms/api/qr/generate.php?data=' . urlencode($code_value);

      echo json_encode(['success'=>true, 'message'=>'Permanent department QR code created', 'qr_code_url'=>$qr_code_url]);
      exit;
    }

    if ($action === 'delete') {
      requireAuth(['admin', 'dean']); // Only admin and dean can delete

      $code_id = (int)($input['code_id'] ?? 0);

      // Prevent deletion of permanent department QR codes
      $stmt = $db->prepare("SELECT code_value FROM qr_codes WHERE code_id=:id AND code_type='department'");
      $stmt->execute([':id'=>$code_id]);
      $qr = $stmt->fetch();

      if ($qr && strpos($qr['code_value'], 'QR-DEPT-SECRETARY-') === 0) {
        echo json_encode(['success'=>false, 'message'=>'Cannot delete permanent department QR code']);
        exit;
      }

      $stmt = $db->prepare("DELETE FROM qr_codes WHERE code_id=:id AND code_type='department'");
      $stmt->execute([':id'=>$code_id]);

      echo json_encode(['success'=>true, 'message'=>'Department QR code deleted']);
      exit;
    }

    echo json_encode(['success'=>false, 'message'=>'Invalid action']);
    exit;
  }

} catch (Throwable $e) {
  http_response_code(500);
  error_log($e->getMessage());
  echo json_encode(['success'=>false, 'message'=>'Server error']);
}
?>