<?php
// /api/schedules/schedules.php
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $db = (new Database())->getConnection();

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Allow faculty to view schedules
    requireAuth($db, ['admin', 'dean', 'secretary', 'faculty']);
     $stmt = $db->query("SELECT s.*, u.first_name, u.last_name, r.name as room_name, r.room_code
                         FROM schedules s
                         JOIN users u ON s.faculty_id = u.user_id
                         JOIN rooms r ON s.room_id = r.room_id
                         ORDER BY s.day_of_week, s.start_time");
     $items = $stmt->fetchAll();
     echo json_encode(['items'=>$items]);
     exit;
   }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only secretary, dean, or admin can create/modify schedules
    requireAuth($db, ['admin', 'dean', 'secretary']);

    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'create') {
      $stmt = $db->prepare("INSERT INTO schedules (faculty_id, room_id, day_of_week, start_time, end_time, status) 
                            VALUES (:f,:r,:d,:s,:e,'active')");
      $stmt->execute([
        ':f'=>$input['faculty_id'], ':r'=>$input['room_id'],
        ':d'=>$input['day_of_week'], ':s'=>$input['start_time'], ':e'=>$input['end_time']
      ]);
      echo json_encode(['success'=>true,'message'=>'Schedule created']);
      exit;
    }

    if ($action === 'toggle') {
      $id = (int)$input['schedule_id'];
      $stmt = $db->prepare("UPDATE schedules 
                            SET status = CASE WHEN status='active' THEN 'inactive' ELSE 'active' END
                            WHERE schedule_id=:id");
      $stmt->execute([':id'=>$id]);
      echo json_encode(['success'=>true,'message'=>'Schedule status updated']);
      exit;
    }

    echo json_encode(['success'=>false,'message'=>'Invalid action']);
    exit;
  }

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Server error']);
}
