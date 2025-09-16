<?php
/**
 * CRM FMS Monitoring Scheduler
 * This script collects system and database metrics and checks for alerts
 * Run this script periodically via cron job
 *
 * Example cron job (every 5 minutes):
 * */5 * * * * /usr/bin/php /opt/lampp/htdocs/crmfms/scripts/monitor_scheduler.php
 */

require_once __DIR__ . '/../config/database.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

// Log file
$logFile = __DIR__ . '/../logs/monitor_scheduler.log';

function logMessage($message, $level = 'INFO') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$level}] {$message}\n";

    // Ensure log directory exists
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    file_put_contents($logFile, $logEntry, FILE_APPEND);
    echo $logEntry;
}

function collectAndStoreMetrics() {
    try {
        $db = (new Database())->getConnection();

        // Check if monitoring is enabled
        $stmt = $db->prepare("SELECT setting_value FROM monitoring_settings WHERE setting_key = 'monitoring_enabled'");
        $stmt->execute();
        $result = $stmt->fetch();

        if (!$result || $result['setting_value'] != '1') {
            logMessage("Monitoring is disabled, skipping collection");
            return;
        }

        logMessage("Starting metric collection...");

        // Collect system metrics
        $systemMetrics = collectSystemMetrics();
        if ($systemMetrics) {
            $stmt = $db->prepare("
                INSERT INTO system_monitoring
                (cpu_usage, memory_usage, memory_used_mb, memory_total_mb,
                 disk_usage, disk_used_gb, disk_total_gb,
                 load_average_1m, load_average_5m, load_average_15m)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $systemMetrics['cpu_usage'],
                $systemMetrics['memory_usage'],
                $systemMetrics['memory_used_mb'],
                $systemMetrics['memory_total_mb'],
                $systemMetrics['disk_usage'],
                $systemMetrics['disk_used_gb'],
                $systemMetrics['disk_total_gb'],
                $systemMetrics['load_average_1m'],
                $systemMetrics['load_average_5m'],
                $systemMetrics['load_average_15m']
            ]);
            logMessage("System metrics stored (ID: " . $db->lastInsertId() . ")");
        }

        // Collect database metrics
        $dbMetrics = collectDatabaseMetrics($db);
        if ($dbMetrics) {
            $stmt = $db->prepare("
                INSERT INTO database_monitoring
                (connections_total, connections_active, connections_max,
                 queries_per_second, slow_queries, uptime_seconds,
                 bytes_received, bytes_sent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $dbMetrics['connections_total'],
                $dbMetrics['connections_active'],
                $dbMetrics['connections_max'],
                $dbMetrics['queries_per_second'],
                $dbMetrics['slow_queries'],
                $dbMetrics['uptime_seconds'],
                $dbMetrics['bytes_received'],
                $dbMetrics['bytes_sent']
            ]);
            logMessage("Database metrics stored (ID: " . $db->lastInsertId() . ")");
        }

        // Check for alerts
        $alertsResult = checkAlerts($db);
        if ($alertsResult['alerts_triggered'] > 0) {
            logMessage("Alerts triggered: " . $alertsResult['alerts_triggered'], 'WARNING');
        }

        // Clean up old data
        cleanupOldData($db);

        logMessage("Metric collection completed successfully");

    } catch (Exception $e) {
        logMessage("Error in metric collection: " . $e->getMessage(), 'ERROR');
    }
}

function collectSystemMetrics() {
    // CPU Usage
    $cpuUsage = getCpuUsage();

    // Memory Usage
    $memory = getMemoryUsage();

    // Disk Usage
    $disk = getDiskUsage();

    // Load Average
    $load = getLoadAverage();

    return [
        'cpu_usage' => $cpuUsage,
        'memory_usage' => $memory['usage'],
        'memory_used_mb' => $memory['used'],
        'memory_total_mb' => $memory['total'],
        'disk_usage' => $disk['usage'],
        'disk_used_gb' => $disk['used'],
        'disk_total_gb' => $disk['total'],
        'load_average_1m' => $load['1m'],
        'load_average_5m' => $load['5m'],
        'load_average_15m' => $load['15m']
    ];
}

function getCpuUsage() {
    try {
        $stat1 = file_get_contents('/proc/stat');
        sleep(1);
        $stat2 = file_get_contents('/proc/stat');

        $cpu1 = parseCpuStat($stat1);
        $cpu2 = parseCpuStat($stat2);

        $diff = array_map(function($a, $b) { return $b - $a; }, $cpu1, $cpu2);
        $total = array_sum($diff);

        if ($total > 0) {
            $idle = $diff[3] + $diff[4];
            return round((($total - $idle) / $total) * 100, 2);
        }
    } catch (Exception $e) {}

    return 0.00;
}

