<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

require_method('POST');

function enrollResultColumnExists(mysqli $mysqli, string $table, string $column): bool
{
    $safeTable = $mysqli->real_escape_string($table);
    $safeColumn = $mysqli->real_escape_string($column);
    $result = $mysqli->query("SHOW COLUMNS FROM {$safeTable} LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

try {
    $input = read_json_body();

    $studentId = require_positive_int($input, 'student_id');
    $fingerIndex = require_positive_int($input, 'finger_index');
    $isSuccess = isset($input['success']) ? (bool) $input['success'] : true;
    $sensorId = $isSuccess ? require_positive_int($input, 'sensor_id') : (int) ($input['sensor_id'] ?? 0);
    $errorMessage = trim((string) ($input['error_message'] ?? 'Enrollment failed on device'));

    $commandLinkColumn = enrollResultColumnExists($mysqli, 'device_commands', 'student_id') ? 'student_id' : (enrollResultColumnExists($mysqli, 'device_commands', 'user_id') ? 'user_id' : null);
    $fingerprintLinkColumn = enrollResultColumnExists($mysqli, 'fingerprints', 'student_id') ? 'student_id' : (enrollResultColumnExists($mysqli, 'fingerprints', 'user_id') ? 'user_id' : null);

    if ($commandLinkColumn === null) {
        api_response(500, [
            'success' => false,
            'message' => 'device_commands must contain student_id or user_id column'
        ]);
    }
    if ($isSuccess && $fingerprintLinkColumn === null) {
        api_response(500, [
            'success' => false,
            'message' => 'fingerprints must contain student_id or user_id column'
        ]);
    }

    $createdOrderColumn = enrollResultColumnExists($mysqli, 'device_commands', 'created_at') ? 'created_at' : 'id';

    $studentStmt = $mysqli->prepare('SELECT id FROM students WHERE id = ? LIMIT 1');
    $studentStmt->bind_param('i', $studentId);
    $studentStmt->execute();

    if (!$studentStmt->get_result()->fetch_assoc()) {
        api_response(404, [
            'success' => false,
            'message' => 'Student not found'
        ]);
    }

    $commandStmt = $mysqli->prepare("SELECT id, device_id, error_message FROM device_commands WHERE mode = 'ENROLL' AND {$commandLinkColumn} = ? AND finger_index = ? AND status IN ('PENDING', 'IN_PROGRESS') ORDER BY {$createdOrderColumn} DESC LIMIT 1");
    $commandStmt->bind_param('ii', $studentId, $fingerIndex);
    $commandStmt->execute();
    $command = $commandStmt->get_result()->fetch_assoc();

    $deviceId = $command ? (int)$command['device_id'] : 1;

    if (!$isSuccess) {
        if ($command) {
            $failStmt = $mysqli->prepare("UPDATE device_commands SET status = 'FAILED', error_message = ? WHERE id = ?");
            $failStmt->bind_param('si', $errorMessage, $command['id']);
            $failStmt->execute();
        }

        api_response(200, [
            'success' => true,
            'message' => 'Enrollment failure recorded',
            'student_id' => $studentId,
            'finger_index' => $fingerIndex
        ]);
    }

    $mysqli->begin_transaction();

    // Remove conflicting rows first to avoid duplicate fingerprint ownership.
    $clearByStudentFingerStmt = $mysqli->prepare("DELETE FROM fingerprints WHERE {$fingerprintLinkColumn} = ? AND finger_index = ? AND sensor_id <> ?");
    $clearByStudentFingerStmt->bind_param('iii', $studentId, $fingerIndex, $sensorId);
    $clearByStudentFingerStmt->execute();

    $clearBySensorStmt = $mysqli->prepare("DELETE FROM fingerprints WHERE sensor_id = ? AND ({$fingerprintLinkColumn} <> ? OR finger_index <> ?)");
    $clearBySensorStmt->bind_param('iii', $sensorId, $studentId, $fingerIndex);
    $clearBySensorStmt->execute();

    $updateFingerprintStmt = $mysqli->prepare("UPDATE fingerprints SET sensor_id = ?, device_id = ? WHERE {$fingerprintLinkColumn} = ? AND finger_index = ? LIMIT 1");
    $updateFingerprintStmt->bind_param('iiii', $sensorId, $deviceId, $studentId, $fingerIndex);
    $updateFingerprintStmt->execute();

    if ($updateFingerprintStmt->affected_rows === 0) {
        $insertFingerprintStmt = $mysqli->prepare("INSERT INTO fingerprints ({$fingerprintLinkColumn}, finger_index, sensor_id, device_id) VALUES (?, ?, ?, ?)");
        $insertFingerprintStmt->bind_param('iiii', $studentId, $fingerIndex, $sensorId, $deviceId);
        $insertFingerprintStmt->execute();
    }

    if ($command) {
        $completeStmt = $mysqli->prepare("UPDATE device_commands SET sensor_id = ?, status = 'COMPLETED' WHERE id = ?");
        $completeStmt->bind_param('ii', $sensorId, $command['id']);
        $completeStmt->execute();
    }

    // Do NOT auto-advance to next finger here.
    // The device must call /api/advance-finger.php to explicitly advance.
    // This prevents the UI from jumping ahead before the device finishes all scans.

    $mysqli->commit();

    api_response(200, [
        'success' => true,
        'message' => 'Enrollment result saved',
        'student_id' => $studentId,
        'finger_index' => $fingerIndex,
        'sensor_id' => $sensorId,
        'advance_required' => true
    ]);
} catch (Throwable $e) {
    if (isset($mysqli) && $mysqli->errno) {
        $mysqli->rollback();
    }

    api_response(500, [
        'success' => false,
        'message' => 'Failed to save enrollment result',
        'error' => $e->getMessage()
    ]);
}
