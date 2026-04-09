<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/student_registration_helper.php';

require_method('POST');

function resolveLinkColumn(mysqli $mysqli, string $tableName): ?string
{
    $safeTable = $mysqli->real_escape_string($tableName);
    $studentResult = $mysqli->query("SHOW COLUMNS FROM {$safeTable} LIKE 'student_id'");
    if ($studentResult && $studentResult->num_rows > 0) {
        return 'student_id';
    }

    $userResult = $mysqli->query("SHOW COLUMNS FROM {$safeTable} LIKE 'user_id'");
    if ($userResult && $userResult->num_rows > 0) {
        return 'user_id';
    }

    return null;
}

function ensureDeviceCommandProgressColumns(mysqli $mysqli): void
{
    $scanStep = $mysqli->query("SHOW COLUMNS FROM device_commands LIKE 'scan_step'");
    if ($scanStep && $scanStep->num_rows === 0) {
        $mysqli->query("ALTER TABLE device_commands ADD COLUMN scan_step TINYINT UNSIGNED DEFAULT NULL AFTER sensor_id");
    }

    $totalSteps = $mysqli->query("SHOW COLUMNS FROM device_commands LIKE 'total_scan_steps'");
    if ($totalSteps && $totalSteps->num_rows === 0) {
        $mysqli->query("ALTER TABLE device_commands ADD COLUMN total_scan_steps TINYINT UNSIGNED DEFAULT 3 AFTER scan_step");
    }
}

