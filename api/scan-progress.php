<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

require_method('POST');

try {
    $input = read_json_body();

    $studentId = require_positive_int($input, 'student_id');
    $fingerIndex = require_positive_int($input, 'finger_index');
    $scanStep = require_positive_int($input, 'scan_step');
    $totalSteps = isset($input['total_steps']) ? (int)$input['total_steps'] : 3;

    // Update the device command with the current scan progress.
    $stmt = $mysqli->prepare("UPDATE device_commands SET scan_step = ?, total_scan_steps = ?, status = 'IN_PROGRESS' WHERE mode = 'ENROLL' AND student_id = ? AND finger_index = ? AND status IN ('PENDING', 'IN_PROGRESS') ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param('iiii', $scanStep, $totalSteps, $studentId, $fingerIndex);
    $stmt->execute();

    $affected = $stmt->affected_rows;

    api_response(200, [
        'success' => $affected > 0,
        'message' => $affected > 0 ? 'Scan progress updated' : 'No matching command found',
        'scan_step' => $scanStep,
        'total_steps' => $totalSteps
    ]);
} catch (Throwable $e) {
    api_response(500, [
        'success' => false,
        'message' => 'Failed to update scan progress',
        'error' => $e->getMessage()
    ]);
}
