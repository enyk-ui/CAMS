<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

require_method('POST');

try {
    $input = read_json_body();

    $sensorId = require_positive_int($input, 'sensor_id');
    $deviceId = require_positive_int($input, 'device_id');

    $fingerprintStmt = $mysqli->prepare('SELECT student_id FROM fingerprints WHERE sensor_id = ? LIMIT 1');
    $fingerprintStmt->bind_param('i', $sensorId);
    $fingerprintStmt->execute();
    $fingerprint = $fingerprintStmt->get_result()->fetch_assoc();

    if (!$fingerprint) {
        api_response(404, [
            'success' => false,
            'message' => 'Invalid sensor_id'
        ]);
    }

    $studentId = (int)$fingerprint['student_id'];

    $studentStmt = $mysqli->prepare('SELECT first_name, last_name FROM students WHERE id = ? LIMIT 1');
    $studentStmt->bind_param('i', $studentId);
    $studentStmt->execute();
    $student = $studentStmt->get_result()->fetch_assoc();
    $studentName = $student ? trim($student['first_name'] . ' ' . $student['last_name']) : 'Student';

    $lastLogStmt = $mysqli->prepare('SELECT type FROM attendance_logs WHERE student_id = ? AND DATE(timestamp) = CURDATE() ORDER BY timestamp DESC LIMIT 1');
    $lastLogStmt->bind_param('i', $studentId);
    $lastLogStmt->execute();
    $lastLog = $lastLogStmt->get_result()->fetch_assoc();

    $nextType = (!$lastLog || $lastLog['type'] === 'OUT') ? 'IN' : 'OUT';

    $insertStmt = $mysqli->prepare('INSERT INTO attendance_logs (student_id, device_id, type) VALUES (?, ?, ?)');
    $insertStmt->bind_param('iis', $studentId, $deviceId, $nextType);
    $insertStmt->execute();

    api_response(200, [
        'success' => true,
        'message' => 'Attendance recorded',
        'student_id' => $studentId,
        'student_name' => $studentName,
        'type' => $nextType,
        'time' => date('H:i:s'),
        'sensor_id' => $sensorId,
        'device_id' => $deviceId
    ]);
} catch (Throwable $e) {
    api_response(500, [
        'success' => false,
        'message' => 'Failed to record attendance',
        'error' => $e->getMessage()
    ]);
}
