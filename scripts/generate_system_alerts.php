<?php
/**
 * Automated System Alert Generation Script
 * This script monitors system metrics and creates alerts when thresholds are exceeded
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = (new Database())->getConnection();

    // Get latest system metrics
    $stmt = $db->query("SELECT * FROM system_monitoring ORDER BY created_at DESC LIMIT 1");
    $metrics = $stmt->fetch();

    if (!$metrics) {
        echo "No system metrics found. Please run system monitoring first.\n";
        exit(1);
    }

    $alertsCreated = 0;

    // Check CPU usage
    if ($metrics['cpu_usage'] > 80) {
        $severity = $metrics['cpu_usage'] > 95 ? 'error' : 'warning';
        $message = "High CPU usage detected: {$metrics['cpu_usage']}% (Threshold: 80%)";
        createAlert($db, $message, $severity, 3);
        $alertsCreated++;
    }

    // Check memory usage
    if ($metrics['memory_usage'] > 85) {
        $severity = $metrics['memory_usage'] > 95 ? 'error' : 'warning';
        $message = "High memory usage detected: {$metrics['memory_usage']}% ({$metrics['memory_used_mb']}MB used of {$metrics['memory_total_mb']}MB)";
        createAlert($db, $message, $severity, 3);
        $alertsCreated++;
    }

    // Check disk usage
    if ($metrics['disk_usage'] > 90) {
        $severity = $metrics['disk_usage'] > 95 ? 'error' : 'warning';
        $message = "High disk usage detected: {$metrics['disk_usage']}% ({$metrics['disk_used_gb']}GB used of {$metrics['disk_total_gb']}GB)";
        createAlert($db, $message, $severity, 3);
        $alertsCreated++;
    }

    // Check load average
    if ($metrics['load_average_1m'] > 2.0) {
        $message = "High system load detected: {$metrics['load_average_1m']} (1min average)";
        createAlert($db, $message, 'warning', 2);
        $alertsCreated++;
    }

    // Check database connections if available
    $stmt = $db->query("SELECT * FROM database_monitoring ORDER BY created_at DESC LIMIT 1");
    $dbMetrics = $stmt->fetch();

    if ($dbMetrics) {
        $connectionUsage = ($dbMetrics['connections_active'] / $dbMetrics['connections_max']) * 100;

        if ($connectionUsage > 80) {
            $severity = $connectionUsage > 95 ? 'error' : 'warning';
            $message = "High database connection usage: {$connectionUsage}% ({$dbMetrics['connections_active']} of {$dbMetrics['connections_max']} connections)";
            createAlert($db, $message, $severity, 2);
            $alertsCreated++;
        }

        if ($dbMetrics['slow_queries'] > 10) {
            $message = "High number of slow queries detected: {$dbMetrics['slow_queries']} slow queries";
            createAlert($db, $message, 'warning', 2);
            $alertsCreated++;
        }
    }

    // Clean up old alerts (keep only last 30 days)
    $stmt = $db->prepare("UPDATE system_alerts SET is_active = 0 WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();

    echo "System alert generation completed. Created $alertsCreated alerts.\n";

} catch (Exception $e) {
    echo "Error generating system alerts: " . $e->getMessage() . "\n";
    exit(1);
}

function createAlert($db, $message, $type, $priority) {
    try {
        // Check if similar alert already exists in the last hour
        $stmt = $db->prepare("
            SELECT alert_id FROM system_alerts
            WHERE message LIKE ?
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND is_active = 1
        ");
        $stmt->execute(["%" . substr($message, 0, 50) . "%"]);

        if ($stmt->fetch()) {
            // Similar alert already exists, don't create duplicate
            return;
        }

        // Create new alert
        $stmt = $db->prepare("
            INSERT INTO system_alerts (message, type, priority, expires_at, is_active, created_at)
            VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR), 1, NOW())
        ");
        $stmt->execute([$message, $type, $priority]);

        echo "Created alert: $message\n";

    } catch (Exception $e) {
        echo "Error creating alert: " . $e->getMessage() . "\n";
    }
}
?>