<?php
// /api/reports/reports.php
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $db = (new Database())->getConnection();
  requireAuth($db);

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $type = $input['type'] ?? 'attendance';
    $start = $input['start_date'] ?: '1970-01-01';
    $end = $input['end_date'] ?: date('Y-m-d');

    if ($type === 'attendance') {
      $stmt = $db->prepare("SELECT a.log_id,u.employee_id,CONCAT(u.first_name,' ',u.last_name) AS name,
                                   a.type,a.time_in,a.time_out,a.status
                            FROM attendance_logs a
                            JOIN users u ON u.user_id=a.user_id
                            WHERE DATE(a.time_in) BETWEEN :s AND :e
                            ORDER BY a.time_in DESC");
      $stmt->execute([':s'=>$start, ':e'=>$end]);
      echo json_encode(['items'=>$stmt->fetchAll()]);
      exit;
    }

    if ($type === 'leaves') {
      $stmt = $db->prepare("SELECT l.leave_id,u.employee_id,CONCAT(u.first_name,' ',u.last_name) AS name,
                                   l.start_date,l.end_date,l.reason,l.status
                            FROM leave_requests l
                            JOIN users u ON u.user_id=l.user_id
                            WHERE l.start_date BETWEEN :s AND :e
                            ORDER BY l.requested_at DESC");
      $stmt->execute([':s'=>$start, ':e'=>$end]);
      echo json_encode(['items'=>$stmt->fetchAll()]);
      exit;
    }

    if ($type === 'delinquents') {
      $stmt = $db->prepare("SELECT u.employee_id,CONCAT(u.first_name,' ',u.last_name) AS name,
                                   COUNT(*) AS missed_count
                            FROM attendance_logs a
                            JOIN users u ON u.user_id=a.user_id
                            WHERE a.status='missed_timeout'
                            AND DATE(a.time_in) BETWEEN :s AND :e
                            GROUP BY u.user_id
                            HAVING COUNT(*) > 0
                            ORDER BY missed_count DESC");
      $stmt->execute([':s'=>$start, ':e'=>$end]);
      echo json_encode(['items'=>$stmt->fetchAll()]);
      exit;
    }

    echo json_encode(['items'=>[]]);
    exit;
  }

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Server error']);
}
