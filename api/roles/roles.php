<?php
// /api/roles/roles.php
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $db = (new Database())->getConnection();
  requireAuth(['admin', 'dean', 'secretary']);

  $stmt = $db->query("SELECT role_id, role_name FROM roles ORDER BY role_name ASC");
  $roles = $stmt->fetchAll();

  echo json_encode(['success' => true, 'roles' => $roles]);
} catch (Throwable $e) {
  http_response_code(500);
  error_log($e->getMessage());
  echo json_encode(['success' => false, 'message' => 'Server error']);
}
