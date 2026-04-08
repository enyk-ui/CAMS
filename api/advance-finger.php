<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

require_method('POST');

try {
    $input = read_json_body();

    $studentId = require_positive_int($input, 'student_id');
    $currentFingerIndex = require_positive_int($input, 'finger_index');

    // Find the completed command for this finger to get total_fingers.
    $commandStmt = $mysqli->prepare("SELECT id, device_id, error_message FROM device_commands WHERE mode = 'ENROLL' AND student_id = ? AND finger_index = ? AND status = 'COMPLETED' ORDER BY updated_at DESC LIMIT 1");
    $commandStmt->bind_param('ii', $studentId, $currentFingerIndex);
    $commandStmt->execute();
    $command = $commandStmt->get_result()->fetch_assoc();

    if (!$command) {
        api_response(400, [
            'success' => false,
            'message' => 'No completed enrollment found for this finger'
        ]);
    }

    $deviceId = (int)$command['device_id'];

    $totalFingers = 1;
    if (!empty($command['error_message']) && preg_match('/total_fingers:(\d+)/', (string)$command['error_message'], $m)) {
        $totalFingers = max(1, (int)$m[1]);
    }

    $mysqli->begin_transaction();

    if ($currentFingerIndex < $totalFingers) {
        // Create command for next finger.
        $nextFinger = $currentFingerIndex + 1;
        $notes = 'total_fingers:' . (string)$totalFingers;

        $nextCommandStmt = $mysqli->prepare("INSERT INTO device_commands (device_id, mode, student_id, finger_index, status, error_message) VALUES (?, 'ENROLL', ?, ?, 'PENDING', ?)");
        $nextCommandStmt->bind_param('iiis', $deviceId, $studentId, $nextFinger, $notes);
        $nextCommandStmt->execute();

        $mysqli->commit();

        api_response(200, [
            'success' => true,
            'message' => 'Advanced to next finger',
            'next_finger_index' => $nextFinger,
            'total_fingers' => $totalFingers,
            'enrollment_complete' => false
        ]);
    } else {
        // All fingers enrolled, switch to IDLE.
        $idleCommandStmt = $mysqli->prepare("INSERT INTO device_commands (device_id, mode, status) VALUES (?, 'IDLE', 'PENDING')");
        $idleCommandStmt->bind_param('i', $deviceId);
        $idleCommandStmt->execute();

        $mysqli->commit();

        api_response(200, [
            'success' => true,
            'message' => 'All fingers enrolled',
            'next_finger_index' => null,
            'total_fingers' => $totalFingers,
            'enrollment_complete' => true
        ]);
    }
} catch (Throwable $e) {
    if (isset($mysqli) && $mysqli->errno) {
        $mysqli->rollback();
    }

    api_response(500, [
        'success' => false,
        'message' => 'Failed to advance finger',
        'error' => $e->getMessage()
    ]);
}
