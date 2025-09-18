<?php
// Script to update all existing users' employee_ids to CRIM001, CRIM002, etc.
require_once __DIR__ . '/../config/database.php';

try {
    $db = (new Database())->getConnection();

    // Get all existing users ordered by user_id
    $stmt = $db->query("SELECT user_id, employee_id FROM users ORDER BY user_id ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($users) . " users to update.\n";
    echo "Current employee_ids:\n";

    foreach ($users as $user) {
        echo "User ID: {$user['user_id']}, Employee ID: {$user['employee_id']}\n";
    }

    echo "\nUpdating employee_ids to CRIM format...\n";

    // Update each user with new CRIM ID
    $counter = 1;
    foreach ($users as $user) {
        $newEmployeeId = 'CRIM' . str_pad($counter, 3, '0', STR_PAD_LEFT);

        $updateStmt = $db->prepare("UPDATE users SET employee_id = :new_id WHERE user_id = :user_id");
        $updateStmt->execute([
            ':new_id' => $newEmployeeId,
            ':user_id' => $user['user_id']
        ]);

        echo "Updated User ID {$user['user_id']}: {$user['employee_id']} -> {$newEmployeeId}\n";
        $counter++;
    }

    echo "\nAll users updated successfully!\n";

    // Verify the updates
    echo "\nVerification - New employee_ids:\n";
    $stmt = $db->query("SELECT user_id, employee_id FROM users ORDER BY user_id ASC");
    $updatedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($updatedUsers as $user) {
        echo "User ID: {$user['user_id']}, Employee ID: {$user['employee_id']}\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>