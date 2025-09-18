<?php
// Debug script to check current session
header('Content-Type: text/plain');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "Session Status: " . (isset($_SESSION['uid']) ? 'Logged in' : 'Not logged in') . "\n";

if (isset($_SESSION['uid'])) {
    echo "User ID: {$_SESSION['uid']}\n";
    echo "User Roles: " . (isset($_SESSION['roles']) ? implode(', ', $_SESSION['roles']) : 'None') . "\n";
} else {
    echo "No session data found\n";
}

echo "\nSession Data:\n";
print_r($_SESSION);
?>