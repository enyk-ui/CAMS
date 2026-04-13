<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

require_method('POST');

try {
    $input = read_json_body();

    $studentId = 0;
    if (isset($input['student_id'])) {
        $rawStudentId = filter_var($input['student_id'], FILTER_VALIDATE_INT);
        if ($rawStudentId === false || $rawStudentId < 0) {
            api_response(400, [
                'success' => false,
                'message' => 'Invalid integer value for: student_id'
            ]);
        }
        $studentId = (int)$rawStudentId;
    }

    if (!isset($input['sensor_id'])) {
        api_response(400, [
            'success' => false,
            'message' => 'Missing required field: sensor_id'
        ]);
    }

    $rawSensorId = filter_var($input['sensor_id'], FILTER_VALIDATE_INT);
    if ($rawSensorId === false || $rawSensorId < 0) {
        api_response(400, [
            'success' => false,
            'message' => 'Invalid integer value for: sensor_id'
        ]);
    }
    $sensorId = (int)$rawSensorId;
    $ok = isset($input['success']) ? (bool)$input['success'] : true;
    $errorMessage = trim((string)($input['error_message'] ?? ''));

    $status = $ok ? 'COMPLETED' : 'FAILED';
    $finalError = $ok ? null : ($errorMessage !== '' ? $errorMessage : 'Delete failed on scanner');

    if ($studentId > 0) {
        $stmt = $mysqli->prepare("UPDATE device_commands SET status = ?, error_message = ? WHERE mode = 'DELETE' AND student_id = ? AND sensor_id = ? AND status IN ('PENDING', 'IN_PROGRESS')");
        $stmt->bind_param('ssii', $status, $finalError, $studentId, $sensorId);
    } else {
        $stmt = $mysqli->prepare("UPDATE device_commands SET status = ?, error_message = ? WHERE mode = 'DELETE' AND sensor_id = ? AND status IN ('PENDING', 'IN_PROGRESS')");
        $stmt->bind_param('ssi', $status, $finalError, $sensorId);
    }
    $stmt->execute();

    api_response(200, [
        'success' => true,
        'message' => 'Delete result saved',
        'sensor_id' => $sensorId,
        'status' => $status
    ]);
} catch (Throwable $e) {
    api_response(500, [
        'success' => false,
        'message' => 'Failed to save delete result',
        'error' => $e->getMessage()
    ]);
}

/*
 * © 2026 TambyTech.
 * This source code is proprietary and confidential.
 * Any unauthorized use, copying, modification, distribution, or disclosure is strictly prohibited.
 * All rights reserved.
 */