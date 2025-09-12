<?php
// /api/admin/admin.php
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $db = (new Database())->getConnection();
  requireAuth($db);

  if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Forbidden']);
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'users') {
      $stmt = $db->query("SELECT u.user_id, u.employee_id, u.first_name, u.last_name, u.email, u.status, GROUP_CONCAT(r.role_name) as roles
                          FROM users u
                          LEFT JOIN user_roles ur ON u.user_id = ur.user_id
                          LEFT JOIN roles r ON ur.role_id = r.role_id
                          GROUP BY u.user_id
                          ORDER BY u.user_id ASC");
      echo json_encode(['items'=>$stmt->fetchAll()]);
      exit;
    }

    if ($action === 'settings') {
      // fake settings table for now
      $stmt = $db->query("SELECT * FROM system_settings LIMIT 1");
      $settings = $stmt->fetch() ?: ['grace_period'=>5,'overtime_threshold'=>8];
      echo json_encode($settings);
      exit;
    }

    if ($action === 'audit') {
      $stmt = $db->query("SELECT a.*, CONCAT(u.first_name,' ',u.last_name) AS user_name
                          FROM audit_trail a
                          JOIN users u ON u.user_id=a.user_id
                          ORDER BY a.created_at DESC LIMIT 50");
      echo json_encode(['items'=>$stmt->fetchAll()]);
      exit;
    }

    echo json_encode(['items'=>[]]);
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'role') {
      $stmt = $db->prepare("UPDATE users SET role=:r WHERE user_id=:id");
      $stmt->execute([':r'=>$input['role'], ':id'=>$input['user_id']]);
      echo json_encode(['success'=>true,'message'=>'Role updated']);
      exit;
    }

    if ($action === 'toggle') {
      $stmt = $db->prepare("UPDATE users 
                            SET status = CASE WHEN status='active' THEN 'inactive' ELSE 'active' END
                            WHERE user_id=:id");
      $stmt->execute([':id'=>$input['user_id']]);
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
      echo json_encode(['success'=>true,'message'=>'Settings saved']);
      exit;
    }

    echo json_encode(['success'=>false,'message'=>'Invalid action']);
    exit;
  }

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Server error']);
}
