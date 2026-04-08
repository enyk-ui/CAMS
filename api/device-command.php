<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

require_method('GET');

try {
    $deviceId = null;
    if (isset($_GET['device_id']) && $_GET['device_id'] !== '') {
        $deviceId = filter_var($_GET['device_id'], FILTER_VALIDATE_INT);
        if ($deviceId === false || $deviceId <= 0) {
            api_response(400, [
                'success' => false,
                'message' => 'Invalid device_id'
            ]);
        }
    }

    $sql = "
        SELECT id, mode, student_id, finger_index, sensor_id
        FROM device_commands
        WHERE status = 'PENDING'
    ";

    if ($deviceId !== null) {
        $sql .= ' AND device_id = ?';
    }

    // Always prioritize actionable commands over IDLE placeholders.
    $sql .= " ORDER BY CASE WHEN UPPER(mode) = 'IDLE' THEN 1 ELSE 0 END ASC, created_at ASC LIMIT 1";

    $stmt = $mysqli->prepare($sql);
    if ($deviceId !== null) {
        $stmt->bind_param('i', $deviceId);
    }

    $stmt->execute();
    $command = $stmt->get_result()->fetch_assoc();

    if (!$command) {
        api_response(200, [
            'success' => true,
            'mode' => 'IDLE',
            'student_id' => null,
            'finger_index' => null
        ]);
    }

    $updateStmt = $mysqli->prepare("UPDATE device_commands SET status = 'IN_PROGRESS' WHERE id = ?");
    $updateStmt->bind_param('i', $command['id']);
    $updateStmt->execute();

    api_response(200, [
        'success' => true,
        'mode' => strtoupper((string)$command['mode']),
        'student_id' => $command['student_id'] !== null ? (int)$command['student_id'] : null,
        'finger_index' => $command['finger_index'] !== null ? (int)$command['finger_index'] : null,
        'sensor_id' => $command['sensor_id'] !== null ? (int)$command['sensor_id'] : null
    ]);
} catch (Throwable $e) {
    api_response(500, [
        'success' => false,
        'message' => 'Failed to fetch device command',
        'error' => $e->getMessage()
    ]);
}
