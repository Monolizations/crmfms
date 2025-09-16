<?php
// /api/leaves/leaves.php
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

function archiveLeaveRequest($db, $leave_id) {
  // Get the leave request data
  $stmt = $db->prepare("SELECT * FROM leave_requests WHERE leave_id = :id");
  $stmt->execute([':id' => $leave_id]);
  $leaveRequest = $stmt->fetch();
  
  if (!$leaveRequest) {
    return false;
  }
  
  // Insert into archived table
  $stmt = $db->prepare("INSERT INTO archived_leave_requests 
                        (original_leave_id, user_id, leave_type, start_date, end_date, reason, status, 
                         requested_at, reviewed_by, reviewed_at, approval_reason, rejection_reason, archived_at)
                        VALUES (:original_id, :user_id, :leave_type, :start_date, :end_date, :reason, :status,
                                :requested_at, :reviewed_by, :reviewed_at, :approval_reason, :rejection_reason, NOW())");
  
  $stmt->execute([
    ':original_id' => $leaveRequest['leave_id'],
    ':user_id' => $leaveRequest['user_id'],
    ':leave_type' => $leaveRequest['leave_type'],
    ':start_date' => $leaveRequest['start_date'],
    ':end_date' => $leaveRequest['end_date'],
    ':reason' => $leaveRequest['reason'],
    ':status' => $leaveRequest['status'],
    ':requested_at' => $leaveRequest['requested_at'],
    ':reviewed_by' => $leaveRequest['reviewed_by'],
    ':reviewed_at' => $leaveRequest['reviewed_at'],
    ':approval_reason' => $leaveRequest['approval_reason'],
    ':rejection_reason' => $leaveRequest['rejection_reason']
  ]);
  
  // Delete from original table
  $stmt = $db->prepare("DELETE FROM leave_requests WHERE leave_id = :id");
  $stmt->execute([':id' => $leave_id]);
  
  return true;
}

$db = (new Database())->getConnection();
requireAuth();

$uid = $_SESSION['uid'];
$userRoles = $_SESSION['roles'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  try {
    $action = $_GET['action'] ?? '';

    if ($action === 'balance') {
      // Get leave balance for current user
      // This is a simplified calculation - in a real system you'd have a leave_balance table
      $stmt = $db->prepare("SELECT
                           COUNT(*) as total_requests,
                           SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
                           SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests
                           FROM leave_requests
                           WHERE user_id = :user_id
                           AND YEAR(requested_at) = YEAR(CURDATE())");
      $stmt->execute([':user_id' => $uid]);
      $leaveStats = $stmt->fetch();

      // Calculate remaining balance (simplified: 10 days per year minus used)
      $usedDays = $leaveStats['approved_requests'] ?? 0;
      $remainingBalance = max(0, 10 - $usedDays);

      echo json_encode(['balance' => $remainingBalance]);
      exit;
    }

    $showArchived = $_GET['archived'] ?? 'false';
    $showArchived = $showArchived === 'true';

    // Check if user has faculty role - faculty cannot see request lists
    if (in_array('faculty', $userRoles)) {
      // Faculty users cannot view any leave request lists
      echo json_encode(['items'=>[], 'archived'=>$showArchived, 'message'=>'Faculty users cannot view leave request lists']);
      exit;
    }

    if ($showArchived) {
      // Show archived requests
      if (!empty(array_intersect($userRoles, ['admin','dean','secretary','program head']))) {
        $stmt = $db->query("SELECT l.*, CONCAT(u.first_name,' ',u.last_name) AS user_name,
                                   (SELECT CONCAT(first_name,' ',last_name) FROM users WHERE user_id=l.reviewed_by) AS reviewer,
                                   l.approval_reason, l.rejection_reason, l.archived_at
                            FROM archived_leave_requests l
                            JOIN users u ON u.user_id=l.user_id
                            ORDER BY l.archived_at DESC");
      } else {
        $stmt = $db->prepare("SELECT l.*, CONCAT(u.first_name,' ',u.last_name) AS user_name,
                                     (SELECT CONCAT(first_name,' ',last_name) FROM users WHERE user_id=l.reviewed_by) AS reviewer,
                                     l.approval_reason, l.rejection_reason, l.archived_at
                              FROM archived_leave_requests l
                              JOIN users u ON u.user_id=l.user_id
                              WHERE l.user_id=:u
                              ORDER BY l.archived_at DESC");
        $stmt->execute([':u'=>$uid]);
      }
    } else {
      // Show only pending requests (active requests)
      if (!empty(array_intersect($userRoles, ['admin','dean','secretary','program head']))) {
        $stmt = $db->query("SELECT l.*, CONCAT(u.first_name,' ',u.last_name) AS user_name,
                                   (SELECT CONCAT(first_name,' ',last_name) FROM users WHERE user_id=l.reviewed_by) AS reviewer,
                                   l.approval_reason, l.rejection_reason
                            FROM leave_requests l
                            JOIN users u ON u.user_id=l.user_id
                            WHERE l.status = 'pending'
                            ORDER BY l.requested_at DESC");
      } else {
        $stmt = $db->prepare("SELECT l.*, CONCAT(u.first_name,' ',u.last_name) AS user_name,
                                     (SELECT CONCAT(first_name,' ',last_name) FROM users WHERE user_id=l.reviewed_by) AS reviewer,
                                     l.approval_reason, l.rejection_reason
                              FROM leave_requests l
                              JOIN users u ON u.user_id=l.user_id
                              WHERE l.user_id=:u AND l.status = 'pending'
                              ORDER BY l.requested_at DESC");
        $stmt->execute([':u'=>$uid]);
      }
    }
    $items = $stmt->fetchAll();
    echo json_encode(['items'=>$items, 'archived'=>$showArchived]);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Database error']);
  }
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $input = json_decode(file_get_contents('php://input'), true);
  $action = $input['action'] ?? '';

  if ($action === 'create') {
    $start = $input['start_date'];
    $end = $input['end_date'];
    $reason = $input['reason'];
    $leave_type = $input['leave_type'] ?? 'Other';

    // Enforce 2 weeks prior rule - EXCEPTION for Sick Leave
    $minDate = date('Y-m-d', strtotime('+14 days'));
    if ($start < $minDate && $leave_type !== 'Sick Leave') {
      echo json_encode(['success'=>false,'message'=>'Leave must be requested at least 2 weeks in advance (except for sick leave)']);
      exit;
    }

    // Additional validation for sick leave (can be requested same day or retroactively within reasonable limits)
    if ($leave_type === 'Sick Leave') {
      $maxSickLeaveRetroactive = date('Y-m-d', strtotime('-7 days')); // Allow sick leave up to 7 days retroactively
      if ($start < $maxSickLeaveRetroactive) {
        echo json_encode(['success'=>false,'message'=>'Sick leave cannot be requested more than 7 days in the past']);
        exit;
      }
    }

    try {
      $stmt = $db->prepare("INSERT INTO leave_requests(user_id,leave_type,start_date,end_date,reason,status)
                            VALUES(:u,:lt,:s,:e,:r,'pending')");
      $stmt->execute([':u'=>$uid, ':lt'=>$leave_type, ':s'=>$start, ':e'=>$end, ':r'=>$reason]);

      $message = 'Leave request submitted';
      if ($leave_type === 'Sick Leave') {
        $message = 'Sick leave request submitted (no advance notice required)';
      }

      echo json_encode(['success'=>true,'message'=>$message]);
    } catch (Throwable $e) {
      http_response_code(500);
      echo json_encode(['success'=>false,'message'=>'Database error']);
    }
    exit;
  }

  if ($action === 'review') {
    if (empty(array_intersect($userRoles, ['dean','secretary','program head']))) {
      http_response_code(403);
      echo json_encode(['success'=>false,'message'=>'Not authorized']);
      exit;
    }

    $status = $input['status'];
    $leave_id = $input['leave_id'];
    $reason = $input['reason'] ?? '';

    // Prepare the SQL statement based on status
    if ($status === 'approved') {
      $stmt = $db->prepare("UPDATE leave_requests
                            SET status=:st, reviewed_by=:rb, reviewed_at=NOW(),
                                approval_reason=:reason, rejection_reason=NULL
                            WHERE leave_id=:id");
    } else if ($status === 'denied') {
      $stmt = $db->prepare("UPDATE leave_requests
                            SET status=:st, reviewed_by=:rb, reviewed_at=NOW(),
                                rejection_reason=:reason, approval_reason=NULL
                            WHERE leave_id=:id");
    } else {
      echo json_encode(['success'=>false,'message'=>'Invalid status']);
      exit;
    }

    try {
      $stmt->execute([
        ':st'=>$status,
        ':rb'=>$uid,
        ':reason'=>$reason,
        ':id'=>$leave_id
      ]);

       // Auto-archive the request after review (move to archived table)
       try {
         $archiveResult = archiveLeaveRequest($db, $leave_id);
         if (!$archiveResult) {
           error_log("Failed to archive leave request ID: $leave_id");
         }
       } catch (Throwable $archiveError) {
         error_log("Error archiving leave request ID $leave_id: " . $archiveError->getMessage());
         // Don't fail the entire request if archiving fails
       }

      $message = $status === 'approved' ? 'Leave request approved' : 'Leave request rejected';
      if (!empty($reason)) {
        $message .= ' with reason provided';
      }
      $message .= ' and archived';

      echo json_encode(['success'=>true,'message'=>$message]);
    } catch (Throwable $e) {
      http_response_code(500);
      echo json_encode(['success'=>false,'message'=>'Database error']);
    }
    exit;
  }

  if ($action === 'archive_old') {
    if (empty(array_intersect($userRoles, ['admin','dean']))) {
      http_response_code(403);
      echo json_encode(['success'=>false,'message'=>'Not authorized']);
      exit;
    }

    try {
      // Archive requests older than 30 days that are not pending
      $stmt = $db->query("SELECT leave_id FROM leave_requests
                          WHERE status != 'pending'
                          AND requested_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
      $oldRequests = $stmt->fetchAll();

      $archivedCount = 0;
      foreach ($oldRequests as $request) {
        if (archiveLeaveRequest($db, $request['leave_id'])) {
          $archivedCount++;
        }
      }

      echo json_encode(['success'=>true,'message'=>"Archived {$archivedCount} old leave requests"]);
    } catch (Throwable $e) {
      http_response_code(500);
      echo json_encode(['success'=>false,'message'=>'Database error']);
    }
    exit;
  }

  echo json_encode(['success'=>false,'message'=>'Invalid action']);
  exit;
}
