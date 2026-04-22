<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

require_method('GET');

function fingerprintOwnerColumnExists(mysqli $mysqli, string $table, string $column): bool
{
    $safeTable = $mysqli->real_escape_string($table);
    $safeColumn = $mysqli->real_escape_string($column);
    $result = $mysqli->query("SHOW COLUMNS FROM {$safeTable} LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

try {
    $rawSensorId = isset($_GET['sensor_id']) ? filter_var($_GET['sensor_id'], FILTER_VALIDATE_INT) : false;
    $rawStudentId = isset($_GET['student_id']) ? filter_var($_GET['student_id'], FILTER_VALIDATE_INT) : false;

    if ($rawSensorId === false || (int)$rawSensorId <= 0) {
        api_response(400, [
            'success' => false,
            'message' => 'Invalid sensor_id'
        ]);
    }

    if ($rawStudentId === false || (int)$rawStudentId <= 0) {
        api_response(400, [
            'success' => false,
            'message' => 'Invalid student_id'
        ]);
    }

    $sensorId = (int)$rawSensorId;
    $studentId = (int)$rawStudentId;

    $linkColumn = fingerprintOwnerColumnExists($mysqli, 'fingerprints', 'student_id')
        ? 'student_id'
        : (fingerprintOwnerColumnExists($mysqli, 'fingerprints', 'user_id') ? 'user_id' : null);

    if ($linkColumn === null) {
        api_response(500, [
            'success' => false,
            'message' => 'fingerprints must contain student_id or user_id column'
        ]);
    }

    $stmt = $mysqli->prepare("SELECT {$linkColumn} AS owner_id FROM fingerprints WHERE sensor_id = ? LIMIT 1");
    $stmt->bind_param('i', $sensorId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    $assigned = $row !== null;
    $ownerId = $assigned ? (int)$row['owner_id'] : null;
    $ownedByOther = $assigned && $ownerId !== $studentId;

    api_response(200, [
        'success' => true,
        'sensor_id' => $sensorId,
        'student_id' => $studentId,
        'assigned' => $assigned,
        'owner_id' => $ownerId,
        'owned_by_other' => $ownedByOther
    ]);
} catch (Throwable $e) {
    api_response(500, [
        'success' => false,
        'message' => 'Failed to check fingerprint ownership',
        'error' => $e->getMessage()
    ]);
}

/*
 * © 2026 TambyTech.
 * This source code is proprietary and confidential.
 * Any unauthorized use, copying, modification, distribution, or disclosure is strictly prohibited.
 * All rights reserved.
 */