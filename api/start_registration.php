<?php
/**
 * START REGISTRATION API
 * Call this when a user clicks "Register Fingerprint" button
 * This initializes the 5-scan registration process
 */

header('Content-Type: application/json');
require_once '../config/db.php';

function ensureFingerprintRegistrationTable($mysqli) {
    $mysqli->query("CREATE TABLE IF NOT EXISTS fingerprint_registrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        finger_number INT NOT NULL DEFAULT 1,
        scan_number INT NOT NULL DEFAULT 0,
        total_fingers INT NOT NULL DEFAULT 1,
        status ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status_updated (status, updated_at),
        INDEX idx_student_status (student_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

$data = json_decode(file_get_contents('php://input'), true);

$studentId = isset($data['student_id']) ? (int)$data['student_id'] : 0;
$totalFingers = isset($data['total_fingers']) ? (int)$data['total_fingers'] : 1;
$totalFingers = max(1, min(5, $totalFingers));

if ($studentId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Student ID required']);
    exit;
}

try {
    ensureFingerprintRegistrationTable($mysqli);

    // Cancel any existing active registrations for this student.
    $stmt = $mysqli->prepare("UPDATE fingerprint_registrations SET status = 'cancelled' WHERE student_id = ? AND status = 'active'");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();

    // Start new registration (Finger 1, Scan 0).
    $stmt = $mysqli->prepare("INSERT INTO fingerprint_registrations (student_id, finger_number, scan_number, total_fingers, status) VALUES (?, 1, 0, ?, 'active')");
    $stmt->bind_param("ii", $studentId, $totalFingers);
    $stmt->execute();

    $registrationId = $mysqli->insert_id;

    // Set mode to registration.
    $registrationMode = 'registration';
    $stmt = $mysqli->prepare("INSERT INTO settings (setting_key, setting_value, description, updated_at) VALUES ('current_mode', ?, 'Current scanner mode', NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
    $stmt->bind_param("s", $registrationMode);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'registration_id' => $registrationId,
        'student_id' => $studentId,
        'finger_number' => 1,
        'scan_number' => 0,
        'total_fingers' => $totalFingers,
        'message' => 'Registration started. Please scan finger 1 (scan 1/5)'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>