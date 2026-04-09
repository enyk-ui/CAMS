<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

require_method('POST');

function scanProgressColumnExists(mysqli $mysqli, string $column): bool
{
    $safe = $mysqli->real_escape_string($column);
    $result = $mysqli->query("SHOW COLUMNS FROM device_commands LIKE '{$safe}'");
    return $result && $result->num_rows > 0;
}

try {
    $input = read_json_body();

    $studentId = require_positive_int($input, 'student_id');
    $fingerIndex = require_positive_int($input, 'finger_index');
    $scanStep = require_positive_int($input, 'scan_step');
    $totalSteps = isset($input['total_steps']) ? (int)$input['total_steps'] : 3;

    $linkColumn = scanProgressColumnExists($mysqli, 'student_id') ? 'student_id' : (scanProgressColumnExists($mysqli, 'user_id') ? 'user_id' : null);
    if ($linkColumn === null) {
        api_response(500, [
            'success' => false,
            'message' => 'device_commands must contain student_id or user_id column'
        ]);
    }

    $orderColumn = scanProgressColumnExists($mysqli, 'created_at') ? 'created_at' : 'id';
    $hasScanStep = scanProgressColumnExists($mysqli, 'scan_step');
    $hasTotalSteps = scanProgressColumnExists($mysqli, 'total_scan_steps');

    if ($hasScanStep && $hasTotalSteps) {
        $stmt = $mysqli->prepare("UPDATE device_commands SET scan_step = ?, total_scan_steps = ?, status = 'IN_PROGRESS' WHERE mode = 'ENROLL' AND {$linkColumn} = ? AND finger_index = ? AND status IN ('PENDING', 'IN_PROGRESS') ORDER BY {$orderColumn} DESC LIMIT 1");
        $stmt->bind_param('iiii', $scanStep, $totalSteps, $studentId, $fingerIndex);
    } else {
        // Fallback for legacy schemas: keep command alive even without progress columns.
        $stmt = $mysqli->prepare("UPDATE device_commands SET status = 'IN_PROGRESS' WHERE mode = 'ENROLL' AND {$linkColumn} = ? AND finger_index = ? AND status IN ('PENDING', 'IN_PROGRESS') ORDER BY {$orderColumn} DESC LIMIT 1");
        $stmt->bind_param('ii', $studentId, $fingerIndex);
    }
    $stmt->execute();

    $affected = $stmt->affected_rows;

    api_response(200, [
        'success' => $affected > 0,
        'message' => $affected > 0 ? 'Scan progress updated' : 'No matching command found',
        'scan_step' => $scanStep,
        'total_steps' => $totalSteps,
        'progress_columns_present' => $hasScanStep && $hasTotalSteps
    ]);
} catch (Throwable $e) {
    api_response(500, [
        'success' => false,
        'message' => 'Failed to update scan progress',
        'error' => $e->getMessage()
    ]);
}
