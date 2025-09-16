<?php
// /api/monitoring/system_monitor.php
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = (new Database())->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';

        // Skip auth for automated store operations and public data collection
        if ($action !== 'store' && $action !== 'collect') {
            requireAuth();
        }

        if ($action === 'collect') {
            // Collect current system metrics
            $metrics = collectSystemMetrics();
            echo json_encode(['success' => true, 'data' => $metrics]);
            exit;
        }

        if ($action === 'history') {
            // Get historical system metrics
            $limit = (int)($_GET['limit'] ?? 100);
            $hours = (int)($_GET['hours'] ?? 24);

            $stmt = $db->prepare("
                SELECT * FROM system_monitoring
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$hours, $limit]);
            $data = $stmt->fetchAll();

            echo json_encode(['success' => true, 'data' => $data]);
            exit;
        }

        if ($action === 'latest') {
            // Get latest system metrics
            $stmt = $db->query("
                SELECT * FROM system_monitoring
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $data = $stmt->fetch();

            echo json_encode(['success' => true, 'data' => $data]);
            exit;
        }

        if ($action === 'store') {
            // Store current metrics in database (allow unauthenticated for cron jobs)
            $metrics = collectSystemMetrics();

            $stmt = $db->prepare("
                INSERT INTO system_monitoring
                (cpu_usage, memory_usage, memory_used_mb, memory_total_mb,
                 disk_usage, disk_used_gb, disk_total_gb,
                 load_average_1m, load_average_5m, load_average_15m)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $metrics['cpu_usage'],
                $metrics['memory_usage'],
                $metrics['memory_used_mb'],
                $metrics['memory_total_mb'],
                $metrics['disk_usage'],
                $metrics['disk_used_gb'],
                $metrics['disk_total_gb'],
                $metrics['load_average_1m'],
                $metrics['load_average_5m'],
                $metrics['load_average_15m']
            ]);

            echo json_encode(['success' => true, 'message' => 'System metrics stored', 'id' => $db->lastInsertId()]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Invalid request']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function collectSystemMetrics() {
    $metrics = [];

    // CPU Usage
    $cpuUsage = getCpuUsage();
    $metrics['cpu_usage'] = $cpuUsage;

    // Memory Usage
    $memory = getMemoryUsage();
    $metrics['memory_usage'] = $memory['usage'];
    $metrics['memory_used_mb'] = $memory['used'];
    $metrics['memory_total_mb'] = $memory['total'];

    // Disk Usage
    $disk = getDiskUsage();
    $metrics['disk_usage'] = $disk['usage'];
    $metrics['disk_used_gb'] = $disk['used'];
    $metrics['disk_total_gb'] = $disk['total'];

    // Load Average
    $load = getLoadAverage();
    $metrics['load_average_1m'] = $load['1m'];
    $metrics['load_average_5m'] = $load['5m'];
    $metrics['load_average_15m'] = $load['15m'];

    return $metrics;
}

function getCpuUsage() {
    try {
        // Get CPU usage using /proc/stat
        $stat1 = file_get_contents('/proc/stat');
        sleep(1); // Wait 1 second
        $stat2 = file_get_contents('/proc/stat');

        $cpu1 = parseCpuStat($stat1);
        $cpu2 = parseCpuStat($stat2);

        $diff = array_map(function($a, $b) { return $b - $a; }, $cpu1, $cpu2);
        $total = array_sum($diff);

        if ($total > 0) {
            $idle = $diff[3] + $diff[4];
            return round((($total - $idle) / $total) * 100, 2);
        }
    } catch (Exception $e) {
        // Fallback method
        $output = shell_exec('top -bn1 | grep "Cpu(s)"');
        if (preg_match('/(\d+\.\d+)%us/', $output, $matches)) {
            return round((float)$matches[1], 2);
        }
    }

    return 0.00;
}

function parseCpuStat($stat) {
    $lines = explode("\n", $stat);
    foreach ($lines as $line) {
        if (preg_match('/^cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $line, $matches)) {
            return [
                (int)$matches[1], // user
                (int)$matches[2], // nice
                (int)$matches[3], // system
                (int)$matches[4], // idle
                (int)$matches[5], // iowait
                (int)$matches[6], // irq
                (int)$matches[7], // softirq
                (int)$matches[8]  // steal
            ];
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
                'used' => round($used / 1024, 2), // Convert to MB
                'total' => round($memTotal / 1024, 2) // Convert to MB
            ];
        }
    } catch (Exception $e) {
        // Fallback method
        $output = shell_exec('free -m | grep Mem');
        if (preg_match('/Mem:\s+(\d+)\s+(\d+)\s+(\d+)/', $output, $matches)) {
            $total = (int)$matches[1];
            $used = (int)$matches[2];
            $usage = $total > 0 ? round(($used / $total) * 100, 2) : 0;
            return [
                'usage' => $usage,
                'used' => $used,
                'total' => $total
            ];
        }
    }

    return ['usage' => 0.00, 'used' => 0, 'total' => 0];
}

function getDiskUsage() {
    try {
        // Get disk usage for the root filesystem
        $output = shell_exec('df -BG / | tail -1');
        if (preg_match('/\s+(\d+)G\s+(\d+)G\s+(\d+)G\s+(\d+)%/', $output, $matches)) {
            $total = (float)$matches[1];
            $used = (float)$matches[2];
            $usage = (float)$matches[4];

            return [
                'usage' => $usage,
                'used' => $used,
                'total' => $total
            ];
        }
    } catch (Exception $e) {
        // Fallback
    }

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
    } catch (Exception $e) {
        // Fallback method
        $output = shell_exec('uptime');
        if (preg_match('/load average:\s+(\d+\.\d+),\s+(\d+\.\d+),\s+(\d+\.\d+)/', $output, $matches)) {
            return [
                '1m' => (float)$matches[1],
                '5m' => (float)$matches[2],
                '15m' => (float)$matches[3]
            ];
        }
    }

    return ['1m' => 0.00, '5m' => 0.00, '15m' => 0.00];
}
?>