<?php
// /api/monitoring/monitoring.php
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $db = (new Database())->getConnection();
  requireAuth($db);

  $uid = $_SESSION['uid'];
  $role = $_SESSION['role'];

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'list') {
      $stmt = $db->prepare("SELECT * FROM monitoring_rounds WHERE program_head_id=:u ORDER BY round_time DESC");
      $stmt->execute([':u'=>$uid]);
      echo json_encode(['items'=>$stmt->fetchAll()]);
      exit;
    }

    if ($action === 'suggestions') {
      // simple: load from monitoring_suggestions table
      $stmt = $db->query("SELECT * FROM monitoring_suggestions ORDER BY created_at DESC LIMIT 10");
      echo json_encode(['items'=>$stmt->fetchAll()]);
      exit;
    }

    echo json_encode(['items'=>[]]);
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'create') {
      if ($role !== 'program_head') {
        http_response_code(403);
        echo json_encode(['success'=>false,'message'=>'Only Program Heads can record rounds']);
        exit;
      }
      $stmt = $db->prepare("INSERT INTO monitoring_rounds(program_head_id,building_id,notes) VALUES(:u,:b,:n)");
      $stmt->execute([':u'=>$uid, ':b'=>$input['building_id'], ':n'=>$input['notes'] ?? null]);
      echo json_encode(['success'=>true,'message'=>'Monitoring round saved']);
      exit;
    }

    echo json_encode(['success'=>false,'message'=>'Invalid action']);
    exit;
  }

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Server error']);
}