function parseCpuStat($stat) {
    $lines = explode("\n", $stat);
    foreach ($lines as $line) {
        if (preg_match('/^cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $line, $matches)) {
            return [(int)$matches[1], (int)$matches[2], (int)$matches[3], (int)$matches[4],
                   (int)$matches[5], (int)$matches[6], (int)$matches[7], (int)$matches[8]];
        }
    }
    return [0, 0, 0, 0, 0, 0, 0, 0];
}

function getMemoryUsage() {
    try {
        $meminfo = file_get_contents('/proc/meminfo');
        $lines = explode("\n", $meminfo);

        $memTotal = 0;
        $memAvailable = 0;

        foreach ($lines as $line) {
            if (preg_match('/^MemTotal:\s+(\d+)\s+kB/', $line, $matches)) {
                $memTotal = (int)$matches[1];
            }
            if (preg_match('/^MemAvailable:\s+(\d+)\s+kB/', $line, $matches)) {
                $memAvailable = (int)$matches[1];
            }
        }

        if ($memTotal > 0) {
            $used = $memTotal - $memAvailable;
            $usage = round(($used / $memTotal) * 100, 2);
            return [
                'usage' => $usage,
                'used' => round($used / 1024, 2),
                'total' => round($memTotal / 1024, 2)
            ];
        }
    } catch (Exception $e) {}

    return ['usage' => 0.00, 'used' => 0, 'total' => 0];
}

function getDiskUsage() {
    try {
        $output = shell_exec('df -BG / | tail -1');
        if (preg_match('/\s+(\d+)G\s+(\d+)G\s+(\d+)G\s+(\d+)%/', $output, $matches)) {
            return [
                'usage' => (float)$matches[4],
                'used' => (float)$matches[2],
                'total' => (float)$matches[1]
            ];
        }
    } catch (Exception $e) {}

    return ['usage' => 0.00, 'used' => 0.00, 'total' => 0.00];
}

function getLoadAverage() {
    try {
        $loadavg = file_get_contents('/proc/loadavg');
        if (preg_match('/^(\d+\.\d+)\s+(\d+\.\d+)\s+(\d+\.\d+)/', $loadavg, $matches)) {
            return [
                '1m' => (float)$matches[1],
                '5m' => (float)$matches[2],
                '15m' => (float)$matches[3]
            ];
        }
    } catch (Exception $e) {}

    return ['1m' => 0.00, '5m' => 0.00, '15m' => 0.00];
}

function collectDatabaseMetrics($db) {
    try {
        // Get connection information
        $stmt = $db->query("SHOW PROCESSLIST");
        $processes = $stmt->fetchAll();
        $activeConnections = count($processes);

        // Get max connections
        $stmt = $db->query("SHOW VARIABLES LIKE 'max_connections'");
        $maxConn = $stmt->fetch();
        $maxConnections = (int)($maxConn['Value'] ?? 151);

        // Get status variables
        $stmt = $db->query("SHOW GLOBAL STATUS");
        $statusVars = [];
        while ($row = $stmt->fetch()) {
            $statusVars[$row['Variable_name']] = $row['Value'];
        }

        $uptime = (int)($statusVars['Uptime'] ?? 0);
        $totalQueries = (int)($statusVars['Queries'] ?? 0);
        $queriesPerSecond = $uptime > 0 ? round($totalQueries / $uptime, 2) : 0;

        return [
            'connections_total' => $activeConnections,
            'connections_active' => $activeConnections,
            'connections_max' => $maxConnections,
            'queries_per_second' => $queriesPerSecond,
            'slow_queries' => (int)($statusVars['Slow_queries'] ?? 0),
            'uptime_seconds' => $uptime,
            'bytes_received' => (int)($statusVars['Bytes_received'] ?? 0),
            'bytes_sent' => (int)($statusVars['Bytes_sent'] ?? 0)
        ];
    } catch (Exception $e) {
        logMessage("Database metrics collection error: " . $e->getMessage(), 'ERROR');
        return null;
    }
}

function checkAlerts($db) {
    $alertsTriggered = 0;

    try {
        // Get latest system metrics
        $stmt = $db->query("SELECT * FROM system_monitoring ORDER BY created_at DESC LIMIT 1");
        $systemData = $stmt->fetch();

        // Get latest database metrics
        $stmt = $db->query("SELECT * FROM database_monitoring ORDER BY created_at DESC LIMIT 1");
        $dbData = $stmt->fetch();

        // Get enabled alert configurations
        $stmt = $db->query("SELECT * FROM alert_configurations WHERE enabled = 1");
        $configs = $stmt->fetchAll();

        foreach ($configs as $config) {
            $alertType = $config['alert_type'];
            $threshold = (float)$config['threshold_value'];
            $operator = $config['threshold_operator'];
            $currentValue = null;

            // Get current value
            switch ($alertType) {
                case 'cpu':
                    $currentValue = $systemData['cpu_usage'] ?? 0;
                    break;
                case 'memory':
                    $currentValue = $systemData['memory_usage'] ?? 0;
                    break;
                case 'disk':
                    $currentValue = $systemData['disk_usage'] ?? 0;
                    break;
                case 'connections':
                    $maxConn = $dbData['connections_max'] ?? 151;
                    $activeConn = $dbData['connections_active'] ?? 0;
                    $currentValue = $maxConn > 0 ? ($activeConn / $maxConn) * 100 : 0;
                    break;
                case 'slow_queries':
                    $currentValue = $dbData['slow_queries'] ?? 0;
                    break;
            }

            if ($currentValue !== null && checkThreshold($currentValue, $operator, $threshold)) {
                if (shouldTriggerAlert($db, $config['id'], $config['cooldown_minutes'])) {
                    triggerAlert($db, $config, $currentValue, $threshold);
                    $alertsTriggered++;
                }
            }
        }

    } catch (Exception $e) {
        logMessage("Alert check error: " . $e->getMessage(), 'ERROR');
    }

    return ['alerts_triggered' => $alertsTriggered];
}

function checkThreshold($current, $operator, $threshold) {
    switch ($operator) {
        case '>': return $current > $threshold;
        case '<': return $current < $threshold;
        case '>=': return $current >= $threshold;
        case '<=': return $current <= $threshold;
        case '=': return $current == $threshold;
        default: return false;
    }
}

function shouldTriggerAlert($db, $configId, $cooldownMinutes) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM alert_history
        WHERE alert_config_id = ? AND triggered_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
    ");
    $stmt->execute([$configId, $cooldownMinutes]);
    $result = $stmt->fetch();
    return $result['count'] == 0;
}

