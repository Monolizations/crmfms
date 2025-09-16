<?php
// /api/monitoring/database_monitor.php
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
            // Collect current database metrics
            $metrics = collectDatabaseMetrics($db);
            echo json_encode(['success' => true, 'data' => $metrics]);
            exit;
        }

        if ($action === 'history') {
            // Get historical database metrics
            $limit = (int)($_GET['limit'] ?? 100);
            $hours = (int)($_GET['hours'] ?? 24);

            $stmt = $db->prepare("
                SELECT * FROM database_monitoring
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
            // Get latest database metrics
            $stmt = $db->query("
                SELECT * FROM database_monitoring
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $data = $stmt->fetch();

            echo json_encode(['success' => true, 'data' => $data]);
            exit;
        }

        if ($action === 'store') {
            // Store current metrics in database (allow unauthenticated for cron jobs)
            $metrics = collectDatabaseMetrics($db);

            $stmt = $db->prepare("
                INSERT INTO database_monitoring
                (connections_total, connections_active, connections_max,
                 queries_per_second, slow_queries, uptime_seconds,
                 bytes_received, bytes_sent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $metrics['connections_total'],
                $metrics['connections_active'],
                $metrics['connections_max'],
                $metrics['queries_per_second'],
                $metrics['slow_queries'],
                $metrics['uptime_seconds'],
                $metrics['bytes_received'],
                $metrics['bytes_sent']
            ]);

            echo json_encode(['success' => true, 'message' => 'Database metrics stored', 'id' => $db->lastInsertId()]);
            exit;
        }

        if ($action === 'status') {
            // Get database status information
            $status = getDatabaseStatus($db);
            echo json_encode(['success' => true, 'data' => $status]);
            exit;
        }

        if ($action === 'variables') {
            // Get important MySQL variables
            $variables = getDatabaseVariables($db);
            echo json_encode(['success' => true, 'data' => $variables]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Invalid request']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function collectDatabaseMetrics($db) {
    $metrics = [];

    try {
        // Get connection information
        $stmt = $db->query("SHOW PROCESSLIST");
        $processes = $stmt->fetchAll();
        $activeConnections = count($processes);

        // Get max connections
        $stmt = $db->query("SHOW VARIABLES LIKE 'max_connections'");
        $maxConn = $stmt->fetch();
        $maxConnections = (int)($maxConn['Value'] ?? 151);

        $metrics['connections_total'] = $activeConnections;
        $metrics['connections_active'] = $activeConnections;
        $metrics['connections_max'] = $maxConnections;

        // Get MySQL uptime and other status variables
        $stmt = $db->query("SHOW GLOBAL STATUS");
        $statusVars = [];
        while ($row = $stmt->fetch()) {
            $statusVars[$row['Variable_name']] = $row['Value'];
        }

        // Calculate queries per second (approximate)
        $uptime = (int)($statusVars['Uptime'] ?? 0);
        $totalQueries = (int)($statusVars['Queries'] ?? 0);
        $queriesPerSecond = $uptime > 0 ? round($totalQueries / $uptime, 2) : 0;

        $metrics['queries_per_second'] = $queriesPerSecond;
        $metrics['slow_queries'] = (int)($statusVars['Slow_queries'] ?? 0);
        $metrics['uptime_seconds'] = $uptime;
        $metrics['bytes_received'] = (int)($statusVars['Bytes_received'] ?? 0);
        $metrics['bytes_sent'] = (int)($statusVars['Bytes_sent'] ?? 0);

    } catch (Exception $e) {
        // Set defaults if queries fail
        $metrics['connections_total'] = 0;
        $metrics['connections_active'] = 0;
        $metrics['connections_max'] = 151;
        $metrics['queries_per_second'] = 0.00;
        $metrics['slow_queries'] = 0;
        $metrics['uptime_seconds'] = 0;
        $metrics['bytes_received'] = 0;
        $metrics['bytes_sent'] = 0;
    }

    return $metrics;
}

function getDatabaseStatus($db) {
    $status = [];

    try {
        // Get MySQL version
        $stmt = $db->query("SELECT VERSION() as version");
        $status['version'] = $stmt->fetch()['version'];

        // Get database size
        $stmt = $db->query("
            SELECT
                SUM(data_length + index_length) as size_bytes,
                COUNT(*) as tables_count
            FROM information_schema.TABLES
            WHERE table_schema = DATABASE()
        ");
        $dbSize = $stmt->fetch();
        $status['database_size_bytes'] = (int)($dbSize['size_bytes'] ?? 0);
        $status['database_size_mb'] = round($status['database_size_bytes'] / (1024 * 1024), 2);
        $status['tables_count'] = (int)($dbSize['tables_count'] ?? 0);

        // Get connection info
        $stmt = $db->query("SHOW PROCESSLIST");
        $processes = $stmt->fetchAll();
        $status['active_connections'] = count($processes);

        // Get key status variables
        $stmt = $db->query("SHOW GLOBAL STATUS WHERE Variable_name IN (
            'Uptime', 'Threads_connected', 'Threads_running', 'Queries',
            'Slow_queries', 'Bytes_received', 'Bytes_sent', 'Max_used_connections'
        )");
        while ($row = $stmt->fetch()) {
            $status[strtolower($row['Variable_name'])] = $row['Value'];
        }

    } catch (Exception $e) {
        $status['error'] = $e->getMessage();
    }

    return $status;
}

function getDatabaseVariables($db) {
    $variables = [];

    try {
        // Get important configuration variables
        $stmt = $db->query("SHOW VARIABLES WHERE Variable_name IN (
            'max_connections', 'query_cache_size', 'innodb_buffer_pool_size',
            'innodb_log_file_size', 'max_allowed_packet', 'wait_timeout',
            'interactive_timeout', 'net_read_timeout', 'net_write_timeout'
        )");
        while ($row = $stmt->fetch()) {
            $variables[$row['Variable_name']] = $row['Value'];
        }

    } catch (Exception $e) {
        $variables['error'] = $e->getMessage();
    }

    return $variables;
}
?>