<?php
/**
 * ESP32 Scan Status Endpoint
 * GET /api/esp32_status.php
 * 
 * Returns current waiting scan information for ESP32 to know what to scan
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

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Any scanner request here counts as heartbeat.
    touchScannerHeartbeat($mysqli, 'api/esp32_status.php');

    // Clean up expired waiting records first
    $stmt = $mysqli->prepare("DELETE FROM scan_waiting WHERE expires_at < NOW()");
    $stmt->execute();
    
    // Get current waiting scans
    $stmt = $mysqli->prepare("
        SELECT waiting_id, session_id, finger_index, scan_index, created_at
        FROM scan_waiting 
        WHERE scan_data IS NULL 
        ORDER BY created_at ASC
        LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => true,
            'waiting_scan' => null,
            'message' => 'No scans waiting'
        ]);
    } else {
        $waiting = $result->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'waiting_scan' => [
                'waiting_id' => $waiting['waiting_id'],
                'session_id' => $waiting['session_id'],
                'finger_index' => $waiting['finger_index'],
                'scan_index' => $waiting['scan_index'],
                'instruction' => "Please place finger {$waiting['finger_index']} on scanner for scan {$waiting['scan_index']}/5"
            ],
            'message' => 'Scan waiting for finger ' . $waiting['finger_index']
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>