try {
    $input = read_json_body();
    ensureDeviceCommandProgressColumns($mysqli);
    $deviceCommandLinkColumn = resolveLinkColumn($mysqli, 'device_commands');
    $fingerprintLinkColumn = resolveLinkColumn($mysqli, 'fingerprints');

    $action = strtolower(trim((string)($input['action'] ?? 'save')));

    if ($action === 'start') {
        $studentId = require_positive_int($input, 'student_id');
        $totalFingers = require_positive_int($input, 'total_fingers');
        $deviceId = isset($input['device_id']) ? require_positive_int($input, 'device_id') : 1;

        if ($totalFingers < 1 || $totalFingers > 5) {
            api_response(400, [
                'success' => false,
                'message' => 'total_fingers must be from 1 to 5'
            ]);
        }

        $studentStmt = $mysqli->prepare('SELECT id FROM students WHERE id = ? LIMIT 1');
        $studentStmt->bind_param('i', $studentId);
        $studentStmt->execute();
        if (!$studentStmt->get_result()->fetch_assoc()) {
            api_response(404, [
                'success' => false,
                'message' => 'Student not found'
            ]);
        }

        if ($deviceCommandLinkColumn === null) {
            api_response(500, [
                'success' => false,
                'message' => 'device_commands must contain student_id or user_id column'
            ]);
        }

        $cancelCommandsStmt = $mysqli->prepare("UPDATE device_commands SET status = 'FAILED', error_message = 'Superseded by new enrollment session' WHERE device_id = ? AND mode = 'ENROLL' AND status IN ('PENDING', 'IN_PROGRESS')");
        $cancelCommandsStmt->bind_param('i', $deviceId);
        $cancelCommandsStmt->execute();

        $notes = 'total_fingers:' . (string)$totalFingers;
        $commandStmt = $mysqli->prepare("INSERT INTO device_commands (device_id, mode, {$deviceCommandLinkColumn}, finger_index, status, error_message) VALUES (?, 'ENROLL', ?, 1, 'PENDING', ?)");
        $commandStmt->bind_param('iis', $deviceId, $studentId, $notes);
        $commandStmt->execute();

        $registrationId = (int)$mysqli->insert_id;
        if ($registrationId <= 0) {
            $lookupStmt = $mysqli->prepare("SELECT id FROM device_commands WHERE device_id = ? AND mode = 'ENROLL' AND {$deviceCommandLinkColumn} = ? AND finger_index = 1 AND status IN ('PENDING', 'IN_PROGRESS') ORDER BY created_at DESC LIMIT 1");
            $lookupStmt->bind_param('ii', $deviceId, $studentId);
            $lookupStmt->execute();
            $lookup = $lookupStmt->get_result()->fetch_assoc();
            if ($lookup) {
                $registrationId = (int)$lookup['id'];
            }
        }

        api_response(200, [
            'success' => true,
            'message' => 'Enrollment started',
            'registration_id' => $registrationId,
            'finger_number' => 1,
            'total_fingers' => $totalFingers
        ]);
    }

    if ($action === 'retry') {
        $studentId = require_positive_int($input, 'student_id');
        $totalFingers = require_positive_int($input, 'total_fingers');

        if ($totalFingers < 1 || $totalFingers > 5) {
            api_response(400, [
                'success' => false,
                'message' => 'total_fingers must be from 1 to 5'
            ]);
        }

        $studentStmt = $mysqli->prepare('SELECT id FROM students WHERE id = ? LIMIT 1');
        $studentStmt->bind_param('i', $studentId);
        $studentStmt->execute();
        if (!$studentStmt->get_result()->fetch_assoc()) {
            api_response(404, [
                'success' => false,
                'message' => 'Student not found'
            ]);
        }

        if ($deviceCommandLinkColumn === null) {
            api_response(500, [
                'success' => false,
                'message' => 'device_commands must contain student_id or user_id column'
            ]);
        }
        if ($fingerprintLinkColumn === null) {
            api_response(500, [
                'success' => false,
                'message' => 'fingerprints must contain student_id or user_id column'
            ]);
        }

        $inTransaction = false;
        try {
            $mysqli->begin_transaction();
            $inTransaction = true;

            $cancelStmt = $mysqli->prepare("UPDATE device_commands SET status = 'FAILED', error_message = 'Retry enrollment requested' WHERE {$deviceCommandLinkColumn} = ? AND mode = 'ENROLL' AND status IN ('PENDING','IN_PROGRESS')");
            $cancelStmt->bind_param('i', $studentId);
            $cancelStmt->execute();

            $fingerStmt = $mysqli->prepare("SELECT sensor_id, device_id FROM fingerprints WHERE {$fingerprintLinkColumn} = ? ORDER BY finger_index ASC");
            $fingerStmt->bind_param('i', $studentId);
            $fingerStmt->execute();
            $result = $fingerStmt->get_result();

            $deviceId = 1;
            while ($row = $result->fetch_assoc()) {
                $sensorId = (int)$row['sensor_id'];
                $deviceId = (int)$row['device_id'];

                $deleteCommandStmt = $mysqli->prepare("INSERT INTO device_commands (device_id, mode, {$deviceCommandLinkColumn}, sensor_id, status, error_message) VALUES (?, 'DELETE', ?, ?, 'PENDING', 'Retry enrollment cleanup')");
                $deleteCommandStmt->bind_param('iii', $deviceId, $studentId, $sensorId);
                $deleteCommandStmt->execute();
            }

            $deleteFingerprintStmt = $mysqli->prepare("DELETE FROM fingerprints WHERE {$fingerprintLinkColumn} = ?");
            $deleteFingerprintStmt->bind_param('i', $studentId);
            $deleteFingerprintStmt->execute();

            $notes = 'total_fingers:' . (string)$totalFingers;
            $enrollStmt = $mysqli->prepare("INSERT INTO device_commands (device_id, mode, {$deviceCommandLinkColumn}, finger_index, status, error_message) VALUES (?, 'ENROLL', ?, 1, 'PENDING', ?)");
            $enrollStmt->bind_param('iis', $deviceId, $studentId, $notes);
            $enrollStmt->execute();

            $registrationId = (int)$mysqli->insert_id;
            $mysqli->commit();
            $inTransaction = false;
        } catch (Throwable $retryError) {
            if ($inTransaction) {
                $mysqli->rollback();
            }
            throw $retryError;
        }

        api_response(200, [
            'success' => true,
            'message' => 'Retry command queued',
            'registration_id' => $registrationId,
            'finger_number' => 1,
            'total_fingers' => $totalFingers
        ]);
    }

    if ($action === 'rollback') {
        $studentId = require_positive_int($input, 'student_id');

        if ($deviceCommandLinkColumn === null) {
            api_response(500, [
                'success' => false,
                'message' => 'device_commands must contain student_id or user_id column'
            ]);
        }
        if ($fingerprintLinkColumn === null) {
            api_response(500, [
                'success' => false,
                'message' => 'fingerprints must contain student_id or user_id column'
            ]);
        }

        $cancelCommandStmt = $mysqli->prepare("UPDATE device_commands SET status = 'FAILED', error_message = 'Registration cancelled by admin' WHERE {$deviceCommandLinkColumn} = ? AND mode = 'ENROLL' AND status IN ('PENDING', 'IN_PROGRESS')");
        $cancelCommandStmt->bind_param('i', $studentId);
        $cancelCommandStmt->execute();

        $deleteFingerprintStmt = $mysqli->prepare("DELETE FROM fingerprints WHERE {$fingerprintLinkColumn} = ?");
        $deleteFingerprintStmt->bind_param('i', $studentId);
        $deleteFingerprintStmt->execute();

        $deleteStudentStmt = $mysqli->prepare('DELETE FROM students WHERE id = ?');
        $deleteStudentStmt->bind_param('i', $studentId);
        $deleteStudentStmt->execute();

        api_response(200, [
            'success' => true,
            'message' => 'Registration rolled back'
        ]);
    }

    $deferSave = isset($input['defer_save']) ? (bool)$input['defer_save'] : false;
    $finalizeSave = isset($input['finalize'])
        ? (bool)$input['finalize']
        : (isset($input['student_no']) || isset($input['student_id_text']));

    if ($deferSave) {
        $tempStudentId = 'TMP-' . strtoupper(bin2hex(random_bytes(6)));
        $tempFirstName = 'PENDING';
        $tempLastName = 'REGISTRATION';

        $stmt = $mysqli->prepare("INSERT INTO students (student_id, first_name, last_name, status) VALUES (?, ?, ?, 'inactive')");
        $stmt->bind_param('sss', $tempStudentId, $tempFirstName, $tempLastName);
        $stmt->execute();

        api_response(200, [
            'success' => true,
            'message' => 'Temporary registration created',
            'student_id' => (int)$mysqli->insert_id,
            'deferred' => true
        ]);
    }

    $studentData = normalizeStudentRegistrationInput($input);
    $validationError = validateStudentRegistrationRequired($studentData);
    if ($validationError !== null) {
        api_response(400, [
            'success' => false,
            'message' => $validationError
        ]);
    }

    $writeMap = buildStudentWriteParts($mysqli, $studentData);

    if ($finalizeSave) {
        $studentId = require_positive_int($input, 'student_id');

        $existsStmt = $mysqli->prepare('SELECT id FROM students WHERE id = ? LIMIT 1');
        $existsStmt->bind_param('i', $studentId);
        $existsStmt->execute();
        if (!$existsStmt->get_result()->fetch_assoc()) {
            api_response(404, [
                'success' => false,
                'message' => 'Temporary registration student not found'
            ]);
        }

        $writeMap['status'] = 'active';
        $setParts = [];
        $values = [];
        $types = '';
        foreach ($writeMap as $column => $value) {
            $setParts[] = "{$column} = ?";
            $values[] = $value;
            $types .= bindTypeForValue($value);
        }

        $types .= 'i';
        $values[] = $studentId;

        $sql = 'UPDATE students SET ' . implode(', ', $setParts) . ' WHERE id = ?';
        $stmt = $mysqli->prepare($sql);
        $bindParams = [$types];
        foreach ($values as $index => $value) {
            $bindParams[] = &$values[$index];
        }
        call_user_func_array([$stmt, 'bind_param'], $bindParams);
        $stmt->execute();

        api_response(200, [
            'success' => true,
            'message' => 'Student record finalized',
            'student_id' => $studentId
        ]);
    }

    $columns = array_keys($writeMap);
    $values = array_values($writeMap);

    $types = '';
    foreach ($values as $value) {
        $types .= bindTypeForValue($value);
    }

    $columnSql = implode(', ', $columns);
    $placeholderSql = rtrim(str_repeat('?, ', count($values)), ', ');
    $stmt = $mysqli->prepare("INSERT INTO students ({$columnSql}) VALUES ({$placeholderSql}, 'active')");
    $bindParams = [$types];
    foreach ($values as $index => $value) {
        $bindParams[] = &$values[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindParams);
    $stmt->execute();

    api_response(200, [
        'success' => true,
        'message' => 'Student created',
        'student_id' => (int)$mysqli->insert_id
    ]);
} catch (Throwable $e) {
    $message = $e->getMessage();
    if (stripos($message, 'Duplicate entry') !== false) {
        $message = 'Student ID or email already exists';
    }

    api_response(500, [
        'success' => false,
        'message' => $message
    ]);
}
