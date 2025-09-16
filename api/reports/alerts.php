<?php
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

try {
    requireAuth(['admin', 'dean', 'secretary']);
    $db = (new Database())->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Fetch active system alerts from database
        $stmt = $db->prepare("SELECT alert_id, message, type, priority, created_at
                              FROM system_alerts
                              WHERE is_active = 1
                              AND (expires_at IS NULL OR expires_at > NOW())
                              ORDER BY priority DESC, created_at DESC");

        $stmt->execute();
        $alerts = $stmt->fetchAll();

        // Format alerts for frontend
        $formattedAlerts = array_map(function($alert) {
            return [
                'alert_id' => $alert['alert_id'],
                'message' => $alert['message'],
                'type' => $alert['type'],
                'priority' => $alert['priority'],
                'created_at' => $alert['created_at']
            ];
        }, $alerts);

        echo json_encode(['items' => $formattedAlerts]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';

        if ($action === 'create') {
            requireAuth(['admin']); // Only admin can create alerts

            $message = sanitize($input['message'] ?? '');
            $type = sanitize($input['type'] ?? 'info');
            $priority = (int)($input['priority'] ?? 1);
            $expires_at = !empty($input['expires_at']) ? $input['expires_at'] : null;

            if (empty($message)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Alert message is required']);
                exit;
            }

            $stmt = $db->prepare("INSERT INTO system_alerts (message, type, priority, expires_at, is_active, created_at)
                                  VALUES (?, ?, ?, ?, 1, NOW())");
            $stmt->execute([$message, $type, $priority, $expires_at]);

            echo json_encode(['success' => true, 'message' => 'Alert created successfully']);
            exit;
        }

        if ($action === 'dismiss') {
            requireAuth(['admin']); // Only admin can dismiss alerts

            $alert_id = (int)($input['alert_id'] ?? 0);
            if ($alert_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid alert ID']);
                exit;
            }

            $stmt = $db->prepare("UPDATE system_alerts SET is_active = 0 WHERE alert_id = ?");
            $stmt->execute([$alert_id]);

            echo json_encode(['success' => true, 'message' => 'Alert dismissed successfully']);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
