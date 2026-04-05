<?php
/**
 * Scanner Online Status API
 * GET  /api/scanner_status.php           -> Read current online/offline status
 * POST /api/scanner_status.php {action}  -> Optional heartbeat ping from scanner
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../config/db.php';

function ensureScannerHeartbeatTable($mysqli) {
    $mysqli->query("CREATE TABLE IF NOT EXISTS scanner_heartbeat (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_ip VARCHAR(45) NOT NULL,
        user_agent VARCHAR(255) NULL,
        source VARCHAR(50) NOT NULL DEFAULT 'unknown',
        last_seen TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_last_seen (last_seen),
        INDEX idx_device_ip (device_ip)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function touchScannerHeartbeat($mysqli, $source = 'unknown') {
    ensureScannerHeartbeatTable($mysqli);

    $deviceIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    $stmt = $mysqli->prepare("INSERT INTO scanner_heartbeat (device_ip, user_agent, source, last_seen) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("sss", $deviceIp, $userAgent, $source);
    $stmt->execute();
}

function getScannerStatus($mysqli) {
    ensureScannerHeartbeatTable($mysqli);

    $stmt = $mysqli->prepare("SELECT device_ip, source, last_seen, TIMESTAMPDIFF(SECOND, last_seen, NOW()) AS seconds_ago
        FROM scanner_heartbeat
        ORDER BY last_seen DESC
        LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return [
            'online' => false,
            'last_seen' => null,
            'seconds_ago' => null,
            'device_ip' => null,
            'source' => null,
            'message' => 'No scanner heartbeat received yet'
        ];
    }

    $row = $result->fetch_assoc();
    $secondsAgo = (int)$row['seconds_ago'];
    $onlineThresholdSeconds = 20;
    $online = $secondsAgo <= $onlineThresholdSeconds;

    return [
        'online' => $online,
        'last_seen' => $row['last_seen'],
        'seconds_ago' => $secondsAgo,
        'device_ip' => $row['device_ip'],
        'source' => $row['source'],
        'message' => $online ? 'Scanner is online' : 'Scanner heartbeat is stale'
    ];
}

try {
    // Allow simple GET heartbeat for microcontroller firmware.
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && (($_GET['action'] ?? '') === 'heartbeat')) {
        touchScannerHeartbeat($mysqli, 'api/scanner_status.php?heartbeat');
        $status = getScannerStatus($mysqli);

        echo json_encode([
            'success' => true,
            'action' => 'heartbeat',
            'status' => $status
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $payload = json_decode(file_get_contents('php://input'), true);
        $action = $payload['action'] ?? '';

        if ($action === 'heartbeat') {
            touchScannerHeartbeat($mysqli, 'api/scanner_status.php');
            $status = getScannerStatus($mysqli);

            echo json_encode([
                'success' => true,
                'action' => 'heartbeat',
                'status' => $status
            ]);
            exit;
        }

        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
        exit;
    }

    $status = getScannerStatus($mysqli);

    echo json_encode([
        'success' => true,
        'scanner' => $status
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>