<?php
// /api/reports/reports.php
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

try {
  requireAuth();
  $db = (new Database())->getConnection();

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'latest_date') {
      $stmt = $db->query("SELECT DATE(MAX(check_in_time)) as latest_date FROM attendance");
      $result = $stmt->fetch();
      echo json_encode(['latest_date' => $result['latest_date'] ?: date('Y-m-d')]);
      exit;
    }

    echo json_encode(['error' => 'Invalid action']);
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $type = $input['type'] ?? 'attendance';
    $start = $input['start_date'] ?: '1970-01-01';
    $end = $input['end_date'] ?: date('Y-m-d');

      if ($type === 'attendance') {
        // Check if user is admin
        $userRoles = $_SESSION['roles'] ?? [];
        if (!is_array($userRoles)) {
          $userRoles = [];
        }
        $isAdmin = in_array('admin', $userRoles);

        $whereClause = "DATE(a.check_in_time) BETWEEN :s AND :e";
        $params = [':s'=>$start, ':e'=>$end];

        // If not admin, only show user's own records
        if (!$isAdmin) {
            $whereClause .= " AND a.user_id = :user_id";
            $params[':user_id'] = (int)$_SESSION['uid'];
        }

            $stmt = $db->prepare("SELECT a.attendance_id,u.employee_id,CONCAT(u.first_name,' ',u.last_name) AS name,
                                       CASE
                                         WHEN a.room_id IS NULL THEN 'Department'
                                         ELSE 'Room'
                                       END as location,
                                       a.check_in_time as time_in,a.check_out_time as time_out,
                                       a.scan_timestamp, a.server_timestamp,
                                       DATE(a.scan_timestamp) as scan_date,
                                       CASE
                                         WHEN a.check_out_time IS NOT NULL THEN 'Checked Out'
                                         WHEN a.room_id IS NOT NULL THEN 'Class Session'
                                         ELSE 'Present'
                                       END as status,
                                       a.latitude, a.longitude,
                                       a.room_id
                                FROM attendance a
                                JOIN users u ON u.user_id=a.user_id
                                WHERE $whereClause
                                ORDER BY a.check_in_time DESC");
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        // Debug: Add some logging to see what's happening
        error_log("Attendance query returned " . count($results) . " records for date range $start to $end");
        if (count($results) > 0) {
          error_log("Sample records: " . json_encode(array_slice($results, 0, 2)));
        }

        echo json_encode(['items'=>$results]);
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
                                     COUNT(*) AS absent_count
                              FROM attendance a
                              JOIN users u ON u.user_id=a.user_id
                              WHERE a.status='absent'
                              AND DATE(a.check_in_time) BETWEEN :s AND :e
                              GROUP BY u.user_id
                              HAVING COUNT(*) > 0
                              ORDER BY absent_count DESC");
        $stmt->execute([':s'=>$start, ':e'=>$end]);
        echo json_encode(['items'=>$stmt->fetchAll()]);
        exit;
      }

      if ($type === 'room_utilization') {
        // Calculate room utilization metrics
        $stmt = $db->prepare("
          SELECT
            r.room_id,
            r.room_code,
            r.name,
            r.capacity,
            b.name as building_name,
            f.floor_number,
            COUNT(DISTINCT a.attendance_id) as total_sessions,
            COUNT(DISTINCT DATE(a.check_in_time)) as active_days,
            AVG(TIMESTAMPDIFF(MINUTE, a.check_in_time, a.check_out_time)) as avg_session_duration,
            MIN(a.check_in_time) as first_use,
            MAX(a.check_out_time) as last_use
          FROM rooms r
          LEFT JOIN attendance a ON r.room_id = a.room_id
            AND DATE(a.check_in_time) BETWEEN :s AND :e
          LEFT JOIN floors f ON r.floor_id = f.floor_id
           LEFT JOIN buildings b ON f.building_id = b.building_id
            WHERE r.status = 'active'
           GROUP BY r.room_id, r.room_code, r.name, r.capacity, building_name, f.floor_number
           ORDER BY building_name, f.floor_number, r.room_code
        ");
        $stmt->execute([':s'=>$start, ':e'=>$end]);
        $results = $stmt->fetchAll();

        // Calculate utilization percentage and format results
        $formattedResults = array_map(function($row) use ($start, $end) {
          $totalDays = (strtotime($end) - strtotime($start)) / (60 * 60 * 24) + 1;
          $utilizationRate = $totalDays > 0 ? round(($row['active_days'] / $totalDays) * 100, 2) : 0;

          return [
            'room_id' => $row['room_id'],
            'room_number' => $row['room_code'],
            'room_name' => $row['name'],
            'building' => $row['building_name'],
            'floor' => $row['floor_number'],
            'capacity' => $row['capacity'],
            'total_sessions' => $row['total_sessions'],
            'active_days' => $row['active_days'],
            'utilization_rate' => $utilizationRate . '%',
            'avg_session_duration' => $row['avg_session_duration'] ? round($row['avg_session_duration'], 0) . ' min' : 'N/A',
            'first_use' => $row['first_use'],
            'last_use' => $row['last_use']
          ];
        }, $results);

        echo json_encode(['items'=>$formattedResults]);
        exit;
      }

      if ($type === 'building_occupancy') {
        // Calculate building occupancy analytics
        $stmt = $db->prepare("
          SELECT
            b.building_id,
            b.name as building_name,
            b.building_code,
            COUNT(DISTINCT r.room_id) as total_rooms,
            SUM(r.capacity) as total_capacity,
            COUNT(DISTINCT CASE WHEN a.check_out_time IS NULL THEN a.attendance_id END) as current_occupancy,
            COUNT(DISTINCT a.attendance_id) as total_sessions,
            COUNT(DISTINCT CASE WHEN HOUR(a.check_in_time) BETWEEN 8 AND 12 THEN a.attendance_id END) as morning_sessions,
            COUNT(DISTINCT CASE WHEN HOUR(a.check_in_time) BETWEEN 13 AND 17 THEN a.attendance_id END) as afternoon_sessions,
            COUNT(DISTINCT CASE WHEN HOUR(a.check_in_time) BETWEEN 18 AND 22 THEN a.attendance_id END) as evening_sessions,
            AVG(CASE WHEN a.check_out_time IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, a.check_in_time, a.check_out_time) END) as avg_session_duration,
            MAX(CASE WHEN a.check_out_time IS NULL THEN TIMESTAMPDIFF(MINUTE, a.check_in_time, NOW()) END) as longest_current_session
          FROM buildings b
          LEFT JOIN floors f ON b.building_id = f.building_id
          LEFT JOIN rooms r ON f.floor_id = r.floor_id AND r.status = 'active'
          LEFT JOIN attendance a ON r.room_id = a.room_id
            AND DATE(a.check_in_time) BETWEEN :s AND :e
          WHERE b.status = 'active'
          GROUP BY b.building_id, building_name, b.building_code
          ORDER BY building_name
        ");
        $stmt->execute([':s'=>$start, ':e'=>$end]);
        $results = $stmt->fetchAll();

        // Calculate occupancy rates and format results
        $formattedResults = array_map(function($row) {
          $totalCapacity = $row['total_capacity'] ?: 1; // Avoid division by zero
          $currentOccupancy = $row['current_occupancy'];
          $occupancyRate = round(($currentOccupancy / $totalCapacity) * 100, 2);
          $totalSessions = $row['total_sessions'];

          // Calculate peak hours distribution
          $morningPct = $totalSessions > 0 ? round(($row['morning_sessions'] / $totalSessions) * 100, 1) : 0;
          $afternoonPct = $totalSessions > 0 ? round(($row['afternoon_sessions'] / $totalSessions) * 100, 1) : 0;
          $eveningPct = $totalSessions > 0 ? round(($row['evening_sessions'] / $totalSessions) * 100, 1) : 0;

          return [
            'building_name' => $row['building_name'],
            'building_code' => $row['building_code'],
            'total_rooms' => $row['total_rooms'],
            'total_capacity' => $totalCapacity,
            'current_occupancy' => $currentOccupancy,
            'occupancy_rate' => $occupancyRate . '%',
            'total_sessions' => $totalSessions,
            'morning_usage' => $morningPct . '%',
            'afternoon_usage' => $afternoonPct . '%',
            'evening_usage' => $eveningPct . '%',
            'avg_session_duration' => $row['avg_session_duration'] ? round($row['avg_session_duration'], 0) . ' min' : 'N/A',
            'longest_current_session' => $row['longest_current_session'] ? round($row['longest_current_session'], 0) . ' min' : 'N/A'
          ];
        }, $results);

        echo json_encode(['items'=>$formattedResults]);
        exit;
      }



      if ($type === 'leave_analytics') {
        // Calculate leave analytics metrics
        $stmt = $db->prepare("
          SELECT
            l.leave_id,
            u.employee_id,
            CONCAT(u.first_name, ' ', u.last_name) as faculty_name,
            l.leave_type,
            l.start_date,
            l.end_date,
            DATEDIFF(l.end_date, l.start_date) + 1 as leave_days,
            l.reason,
            l.status,
            l.requested_at as requested_at,
            l.reviewed_at as approved_at,
            CASE
              WHEN l.reviewed_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, l.requested_at, l.reviewed_at)
              ELSE NULL
            END as approval_hours,
            CASE
              WHEN l.status = 'approved' THEN 'Approved'
              WHEN l.status = 'denied' THEN 'Rejected'
              ELSE 'Pending'
            END as approval_status,
            MONTH(l.start_date) as leave_month,
            YEAR(l.start_date) as leave_year,
            CONCAT(u2.first_name, ' ', u2.last_name) as approved_by_name
          FROM leave_requests l
          JOIN users u ON l.user_id = u.user_id
          LEFT JOIN users u2 ON l.reviewed_by = u2.user_id
          WHERE l.start_date BETWEEN :s AND :e
          ORDER BY l.requested_at DESC
        ");
        $stmt->execute([':s'=>$start, ':e'=>$end]);
        $results = $stmt->fetchAll();

        // Calculate analytics
        $analytics = [
          'total_leaves' => count($results),
          'approved_leaves' => 0,
          'rejected_leaves' => 0,
          'pending_leaves' => 0,
          'total_leave_days' => 0,
          'avg_approval_time' => 0,
          'leave_type_distribution' => [],
          'monthly_distribution' => [],
          'status_distribution' => []
        ];

        $approvalTimes = [];
        foreach ($results as $row) {
          // Count by status
           switch ($row['status']) {
             case 'approved':
               $analytics['approved_leaves']++;
               break;
             case 'denied':
               $analytics['rejected_leaves']++;
               break;
             case 'pending':
               $analytics['pending_leaves']++;
               break;
           }

          // Sum leave days
          $analytics['total_leave_days'] += $row['leave_days'];

          // Leave type distribution
          $leaveType = $row['leave_type'];
          if (!isset($analytics['leave_type_distribution'][$leaveType])) {
            $analytics['leave_type_distribution'][$leaveType] = 0;
          }
          $analytics['leave_type_distribution'][$leaveType]++;

          // Monthly distribution
          $monthKey = $row['leave_year'] . '-' . str_pad($row['leave_month'], 2, '0', STR_PAD_LEFT);
          if (!isset($analytics['monthly_distribution'][$monthKey])) {
            $analytics['monthly_distribution'][$monthKey] = 0;
          }
          $analytics['monthly_distribution'][$monthKey]++;

          // Collect approval times for average calculation
          if ($row['approval_hours'] !== null) {
            $approvalTimes[] = $row['approval_hours'];
          }
        }

        // Calculate average approval time
        if (count($approvalTimes) > 0) {
          $analytics['avg_approval_time'] = round(array_sum($approvalTimes) / count($approvalTimes), 1);
        }

        // Format leave type distribution as percentages
        $totalLeaves = $analytics['total_leaves'];
        foreach ($analytics['leave_type_distribution'] as $type => $count) {
          $analytics['leave_type_distribution'][$type] = [
            'count' => $count,
            'percentage' => $totalLeaves > 0 ? round(($count / $totalLeaves) * 100, 1) . '%' : '0%'
          ];
        }

        // Format status distribution
        $analytics['status_distribution'] = [
          'approved' => [
            'count' => $analytics['approved_leaves'],
            'percentage' => $totalLeaves > 0 ? round(($analytics['approved_leaves'] / $totalLeaves) * 100, 1) . '%' : '0%'
          ],
          'denied' => [
            'count' => $analytics['rejected_leaves'],
            'percentage' => $totalLeaves > 0 ? round(($analytics['rejected_leaves'] / $totalLeaves) * 100, 1) . '%' : '0%'
          ],
          'pending' => [
            'count' => $analytics['pending_leaves'],
            'percentage' => $totalLeaves > 0 ? round(($analytics['pending_leaves'] / $totalLeaves) * 100, 1) . '%' : '0%'
          ]
        ];

        // Format individual leave records
        $formattedResults = array_map(function($row) {
          return [
            'leave_id' => $row['leave_id'],
            'employee_id' => $row['employee_id'],
            'faculty_name' => $row['faculty_name'],
            'leave_type' => $row['leave_type'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'leave_days' => $row['leave_days'],
            'reason' => $row['reason'] ?: 'Not specified',
            'status' => $row['approval_status'],
            'requested_at' => $row['requested_at'],
            'approved_at' => $row['approved_at'],
            'approval_time_hours' => $row['approval_hours'] ? round($row['approval_hours'], 1) . ' hrs' : 'N/A',
            'approved_by' => $row['approved_by_name'] ?: 'N/A'
          ];
        }, $results);

        // Return both analytics summary and detailed records
        echo json_encode([
          'analytics' => $analytics,
          'items' => $formattedResults
        ]);
        exit;
      }

      if ($type === 'system_performance') {
        // Calculate system performance metrics
        $stmt = $db->prepare("
          SELECT
            DATE(a.created_at) as date,
            COUNT(*) as total_actions,
            COUNT(CASE WHEN a.action LIKE '%SUCCESS%' OR a.action LIKE '%CHECK_IN%' OR a.action LIKE '%CHECK_OUT%' THEN 1 END) as successful_actions,
            COUNT(CASE WHEN a.action LIKE '%ERROR%' OR a.action LIKE '%FAILED%' OR a.action LIKE '%DENIED%' THEN 1 END) as error_actions,
            COUNT(DISTINCT a.user_id) as active_users,
            COUNT(DISTINCT CASE WHEN HOUR(a.created_at) BETWEEN 8 AND 12 THEN a.user_id END) as morning_users,
            COUNT(DISTINCT CASE WHEN HOUR(a.created_at) BETWEEN 13 AND 17 THEN a.user_id END) as afternoon_users,
            COUNT(DISTINCT CASE WHEN HOUR(a.created_at) BETWEEN 18 AND 22 THEN a.user_id END) as evening_users,
            AVG(CASE WHEN JSON_EXTRACT(a.details, '$.scan_timestamp') IS NOT NULL
                     THEN TIMESTAMPDIFF(SECOND,
                          JSON_EXTRACT(a.details, '$.scan_timestamp'),
                          JSON_EXTRACT(a.details, '$.server_timestamp'))
                     END) as avg_response_time_seconds
          FROM audit_trail a
          WHERE DATE(a.created_at) BETWEEN :s AND :e
          GROUP BY DATE(a.created_at)
          ORDER BY date DESC
        ");
        $stmt->execute([':s'=>$start, ':e'=>$end]);
        $dailyStats = $stmt->fetchAll();

        // Calculate attendance success rates
        $attendanceStmt = $db->prepare("
          SELECT
            COUNT(*) as total_checkins,
            COUNT(CASE WHEN scan_timestamp IS NOT NULL THEN 1 END) as qr_checkins,
            COUNT(CASE WHEN scan_timestamp IS NULL THEN 1 END) as manual_checkins,
            COUNT(DISTINCT user_id) as unique_users,
            AVG(CASE WHEN check_out_time IS NOT NULL
                     THEN TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time)
                     END) as avg_session_duration
          FROM attendance
          WHERE DATE(check_in_time) BETWEEN :s AND :e
        ");
        $attendanceStmt->execute([':s'=>$start, ':e'=>$end]);
        $attendanceStats = $attendanceStmt->fetch();

        // Calculate error rates and system health
        $errorStmt = $db->prepare("
          SELECT
            action,
            COUNT(*) as count,
            COUNT(DISTINCT user_id) as affected_users
          FROM audit_trail
          WHERE (action LIKE '%ERROR%' OR action LIKE '%FAILED%' OR action LIKE '%DENIED%')
            AND DATE(created_at) BETWEEN :s AND :e
          GROUP BY action
          ORDER BY count DESC
          LIMIT 10
        ");
        $errorStmt->execute([':s'=>$start, ':e'=>$end]);
        $errorStats = $errorStmt->fetchAll();

        // Calculate overall metrics
        $totalDays = (strtotime($end) - strtotime($start)) / (60 * 60 * 24) + 1;
        $totalActions = array_sum(array_column($dailyStats, 'total_actions'));
        $totalSuccessful = array_sum(array_column($dailyStats, 'successful_actions'));
        $totalErrors = array_sum(array_column($dailyStats, 'error_actions'));
        $avgDailyUsers = count($dailyStats) > 0 ? round(array_sum(array_column($dailyStats, 'active_users')) / count($dailyStats), 1) : 0;

        $successRate = $totalActions > 0 ? round(($totalSuccessful / $totalActions) * 100, 2) : 0;
        $errorRate = $totalActions > 0 ? round(($totalErrors / $totalActions) * 100, 2) : 0;

        // Format daily performance data
        $formattedDailyStats = array_map(function($day) {
          $daySuccessRate = $day['total_actions'] > 0 ? round(($day['successful_actions'] / $day['total_actions']) * 100, 2) : 0;
          $dayErrorRate = $day['total_actions'] > 0 ? round(($day['error_actions'] / $day['total_actions']) * 100, 2) : 0;

          return [
            'date' => $day['date'],
            'total_actions' => $day['total_actions'],
            'successful_actions' => $day['successful_actions'],
            'error_actions' => $day['error_actions'],
            'success_rate' => $daySuccessRate . '%',
            'error_rate' => $dayErrorRate . '%',
            'active_users' => $day['active_users'],
            'morning_users' => $day['morning_users'],
            'afternoon_users' => $day['afternoon_users'],
            'evening_users' => $day['evening_users'],
            'avg_response_time' => $day['avg_response_time_seconds'] ? round($day['avg_response_time_seconds'], 2) . ' sec' : 'N/A'
          ];
        }, $dailyStats);

        // Format error statistics
        $formattedErrorStats = array_map(function($error) {
          return [
            'action' => $error['action'],
            'count' => $error['count'],
            'affected_users' => $error['affected_users']
          ];
        }, $errorStats);

        // Return comprehensive performance data
        echo json_encode([
          'summary' => [
            'total_days' => $totalDays,
            'total_actions' => $totalActions,
            'success_rate' => $successRate . '%',
            'error_rate' => $errorRate . '%',
            'avg_daily_users' => $avgDailyUsers,
            'total_checkins' => $attendanceStats['total_checkins'] ?? 0,
            'qr_checkins' => $attendanceStats['qr_checkins'] ?? 0,
            'manual_checkins' => $attendanceStats['manual_checkins'] ?? 0,
            'unique_attendance_users' => $attendanceStats['unique_users'] ?? 0,
            'avg_session_duration' => $attendanceStats['avg_session_duration'] ? round($attendanceStats['avg_session_duration'], 0) . ' min' : 'N/A'
          ],
          'daily_performance' => $formattedDailyStats,
          'error_analysis' => $formattedErrorStats
        ]);
        exit;
      }

      if ($type === 'department_performance') {
        // Calculate department/role-based performance metrics
        $stmt = $db->prepare("
          SELECT
            r.role_name as department_group,
            COUNT(DISTINCT u.user_id) as total_users,
            COUNT(DISTINCT DATE(a.check_in_time)) as total_user_days,
            COUNT(DISTINCT CASE WHEN a.status = 'present' THEN DATE(a.check_in_time) END) as present_days,
            COUNT(DISTINCT CASE WHEN TIME(a.check_in_time) > '09:00:00' THEN DATE(a.check_in_time) END) as late_days,
            COUNT(DISTINCT CASE WHEN a.status = 'absent' THEN DATE(a.check_in_time) END) as absent_days,
            AVG(CASE WHEN a.check_out_time IS NOT NULL
                     THEN TIMESTAMPDIFF(MINUTE, a.check_in_time, a.check_out_time)
                     END) as avg_daily_hours,
            COUNT(DISTINCT CASE WHEN a.room_id IS NOT NULL THEN a.attendance_id END) as room_sessions,
            COUNT(DISTINCT CASE WHEN a.room_id IS NULL THEN a.attendance_id END) as department_sessions,
            COUNT(DISTINCT CASE WHEN s.schedule_id IS NOT NULL THEN s.schedule_id END) as scheduled_sessions
          FROM roles r
          LEFT JOIN user_roles ur ON r.role_id = ur.role_id
          LEFT JOIN users u ON ur.user_id = u.user_id AND u.status = 'active'
          LEFT JOIN attendance a ON u.user_id = a.user_id
            AND DATE(a.check_in_time) BETWEEN :s AND :e
          LEFT JOIN schedules s ON u.user_id = s.user_id
          WHERE r.role_name IN ('faculty', 'staff', 'program head', 'secretary')
          GROUP BY r.role_name
          ORDER BY r.role_name
        ");
        $stmt->execute([':s'=>$start, ':e'=>$end]);
        $results = $stmt->fetchAll();

        // Calculate performance metrics for each department group
        $totalDays = (strtotime($end) - strtotime($start)) / (60 * 60 * 24) + 1;
        $formattedResults = array_map(function($row) use ($totalDays) {
          $totalUsers = $row['total_users'];
          $expectedUserDays = $totalUsers * $totalDays;
          $actualPresentDays = $row['present_days'];
          $attendanceRate = $expectedUserDays > 0 ? round(($actualPresentDays / $expectedUserDays) * 100, 2) : 0;
          $punctualityRate = $actualPresentDays > 0 ? round((($actualPresentDays - $row['late_days']) / $actualPresentDays) * 100, 2) : 0;

          $roomUtilization = ($row['room_sessions'] + $row['department_sessions']) > 0 ?
            round(($row['room_sessions'] / ($row['room_sessions'] + $row['department_sessions'])) * 100, 1) : 0;

          return [
            'department_group' => ucfirst($row['department_group']),
            'total_users' => $totalUsers,
            'expected_user_days' => $expectedUserDays,
            'actual_present_days' => $actualPresentDays,
            'absent_days' => $row['absent_days'],
            'late_days' => $row['late_days'],
            'attendance_rate' => $attendanceRate . '%',
            'punctuality_rate' => $punctualityRate . '%',
            'avg_daily_hours' => $row['avg_daily_hours'] ? round($row['avg_daily_hours'], 0) . ' min' : 'N/A',
            'room_sessions' => $row['room_sessions'],
            'department_sessions' => $row['department_sessions'],
            'room_utilization_rate' => $roomUtilization . '%',
            'scheduled_sessions' => $row['scheduled_sessions']
          ];
        }, $results);

        // Calculate comparative metrics
        $comparison = [];
        if (count($results) > 1) {
          $avgAttendanceRate = array_sum(array_map(function($r) use ($totalDays) {
            $expected = $r['total_users'] * $totalDays;
            $actual = $r['present_days'];
            return $expected > 0 ? ($actual / $expected) * 100 : 0;
          }, $results)) / count($results);

          $comparison = [
            'avg_attendance_rate' => round($avgAttendanceRate, 2) . '%',
            'best_performing_group' => array_reduce($formattedResults, function($carry, $item) {
              $rate = floatval(str_replace('%', '', $item['attendance_rate']));
              return !$carry || $rate > floatval(str_replace('%', '', $carry['attendance_rate'])) ? $item : $carry;
            }),
            'total_active_users' => array_sum(array_column($results, 'total_users')),
            'total_present_days' => array_sum(array_column($results, 'present_days')),
            'total_room_sessions' => array_sum(array_column($results, 'room_sessions'))
          ];
        }

        echo json_encode([
          'items' => $formattedResults,
          'comparison' => $comparison,
          'period_days' => $totalDays
        ]);
        exit;
      }

      if ($type === 'time_analytics') {
        // Calculate time-based attendance patterns
        $stmt = $db->prepare("
          SELECT
            HOUR(a.check_in_time) as hour_of_day,
            COUNT(*) as checkins_count,
            COUNT(DISTINCT a.user_id) as unique_users,
            DAYNAME(a.check_in_time) as day_name,
            DATE(a.check_in_time) as checkin_date,
            MONTH(a.check_in_time) as month_num,
            YEAR(a.check_in_time) as year_num,
            CASE
              WHEN HOUR(a.check_in_time) BETWEEN 6 AND 11 THEN 'Morning'
              WHEN HOUR(a.check_in_time) BETWEEN 12 AND 16 THEN 'Afternoon'
              WHEN HOUR(a.check_in_time) BETWEEN 17 AND 21 THEN 'Evening'
              ELSE 'Night'
            END as time_period
          FROM attendance a
          WHERE DATE(a.check_in_time) BETWEEN :s AND :e
          GROUP BY HOUR(a.check_in_time), DAYNAME(a.check_in_time), DATE(a.check_in_time), MONTH(a.check_in_time), YEAR(a.check_in_time)
          ORDER BY checkin_date, hour_of_day
        ");
        $stmt->execute([':s'=>$start, ':e'=>$end]);
        $hourlyData = $stmt->fetchAll();

        // Calculate daily patterns
        $dailyStmt = $db->prepare("
          SELECT
            DAYNAME(a.check_in_time) as day_name,
            COUNT(*) as total_checkins,
            COUNT(DISTINCT a.user_id) as unique_users,
            AVG(CASE WHEN a.check_out_time IS NOT NULL
                     THEN TIMESTAMPDIFF(MINUTE, a.check_in_time, a.check_out_time)
                     END) as avg_duration,
            MIN(TIME(a.check_in_time)) as earliest_checkin,
            MAX(TIME(a.check_in_time)) as latest_checkin
          FROM attendance a
          WHERE DATE(a.check_in_time) BETWEEN :s AND :e
          GROUP BY DAYNAME(a.check_in_time)
          ORDER BY FIELD(day_name, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
        ");
        $dailyStmt->execute([':s'=>$start, ':e'=>$end]);
        $dailyPatterns = $dailyStmt->fetchAll();

        // Calculate monthly trends
        $monthlyStmt = $db->prepare("
          SELECT
            DATE_FORMAT(a.check_in_time, '%Y-%m') as month_year,
            COUNT(*) as total_checkins,
            COUNT(DISTINCT a.user_id) as unique_users,
            COUNT(DISTINCT DATE(a.check_in_time)) as active_days,
            AVG(CASE WHEN a.check_out_time IS NOT NULL
                     THEN TIMESTAMPDIFF(MINUTE, a.check_in_time, a.check_out_time)
                     END) as avg_duration
          FROM attendance a
          WHERE DATE(a.check_in_time) BETWEEN :s AND :e
          GROUP BY DATE_FORMAT(a.check_in_time, '%Y-%m')
          ORDER BY month_year
        ");
        $monthlyStmt->execute([':s'=>$start, ':e'=>$end]);
        $monthlyTrends = $monthlyStmt->fetchAll();

        // Process hourly data for patterns
        $hourlyPatterns = [];
        $timePeriodStats = [];
        foreach ($hourlyData as $row) {
          $hour = $row['hour_of_day'];
          if (!isset($hourlyPatterns[$hour])) {
            $hourlyPatterns[$hour] = [
              'hour' => $hour,
              'total_checkins' => 0,
              'unique_users' => 0,
              'avg_users_per_day' => 0,
              'days_count' => 0
            ];
          }
          $hourlyPatterns[$hour]['total_checkins'] += $row['checkins_count'];
          $hourlyPatterns[$hour]['unique_users'] += $row['unique_users'];
          $hourlyPatterns[$hour]['days_count']++;

          // Time period stats
          $period = $row['time_period'];
          if (!isset($timePeriodStats[$period])) {
            $timePeriodStats[$period] = ['checkins' => 0, 'users' => 0];
          }
          $timePeriodStats[$period]['checkins'] += $row['checkins_count'];
          $timePeriodStats[$period]['users'] += $row['unique_users'];
        }

        // Calculate averages for hourly patterns
        foreach ($hourlyPatterns as &$pattern) {
          $pattern['avg_users_per_day'] = $pattern['days_count'] > 0 ?
            round($pattern['unique_users'] / $pattern['days_count'], 1) : 0;
        }

        // Format results
        $formattedHourly = array_values($hourlyPatterns);
        $formattedDaily = array_map(function($day) {
          return [
            'day_name' => $day['day_name'],
            'total_checkins' => $day['total_checkins'],
            'unique_users' => $day['unique_users'],
            'avg_duration' => $day['avg_duration'] ? round($day['avg_duration'], 0) . ' min' : 'N/A',
            'earliest_checkin' => $day['earliest_checkin'],
            'latest_checkin' => $day['latest_checkin']
          ];
        }, $dailyPatterns);

        $formattedMonthly = array_map(function($month) {
          return [
            'month_year' => $month['month_year'],
            'total_checkins' => $month['total_checkins'],
            'unique_users' => $month['unique_users'],
            'active_days' => $month['active_days'],
            'avg_duration' => $month['avg_duration'] ? round($month['avg_duration'], 0) . ' min' : 'N/A'
          ];
        }, $monthlyTrends);

        // Calculate peak periods
        $totalCheckins = array_sum(array_column($timePeriodStats, 'checkins'));
        $peakAnalysis = [];
        foreach ($timePeriodStats as $period => $stats) {
          $percentage = $totalCheckins > 0 ? round(($stats['checkins'] / $totalCheckins) * 100, 1) : 0;
          $peakAnalysis[$period] = [
            'period' => $period,
            'checkins' => $stats['checkins'],
            'unique_users' => $stats['users'],
            'percentage' => $percentage . '%'
          ];
        }

        echo json_encode([
          'hourly_patterns' => $formattedHourly,
          'daily_patterns' => $formattedDaily,
          'monthly_trends' => $formattedMonthly,
          'peak_periods' => array_values($peakAnalysis)
        ]);
        exit;
      }

      if ($type === 'alert_incident') {
        // Calculate alert and incident analytics
        $alertStmt = $db->prepare("
          SELECT
            sa.alert_id,
            sa.message,
            sa.type,
            sa.priority,
            sa.created_at,
            sa.is_active,
            sa.expires_at,
            TIMESTAMPDIFF(HOUR, sa.created_at, COALESCE(sa.expires_at, NOW())) as alert_duration_hours,
            CASE
              WHEN sa.is_active = 1 AND sa.expires_at IS NULL THEN 'Active'
              WHEN sa.is_active = 1 AND sa.expires_at > NOW() THEN 'Active (Expiring)'
              WHEN sa.is_active = 0 THEN 'Resolved'
              ELSE 'Expired'
            END as status
          FROM system_alerts sa
          WHERE DATE(sa.created_at) BETWEEN :s AND :e
          ORDER BY sa.created_at DESC
        ");
        $alertStmt->execute([':s'=>$start, ':e'=>$end]);
        $alerts = $alertStmt->fetchAll();

        // Calculate attendance incidents (late arrivals, absences, etc.)
        $incidentStmt = $db->prepare("
          SELECT
            DATE(a.check_in_time) as incident_date,
            'Late Arrival' as incident_type,
            CONCAT(u.first_name, ' ', u.last_name) as affected_user,
            u.employee_id,
            TIME(a.check_in_time) as checkin_time,
            TIMESTAMPDIFF(MINUTE, CONCAT(DATE(a.check_in_time), ' 09:00:00'), a.check_in_time) as minutes_late,
            CASE
              WHEN TIMESTAMPDIFF(MINUTE, CONCAT(DATE(a.check_in_time), ' 09:00:00'), a.check_in_time) > 30 THEN 'Critical'
              WHEN TIMESTAMPDIFF(MINUTE, CONCAT(DATE(a.check_in_time), ' 09:00:00'), a.check_in_time) > 15 THEN 'Warning'
              ELSE 'Minor'
            END as severity
          FROM attendance a
          JOIN users u ON a.user_id = u.user_id
          WHERE DATE(a.check_in_time) BETWEEN :s AND :e
            AND TIME(a.check_in_time) > '09:00:00'
            AND TIMESTAMPDIFF(MINUTE, CONCAT(DATE(a.check_in_time), ' 09:00:00'), a.check_in_time) > 0

          UNION ALL

          SELECT
            DATE(a.check_in_time) as incident_date,
            'Early Departure' as incident_type,
            CONCAT(u.first_name, ' ', u.last_name) as affected_user,
            u.employee_id,
            TIME(a.check_out_time) as checkin_time,
            TIMESTAMPDIFF(MINUTE, a.check_out_time, CONCAT(DATE(a.check_in_time), ' 17:00:00')) as minutes_late,
            CASE
              WHEN TIMESTAMPDIFF(MINUTE, a.check_out_time, CONCAT(DATE(a.check_in_time), ' 17:00:00')) > 60 THEN 'Critical'
              WHEN TIMESTAMPDIFF(MINUTE, a.check_out_time, CONCAT(DATE(a.check_in_time), ' 17:00:00')) > 30 THEN 'Warning'
              ELSE 'Minor'
            END as severity
          FROM attendance a
          JOIN users u ON a.user_id = u.user_id
          WHERE DATE(a.check_in_time) BETWEEN :s AND :e
            AND a.check_out_time IS NOT NULL
            AND TIME(a.check_out_time) < '17:00:00'
            AND TIMESTAMPDIFF(MINUTE, a.check_out_time, CONCAT(DATE(a.check_in_time), ' 17:00:00')) > 0

          ORDER BY incident_date DESC, severity DESC
        ");
        $incidentStmt->execute([':s'=>$start, ':e'=>$end]);
        $incidents = $incidentStmt->fetchAll();

        // Calculate system errors from audit trail
        $errorStmt = $db->prepare("
          SELECT
            DATE(a.created_at) as error_date,
            a.action as error_type,
            COUNT(*) as occurrences,
            COUNT(DISTINCT a.user_id) as affected_users,
            GROUP_CONCAT(DISTINCT CONCAT(u.first_name, ' ', u.last_name) LIMIT 3) as sample_users
          FROM audit_trail a
          LEFT JOIN users u ON a.user_id = u.user_id
          WHERE DATE(a.created_at) BETWEEN :s AND :e
            AND (a.action LIKE '%ERROR%' OR a.action LIKE '%FAILED%' OR a.action LIKE '%DENIED%' OR a.action LIKE '%INVALID%')
          GROUP BY DATE(a.created_at), a.action
          ORDER BY error_date DESC, occurrences DESC
        ");
        $errorStmt->execute([':s'=>$start, ':e'=>$end]);
        $systemErrors = $errorStmt->fetchAll();

        // Calculate summary statistics
        $totalDays = (strtotime($end) - strtotime($start)) / (60 * 60 * 24) + 1;
        $alertsByType = [];
        $alertsByPriority = [];
        $incidentsByType = [];
        $incidentsBySeverity = [];

        foreach ($alerts as $alert) {
          $type = $alert['type'];
          $priority = $alert['priority'];
          if (!isset($alertsByType[$type])) $alertsByType[$type] = 0;
          if (!isset($alertsByPriority[$priority])) $alertsByPriority[$priority] = 0;
          $alertsByType[$type]++;
          $alertsByPriority[$priority]++;
        }

        foreach ($incidents as $incident) {
          $type = $incident['incident_type'];
          $severity = $incident['severity'];
          if (!isset($incidentsByType[$type])) $incidentsByType[$type] = 0;
          if (!isset($incidentsBySeverity[$severity])) $incidentsBySeverity[$severity] = 0;
          $incidentsByType[$type]++;
          $incidentsBySeverity[$severity]++;
        }

        // Format results
        $formattedAlerts = array_map(function($alert) {
          return [
            'alert_id' => $alert['alert_id'],
            'message' => $alert['message'],
            'type' => $alert['type'],
            'priority' => $alert['priority'],
            'status' => $alert['status'],
            'created_at' => $alert['created_at'],
            'duration_hours' => $alert['alert_duration_hours'] ? round($alert['alert_duration_hours'], 1) : 'N/A'
          ];
        }, $alerts);

        $formattedIncidents = array_map(function($incident) {
          return [
            'incident_date' => $incident['incident_date'],
            'incident_type' => $incident['incident_type'],
            'affected_user' => $incident['affected_user'],
            'employee_id' => $incident['employee_id'],
            'details' => $incident['checkin_time'],
            'minutes_deviation' => $incident['minutes_late'],
            'severity' => $incident['severity']
          ];
        }, $incidents);

        $formattedErrors = array_map(function($error) {
          return [
            'error_date' => $error['error_date'],
            'error_type' => $error['error_type'],
            'occurrences' => $error['occurrences'],
            'affected_users' => $error['affected_users'],
            'sample_users' => $error['sample_users']
          ];
        }, $systemErrors);

        echo json_encode([
          'summary' => [
            'total_days' => $totalDays,
            'total_alerts' => count($alerts),
            'total_incidents' => count($incidents),
            'total_system_errors' => array_sum(array_column($systemErrors, 'occurrences')),
            'alerts_per_day' => $totalDays > 0 ? round(count($alerts) / $totalDays, 2) : 0,
            'incidents_per_day' => $totalDays > 0 ? round(count($incidents) / $totalDays, 2) : 0
          ],
          'alerts' => $formattedAlerts,
          'incidents' => $formattedIncidents,
          'system_errors' => $formattedErrors,
          'breakdown' => [
            'alerts_by_type' => $alertsByType,
            'alerts_by_priority' => $alertsByPriority,
            'incidents_by_type' => $incidentsByType,
            'incidents_by_severity' => $incidentsBySeverity
          ]
        ]);
        exit;
      }

      if ($type === 'resource_allocation') {
        // Calculate resource allocation and utilization analytics
        $roomStmt = $db->prepare("
          SELECT
            r.room_id,
            r.room_code,
            r.name,
            r.capacity,
            b.name as building_name,
            f.floor_number,
            COUNT(DISTINCT a.attendance_id) as total_sessions,
            COUNT(DISTINCT DATE(a.check_in_time)) as active_days,
            AVG(CASE WHEN a.check_out_time IS NOT NULL
                     THEN TIMESTAMPDIFF(MINUTE, a.check_in_time, a.check_out_time)
                     END) as avg_session_duration,
            COUNT(DISTINCT s.schedule_id) as scheduled_sessions,
            MAX(a.check_in_time) as last_used,
            DATEDIFF(NOW(), MAX(a.check_in_time)) as days_since_last_use
          FROM rooms r
          LEFT JOIN floors f ON r.floor_id = f.floor_id
          LEFT JOIN buildings b ON f.building_id = b.building_id
          LEFT JOIN attendance a ON r.room_id = a.room_id
            AND DATE(a.check_in_time) BETWEEN :s AND :e
           LEFT JOIN schedules s ON r.room_id = s.room_id
           WHERE r.status = 'active'
           GROUP BY r.room_id, r.room_code, r.name, r.capacity, building_name, f.floor_number
           ORDER BY building_name, f.floor_number, r.room_code
        ");
        $roomStmt->execute([':s'=>$start, ':e'=>$end]);
        $roomData = $roomStmt->fetchAll();

        // Calculate building-level resource allocation
        $buildingStmt = $db->prepare("
          SELECT
            b.building_id,
            b.name as building_name,
            COUNT(DISTINCT r.room_id) as total_rooms,
            SUM(r.capacity) as total_capacity,
            COUNT(DISTINCT CASE WHEN a.room_id IS NOT NULL THEN a.attendance_id END) as utilized_sessions,
            COUNT(DISTINCT CASE WHEN a.room_id IS NOT NULL THEN DATE(a.check_in_time) END) as active_days,
            COUNT(DISTINCT s.schedule_id) as total_scheduled_sessions,
            AVG(r.capacity) as avg_room_capacity
          FROM buildings b
          LEFT JOIN floors f ON b.building_id = f.building_id
          LEFT JOIN rooms r ON f.floor_id = r.floor_id AND r.status = 'active'
          LEFT JOIN attendance a ON r.room_id = a.room_id
            AND DATE(a.check_in_time) BETWEEN :s AND :e
          LEFT JOIN schedules s ON r.room_id = s.room_id
          WHERE b.status = 'active'
          GROUP BY b.building_id, building_name
          ORDER BY building_name
        ");
        $buildingStmt->execute([':s'=>$start, ':e'=>$end]);
        $buildingData = $buildingStmt->fetchAll();

        // Calculate utilization efficiency metrics
        $totalDays = (strtotime($end) - strtotime($start)) / (60 * 60 * 24) + 1;

        $formattedRooms = array_map(function($room) use ($totalDays) {
          $utilizationRate = $totalDays > 0 ? round(($room['active_days'] / $totalDays) * 100, 2) : 0;
          $capacityEfficiency = $room['total_sessions'] > 0 ?
            round(($room['total_sessions'] / ($room['capacity'] * $room['active_days'])) * 100, 2) : 0;

          $allocationStatus = 'Under-utilized';
          if ($utilizationRate > 80) $allocationStatus = 'Over-utilized';
          else if ($utilizationRate > 50) $allocationStatus = 'Well-utilized';
          else if ($utilizationRate > 20) $allocationStatus = 'Moderately-utilized';

          return [
            'room_id' => $room['room_id'],
            'room_number' => $room['room_code'],
            'room_name' => $room['name'],
            'building' => $room['building_name'],
            'floor' => $room['floor_number'],
            'capacity' => $room['capacity'],
            'total_sessions' => $room['total_sessions'],
            'active_days' => $room['active_days'],
            'utilization_rate' => $utilizationRate . '%',
            'capacity_efficiency' => $capacityEfficiency . '%',
            'scheduled_sessions' => $room['scheduled_sessions'],
            'avg_session_duration' => $room['avg_session_duration'] ? round($room['avg_session_duration'], 0) . ' min' : 'N/A',
            'allocation_status' => $allocationStatus,
            'last_used' => $room['last_used'],
            'days_since_last_use' => $room['days_since_last_use']
          ];
        }, $roomData);

        $formattedBuildings = array_map(function($building) use ($totalDays) {
          $utilizationRate = $totalDays > 0 ? round(($building['active_days'] / $totalDays) * 100, 2) : 0;
          $allocationEfficiency = $building['total_rooms'] > 0 ?
            round(($building['utilized_sessions'] / ($building['total_capacity'] * $building['active_days'])) * 100, 2) : 0;

          return [
            'building_name' => $building['building_name'],
            'total_rooms' => $building['total_rooms'],
            'total_capacity' => $building['total_capacity'],
            'utilized_sessions' => $building['utilized_sessions'],
            'active_days' => $building['active_days'],
            'utilization_rate' => $utilizationRate . '%',
            'allocation_efficiency' => $allocationEfficiency . '%',
            'scheduled_sessions' => $building['total_scheduled_sessions'],
            'avg_room_capacity' => round($building['avg_room_capacity'], 1)
          ];
        }, $buildingData);

        // Calculate overall resource allocation insights
        $totalRooms = array_sum(array_column($buildingData, 'total_rooms'));
        $totalCapacity = array_sum(array_column($buildingData, 'total_capacity'));
        $totalUtilizedSessions = array_sum(array_column($buildingData, 'utilized_sessions'));
        $totalScheduledSessions = array_sum(array_column($buildingData, 'total_scheduled_sessions'));

        $overallUtilization = $totalCapacity > 0 && $totalDays > 0 ?
          round(($totalUtilizedSessions / ($totalCapacity * $totalDays)) * 100, 2) : 0;

        $insights = [
          'under_utilized_rooms' => count(array_filter($formattedRooms, function($r) {
            return strpos($r['allocation_status'], 'Under-utilized') !== false;
          })),
          'well_utilized_rooms' => count(array_filter($formattedRooms, function($r) {
            return strpos($r['allocation_status'], 'Well-utilized') !== false;
          })),
          'over_utilized_rooms' => count(array_filter($formattedRooms, function($r) {
            return strpos($r['allocation_status'], 'Over-utilized') !== false;
          })),
          'unused_rooms' => count(array_filter($formattedRooms, function($r) {
            return $r['active_days'] == 0;
          })),
          'avg_utilization_rate' => $totalRooms > 0 ?
            round(array_sum(array_map(function($r) {
              return floatval(str_replace('%', '', $r['utilization_rate']));
            }, $formattedRooms)) / $totalRooms, 2) . '%' : '0%'
        ];

        echo json_encode([
          'summary' => [
            'total_rooms' => $totalRooms,
            'total_capacity' => $totalCapacity,
            'overall_utilization' => $overallUtilization . '%',
            'total_scheduled_sessions' => $totalScheduledSessions,
            'period_days' => $totalDays
          ],
          'buildings' => $formattedBuildings,
          'rooms' => $formattedRooms,
          'insights' => $insights
        ]);
        exit;
      }

      if ($type === 'faculty_attendance') {
        // Calculate faculty attendance summary metrics
        $stmt = $db->prepare("
          SELECT
            u.user_id,
            u.employee_id,
            CONCAT(u.first_name, ' ', u.last_name) as faculty_name,
            COUNT(DISTINCT DATE(a.check_in_time)) as total_days_present,
            COUNT(DISTINCT CASE WHEN TIME(a.check_in_time) > '09:00:00' THEN DATE(a.check_in_time) END) as late_days,
            COUNT(DISTINCT CASE WHEN a.status = 'absent' THEN DATE(a.check_in_time) END) as absent_days,
            AVG(CASE WHEN a.check_out_time IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, a.check_in_time, a.check_out_time) END) as avg_daily_hours,
            MIN(TIME(a.check_in_time)) as earliest_checkin_time,
            MAX(TIME(a.check_in_time)) as latest_checkin_time,
            GROUP_CONCAT(DISTINCT r.role_name) as roles
          FROM users u
          LEFT JOIN attendance a ON u.user_id = a.user_id
            AND DATE(a.check_in_time) BETWEEN :s AND :e
          LEFT JOIN user_roles ur ON u.user_id = ur.user_id
          LEFT JOIN roles r ON ur.role_id = r.role_id
          WHERE u.status = 'active' AND (
            r.role_name = 'faculty' OR
            r.role_name = 'program head' OR
            r.role_name = 'staff'
          )
          GROUP BY u.user_id, u.employee_id, u.first_name, u.last_name
          ORDER BY u.last_name, u.first_name
        ");
        $stmt->execute([':s'=>$start, ':e'=>$end]);
        $results = $stmt->fetchAll();

        // Calculate attendance percentage and format results
        $formattedResults = array_map(function($row) use ($start, $end) {
          $totalDays = (strtotime($end) - strtotime($start)) / (60 * 60 * 24) + 1;
          $presentDays = $row['total_days_present'];
          $absentDays = $row['absent_days'];
          $attendanceRate = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 2) : 0;
          $lateRate = $presentDays > 0 ? round(($row['late_days'] / $presentDays) * 100, 2) : 0;

          return [
            'employee_id' => $row['employee_id'],
            'faculty_name' => $row['faculty_name'],
            'roles' => $row['roles'] ?: 'No Role',
            'total_days_present' => $presentDays,
            'absent_days' => $absentDays,
            'late_days' => $row['late_days'],
            'attendance_rate' => $attendanceRate . '%',
            'punctuality_rate' => (100 - $lateRate) . '%',
            'avg_daily_hours' => $row['avg_daily_hours'] ? round($row['avg_daily_hours'] / 60, 1) . ' hrs' : 'N/A',
            'earliest_checkin' => $row['earliest_checkin_time'] ?: 'N/A',
            'latest_checkin' => $row['latest_checkin_time'] ?: 'N/A'
          ];
        }, $results);

        echo json_encode(['items'=>$formattedResults]);
        exit;
      }

    echo json_encode(['items'=>[]]);
    exit;
  }

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Server error']);
}
