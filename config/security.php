<?php
session_start();

function requireAuth(PDO $db, array $requiredRoles = []) {
  if (!isset($_SESSION['uid'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
  }

  if (empty($requiredRoles)) {
    return; // No specific roles required, just authentication
  }

  $userRoles = $_SESSION['roles'] ?? [];
  $hasPermission = !empty(array_intersect($userRoles, $requiredRoles));

  if (!$hasPermission) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
  }
}

function sanitize($s) { return trim((string)$s); }
