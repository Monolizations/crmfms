<?php
// /api/monitoring/alerts.php
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = (new Database())->getConnection();

    // Skip auth for public data endpoints
    $action = $_GET['action'] ?? '';
    if (!in_array($action, ['list', 'active', 'history'])) {
        requireAuth();
    }

    $uid = $_SESSION['uid'] ?? null;
    $roles = $_SESSION['roles'] ?? [];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';

        if ($action === 'list') {
            // List all alert configurations
            $stmt = $db->query("SELECT * FROM alert_configurations ORDER BY alert_type, severity DESC");
            $alerts = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $alerts]);
            exit;
        }

        if ($action === 'history') {
            // Get alert history
            $limit = (int)($_GET['limit'] ?? 50);
            $status = $_GET['status'] ?? 'all'; // 'all', 'active', 'resolved'

            $query = "SELECT ah.*, ac.description as config_description
                     FROM alert_history ah
                     LEFT JOIN alert_configurations ac ON ah.alert_config_id = ac.id";

            if ($status !== 'all') {
                $query .= " WHERE ah.status = ?";
            }

            $query .= " ORDER BY ah.triggered_at DESC LIMIT ?";

            $stmt = $db->prepare($query);
            if ($status !== 'all') {
                $stmt->execute([$status, $limit]);
            } else {
                $stmt->execute([$limit]);
            }

            $alerts = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $alerts]);
            exit;
        }

        if ($action === 'check') {
            // Manually trigger alert checking
            $result = checkAllAlerts($db);
            echo json_encode(['success' => true, 'message' => 'Alert check completed', 'result' => $result]);
            exit;
        }

        if ($action === 'active') {
            // Get active alerts
            $stmt = $db->query("
                SELECT ah.*, ac.description as config_description, ac.threshold_value, ac.threshold_operator
                FROM alert_history ah
                LEFT JOIN alert_configurations ac ON ah.alert_config_id = ac.id
                WHERE ah.status = 'active'
                ORDER BY ah.triggered_at DESC
            ");
            $alerts = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $alerts]);
            exit;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';

        if ($action === 'create') {
            // Create new alert configuration
            if (!in_array('admin', $roles)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit;
            }

            $stmt = $db->prepare("
                INSERT INTO alert_configurations
                (alert_type, threshold_value, threshold_operator, severity, enabled, notification_email, cooldown_minutes, description)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $input['alert_type'],
                $input['threshold_value'],
                $input['threshold_operator'] ?? '>',
                $input['severity'] ?? 'warning',
                $input['enabled'] ?? 1,
                $input['notification_email'] ?? null,
                $input['cooldown_minutes'] ?? 60,
                $input['description'] ?? ''
            ]);

            echo json_encode(['success' => true, 'message' => 'Alert configuration created', 'id' => $db->lastInsertId()]);
            exit;
        }

        if ($action === 'update') {
            // Update alert configuration
            if (!in_array('admin', $roles)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit;
            }

            $stmt = $db->prepare("
                UPDATE alert_configurations SET
                threshold_value = ?, threshold_operator = ?, severity = ?, enabled = ?,
                notification_email = ?, cooldown_minutes = ?, description = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $input['threshold_value'],
                $input['threshold_operator'] ?? '>',
                $input['severity'] ?? 'warning',
                $input['enabled'] ?? 1,
                $input['notification_email'] ?? null,
                $input['cooldown_minutes'] ?? 60,
                $input['description'] ?? '',
                $input['id']
            ]);

            echo json_encode(['success' => true, 'message' => 'Alert configuration updated']);
            exit;
        }

        if ($action === 'resolve') {
            // Resolve an alert
            $stmt = $db->prepare("
                UPDATE alert_history SET
                status = 'resolved', resolved_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$input['alert_id']]);

            echo json_encode(['success' => true, 'message' => 'Alert resolved']);
            exit;
        }

        if ($action === 'acknowledge') {
            // Acknowledge an alert
            $stmt = $db->prepare("
                UPDATE alert_history SET
                status = 'acknowledged'
                WHERE id = ?
            ");
            $stmt->execute([$input['alert_id']]);

            echo json_encode(['success' => true, 'message' => 'Alert acknowledged']);
            exit;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $action = $_GET['action'] ?? '';

        if ($action === 'delete') {
            // Delete alert configuration
            if (!in_array('admin', $roles)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit;
            }

            $stmt = $db->prepare("DELETE FROM alert_configurations WHERE id = ?");
            $stmt->execute([$_GET['id']]);

            echo json_encode(['success' => true, 'message' => 'Alert configuration deleted']);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Invalid request']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function checkAllAlerts($db) {
    $alertsTriggered = 0;

    try {
        // Get system metrics
        $systemMetrics = json_decode(file_get_contents('http://localhost/crmfms/api/monitoring/system_monitor.php?action=collect'), true);
        $systemData = $systemMetrics['data'] ?? [];

        // Get database metrics
        $dbMetrics = json_decode(file_get_contents('http://localhost/crmfms/api/monitoring/database_monitor.php?action=collect'), true);
        $dbData = $dbMetrics['data'] ?? [];

        // Get all enabled alert configurations
        $stmt = $db->query("SELECT * FROM alert_configurations WHERE enabled = 1");
        $configs = $stmt->fetchAll();

        foreach ($configs as $config) {
            $alertType = $config['alert_type'];
            $threshold = (float)$config['threshold_value'];
            $operator = $config['threshold_operator'];
            $currentValue = null;

            // Get current value based on alert type
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
                // Check if we should trigger alert (cooldown check)
                if (shouldTriggerAlert($db, $config['id'], $config['cooldown_minutes'])) {
                    triggerAlert($db, $config, $currentValue, $threshold);
                    $alertsTriggered++;
                }
            }
        }

    } catch (Exception $e) {
        error_log("Alert check error: " . $e->getMessage());
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
    // Check if there's a recent alert for this configuration
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM alert_history
        WHERE alert_config_id = ? AND triggered_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
    ");
    $stmt->execute([$configId, $cooldownMinutes]);
    $result = $stmt->fetch();

    return $result['count'] == 0;
}

function triggerAlert($db, $config, $currentValue, $threshold) {
    // Create alert message
    $messages = [
        'cpu' => "CPU usage is {$currentValue}%, threshold: {$threshold}%",
        'memory' => "Memory usage is {$currentValue}%, threshold: {$threshold}%",
        'disk' => "Disk usage is {$currentValue}%, threshold: {$threshold}%",
        'connections' => "Database connections at {$currentValue}%, threshold: {$threshold}%",
        'slow_queries' => "Slow queries: {$currentValue}, threshold: {$threshold}"
    ];

    $message = $messages[$config['alert_type']] ?? "Alert triggered for {$config['alert_type']}";

    // Insert alert into history
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

    // Send notification if configured
    if (!empty($config['notification_email'])) {
        sendAlertEmail($config, $message);
    }
}

function sendAlertEmail($config, $message) {
    // Basic email sending (you may want to enhance this)
    $to = $config['notification_email'];
    $subject = "CRM FMS Alert: " . ucfirst($config['alert_type']);
    $headers = "From: noreply@crmfms.local\r\n";

    $body = "Alert Details:\n";
    $body .= "Type: " . $config['alert_type'] . "\n";
    $body .= "Severity: " . $config['severity'] . "\n";
    $body .= "Message: " . $message . "\n";
    $body .= "Time: " . date('Y-m-d H:i:s') . "\n";

    // Note: In production, use proper mail configuration
    // mail($to, $subject, $body, $headers);
    error_log("Alert email would be sent to {$to}: {$subject}");
}
?>