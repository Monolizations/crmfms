<?php
// /api/buildings/buildings.php
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
  requireAuth($db, ['admin', 'dean']); // Only admin and dean can manage buildings

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->query("SELECT * FROM buildings ORDER BY name ASC");
    $items = $stmt->fetchAll();
    echo json_encode(['items'=>$items]);
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'create') {
      validateRequiredFields($input, ['name']);
      $stmt = $db->prepare("INSERT INTO buildings(name, description) VALUES(:n, :d)");
      $stmt->execute([
        ':n'=>$input['name'],
        ':d'=>$input['description'] ?? null
      ]);
      echo json_encode(['success'=>true,'message'=>'Building created successfully']);
      exit;
    }

    if ($action === 'update') {
      validateRequiredFields($input, ['building_id', 'name']);
      $stmt = $db->prepare("UPDATE buildings SET name=:n, description=:d WHERE building_id=:id");
      $stmt->execute([
        ':n'=>$input['name'],
        ':d'=>$input['description'] ?? null,
        ':id'=>$input['building_id']
      ]);
      echo json_encode(['success'=>true,'message'=>'Building updated successfully']);
      exit;
    }

    if ($action === 'delete') {
      validateRequiredFields($input, ['building_id']);
      $stmt = $db->prepare("DELETE FROM buildings WHERE building_id=:id");
      $stmt->execute([':id'=>$input['building_id']]);
      echo json_encode(['success'=>true,'message'=>'Building deleted successfully']);
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
