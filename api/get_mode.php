<?php
/**
 * Get Mode API
 * Returns current system mode: "registration" or "attendance"
 * Arduino checks this every 3 seconds to know which mode to operate in
 *
 * For REGISTRATION mode, also returns:
 * - registration_id: Current user being registered
 * - finger_number: Which finger (1-10)
 * - scan_number: Which scan (0-4 for 5 total scans)
 * - total_fingers: How many fingers to register
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

try {
    ensureFingerprintRegistrationTable($mysqli);

    // Get current mode from settings table used by the active schema.
    $stmt = $mysqli->prepare("SELECT setting_value FROM settings WHERE setting_key = 'current_mode' LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    $mode = $result ? $result['setting_value'] : 'attendance';

    $response = [
        'success' => true,
        'mode' => $mode,
        'timestamp' => time()
    ];

    // If in registration mode, get active registration details.
    if ($mode === 'registration') {
        $stmt = $mysqli->prepare("SELECT id AS registration_id, student_id, finger_number, scan_number, total_fingers, status FROM fingerprint_registrations WHERE status = 'active' ORDER BY updated_at DESC LIMIT 1");
        $stmt->execute();
        $regData = $stmt->get_result()->fetch_assoc();

        if ($regData) {
            $response['registration_id'] = $regData['registration_id'];
            $response['student_id'] = $regData['student_id'];
            $response['finger_number'] = (int)$regData['finger_number'];
            $response['scan_number'] = (int)$regData['scan_number'];
            $response['total_fingers'] = (int)$regData['total_fingers'];
        } else {
            $response['registration_id'] = '';
            $response['finger_number'] = 1;
            $response['scan_number'] = 0;
            $response['total_fingers'] = 1;
        }
    }

    echo json_encode($response);

} catch (Exception $e) {
    // Default to attendance mode on error to avoid blocking scanner loop.
    echo json_encode([
        'success' => true,
        'mode' => 'attendance',
        'error' => $e->getMessage()
    ]);
}
?>