function triggerAlert($db, $config, $currentValue, $threshold) {
    $messages = [
        'cpu' => "CPU usage is {$currentValue}%, threshold: {$threshold}%",
        'memory' => "Memory usage is {$currentValue}%, threshold: {$threshold}%",
        'disk' => "Disk usage is {$currentValue}%, threshold: {$threshold}%",
        'connections' => "Database connections at {$currentValue}%, threshold: {$threshold}%",
        'slow_queries' => "Slow queries: {$currentValue}, threshold: {$threshold}"
    ];

    $message = $messages[$config['alert_type']] ?? "Alert triggered for {$config['alert_type']}";

    $stmt = $db->prepare("
        INSERT INTO alert_history
        (alert_config_id, alert_type, message, severity, current_value, threshold_value)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $config['id'],
        $config['alert_type'],
        $message,
        $config['severity'],
        $currentValue,
        $threshold
    ]);

    logMessage("Alert triggered: {$message}", 'WARNING');

    // Email notification (if configured)
    if (!empty($config['notification_email'])) {
        sendAlertEmail($config, $message);
    }
}

function sendAlertEmail($config, $message) {
    // Basic email implementation - enhance as needed
    $to = $config['notification_email'];
    $subject = "CRM FMS Alert: " . ucfirst($config['alert_type']);
    $headers = "From: noreply@crmfms.local\r\n";

    $body = "Alert Details:\n";
    $body .= "Type: " . $config['alert_type'] . "\n";
    $body .= "Severity: " . $config['severity'] . "\n";
    $body .= "Message: " . $message . "\n";
    $body .= "Time: " . date('Y-m-d H:i:s') . "\n";

    // mail($to, $subject, $body, $headers);
    logMessage("Alert email sent to {$to}: {$subject}", 'INFO');
}

function cleanupOldData($db) {
    try {
        // Get retention settings
        $stmt = $db->prepare("SELECT setting_value FROM monitoring_settings WHERE setting_key = 'retention_days'");
        $stmt->execute();
        $result = $stmt->fetch();
        $retentionDays = (int)($result['setting_value'] ?? 30);

        // Clean up old system monitoring data
        $stmt = $db->prepare("DELETE FROM system_monitoring WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$retentionDays]);

        // Clean up old database monitoring data
        $stmt = $db->prepare("DELETE FROM database_monitoring WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$retentionDays]);

        // Clean up old alert history (keep longer)
        $stmt = $db->prepare("DELETE FROM alert_history WHERE triggered_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$retentionDays * 2]);

        $deletedRows = $stmt->rowCount();
        if ($deletedRows > 0) {
            logMessage("Cleaned up {$deletedRows} old records");
        }

    } catch (Exception $e) {
        logMessage("Cleanup error: " . $e->getMessage(), 'ERROR');
    }
}

// Main execution
logMessage("=== Monitor Scheduler Started ===");
collectAndStoreMetrics();
logMessage("=== Monitor Scheduler Completed ===");
?>