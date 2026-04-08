<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

require_method('GET');

try {
    $lastCompletedStmt = $mysqli->prepare("SELECT sensor_id FROM device_commands WHERE mode = 'ENROLL' AND status = 'COMPLETED' AND sensor_id IS NOT NULL ORDER BY updated_at DESC LIMIT 1");
    $lastCompletedStmt->execute();
    $lastCompleted = $lastCompletedStmt->get_result()->fetch_assoc();
    $lastSensorId = $lastCompleted ? (int)$lastCompleted['sensor_id'] : null;

    $stmt = $mysqli->prepare("SELECT id, student_id, finger_index, scan_step, total_scan_steps, error_message FROM device_commands WHERE mode = 'ENROLL' AND status IN ('PENDING','IN_PROGRESS') ORDER BY created_at DESC LIMIT 1");
    $stmt->execute();
    $command = $stmt->get_result()->fetch_assoc();

    if (!$command) {
        api_response(200, [
            'success' => true,
            'mode' => 'attendance',
            'message' => 'No active registration',
            'last_sensor_id' => $lastSensorId
        ]);
    }

    $totalFingers = 1;
    if (!empty($command['error_message']) && preg_match('/total_fingers:(\d+)/', (string)$command['error_message'], $m)) {
        $totalFingers = max(1, (int)$m[1]);
    }

    $scanStep = $command['scan_step'] !== null ? (int)$command['scan_step'] : 0;
    $totalScanSteps = $command['total_scan_steps'] !== null ? (int)$command['total_scan_steps'] : 3;

    api_response(200, [
        'success' => true,
        'mode' => 'registration',
        'registration_id' => (int)$command['id'],
        'finger_number' => (int)$command['finger_index'],
        'total_fingers' => $totalFingers,
        'scan_step' => $scanStep,
        'total_scan_steps' => $totalScanSteps,
        'message' => 'Waiting for scan...',
        'last_sensor_id' => $lastSensorId
    ]);
} catch (Throwable $e) {
    api_response(500, [
        'success' => false,
        'message' => 'Failed to get mode',
        'error' => $e->getMessage()
    ]);
}
