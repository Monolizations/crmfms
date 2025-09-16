<?php
// /api/admin/admin.php
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

try {
  requireAuth(['admin']);
  $db = (new Database())->getConnection();

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'users') {
      try {
        $page = (int)($_GET['page'] ?? 1);
        $per_page = 15;
        $offset = ($page - 1) * $per_page;

        // Get total count
        $count_stmt = $db->query("SELECT COUNT(*) as total FROM users");
        $total = $count_stmt->fetch()['total'];

        $stmt = $db->prepare("SELECT u.user_id, u.employee_id, u.first_name, u.last_name, u.email, u.status,
                              (SELECT GROUP_CONCAT(r.role_name)
                               FROM user_roles ur
                               LEFT JOIN roles r ON ur.role_id = r.role_id
                               WHERE ur.user_id = u.user_id) as roles
                              FROM users u
                              ORDER BY u.user_id ASC
                              LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $per_page, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        echo json_encode(['items'=>$stmt->fetchAll(), 'total'=>$total, 'page'=>$page, 'per_page'=>$per_page]);
      } catch (Throwable $e) {
        echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
      }
      exit;
    }

    if ($action === 'audit') {
      try {
        $where = [];
        $params = [];

        if (!empty($_GET['start_date'])) {
          $where[] = "DATE(a.created_at) >= ?";
          $params[] = $_GET['start_date'];
        }
        if (!empty($_GET['end_date'])) {
          $where[] = "DATE(a.created_at) <= ?";
          $params[] = $_GET['end_date'];
        }
        if (!empty($_GET['user_id'])) {
          $where[] = "a.user_id = ?";
          $params[] = $_GET['user_id'];
        }
        if (!empty($_GET['action_filter'])) {
          $where[] = "a.action LIKE ?";
          $params[] = '%' . $_GET['action_filter'] . '%';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $limit = $_GET['limit'] ?? null;
        if ($limit === 'all') {
          // For export, no limit
          $stmt = $db->prepare("SELECT a.id as audit_id, a.*, CONCAT(u.first_name,' ',u.last_name) AS user_name
                                FROM audit_trail a
                                LEFT JOIN users u ON u.user_id=a.user_id
                                $whereClause
                                ORDER BY a.created_at DESC");
          $stmt->execute($params);
          echo json_encode(['items'=>$stmt->fetchAll()]);
          exit;
        }

        $page = (int)($_GET['page'] ?? 1);
        $per_page = 5;
        $offset = ($page - 1) * $per_page;

        // Get total count
        $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM audit_trail a $whereClause");
        $count_stmt->execute($params);
        $total = $count_stmt->fetch()['total'];

        $stmt = $db->prepare("SELECT a.id as audit_id, a.*, CONCAT(u.first_name,' ',u.last_name) AS user_name
                              FROM audit_trail a
                              LEFT JOIN users u ON u.user_id=a.user_id
                              $whereClause
                              ORDER BY a.created_at DESC
                              LIMIT ? OFFSET ?");
        $paramCount = count($params);
        for ($i = 0; $i < $paramCount; $i++) {
          $stmt->bindValue($i + 1, $params[$i], PDO::PARAM_STR);
        }
        $stmt->bindValue($paramCount + 1, $per_page, PDO::PARAM_INT);
        $stmt->bindValue($paramCount + 2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        echo json_encode(['items'=>$stmt->fetchAll(), 'total'=>$total, 'page'=>$page, 'per_page'=>$per_page]);
      } catch (Throwable $e) {
        echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
      }
      exit;
    }

    if ($action === 'audit_users') {
      $stmt = $db->query("SELECT DISTINCT u.user_id, CONCAT(u.first_name,' ',u.last_name) AS name FROM users u JOIN audit_trail a ON u.user_id=a.user_id ORDER BY name");
      echo json_encode(['users'=>$stmt->fetchAll()]);
      exit;
    }

    if ($action === 'audit_actions') {
      $stmt = $db->query("SELECT DISTINCT action FROM audit_trail ORDER BY action");
      echo json_encode(['actions'=>$stmt->fetchAll(PDO::FETCH_COLUMN)]);
      exit;
    }

    if ($action === 'stats') {
      // Get comprehensive admin statistics
      $total_users = $db->query("SELECT COUNT(*) as count FROM users WHERE status='active'")->fetch()['count'];
      $today_checkins = $db->query("SELECT COUNT(DISTINCT user_id) as count FROM attendance WHERE DATE(check_in_time) = CURDATE()")->fetch()['count'];
      $pending_leaves = $db->query("SELECT COUNT(*) as count FROM leave_requests WHERE status='pending'")->fetch()['count'];

      echo json_encode([
        'total_users' => $total_users,
        'active_today' => $today_checkins,
        'pending_leaves' => $pending_leaves
      ]);
      exit;
    }

    if ($action === 'recent_activity') {
      $stmt = $db->query("SELECT
                          u.last_name,
                          CASE
                            WHEN a.room_id IS NOT NULL THEN CONCAT(r.name, ' ', r.room_code)
                            ELSE 'Department Office'
                          END as location_info,
                          TIMESTAMPDIFF(MINUTE, a.check_in_time, NOW()) as minutes_ago
                          FROM attendance a
                          JOIN users u ON u.user_id = a.user_id
                          LEFT JOIN rooms r ON a.room_id = r.room_id
                          ORDER BY a.check_in_time DESC LIMIT 20");

      $activities = $stmt->fetchAll();
      $formatted_activities = array_map(function($activity) {
        $time_ago = $activity['minutes_ago'] < 60
          ? $activity['minutes_ago'] . 'm ago'
          : round($activity['minutes_ago']/60) . 'h ago';
        return [
          'action' => $activity['last_name'] . ' checked in on ' . $activity['location_info'],
          'details' => '',
          'time_ago' => $time_ago
        ];
      }, $activities);

      echo json_encode(['items' => $formatted_activities]);
      exit;
    }

    echo json_encode(['items'=>[]]);
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'role') {
      // Get current role for audit
      $current_stmt = $db->prepare("SELECT role, CONCAT(first_name,' ',last_name) as name FROM users WHERE user_id=?");
      $current_stmt->execute([$input['user_id']]);
      $current = $current_stmt->fetch();

      $stmt = $db->prepare("UPDATE users SET role=:r WHERE user_id=:id");
      $stmt->execute([':r'=>$input['role'], ':id'=>$input['user_id']]);

      // Log the change
      $audit_stmt = $db->prepare("INSERT INTO audit_trail (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
      $audit_stmt->execute([
        $_SESSION['uid'],
        'ADMIN_USER_ROLE_UPDATE',
        "Changed role for {$current['name']} from {$current['role']} to {$input['role']}",
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      ]);

      echo json_encode(['success'=>true,'message'=>'Role updated']);
      exit;
    }

    if ($action === 'toggle') {
      // Get current status for audit
      $current_stmt = $db->prepare("SELECT status, CONCAT(first_name,' ',last_name) as name FROM users WHERE user_id=?");
      $current_stmt->execute([$input['user_id']]);
      $current = $current_stmt->fetch();

      $new_status = $current['status'] === 'active' ? 'inactive' : 'active';

      $stmt = $db->prepare("UPDATE users SET status=? WHERE user_id=?");
      $stmt->execute([$new_status, $input['user_id']]);

      // Log the change
      $audit_stmt = $db->prepare("INSERT INTO audit_trail (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
      $audit_stmt->execute([
        $_SESSION['uid'],
        'ADMIN_USER_STATUS_TOGGLE',
        "Changed status for {$current['name']} from {$current['status']} to {$new_status}",
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      ]);

      echo json_encode(['success'=>true,'message'=>'User status toggled']);
      exit;
    }

    if ($action === 'update') {
      $user_id = (int)$input['user_id'];
      $employee_id = $input['employee_id'];
      $first_name = $input['first_name'];
      $last_name = $input['last_name'];
      $email = $input['email'];
      $password = $input['password'] ?? null;
      $roles = $input['roles'] ?? [];

      $sql = "UPDATE users SET employee_id=:emp, first_name=:fn, last_name=:ln, email=:em";
      if ($password) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $sql .= ", password_hash=:pw";
      }
      $sql .= " WHERE user_id=:id";

      $stmt = $db->prepare($sql);
      $params = [
        ':emp'=>$employee_id,
        ':fn'=>$first_name,
        ':ln'=>$last_name,
        ':em'=>$email,
        ':id'=>$user_id
      ];
      if ($password) {
        $params[':pw'] = $password_hash;
      }
      $stmt->execute($params);

      // Update roles
      $stmt = $db->prepare("DELETE FROM user_roles WHERE user_id=:id");
      $stmt->execute([':id'=>$user_id]);

      if (!empty($roles) && is_array($roles)) {
        $stmt = $db->prepare("INSERT INTO user_roles(user_id, role_id) VALUES (:user_id, :role_id)");
        foreach ($roles as $role_id) {
          $stmt->execute([':user_id' => $user_id, ':role_id' => $role_id]);
        }
      }

      // Log the update
      $audit_stmt = $db->prepare("INSERT INTO audit_trail (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
      $audit_stmt->execute([
        $_SESSION['uid'],
        'ADMIN_USER_UPDATE',
        "Updated user details for {$first_name} {$last_name} ({$employee_id})",
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      ]);

      echo json_encode(['success'=>true,'message'=>'User updated successfully']);
      exit;
    }

    if ($action === 'saveSettings') {
      $stmt = $db->query("SELECT COUNT(*) FROM system_settings");
      if ($stmt->fetchColumn() == 0) {
        $stmt = $db->prepare("INSERT INTO system_settings(grace_period,overtime_threshold) VALUES(:g,:o)");
      } else {
        $stmt = $db->prepare("UPDATE system_settings SET grace_period=:g, overtime_threshold=:o");
      }
      $stmt->execute([':g'=>$input['grace_period'], ':o'=>$input['overtime_threshold']]);

      // Log the settings change
      $audit_stmt = $db->prepare("INSERT INTO audit_trail (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
      $audit_stmt->execute([
        $_SESSION['uid'],
        'ADMIN_SETTINGS_UPDATE',
        "Updated system settings: grace_period={$input['grace_period']}, overtime_threshold={$input['overtime_threshold']}",
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      ]);

      echo json_encode(['success'=>true,'message'=>'Settings saved']);
      exit;
    }

    if ($action === 'set_grace_period') {
      $grace_period = (int)($input['grace_period'] ?? 5);
      $stmt = $db->query("SELECT COUNT(*) FROM system_settings");
      if ($stmt->fetchColumn() == 0) {
        $stmt = $db->prepare("INSERT INTO system_settings(grace_period) VALUES(:g)");
      } else {
        $stmt = $db->prepare("UPDATE system_settings SET grace_period=:g");
      }
      $stmt->execute([':g'=>$grace_period]);

      // Log the grace period change
      $audit_stmt = $db->prepare("INSERT INTO audit_trail (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
      $audit_stmt->execute([
        $_SESSION['uid'],
        'ADMIN_GRACE_PERIOD_UPDATE',
        "Updated grace period to {$grace_period} minutes",
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      ]);

      echo json_encode(['success'=>true,'message'=>'Grace period updated']);
      exit;
    }

    echo json_encode(['success'=>false,'message'=>'Invalid action']);
    exit;
  }

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Server error']);
}
