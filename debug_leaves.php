<?php
// Debug script to check leave requests
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';

header('Content-Type: text/plain');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['uid'])) {
    echo "Not logged in\n";
    exit;
}

$uid = $_SESSION['uid'];
$userRoles = $_SESSION['roles'] ?? [];

echo "User ID: $uid\n";
echo "User Roles: " . implode(', ', $userRoles) . "\n\n";

$db = (new Database())->getConnection();

echo "=== LEAVE REQUESTS ===\n";
$stmt = $db->prepare("SELECT * FROM leave_requests WHERE user_id = ? ORDER BY requested_at DESC");
$stmt->execute([$uid]);
$leaves = $stmt->fetchAll();

if (empty($leaves)) {
    echo "No leave requests found for this user\n";
} else {
    foreach ($leaves as $leave) {
        echo "ID: {$leave['leave_id']}, Type: {$leave['leave_type']}, Start: {$leave['start_date']}, End: {$leave['end_date']}, Status: {$leave['status']}, Requested: {$leave['requested_at']}\n";
    }
}

echo "\n=== ARCHIVED LEAVE REQUESTS ===\n";
$stmt = $db->prepare("SELECT * FROM archived_leave_requests WHERE user_id = ? ORDER BY archived_at DESC");
$stmt->execute([$uid]);
$archived = $stmt->fetchAll();

if (empty($archived)) {
    echo "No archived leave requests found for this user\n";
} else {
    foreach ($archived as $leave) {
        echo "ID: {$leave['leave_id']}, Type: {$leave['leave_type']}, Start: {$leave['start_date']}, End: {$leave['end_date']}, Status: {$leave['status']}, Archived: {$leave['archived_at']}\n";
    }
}
?>