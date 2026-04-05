<?php
/**
 * ESP32 Fingerprint Scan Endpoint
 * POST /api/esp32_scan.php
 * 
 * Receives fingerprint data from ESP32 device during enrollment
 * Updates waiting scan records and integrates with session-based enrollment
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

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    touchScannerHeartbeat($mysqli, 'api/esp32_scan.php');

    $input = json_decode(file_get_contents('php://input'), true);
    
    // Required fields from ESP32
    $waitingId = $input['waiting_id'] ?? null;
    $scanData = $input['scan_data'] ?? null;
    $quality = $input['quality'] ?? null;
    $confidence = $input['confidence'] ?? null;
    $fingerIndex = $input['finger_index'] ?? null;
    $scanIndex = $input['scan_index'] ?? null;
    
    if (!$waitingId || !$scanData) {
        throw new Exception("Missing required fields: waiting_id, scan_data");
    }
    
    // Never persist raw scan payload; store a one-way digest only.
    $scanDataDigest = hash('sha256', (string)$scanData);

    // Validate quality and confidence
    $quality = max(0, min(100, intval($quality ?? 0)));
    $confidence = max(0, min(100, intval($confidence ?? 0)));
    
    // Find waiting scan record
    $stmt = $mysqli->prepare("
        SELECT id, session_id, finger_index, scan_index 
        FROM scan_waiting 
        WHERE waiting_id = ? AND scan_data IS NULL AND expires_at > NOW()
    ");
    $stmt->bind_param("s", $waitingId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("No valid waiting scan found for ID: $waitingId");
    }
    
    $waitingRecord = $result->fetch_assoc();
    $sessionId = $waitingRecord['session_id'];
    
    // Update the waiting record with scan data
    $stmt = $mysqli->prepare("
        UPDATE scan_waiting 
        SET scan_data = ?, quality = ?, confidence = ? 
        WHERE waiting_id = ?
    ");
    $stmt->bind_param("siis", $scanDataDigest, $quality, $confidence, $waitingId);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        throw new Exception("Failed to update scan data");
    }
    
    // Update PHP session with scan data
    session_id($sessionId);
    session_start();
    
    $sessionKey = "enrollment_$sessionId";
    if (!isset($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = [
            'student_id' => null,
            'num_fingers' => 0,
            'scans' => [],
            'completed_fingers' => []
        ];
    }
    
    // Store the scan data in session
    $_SESSION[$sessionKey]['scans'][$fingerIndex][$scanIndex] = [
        'finger_index' => $fingerIndex,
        'scan_index' => $scanIndex,
        'scan_data' => $scanDataDigest,
        'quality' => $quality,
        'confidence' => $confidence,
        'timestamp' => time()
    ];
    
    // Check if this finger has completed all 5 scans
    $fingerScans = $_SESSION[$sessionKey]['scans'][$fingerIndex] ?? [];
    if (count($fingerScans) >= 5) {
        // Generate combined template for this finger
        $templateData = [
            'finger_index' => $fingerIndex,
            'scans' => $fingerScans,
            'combined_template' => 'TEMPLATE_' . $fingerIndex . '_' . uniqid(),
            'average_quality' => array_sum(array_column($fingerScans, 'quality')) / count($fingerScans),
            'enrollment_complete' => true
        ];
        
        $_SESSION[$sessionKey]['completed_fingers'][$fingerIndex] = $templateData;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Fingerprint scan data received successfully',
        'finger_index' => $waitingRecord['finger_index'],
        'scan_index' => $waitingRecord['scan_index'],
        'quality' => $quality,
        'confidence' => $confidence,
        'finger_complete' => isset($_SESSION[$sessionKey]['completed_fingers'][$fingerIndex])
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>