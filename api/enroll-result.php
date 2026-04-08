<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

require_method('POST');

try {
    $input = read_json_body();

    $studentId = require_positive_int($input, 'student_id');
    $fingerIndex = require_positive_int($input, 'finger_index');
    $sensorId = require_positive_int($input, 'sensor_id');

    $studentStmt = $mysqli->prepare('SELECT id FROM students WHERE id = ? LIMIT 1');
    $studentStmt->bind_param('i', $studentId);
    $studentStmt->execute();

    if (!$studentStmt->get_result()->fetch_assoc()) {
        api_response(404, [
            'success' => false,
            'message' => 'Student not found'
        ]);
    }

    $commandStmt = $mysqli->prepare("SELECT id, device_id, error_message FROM device_commands WHERE mode = 'ENROLL' AND student_id = ? AND finger_index = ? AND status IN ('PENDING', 'IN_PROGRESS') ORDER BY created_at DESC LIMIT 1");
    $commandStmt->bind_param('ii', $studentId, $fingerIndex);
    $commandStmt->execute();
    $command = $commandStmt->get_result()->fetch_assoc();

    $deviceId = $command ? (int)$command['device_id'] : 1;

    $mysqli->begin_transaction();

    // Remove conflicting rows first to avoid duplicate fingerprint ownership.
    $clearByStudentFingerStmt = $mysqli->prepare('DELETE FROM fingerprints WHERE student_id = ? AND finger_index = ? AND sensor_id <> ?');
    $clearByStudentFingerStmt->bind_param('iii', $studentId, $fingerIndex, $sensorId);
    $clearByStudentFingerStmt->execute();

    $clearBySensorStmt = $mysqli->prepare('DELETE FROM fingerprints WHERE sensor_id = ? AND (student_id <> ? OR finger_index <> ?)');
    $clearBySensorStmt->bind_param('iii', $sensorId, $studentId, $fingerIndex);
    $clearBySensorStmt->execute();

    $updateFingerprintStmt = $mysqli->prepare('UPDATE fingerprints SET sensor_id = ?, device_id = ? WHERE student_id = ? AND finger_index = ? LIMIT 1');
    $updateFingerprintStmt->bind_param('iiii', $sensorId, $deviceId, $studentId, $fingerIndex);
    $updateFingerprintStmt->execute();

    if ($updateFingerprintStmt->affected_rows === 0) {
        $insertFingerprintStmt = $mysqli->prepare('INSERT INTO fingerprints (student_id, finger_index, sensor_id, device_id) VALUES (?, ?, ?, ?)');
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
