<?php
/**
 * Set Mode API
 * Called by registration/attendance pages to set the current system mode
 * This tells the Arduino scanner which mode to operate in
 */

header('Content-Type: application/json');
require_once '../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    $mode = $_POST['mode'] ?? $_GET['mode'] ?? 'attendance';
} else {
    $mode = $data['mode'] ?? 'attendance';
}

if (!in_array($mode, ['registration', 'attendance'], true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid mode. Must be "registration" or "attendance"'
    ]);
    exit;
}

try {
    $stmt = $mysqli->prepare("INSERT INTO settings (setting_key, setting_value, description, updated_at) VALUES ('current_mode', ?, 'Current scanner mode', NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
    $stmt->bind_param("s", $mode);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'mode' => $mode,
        'message' => "Mode set to $mode"
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>