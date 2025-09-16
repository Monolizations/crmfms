<?php
// /api/dean/dean.php
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $db = (new Database())->getConnection();
  requireAuth($db);

  // Check if user has dean role
  $userRoles = $_SESSION['roles'] ?? [];
  if (!in_array('dean', $userRoles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'dashboard') {
      // Comprehensive dean dashboard data
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

    if ($action === 'faculty_performance') {
      // Faculty performance metrics
      $stmt = $db->query("SELECT
                          CONCAT(u.first_name, ' ', u.last_name) as faculty_name,
                          COUNT(DISTINCT DATE(a.check_in_time)) as days_present,
                          ROUND(COUNT(DISTINCT DATE(a.check_in_time)) / 30 * 100, 1) as attendance_rate,
                          SUM(CASE WHEN TIME(a.check_out_time) > '17:00:00' THEN 1 ELSE 0 END) as overtime_days
                          FROM users u
                          LEFT JOIN attendance a ON u.user_id = a.user_id
                          AND a.check_in_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                          JOIN user_roles ur ON u.user_id = ur.user_id
                          JOIN roles r ON ur.role_id = r.role_id
                          WHERE r.role_name IN ('faculty', 'program head')
                          AND u.status = 'active'
                          GROUP BY u.user_id, u.first_name, u.last_name
                          HAVING attendance_rate < 80 OR overtime_days > 5
                          ORDER BY attendance_rate ASC");

      $performance_issues = $stmt->fetchAll();
      echo json_encode(['items' => $performance_issues]);
      exit;
    }

    if ($action === 'pending_approvals') {
      // Leave requests pending dean approval
      $stmt = $db->query("SELECT lr.*,
                          CONCAT(u.first_name, ' ', u.last_name) as faculty_name,
                          r.role_name
                          FROM leave_requests lr
                          JOIN users u ON lr.user_id = u.user_id
                          LEFT JOIN user_roles ur ON u.user_id = ur.user_id
                          LEFT JOIN roles r ON ur.role_id = r.role_id
                          WHERE lr.status = 'pending'
                          ORDER BY lr.created_at DESC");

      $approvals = $stmt->fetchAll();
      echo json_encode(['items' => $approvals]);
      exit;
    }

    if ($action === 'department_analytics') {
      // Department analytics data
      $monthly_trend = $db->query("SELECT
                                   DATE_FORMAT(check_in_time, '%Y-%m') as month,
                                   COUNT(DISTINCT user_id) as unique_attendees,
                                   COUNT(*) as total_checkins
                                   FROM attendance
                                   WHERE check_in_time >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                                   GROUP BY DATE_FORMAT(check_in_time, '%Y-%m')
                                   ORDER BY month DESC")->fetchAll();

       $room_utilization = $db->query("SELECT r.name as room_name, r.building,
                                       COUNT(a.attendance_id) as usage_count
                                       FROM rooms r
                                       LEFT JOIN attendance a ON r.room_id = a.room_id
                                       AND a.check_in_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                                       WHERE r.status = 'active'
                                       GROUP BY r.room_id, r.name, r.building
                                       ORDER BY usage_count DESC
                                       LIMIT 10")->fetchAll();

      echo json_encode([
        'monthly_trend' => $monthly_trend,
        'room_utilization' => $room_utilization
      ]);
      exit;
    }
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'approve_leave') {
      $leave_id = (int)$input['leave_id'];
      $decision = $input['decision']; // 'approve' or 'reject'
      $comments = $input['comments'] ?? '';

      if (!in_array($decision, ['approve', 'reject'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid decision']);
        exit;
      }

      $status = $decision === 'approve' ? 'approved' : 'rejected';

      $stmt = $db->prepare("UPDATE leave_requests
                           SET status = :status, approved_by = :approved_by,
                           approval_date = NOW(), comments = :comments
                           WHERE leave_id = :leave_id AND status = 'pending'");

      $stmt->execute([
        ':status' => $status,
        ':approved_by' => $_SESSION['uid'],
        ':comments' => $comments,
        ':leave_id' => $leave_id
      ]);

      if ($stmt->rowCount() > 0) {
        // Log the approval action
        $log_stmt = $db->prepare("INSERT INTO audit_trail (user_id, action, details)
                                 VALUES (:user_id, :action, :details)");
        $log_stmt->execute([
          ':user_id' => $_SESSION['uid'],
          ':action' => 'Leave ' . $decision,
          ':details' => 'Dean ' . $decision . 'd leave request ID: ' . $leave_id
        ]);

        echo json_encode(['success' => true, 'message' => 'Leave request ' . $decision . 'd']);
      } else {
        echo json_encode(['success' => false, 'message' => 'Leave request not found or already processed']);
      }
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