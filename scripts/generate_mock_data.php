<?php
// Mock Data Generator for CRM FMS Reports Testing
// Run this script to populate the database with realistic test data

require_once __DIR__ . '/../config/database.php';

try {
    $db = (new Database())->getConnection();
    echo "Starting mock data generation...\n";

    // Generate attendance data for the last 30 days
    $startDate = date('Y-m-d', strtotime('-30 days'));
    $endDate = date('Y-m-d');

    echo "Generating attendance data from $startDate to $endDate...\n";

    // Get all active users
    $userStmt = $db->query("SELECT user_id, employee_id FROM users WHERE status = 'active' ORDER BY user_id");
    $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all rooms
    $roomStmt = $db->query("SELECT room_id FROM rooms WHERE status = 'active'");
    $rooms = $roomStmt->fetchAll(PDO::FETCH_COLUMN);

    $attendanceCount = 0;
    $scheduleCount = 0;

    foreach ($users as $user) {
        $userId = $user['user_id'];

        // Generate attendance for each day
        $currentDate = strtotime($startDate);
        $endDateTime = strtotime($endDate);

        while ($currentDate <= $endDateTime) {
            $dateStr = date('Y-m-d', $currentDate);
            $dayOfWeek = date('N', $currentDate); // 1=Monday, 7=Sunday

            // Skip weekends (simulate typical work week)
            if ($dayOfWeek >= 6) {
                $currentDate = strtotime('+1 day', $currentDate);
                continue;
            }

            // 85% attendance rate (some absences)
            if (rand(1, 100) <= 85) {
                // Generate check-in time (between 8:00-9:30 AM)
                $checkInHour = rand(8, 9);
                $checkInMinute = rand(0, 59);
                if ($checkInHour == 9) $checkInMinute = rand(0, 30); // No later than 9:30

                $checkInTime = sprintf('%s %02d:%02d:00', $dateStr, $checkInHour, $checkInMinute);

                // Generate check-out time (4-6 hours later)
                $sessionHours = rand(4, 6);
                $checkOutTime = date('Y-m-d H:i:s', strtotime($checkInTime) + ($sessionHours * 3600));

                // 70% chance of room-based attendance, 30% department attendance
                $roomId = null;
                if (rand(1, 100) <= 70 && !empty($rooms)) {
                    $roomId = $rooms[array_rand($rooms)];
                }

                // Insert attendance record
                $stmt = $db->prepare("INSERT INTO attendance (user_id, check_in_time, check_out_time, room_id)
                                     VALUES (?, ?, ?, ?)");
                $stmt->execute([$userId, $checkInTime, $checkOutTime, $roomId]);
                $attendanceCount++;
            }

            $currentDate = strtotime('+1 day', $currentDate);
        }

        // Generate schedules for faculty users (simulate 2-3 classes per week)
        if (rand(1, 100) <= 60) { // 60% of users have schedules
            $classesPerWeek = rand(2, 4);

            for ($i = 0; $i < $classesPerWeek; $i++) {
                $dayOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'][rand(0, 4)]; // Monday to Friday
                $startHour = rand(8, 14); // 8 AM to 2 PM
                $duration = rand(1, 3); // 1-3 hours

                $startTime = sprintf('%02d:00:00', $startHour);
                $endTime = sprintf('%02d:00:00', $startHour + $duration);

                $subject = ['Mathematics', 'Physics', 'Chemistry', 'Biology', 'Computer Science', 'English', 'History'][array_rand(['Mathematics', 'Physics', 'Chemistry', 'Biology', 'Computer Science', 'English', 'History'])];

                if (!empty($rooms)) {
                    $roomId = $rooms[array_rand($rooms)];

                    $stmt = $db->prepare("INSERT INTO schedules (user_id, room_id, day_of_week, start_time, end_time, subject)
                                         VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$userId, $roomId, $dayOfWeek, $startTime, $endTime, $subject]);
                    $scheduleCount++;
                }
            }
        }
    }

    // Generate some leave requests
    echo "Generating leave requests...\n";
    $leaveCount = 0;
    $leaveTypes = ['Sick Leave', 'Vacation', 'Personal Leave', 'Maternity Leave', 'Emergency Leave'];

    for ($i = 0; $i < 15; $i++) { // Generate 15 leave requests
        $userId = $users[array_rand($users)]['user_id'];
        $leaveType = $leaveTypes[array_rand($leaveTypes)];
        $startDate = date('Y-m-d', strtotime('-' . rand(0, 30) . ' days'));
        $endDate = date('Y-m-d', strtotime($startDate . ' +' . rand(1, 5) . ' days'));
        $reason = ['Medical appointment', 'Family emergency', 'Vacation', 'Personal matters', 'Doctor visit'][array_rand(['Medical appointment', 'Family emergency', 'Vacation', 'Personal matters', 'Doctor visit'])];
        $status = ['approved', 'pending', 'rejected'][array_rand(['approved', 'pending', 'rejected'])];

        $stmt = $db->prepare("INSERT INTO leaves (user_id, leave_type, start_date, end_date, reason, status, created_at)
                             VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $leaveType, $startDate, $endDate, $reason, $status]);
        $leaveCount++;
    }

    // Generate some system alerts
    echo "Generating system alerts...\n";
    $alertCount = 0;
    $alertMessages = [
        'High server load detected',
        'Database backup completed successfully',
        'New user registration requires approval',
        'System maintenance scheduled for tonight',
        'Attendance system sync completed',
        'Low disk space warning',
        'Network connectivity issues detected',
        'QR code generation service restarted'
    ];

    for ($i = 0; $i < 10; $i++) {
        $message = $alertMessages[array_rand($alertMessages)];
        $type = ['info', 'warning', 'error', 'success'][array_rand(['info', 'warning', 'error', 'success'])];
        $priority = rand(1, 3);

        $stmt = $db->prepare("INSERT INTO system_alerts (message, type, priority, created_at, is_active)
                             VALUES (?, ?, ?, NOW(), 1)");
        $stmt->execute([$message, $type, $priority]);
        $alertCount++;
    }

    // Generate some audit trail entries
    echo "Generating audit trail entries...\n";
    $auditCount = 0;
    $actions = ['LOGIN_SUCCESS', 'LOGOUT', 'QR_SCAN_SUCCESS', 'MANUAL_CHECK_IN', 'MANUAL_CHECK_OUT', 'REPORT_GENERATED', 'USER_UPDATED'];

    for ($i = 0; $i < 50; $i++) {
        $userId = $users[array_rand($users)]['user_id'];
        $action = $actions[array_rand($actions)];
        $details = json_encode(['timestamp' => date('Y-m-d H:i:s'), 'ip' => '192.168.1.' . rand(1, 255)]);

        $stmt = $db->prepare("INSERT INTO audit_trail (user_id, action, details, created_at)
                             VALUES (?, ?, ?, NOW())");
        $stmt->execute([$userId, $action, $details]);
        $auditCount++;
    }

    echo "\nMock data generation completed successfully!\n";
    echo "Summary:\n";
    echo "- Attendance records: $attendanceCount\n";
    echo "- Schedule entries: $scheduleCount\n";
    echo "- Leave requests: $leaveCount\n";
    echo "- System alerts: $alertCount\n";
    echo "- Audit trail entries: $auditCount\n";

    echo "\nYou can now test the reports with realistic data!\n";

} catch (Throwable $e) {
    echo "Error generating mock data: " . $e->getMessage() . "\n";
    exit(1);
}
?>