<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

require_method('GET');

function deviceCommandColumnExists(mysqli $mysqli, string $column): bool
{
    $safe = $mysqli->real_escape_string($column);
    $result = $mysqli->query("SHOW COLUMNS FROM device_commands LIKE '{$safe}'");
    return $result && $result->num_rows > 0;
}

try {
    $linkColumn = null;
    if (deviceCommandColumnExists($mysqli, 'student_id')) {
        $linkColumn = 'student_id';
    } elseif (deviceCommandColumnExists($mysqli, 'user_id')) {
        $linkColumn = 'user_id';
    }

    if ($linkColumn === null) {
        api_response(500, [
            'success' => false,
            'message' => 'device_commands must contain student_id or user_id column'
        ]);
    }

    $orderColumn = deviceCommandColumnExists($mysqli, 'created_at') ? 'created_at' : 'id';

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
        SELECT id, mode, {$linkColumn} AS student_ref, finger_index, sensor_id, status, created_at
        FROM device_commands
        WHERE status IN ('PENDING', 'IN_PROGRESS')
    ";

    if ($deviceId !== null) {
        $sql .= ' AND device_id = ?';
    }

    // Prioritize active commands first so enrollment does not fall back to IDLE mid-process.
    $sql .= " ORDER BY CASE WHEN status = 'IN_PROGRESS' THEN 0 ELSE 1 END ASC, CASE WHEN UPPER(mode) = 'IDLE' THEN 1 ELSE 0 END ASC, {$orderColumn} DESC LIMIT 1";

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

    if (strtoupper((string)$command['status']) === 'PENDING') {
        $studentRef = $command['student_ref'] !== null ? (int)$command['student_ref'] : 0;
        $fingerIndex = $command['finger_index'] !== null ? (int)$command['finger_index'] : 0;
        $sensorId = $command['sensor_id'] !== null ? (int)$command['sensor_id'] : 0;
        $createdAt = (string)($command['created_at'] ?? '');

        $updateStmt = $mysqli->prepare("UPDATE device_commands SET status = 'IN_PROGRESS' WHERE status = 'PENDING' AND device_id = ? AND mode = ? AND {$linkColumn} = ? AND finger_index = ? AND COALESCE(sensor_id, 0) = ? AND created_at = ? LIMIT 1");
        $modeValue = (string)$command['mode'];
        $updateStmt->bind_param('isiiis', $deviceId, $modeValue, $studentRef, $fingerIndex, $sensorId, $createdAt);
        $updateStmt->execute();
    }

    api_response(200, [
        'success' => true,
        'mode' => strtoupper((string)$command['mode']),
        'student_id' => $command['student_ref'] !== null ? (int)$command['student_ref'] : null,
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

/*
 * © 2026 TambyTech.
 * This source code is proprietary and confidential.
 * Any unauthorized use, copying, modification, distribution, or disclosure is strictly prohibited.
 * All rights reserved.
 */