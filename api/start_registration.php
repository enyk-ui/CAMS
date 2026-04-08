<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

require_method('POST');

try {
    $input = read_json_body();
    $userId = require_positive_int($input, 'student_id');
    $totalFingers = require_positive_int($input, 'total_fingers');
    $deviceId = isset($input['device_id']) ? require_positive_int($input, 'device_id') : 1;

    if ($totalFingers < 1 || $totalFingers > 5) {
        api_response(400, [
            'success' => false,
            'message' => 'total_fingers must be from 1 to 5'
        ]);
    }

    $studentStmt = $mysqli->prepare('SELECT id FROM students WHERE id = ? LIMIT 1');
    $studentStmt->bind_param('i', $userId);
    $studentStmt->execute();
    if (!$studentStmt->get_result()->fetch_assoc()) {
        api_response(404, [
            'success' => false,
            'message' => 'Student not found'
        ]);
    }

    $cancelCommandsStmt = $mysqli->prepare("UPDATE device_commands SET status = 'FAILED', error_message = 'Superseded by new enrollment session' WHERE student_id = ? AND mode = 'ENROLL' AND status IN ('PENDING', 'IN_PROGRESS')");
    $cancelCommandsStmt->bind_param('i', $userId);
    $cancelCommandsStmt->execute();

    $notes = 'total_fingers:' . (string)$totalFingers;
    $commandStmt = $mysqli->prepare("INSERT INTO device_commands (device_id, mode, student_id, finger_index, status, error_message) VALUES (?, 'ENROLL', ?, 1, 'PENDING', ?)");
    $commandStmt->bind_param('iis', $deviceId, $userId, $notes);
    $commandStmt->execute();
    $registrationId = (int)$mysqli->insert_id;

    api_response(200, [
        'success' => true,
        'message' => 'Enrollment started',
        'registration_id' => $registrationId,
        'finger_number' => 1,
        'total_fingers' => $totalFingers
    ]);
} catch (Throwable $e) {
    api_response(500, [
        'success' => false,
        'message' => 'Failed to start registration',
        'error' => $e->getMessage()
    ]);
}
