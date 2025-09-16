<?php
// /api/floors/floors.php
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

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    requireAuth(['admin', 'dean', 'secretary']); // Allow secretaries to view floors for room management
    $building_id = $_GET['building_id'] ?? null;
    if ($building_id) {
      $stmt = $db->prepare("SELECT * FROM floors WHERE building_id = :bid ORDER BY floor_number ASC");
      $stmt->execute([':bid'=>$building_id]);
    } else {
      $stmt = $db->query("SELECT f.*, b.name as building_name FROM floors f JOIN buildings b ON f.building_id = b.building_id ORDER BY b.name, f.floor_number ASC");
    }
    $items = $stmt->fetchAll();
    echo json_encode(['items'=>$items]);
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAuth(['admin', 'dean']); // Only admin and dean can modify floors
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'create') {
      validateRequiredFields($input, ['building_id', 'floor_number']);
      $stmt = $db->prepare("INSERT INTO floors(building_id, floor_number, name, description) VALUES(:bid, :fn, :n, :d)");
      $stmt->execute([
        ':bid'=>$input['building_id'],
        ':fn'=>$input['floor_number'],
        ':n'=>$input['name'] ?? null,
        ':d'=>$input['description'] ?? null
      ]);
      echo json_encode(['success'=>true,'message'=>'Floor created successfully']);
      exit;
    }

    if ($action === 'update') {
      validateRequiredFields($input, ['floor_id', 'building_id', 'floor_number']);
      $stmt = $db->prepare("UPDATE floors SET building_id=:bid, floor_number=:fn, name=:n, description=:d WHERE floor_id=:id");
      $stmt->execute([
        ':bid'=>$input['building_id'],
        ':fn'=>$input['floor_number'],
        ':n'=>$input['name'] ?? null,
        ':d'=>$input['description'] ?? null,
        ':id'=>$input['floor_id']
      ]);
      echo json_encode(['success'=>true,'message'=>'Floor updated successfully']);
      exit;
    }

    if ($action === 'delete') {
      validateRequiredFields($input, ['floor_id']);
      $stmt = $db->prepare("DELETE FROM floors WHERE floor_id=:id");
      $stmt->execute([':id'=>$input['floor_id']]);
      echo json_encode(['success'=>true,'message'=>'Floor deleted successfully']);
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
