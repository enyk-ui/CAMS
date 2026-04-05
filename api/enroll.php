<?php
/**
 * Enhanced Fingerprint Enrollment API
 * POST /api/enroll.php
 *
 * Handles real fingerprint enrollment with ESP8266
 * Manages step-by-step scanning and finger confirmation
 *
 * Parameters:
 * - action: 'init', 'start_finger', 'poll_status', 'complete_finger', 'finish'
 * - device_ip: ESP8266 IP address
 * - finger_index: which finger (1-5)
 * - enrollment_id: current enrollment session ID (from device)
 * - student_id: optional, for storing fingerprints
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../config/db.php';
require_once '../helpers/ESP8266Helper.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = isset($input['action']) ? $input['action'] : '';

    if (empty($action)) {
        throw new Exception("Missing action parameter");
    }

    // Route based on action
    switch ($action) {
        case 'init':
            handleInit($input);
            break;

        case 'start_finger':
            handleStartFinger($input);
            break;

        case 'poll_status':
            handlePollStatus($input);
            break;

        case 'complete_finger':
            handleCompleteFinger($input);
            break;

        case 'finish':
            handleFinish($input);
            break;

        case 'cancel':
            handleCancel($input);
            break;

        case 'device_status':
            handleDeviceStatus($input);
            break;

        default:
            throw new Exception("Unknown action: $action");
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Initialize enrollment session
 * Creates enrollment state in session storage
 */
function handleInit($input) {
    if (empty($input['device_ip'])) {
        throw new Exception("Missing device_ip");
    }

    $device_ip = $input['device_ip'];
    $num_fingers = isset($input['num_fingers']) ? (int)$input['num_fingers'] : 1;

    if ($num_fingers < 1 || $num_fingers > 5) {
        throw new Exception("Invalid num_fingers: must be 1-5");
    }

    // Verify device is reachable
    $esp = new ESP8266Helper($device_ip);
    $status = $esp->getStatus();

    if (!$status['success'] ?? false) {
        throw new Exception("Cannot reach ESP8266 device at $device_ip");
    }

    // Initialize session enrollment data
    $enrollment_session = [
        'device_ip' => $device_ip,
        'num_fingers' => $num_fingers,
        'current_finger' => 1,
        'fingerprints' => [],
        'started_at' => time()
    ];

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Enrollment session initialized',
        'session' => $enrollment_session,
        'device_ready' => true
    ]);
}

/**
 * Start enrollment for a specific finger
 */
function handleStartFinger($input) {
    if (empty($input['device_ip']) || empty($input['finger_index'])) {
        throw new Exception("Missing device_ip or finger_index");
    }

    $device_ip = $input['device_ip'];
    $finger_index = (int)$input['finger_index'];

    if ($finger_index < 1 || $finger_index > 5) {
        throw new Exception("Invalid finger_index");
    }

    // Communicate with ESP8266
    $esp = new ESP8266Helper($device_ip);
    $response = $esp->startEnrollment($finger_index);

    if (!($response['success'] ?? false)) {
        throw new Exception("ESP8266 error: " . ($response['error'] ?? 'Start enrollment failed'));
    }

    // Get enrollment ID from device
    $enrollment_id = $response['enrollment_id'] ?? null;
    if (!$enrollment_id) {
        throw new Exception("Device did not return enrollment_id");
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Finger enrollment started',
        'enrollment_id' => $enrollment_id,
        'finger_index' => $finger_index,
        'step' => 'place_finger_first',
        'instruction' => 'Place your finger on the sensor (Scan 1 of 2)',
        'scan_number' => 1
    ]);
}

/**
 * Poll device status for current finger enrollment
 */
