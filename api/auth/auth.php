<?php
// /api/auth/auth.php
ini_set('display_errors', 0);
error_reporting(0);
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

// Start session
session_start();

try {
  $db = (new Database())->getConnection();
  $input = json_decode(file_get_contents('php://input'), true);

  $email = sanitize($input['email'] ?? '');
  $password = $input['password'] ?? '';

  if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success'=>false, 'message'=>'Missing email or password']);
    exit;
  }

  $stmt = $db->prepare("SELECT u.*, GROUP_CONCAT(r.role_name) as roles FROM users u LEFT JOIN user_roles ur ON u.user_id = ur.user_id LEFT JOIN roles r ON ur.role_id = r.role_id WHERE u.email = :e AND u.status='active' GROUP BY u.user_id LIMIT 1");
  $stmt->execute([':e' => $email]);
  $user = $stmt->fetch();

  if (!$user) {
    echo json_encode(['success'=>false, 'message'=>'User not found']);
    exit;
  }

  // Verify password using password_verify() for bcrypt hashes
  if (!password_verify($password, $user['password_hash'])) {
    echo json_encode(['success'=>false, 'message'=>'Invalid credentials']);
    exit;
  }

  $roles = $user['roles'] ? explode(',', $user['roles']) : [];
  $_SESSION['uid'] = $user['user_id'];
  $_SESSION['roles'] = $roles;

  echo json_encode([
    'success' => true,
    'message' => 'Login successful',
    'user' => [
      'user_id' => $user['user_id'],
      'employee_id' => $user['employee_id'],
      'name' => $user['first_name']." ".$user['last_name'],
      'email' => $user['email'],
      'roles' => $roles
    ]
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Server error']);
}
