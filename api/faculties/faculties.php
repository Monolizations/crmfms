<?php
// /api/faculties/faculties.php
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

function validateRequiredFields($input, $requiredFields) {
  foreach ($requiredFields as $field) {
    if (!isset($input[$field]) || (is_string($input[$field]) && trim($input[$field]) === '') || (is_array($input[$field]) && empty($input[$field]))) {
      http_response_code(400);
      echo json_encode(['success'=>false, 'message'=> ucfirst(str_replace('_', ' ', $field)) . ' is required.']);
      exit;
    }
  }
}

try {
  $db = (new Database())->getConnection();
  // Allow faculty to view other faculty for scheduling purposes
  requireAuth($db, ['admin', 'dean', 'secretary', 'faculty']);

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->query("SELECT u.user_id, u.employee_id, u.first_name, u.last_name, u.email, u.status, GROUP_CONCAT(r.role_name) as roles
                        FROM users u
                        LEFT JOIN user_roles ur ON u.user_id = ur.user_id
                        LEFT JOIN roles r ON ur.role_id = r.role_id
                        GROUP BY u.user_id
                        ORDER BY u.user_id ASC");
    $items = $stmt->fetchAll();

    echo json_encode(['items'=>$items]);
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'create') {
      validateRequiredFields($input, ['employee_id', 'first_name', 'last_name', 'email', 'password', 'roles']);

      // Get logged-in user's roles
      $loggedInUserRoles = $_SESSION['roles'] ?? [];

      // If the logged-in user is a secretary, restrict role creation
      if (in_array('secretary', $loggedInUserRoles)) {
        $allowedRoleNames = ['program head', 'faculty'];
        $selectedRoleIds = $input['roles'] ?? [];
        
        // Fetch role names for selected role IDs
        $placeholders = implode(',', array_fill(0, count($selectedRoleIds), '?'));
        $stmt = $db->prepare("SELECT role_name FROM roles WHERE role_id IN ($placeholders)");
        $stmt->execute($selectedRoleIds);
        $selectedRoleNames = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Check if all selected roles are allowed
        foreach ($selectedRoleNames as $roleName) {
          if (!in_array($roleName, $allowedRoleNames)) {
            http_response_code(403);
            echo json_encode(['success'=>false, 'message'=>'Secretaries can only create Program Head or Faculty users.']);
            exit;
          }
        }
      }

      $password_hash = password_hash($input['password'], PASSWORD_DEFAULT);
      $stmt = $db->prepare("INSERT INTO users(employee_id,first_name,last_name,email,password_hash,status)
                            VALUES(:emp,:fn,:ln,:em,:pw,'active')");
      $stmt->execute([
        ':emp'=>$input['employee_id'],
        ':fn'=>$input['first_name'],
        ':ln'=>$input['last_name'],
        ':em'=>$input['email'],
        ':pw'=>$password_hash
      ]);
      $uid = $db->lastInsertId();

      // Assign roles
      if (!empty($input['roles']) && is_array($input['roles'])) {
        $stmt = $db->prepare("INSERT INTO user_roles(user_id, role_id) VALUES (:user_id, :role_id)");
        foreach ($input['roles'] as $role_id) {
          $stmt->execute([':user_id' => $uid, ':role_id' => $role_id]);
        }
      }

      echo json_encode(['success'=>true,'message'=>'User created successfully']);
      exit;
    }

    if ($action === 'toggle') {
      $id = (int)$input['user_id'];
      $stmt = $db->prepare("UPDATE users SET status = CASE WHEN status='active' THEN 'inactive' ELSE 'active' END WHERE user_id=:id");
      $stmt->execute([':id'=>$id]);
      echo json_encode(['success'=>true,'message'=>'User status updated']);
      exit;
    }

    if ($action === 'update') {
      validateRequiredFields($input, ['user_id', 'employee_id', 'first_name', 'last_name', 'email', 'roles']);
      requireAuth($db, ['admin', 'dean']); // Only admin and dean can update
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


    echo json_encode(['success'=>false,'message'=>'Invalid action']);
    exit;
  }

} catch (Throwable $e) {
  http_response_code(500);
  error_log($e->getMessage());
  echo json_encode(['success'=>false,'message'=>'Server error']);
}