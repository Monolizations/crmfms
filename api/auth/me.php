<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../config/security.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!isset($_SESSION['uid'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

$roles = $_SESSION['roles'] ?? [];

// Return user data from session
$userData = [
    'success' => true,
    'user_id' => $_SESSION['uid'],
    'roles' => $roles
];

echo json_encode($userData);
?>
