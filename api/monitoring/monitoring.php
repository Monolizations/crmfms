<?php
// /api/monitoring/monitoring.php
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $db = (new Database())->getConnection();

   // Skip auth for suggestions and building_checkins (public data)
   $action = $_GET['action'] ?? '';
   if ($action !== 'suggestions' && $action !== 'building_checkins') {
     requireAuth($db);
   }

  $uid = $_SESSION['uid'] ?? null;
  $roles = $_SESSION['roles'] ?? [];

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'list') {
      $stmt = $db->prepare("SELECT * FROM monitoring_rounds WHERE program_head_id=:u ORDER BY round_time DESC");
      $stmt->execute([':u'=>$uid]);
      echo json_encode(['items'=>$stmt->fetchAll()]);
      exit;
    }

    if ($action === 'suggestions') {
      // Generate dynamic suggestions instead of querying non-existent table
      $suggestions = [];

      // 1. Buildings not monitored in the last 7 days
      $stmt = $db->prepare("
          SELECT b.name as building_name, b.building_id,
                 MAX(mr.round_time) as last_monitoring
          FROM buildings b
          LEFT JOIN monitoring_rounds mr ON b.building_id = mr.building_id
          GROUP BY b.building_id, b.name
          HAVING last_monitoring IS NULL OR last_monitoring < DATE_SUB(NOW(), INTERVAL 7 DAY)
          ORDER BY last_monitoring ASC
          LIMIT 3
      ");
      $stmt->execute();
      $unmonitoredBuildings = $stmt->fetchAll();

      foreach ($unmonitoredBuildings as $building) {
          $daysSince = $building['last_monitoring']
              ? floor((strtotime('now') - strtotime($building['last_monitoring'])) / (60*60*24))
              : 'never';

          $suggestions[] = [
              'building' => $building['building_name'],
              'note' => "Not monitored for {$daysSince} days - consider scheduling a round",
              'type' => 'monitoring_gap',
              'priority' => 'high',
              'created_at' => date('Y-m-d H:i:s')
          ];
      }

      // 2. Active system alerts
      $stmt = $db->prepare("
          SELECT message, type, priority
          FROM system_alerts
          WHERE is_active = 1
          AND (expires_at IS NULL OR expires_at > NOW())
          ORDER BY priority DESC, created_at DESC
          LIMIT 3
      ");
      $stmt->execute();
      $activeAlerts = $stmt->fetchAll();

      foreach ($activeAlerts as $alert) {
          $suggestions[] = [
              'building' => 'System Wide',
              'note' => $alert['message'],
              'type' => 'system_alert',
              'priority' => $alert['priority'] == 3 ? 'high' : ($alert['priority'] == 2 ? 'medium' : 'low'),
              'created_at' => date('Y-m-d H:i:s')
          ];
      }

      // 3. Peak attendance times analysis
      $stmt = $db->prepare("
          SELECT
              HOUR(a.check_in_time) as hour,
              COUNT(*) as checkin_count,
              b.name as building_name
          FROM attendance a
          JOIN rooms r ON a.room_id = r.room_id
          JOIN floors f ON r.floor_id = f.floor_id
          JOIN buildings b ON f.building_id = b.building_id
          WHERE DATE(a.check_in_time) = CURDATE()
          GROUP BY HOUR(a.check_in_time), b.name
          ORDER BY checkin_count DESC
          LIMIT 3
      ");
      $stmt->execute();
      $peakHours = $stmt->fetchAll();

      foreach ($peakHours as $peak) {
          if ($peak['checkin_count'] > 10) { // Only suggest for high activity
              $suggestions[] = [
                  'building' => $peak['building_name'],
                  'note' => "High activity at {$peak['hour']}:00 ({$peak['checkin_count']} check-ins) - consider monitoring",
                  'type' => 'peak_activity',
                  'priority' => 'medium',
                  'created_at' => date('Y-m-d H:i:s')
              ];
          }
      }

      // 4. Rooms with frequent check-ins today
      $stmt = $db->prepare("
          SELECT
              r.name as room_name,
              b.name as building_name,
              COUNT(a.attendance_id) as activity_count
          FROM attendance a
          JOIN rooms r ON a.room_id = r.room_id
          JOIN floors f ON r.floor_id = f.floor_id
          JOIN buildings b ON f.building_id = b.building_id
          WHERE DATE(a.check_in_time) = CURDATE()
          GROUP BY r.room_id, r.name, b.name
          HAVING activity_count > 5
          ORDER BY activity_count DESC
          LIMIT 3
      ");
      $stmt->execute();
      $activeRooms = $stmt->fetchAll();

      foreach ($activeRooms as $room) {
          $suggestions[] = [
              'building' => $room['building_name'],
              'note' => "Room {$room['room_name']} has high activity ({$room['activity_count']} visits today)",
              'type' => 'room_activity',
              'priority' => 'medium',
              'created_at' => date('Y-m-d H:i:s')
          ];
      }

      // 5. Pending leave requests count
      $stmt = $db->prepare("
          SELECT COUNT(*) as total_pending_leaves
          FROM leave_requests
          WHERE status = 'pending'
      ");
      $stmt->execute();
      $pendingLeaves = $stmt->fetch();

      if ($pendingLeaves['total_pending_leaves'] > 0) {
          $suggestions[] = [
              'building' => 'System Wide',
              'note' => "{$pendingLeaves['total_pending_leaves']} pending leave requests - review faculty availability",
              'type' => 'leave_requests',
              'priority' => 'low',
              'created_at' => date('Y-m-d H:i:s')
          ];
      }

      // If no automated suggestions, provide some general tips
      if (empty($suggestions)) {
          $suggestions = [
              [
                  'building' => 'General',
                  'note' => 'All buildings monitored recently - great job maintaining coverage!',
                  'type' => 'general',
                  'priority' => 'low',
                  'created_at' => date('Y-m-d H:i:s')
              ],
              [
                  'building' => 'General',
                  'note' => 'Consider monitoring during peak hours (9-11 AM) for optimal coverage',
                  'type' => 'general',
                  'priority' => 'low',
                  'created_at' => date('Y-m-d H:i:s')
              ]
          ];
      }

      // Limit to top 10 suggestions and sort by priority
      $priorityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
      usort($suggestions, function($a, $b) use ($priorityOrder) {
          return $priorityOrder[$b['priority']] <=> $priorityOrder[$a['priority']];
      });

      $suggestions = array_slice($suggestions, 0, 10);

       echo json_encode(['items'=>$suggestions]);
       exit;
     }

      if ($action === 'building_checkins') {
        $stmt = $db->prepare("
          SELECT b.building_id, b.name, COUNT(a.attendance_id) as checkins_today
          FROM buildings b
          LEFT JOIN floors f ON b.building_id = f.building_id
          LEFT JOIN rooms r ON f.floor_id = r.floor_id
          LEFT JOIN attendance a ON r.room_id = a.room_id AND DATE(a.check_in_time) = CURDATE()
          GROUP BY b.building_id, b.name
          ORDER BY b.name
        ");
        $stmt->execute();
        $buildings = $stmt->fetchAll();

       // Calculate status based on check-ins
       foreach ($buildings as &$building) {
         $count = (int)$building['checkins_today'];
         if ($count < 10) {
           $building['status'] = 'low';
         } elseif ($count > 50) {
           $building['status'] = 'high';
         } else {
           $building['status'] = 'normal';
         }
       }

       echo json_encode(['buildings'=>$buildings]);
       exit;
     }

     echo json_encode(['items'=>[]]);
     exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'create') {
      if (!in_array('program_head', $roles)) {
        http_response_code(403);
        echo json_encode(['success'=>false,'message'=>'Only Program Heads can record rounds']);
        exit;
      }
      $stmt = $db->prepare("INSERT INTO monitoring_rounds(program_head_id,building_id,notes) VALUES(:u,:b,:n)");
      $stmt->execute([':u'=>$uid, ':b'=>$input['building_id'], ':n'=>$input['notes'] ?? null]);
      echo json_encode(['success'=>true,'message'=>'Monitoring round saved']);
      exit;
    }

    echo json_encode(['success'=>false,'message'=>'Invalid action']);
    exit;
  }

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Server error']);
}
