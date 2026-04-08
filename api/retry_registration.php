<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

require_method('POST');

try {
    $input = read_json_body();
    $userId = require_positive_int($input, 'student_id');
    $totalFingers = require_positive_int($input, 'total_fingers');

    if ($totalFingers < 1 || $totalFingers > 5) {
        api_response(400, [
            'success' => false,
            'message' => 'total_fingers must be from 1 to 5'
        ]);
    }

    $userStmt = $mysqli->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
    $userStmt->bind_param('i', $userId);
    $userStmt->execute();
    if (!$userStmt->get_result()->fetch_assoc()) {
        api_response(404, [
            'success' => false,
            'message' => 'User not found'
        ]);
    }

    $mysqli->begin_transaction();

    $cancelStmt = $mysqli->prepare("UPDATE device_commands SET status = 'FAILED', error_message = 'Retry enrollment requested' WHERE user_id = ? AND mode = 'ENROLL' AND status IN ('PENDING','IN_PROGRESS')");
    $cancelStmt->bind_param('i', $userId);
    $cancelStmt->execute();

    $fingerStmt = $mysqli->prepare('SELECT sensor_id, device_id FROM fingerprints WHERE user_id = ? ORDER BY finger_index ASC');
    $fingerStmt->bind_param('i', $userId);
    $fingerStmt->execute();
    $result = $fingerStmt->get_result();

    $deviceId = 1;
    while ($row = $result->fetch_assoc()) {
        $sensorId = (int)$row['sensor_id'];
        $deviceId = (int)$row['device_id'];

        $deleteCommandStmt = $mysqli->prepare("INSERT INTO device_commands (device_id, mode, user_id, sensor_id, status, error_message) VALUES (?, 'DELETE', ?, ?, 'PENDING', 'Retry enrollment cleanup')");
        $deleteCommandStmt->bind_param('iii', $deviceId, $userId, $sensorId);
        $deleteCommandStmt->execute();
    }

    $deleteFingerprintStmt = $mysqli->prepare('DELETE FROM fingerprints WHERE user_id = ?');
    $deleteFingerprintStmt->bind_param('i', $userId);
    $deleteFingerprintStmt->execute();

    $notes = 'total_fingers:' . (string)$totalFingers;
    $enrollStmt = $mysqli->prepare("INSERT INTO device_commands (device_id, mode, user_id, finger_index, status, error_message) VALUES (?, 'ENROLL', ?, 1, 'PENDING', ?)");
    $enrollStmt->bind_param('iis', $deviceId, $userId, $notes);
    $enrollStmt->execute();

    $registrationId = (int)$mysqli->insert_id;

    $mysqli->commit();

    api_response(200, [
        'success' => true,
        'message' => 'Retry command queued',
        'registration_id' => $registrationId,
        'finger_number' => 1,
        'total_fingers' => $totalFingers
    ]);
} catch (Throwable $e) {
    if (isset($mysqli)) {
        $mysqli->rollback();
    }

    api_response(500, [
        'success' => false,
        'message' => 'Failed to queue retry enrollment',
        'error' => $e->getMessage()
    ]);
}
