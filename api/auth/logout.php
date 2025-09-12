<?php
// /api/auth/logout.php
require_once __DIR__ . '/../../config/cors.php';
session_start();
session_unset();
session_destroy();

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'message' => 'Logged out successfully'
]);
exit;
