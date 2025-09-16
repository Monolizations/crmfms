<?php
// /api/secretary/secretary.php
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $db = (new Database())->getConnection();
  requireAuth(['secretary']);

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'dashboard') {
      // Secretary dashboard data - similar to dean but focused on department/faculty management
      $total_faculty = $db->query("SELECT COUNT(*) as count FROM users u
                                   JOIN user_roles ur ON u.user_id = ur.user_id
                                   JOIN roles r ON ur.role_id = r.role_id
                                   WHERE r.role_name IN ('faculty', 'program head')
                                   AND u.status = 'active'")->fetch()['count'];

      $present_today = $db->query("SELECT COUNT(DISTINCT a.user_id) as count
                                   FROM attendance a
                                   JOIN user_roles ur ON a.user_id = ur.user_id
                                   JOIN roles r ON ur.role_id = r.role_id
                                   WHERE r.role_name IN ('faculty', 'program head')
                                   AND DATE(a.check_in_time) = CURDATE()")->fetch()['count'];

      $pending_leaves = $db->query("SELECT COUNT(*) as count FROM leave_requests
                                    WHERE status = 'pending'")->fetch()['count'];

      $active_rooms = $db->query("SELECT COUNT(*) as count FROM rooms
                                  WHERE status = 'active'")->fetch()['count'];

      echo json_encode([
        'total_faculty' => $total_faculty,
        'present_today' => $present_today,
        'pending_leaves' => $pending_leaves,
        'active_rooms' => $active_rooms
      ]);
      exit;
    }
  }

  echo json_encode(['success' => false, 'message' => 'Invalid action']);

} catch (Throwable $e) {
  http_response_code(500);
  error_log($e->getMessage());
  echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>