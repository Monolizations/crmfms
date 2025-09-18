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
  requireAuth(['admin', 'dean', 'secretary', 'faculty', 'program head']);

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'current_dean') {
      // Get current dean
      $stmt = $db->query("SELECT u.user_id, u.employee_id, u.first_name, u.last_name, u.email
                          FROM users u
                          JOIN user_roles ur ON u.user_id = ur.user_id
                          JOIN roles r ON ur.role_id = r.role_id
                          WHERE r.role_name = 'dean' AND u.status = 'active'
                          LIMIT 1");
      $dean = $stmt->fetch();

      // Get dean candidates with pagination and search
      $page = (int)($_GET['page'] ?? 1);
      $per_page = 10; // Limit 10 entries for dean modal
      $offset = ($page - 1) * $per_page;
      $search = $_GET['search'] ?? '';

      // Build search condition
      $searchCondition = '';
      $searchParams = [];
      if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $searchCondition = " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.employee_id LIKE ? OR u.email LIKE ?)";
        $searchParams = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
      }

      // Get total count for pagination
      $count_stmt = $db->prepare("SELECT COUNT(DISTINCT u.user_id) as total FROM users u
                                  LEFT JOIN user_roles ur ON u.user_id = ur.user_id
                                  LEFT JOIN roles r ON ur.role_id = r.role_id
                                  WHERE u.status = 'active' $searchCondition");
      if (!empty($searchParams)) {
        for ($i = 0; $i < count($searchParams); $i++) {
          $count_stmt->bindValue($i + 1, $searchParams[$i]);
        }
      }
      $count_stmt->execute();
      $total = $count_stmt->fetch()['total'];

      // Get paginated candidates
      $stmt = $db->prepare("SELECT u.user_id, u.employee_id, u.first_name, u.last_name, u.email, u.status,
                                   GROUP_CONCAT(DISTINCT r.role_name) as roles
                            FROM users u
                            LEFT JOIN user_roles ur ON u.user_id = ur.user_id
                            LEFT JOIN roles r ON ur.role_id = r.role_id
                            WHERE u.status = 'active' $searchCondition
                            GROUP BY u.user_id, u.employee_id, u.first_name, u.last_name, u.email, u.status
                            ORDER BY u.first_name ASC, u.last_name ASC
                            LIMIT ? OFFSET ?");

      // Bind search parameters first
      $paramIndex = 1;
      if (!empty($searchParams)) {
        for ($i = 0; $i < count($searchParams); $i++) {
          $stmt->bindValue($paramIndex++, $searchParams[$i]);
        }
      }

      // Bind pagination parameters
      $stmt->bindValue($paramIndex++, $per_page, PDO::PARAM_INT);
      $stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);

      $stmt->execute();
      $candidates = $stmt->fetchAll();

      echo json_encode([
        'dean' => $dean,
        'candidates' => $candidates,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page),
        'search' => $search
      ]);
      exit;
    }

    // Get pagination parameters
    $page = (int)($_GET['page'] ?? 1);
    $per_page = 20; // Fixed limit of 20 entries per page
    $offset = ($page - 1) * $per_page;

    // Get total count for pagination
    $count_stmt = $db->query("SELECT COUNT(*) as total FROM users");
    $total = $count_stmt->fetch()['total'];

    // Get paginated results
    $stmt = $db->prepare("SELECT u.user_id, u.employee_id, u.first_name, u.last_name, u.email, u.status, GROUP_CONCAT(r.role_name) as roles
                          FROM users u
                          LEFT JOIN user_roles ur ON u.user_id = ur.user_id
                          LEFT JOIN roles r ON ur.role_id = r.role_id
                          GROUP BY u.user_id
                          ORDER BY u.user_id ASC
                          LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $per_page, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    echo json_encode([
      'items' => $items,
      'total' => $total,
      'page' => $page,
      'per_page' => $per_page,
      'total_pages' => ceil($total / $per_page)
    ]);
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'create') {
      validateRequiredFields($input, ['first_name', 'last_name', 'email', 'password', 'roles']);

      // Generate next employee_id starting from CRIM001
      $stmt = $db->prepare("SELECT employee_id FROM users WHERE employee_id LIKE 'CRIM%' ORDER BY CAST(SUBSTRING(employee_id, 5) AS UNSIGNED) DESC LIMIT 1");
      $stmt->execute();
      $lastEmployee = $stmt->fetch();

      if ($lastEmployee) {
        $lastNumber = (int)substr($lastEmployee['employee_id'], 4);
        $nextNumber = $lastNumber + 1;
      } else {
        $nextNumber = 1;
      }

      $employee_id = 'CRIM' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

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
        ':emp'=>$employee_id,
        ':fn'=>$input['first_name'],
        ':ln'=>$input['last_name'],
        ':em'=>$input['email'],
        ':pw'=>$password_hash
      ]);
      $uid = $db->lastInsertId();

       // Assign roles
       if (!empty($input['roles']) && is_array($input['roles'])) {
         // Check if dean role is being assigned - make it unique
         $deanRoleId = null;
         $stmt = $db->prepare("SELECT role_id FROM roles WHERE role_name = 'dean'");
         $stmt->execute();
         $deanRole = $stmt->fetch();
         if ($deanRole && in_array($deanRole['role_id'], $input['roles'])) {
           $deanRoleId = $deanRole['role_id'];
           // Remove dean role from all other users
           $stmt = $db->prepare("DELETE FROM user_roles WHERE role_id = :role_id AND user_id != :user_id");
           $stmt->execute([':role_id' => $deanRoleId, ':user_id' => $uid]);
         }

         $stmt = $db->prepare("INSERT INTO user_roles(user_id, role_id) VALUES (:user_id, :role_id)");
         foreach ($input['roles'] as $role_id) {
           $stmt->execute([':user_id' => $uid, ':role_id' => $role_id]);
         }
       }

      echo json_encode(['success'=>true,'message'=>'User created successfully','employee_id'=>$employee_id]);
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
      requireAuth(['admin', 'dean']); // Only admin and dean can update
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
        // Check if dean role is being assigned - make it unique
        $deanRoleId = null;
        $stmt = $db->prepare("SELECT role_id FROM roles WHERE role_name = 'dean'");
        $stmt->execute();
        $deanRole = $stmt->fetch();
        if ($deanRole && in_array($deanRole['role_id'], $roles)) {
          $deanRoleId = $deanRole['role_id'];
          // Remove dean role from all other users
          $stmt = $db->prepare("DELETE FROM user_roles WHERE role_id = :role_id AND user_id != :user_id");
          $stmt->execute([':role_id' => $deanRoleId, ':user_id' => $user_id]);
        }

        $stmt = $db->prepare("INSERT INTO user_roles(user_id, role_id) VALUES (:user_id, :role_id)");
        foreach ($roles as $role_id) {
          $stmt->execute([':user_id' => $user_id, ':role_id' => $role_id]);
        }
      }

      echo json_encode(['success'=>true,'message'=>'User updated successfully']);
      exit;
    }

    if ($action === 'set_dean') {
      requireAuth(['admin']); // Only admin can set dean
      $user_id = (int)$input['user_id'];

      // Get dean role ID
      $stmt = $db->prepare("SELECT role_id FROM roles WHERE role_name = 'dean'");
      $stmt->execute();
      $deanRole = $stmt->fetch();

      if (!$deanRole) {
        echo json_encode(['success'=>false,'message'=>'Dean role not found']);
        exit;
      }

      // Remove dean role from all users
      $stmt = $db->prepare("DELETE FROM user_roles WHERE role_id = :role_id");
      $stmt->execute([':role_id' => $deanRole['role_id']]);

      // Assign dean role to the selected user
      $stmt = $db->prepare("INSERT INTO user_roles(user_id, role_id) VALUES (:user_id, :role_id)");
      $stmt->execute([':user_id' => $user_id, ':role_id' => $deanRole['role_id']]);

      echo json_encode(['success'=>true,'message'=>'Dean role assigned successfully']);
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