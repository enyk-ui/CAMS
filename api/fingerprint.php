<?php
/**
 * Fingerprint Data API
 * Handles fingerprint enrollment data storage
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors in output, log them instead
ini_set('log_errors', 1);

require_once '../config/db.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }
    
    $action = $input['action'] ?? '';

    switch ($action) {
        case 'start_enrollment':
            startEnrollment($input);
            break;
            
        case 'wait_scan':
            waitForScan($input);
            break;
            
        case 'complete_finger':
            completeFinger($input);
            break;
            
        case 'save_fingerprints':
            saveFingerprints($input);
            break;
            
        case 'check_scan':
            checkScanStatus($input);
            break;
            
        default:
            throw new Exception("Invalid action: $action");
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function startEnrollment($input) {
    $studentId = $input['student_id'] ?? null; // Can be null for new registrations
    $numFingers = $input['num_fingers'] ?? 1;
    $mode = $input['mode'] ?? 'registration'; // 'registration' or 'update'
    
    if (!$numFingers || $numFingers < 1) {
        throw new Exception("Invalid num_fingers");
    }
    
    // Create enrollment session
    $sessionId = uniqid('enrollment_', true);
    
    // Store in session or temporary table
    session_start();
    $_SESSION["enrollment_$sessionId"] = [
        'student_id' => $studentId,
        'num_fingers' => $numFingers,
        'current_finger' => 1,
        'current_scan' => 1,
        'enrolled_data' => [],
        'started_at' => time()
    ];
    
    echo json_encode([
        'success' => true,
        'session_id' => $sessionId,
        'message' => 'Enrollment session started'
    ]);
}

function waitForScan($input) {
    global $mysqli;
    
    $sessionId = $input['session_id'] ?? null;
    $fingerIndex = $input['finger_index'] ?? 1;
    $scanIndex = $input['scan_index'] ?? 1;
    
    if (!$sessionId) {
        throw new Exception("Missing session_id");
    }
    
    session_start();
    $sessionKey = "enrollment_$sessionId";
    
    if (!isset($_SESSION[$sessionKey])) {
        throw new Exception("Invalid enrollment session");
    }
    
    // Create a scan waiting record in database
    $waitingId = uniqid('scan_', true);
    
    $stmt = $mysqli->prepare("
        INSERT INTO scan_waiting (waiting_id, session_id, finger_index, scan_index, created_at, expires_at) 
        VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 60 SECOND))
    ");
    $stmt->bind_param("ssii", $waitingId, $sessionId, $fingerIndex, $scanIndex);
    $stmt->execute();
    
    // Wait for ESP32 to POST scan data (polling approach)
    $maxWaitTime = 60; // 60 seconds timeout
    $pollInterval = 1; // Check every 1 second
    $waited = 0;
    
    while ($waited < $maxWaitTime) {
        // Check if scan data was received
        $stmt = $mysqli->prepare("
            SELECT scan_data, quality, confidence 
            FROM scan_waiting 
            WHERE waiting_id = ? AND scan_data IS NOT NULL
        ");
        $stmt->bind_param("s", $waitingId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $scanData = $result->fetch_assoc();
            
            // Clean up waiting record
            $stmt = $mysqli->prepare("DELETE FROM scan_waiting WHERE waiting_id = ?");
            $stmt->bind_param("s", $waitingId);
            $stmt->execute();
            
            // Store successful scan in session
            $_SESSION[$sessionKey]['scans'][$fingerIndex][$scanIndex] = [
                'finger_index' => $fingerIndex,
                'scan_index' => $scanIndex,
                'scan_data' => $scanData['scan_data'],
                'quality' => $scanData['quality'],
                'confidence' => $scanData['confidence'],
                'timestamp' => time()
            ];
            
            echo json_encode([
                'success' => true,
                'status' => 'scan_complete',
                'scan_data' => ['received' => true],
                'message' => "Scan $scanIndex complete for finger $fingerIndex"
            ]);
            return;
        }
        
        sleep($pollInterval);
        $waited += $pollInterval;
    }
    
    // Timeout - clean up waiting record
    $stmt = $mysqli->prepare("DELETE FROM scan_waiting WHERE waiting_id = ?");
    $stmt->bind_param("s", $waitingId);
    $stmt->execute();
    
    echo json_encode([
        'success' => false,
        'status' => 'timeout',
        'message' => 'Scan timeout - no fingerprint data received. Please ensure scanner is connected and try again.'
    ]);
}

function completeFinger($input) {
    global $mysqli;
    
    $sessionId = $input['session_id'] ?? null;
    $fingerIndex = $input['finger_index'] ?? null;
    
    if (!$sessionId || !$fingerIndex) {
        throw new Exception("Missing session_id or finger_index");
    }
    
    session_start();
    $sessionKey = "enrollment_$sessionId";
    
    if (!isset($_SESSION[$sessionKey])) {
        throw new Exception("Invalid enrollment session");
    }
    
    $session = $_SESSION[$sessionKey];
    
    // Check if all 5 scans are complete for this finger
    $scans = $_SESSION[$sessionKey]['scans'][$fingerIndex] ?? [];
    
    if (count($scans) < 5) {
        throw new Exception("Not all scans complete for finger $fingerIndex");
    }
    
    // Combine scan data into a fingerprint template
    $templateData = [
        'finger_index' => $fingerIndex,
        'scans' => $scans,
        'combined_template' => 'combined_' . $sessionId . '_' . $fingerIndex,
        'created_at' => time()
    ];
    
    // Store completed finger data
    $_SESSION[$sessionKey]['completed_fingers'][$fingerIndex] = $templateData;
    
    echo json_encode([
        'success' => true,
        'finger_complete' => true,
        'template_data' => $templateData,
        'message' => "Finger $fingerIndex enrollment complete"
    ]);
}

function saveFingerprints($input) {
    global $mysqli;
    
    $sessionId = $input['session_id'] ?? null;
    $studentId = $input['student_id'] ?? null;
    
    if (!$sessionId) {
        throw new Exception("Missing session_id");
    }
    
    session_start();
    $sessionKey = "enrollment_$sessionId";
    
    if (!isset($_SESSION[$sessionKey])) {
        throw new Exception("Invalid enrollment session");
    }
    
    $session = $_SESSION[$sessionKey];
    $completedFingers = $session['completed_fingers'] ?? [];
    
    if (empty($completedFingers)) {
        throw new Exception("No completed fingerprints to save");
    }
    
    // Use student_id from session if not provided
    if (!$studentId) {
        $studentId = $session['student_id'];
    }
    
    // Start database transaction
    $mysqli->begin_transaction();
    
    try {
        // Clear existing fingerprints for this student (for updates)
        $stmt = $mysqli->prepare("DELETE FROM fingerprints WHERE student_id = ?");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        
        // Insert new fingerprints
        $stmt = $mysqli->prepare("
            INSERT INTO fingerprints (student_id, finger_index, sensor_id, is_active, created_at) 
            VALUES (?, ?, ?, 1, NOW())
        ");
        
        $savedCount = 0;
        foreach ($completedFingers as $fingerIndex => $templateData) {
            $sensorId = $templateData['combined_template'];
            $stmt->bind_param("iis", $studentId, $fingerIndex, $sensorId);
            $stmt->execute();
            $savedCount++;
        }
        
        $mysqli->commit();
        
        // Clean up session
        unset($_SESSION[$sessionKey]);
        
        echo json_encode([
            'success' => true,
            'saved_count' => $savedCount,
            'message' => "Successfully saved $savedCount fingerprint(s)"
        ]);
        
    } catch (Exception $e) {
        $mysqli->rollback();
        throw new Exception("Database error: " . $e->getMessage());
    }
}

function checkScanStatus($input) {
    global $mysqli;
    
    $studentId = $input['student_id'] ?? null;
    $fingerIndex = $input['finger_index'] ?? null;
    $scanIndex = $input['scan_index'] ?? null;
    
    if (!$studentId || !$fingerIndex || !$scanIndex) {
        throw new Exception("Missing required parameters");
    }
    
    session_start();
    $sessionKey = "enrollment_" . session_id();
    
    // Check if scan is complete in session
    if (isset($_SESSION[$sessionKey]['scans'][$fingerIndex][$scanIndex])) {
        echo json_encode([
            'success' => true,
            'completed' => true,
            'scan_data' => ['received' => true],
            'message' => 'Scan completed'
        ]);
        return;
    }
    
    // Check if there's still a waiting scan record
    $stmt = $mysqli->prepare("
        SELECT COUNT(*) as waiting_count 
        FROM scan_waiting 
        WHERE session_id = ? 
          AND finger_index = ? 
          AND scan_index = ? 
          AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)
    ");
    $sessionId = session_id();
    $stmt->bind_param("sii", $sessionId, $fingerIndex, $scanIndex);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['waiting_count'] > 0) {
        echo json_encode([
            'success' => true,
            'completed' => false,
            'message' => 'Still waiting for scan'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'completed' => false,
            'message' => 'Scan timeout or expired'
        ]);
    }
}
?>