function handlePollStatus($input) {
    if (empty($input['device_ip']) || empty($input['enrollment_id'])) {
        throw new Exception("Missing device_ip or enrollment_id");
    }

    $device_ip = $input['device_ip'];
    $enrollment_id = $input['enrollment_id'];

    $esp = new ESP8266Helper($device_ip);
    $status = $esp->getEnrollmentStatus($enrollment_id);

    if (!($status['success'] ?? false)) {
        throw new Exception("Failed to get status: " . ($status['error'] ?? 'Unknown error'));
    }

    // Current step and instruction
    $current_step = $status['step'] ?? 'unknown';
    $instructions = [
        'place_finger_first'  => 'Place your finger on the sensor (Scan 1 of 2)',
        'remove_finger_first' => 'Remove your finger from the sensor',
        'place_finger_second' => 'Place your finger again on the sensor (Scan 2 of 2)',
        'remove_finger_second'=> 'Remove your finger from the sensor',
        'processing'         => 'Processing fingerprint template...',
        'success'           => 'Fingerprint successfully enrolled!',
        'failed'            => 'Enrollment failed, please retry',
        'duplicate'         => 'Duplicate fingerprint detected'
    ];

    $instruction = $instructions[$current_step] ?? 'Processing...';

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'status' => $current_step,
        'instruction' => $instruction,
        'progress' => $status['progress'] ?? 0,
        'enrollment_id' => $enrollment_id,
        'response_data' => $status // Include full response for debugging
    ]);
}

/**
 * Complete enrollment for a finger and store sensor ID
 */
function handleCompleteFinger($input) {
    if (empty($input['device_ip']) || empty($input['enrollment_id'])) {
        throw new Exception("Missing device_ip or enrollment_id");
    }

    $device_ip = $input['device_ip'];
    $enrollment_id = $input['enrollment_id'];
    $finger_index = isset($input['finger_index']) ? (int)$input['finger_index'] : 0;
    $student_id = isset($input['student_id']) ? (int)$input['student_id'] : null;

    // Get final sensor ID from device
    $esp = new ESP8266Helper($device_ip);
    $result = $esp->completeEnrollment($enrollment_id);

    if (!($result['success'] ?? false)) {
        throw new Exception("Failed to complete enrollment: " . ($result['error'] ?? 'Unknown error'));
    }

    $sensor_id = $result['sensor_id'] ?? null;
    if (!$sensor_id) {
        throw new Exception("Device did not return sensor_id");
    }

    // Store in database if student_id provided
    if ($student_id && $finger_index) {
        $conn = $GLOBALS['conn'] ?? null;
        if ($conn) {
            // Check if this sensor_id already exists
            $stmt = $conn->prepare("SELECT id FROM fingerprints WHERE sensor_id = ?");
            $stmt->bind_param("s", $sensor_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception("Sensor ID already registered to another student");
            }

            // Insert fingerprint record
            $stmt = $conn->prepare("
                INSERT INTO fingerprints (student_id, finger_index, sensor_id, is_active)
                VALUES (?, ?, ?, true)
            ");
            $stmt->bind_param("iis", $student_id, $finger_index, $sensor_id);
            $stmt->execute();
        }
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Fingerprint enrollment completed',
        'finger_index' => $finger_index,
        'sensor_id' => $sensor_id,
        'stored' => ($student_id && $finger_index) ? true : false
    ]);
}

/**
 * Finish enrollment session
 */
function handleFinish($input) {
    if (empty($input['device_ip'])) {
        throw new Exception("Missing device_ip");
    }

    $device_ip = $input['device_ip'];
    $fingerprints = isset($input['fingerprints']) ? $input['fingerprints'] : [];

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Enrollment session finished',
        'total_fingerprints' => count($fingerprints),
        'fingerprints' => $fingerprints // Return collected fingerprints
    ]);
}

/**
 * Cancel enrollment session
 */
function handleCancel($input) {
    if (empty($input['device_ip'])) {
        throw new Exception("Missing device_ip");
    }

    $device_ip = $input['device_ip'];
    $enrollment_id = isset($input['enrollment_id']) ? $input['enrollment_id'] : null;

    if ($enrollment_id) {
        $esp = new ESP8266Helper($device_ip);
        $esp->cancelEnrollment($enrollment_id);
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Enrollment cancelled'
    ]);
}

/**
 * Check device status
 */
function handleDeviceStatus($input) {
    if (empty($input['device_ip'])) {
        throw new Exception("Missing device_ip");
    }

    $device_ip = $input['device_ip'];
    $esp = new ESP8266Helper($device_ip);
    $status = $esp->getStatus();

    if ($status['success'] ?? false) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'device_ip' => $device_ip,
            'device_status' => $status,
            'reachable' => true
        ]);
    } else {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'message' => 'Device unreachable',
            'error' => $status['error'] ?? 'No response',
            'reachable' => false
        ]);
    }
}
