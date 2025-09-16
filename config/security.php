<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireAuth(array $requiredRoles = []) {
  if (!isset($_SESSION['uid'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
  }

  if (empty($requiredRoles)) {
    return; // No specific roles required, just authentication
  }

  $userRoles = $_SESSION['roles'] ?? [];
  if (!is_array($userRoles)) {
    $userRoles = [];
  }
  $hasPermission = !empty(array_intersect($userRoles, $requiredRoles));

  if (!$hasPermission) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
  }
}

function sanitize($s) { return trim((string)$s); }
