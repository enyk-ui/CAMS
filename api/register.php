<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

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

function registerColumnExists(mysqli $mysqli, string $table, string $column): bool
{
    $safeTable = $mysqli->real_escape_string($table);
    $safeColumn = $mysqli->real_escape_string($column);
    $result = $mysqli->query("SHOW COLUMNS FROM {$safeTable} LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

function normalizeName(string $value): string
{
    $clean = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    return $clean === '' ? '' : ucwords(strtolower($clean));
}

function bindTypeForValue($value): string
{
    return is_int($value) ? 'i' : 's';
}

function detectStudentDuplicateConflict(mysqli $mysqli, array $writeMap, int $excludeStudentId = 0): ?string
{
    if (registerColumnExists($mysqli, 'students', 'student_id') && isset($writeMap['student_id']) && trim((string)$writeMap['student_id']) !== '') {
        $studentNo = trim((string)$writeMap['student_id']);
        if ($excludeStudentId > 0) {
            $stmt = $mysqli->prepare('SELECT id FROM students WHERE student_id = ? AND id <> ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('si', $studentNo, $excludeStudentId);
                $stmt->execute();
                $exists = (bool)$stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($exists) {
                    return 'Student ID already exists.';
                }
            }
        } else {
            $stmt = $mysqli->prepare('SELECT id FROM students WHERE student_id = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $studentNo);
                $stmt->execute();
                $exists = (bool)$stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($exists) {
                    return 'Student ID already exists.';
                }
            }
        }
    }

    if (registerColumnExists($mysqli, 'students', 'email') && isset($writeMap['email']) && trim((string)$writeMap['email']) !== '') {
        $email = trim((string)$writeMap['email']);
        if ($excludeStudentId > 0) {
            $stmt = $mysqli->prepare('SELECT id FROM students WHERE email = ? AND id <> ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('si', $email, $excludeStudentId);
                $stmt->execute();
                $exists = (bool)$stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($exists) {
                    return 'Email already exists.';
                }
            }
        } else {
            $stmt = $mysqli->prepare('SELECT id FROM students WHERE email = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $exists = (bool)$stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($exists) {
                    return 'Email already exists.';
                }
            }
        }
    }

    return null;
}

function userFriendlyDuplicateMessage(string $rawMessage): string
{
    $msg = strtolower($rawMessage);
    if (strpos($msg, 'student_id') !== false || strpos($msg, 'student_no') !== false) {
        return 'Student record already exists.';
    }

    return 'Student record already exists.';
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

        if ($totalFingers !== 1) {
            api_response(400, [
                'success' => false,
                'message' => 'Only one fingerprint is allowed per student (total_fingers must be 1)'
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

        if ($totalFingers !== 1) {
            api_response(400, [
                'success' => false,
                'message' => 'Only one fingerprint is allowed per student (total_fingers must be 1)'
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

        $attendanceRefStmt = $mysqli->prepare('SELECT id FROM attendance WHERE student_id = ? LIMIT 1');
        if ($attendanceRefStmt) {
            $attendanceRefStmt->bind_param('i', $studentId);
            $attendanceRefStmt->execute();
            $hasAttendanceRef = $attendanceRefStmt->get_result()->fetch_assoc();
            $attendanceRefStmt->close();
            if ($hasAttendanceRef) {
                api_response(409, [
                    'success' => false,
                    'message' => 'Rollback blocked: student already has attendance records.'
                ]);
            }
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
        $tempFirstName = 'PENDING';
        $tempLastName = 'REGISTRATION';

        $stmt = $mysqli->prepare("INSERT INTO students (first_name, last_name, status) VALUES (?, ?, 'inactive')");
        $stmt->bind_param('ss', $tempFirstName, $tempLastName);
        $stmt->execute();

        api_response(200, [
            'success' => true,
            'message' => 'Temporary registration created',
            'student_id' => (int)$mysqli->insert_id,
            'deferred' => true
        ]);
    }

    $sectionId = isset($input['section_id']) ? (int)$input['section_id'] : 0;
    $firstName = normalizeName((string)($input['first_name'] ?? ''));
    $lastName = normalizeName((string)($input['last_name'] ?? ''));
    $middleInitial = strtoupper(substr(trim((string)($input['middle_initial'] ?? '')), 0, 1));
    $extension = normalizeName((string)($input['extension'] ?? ''));

    if ($firstName === '' || $lastName === '') {
        api_response(400, [
            'success' => false,
            'message' => 'Missing required fields: first_name, last_name, section_id'
        ]);
    }

    if ($sectionId <= 0) {
        $legacyYear = isset($input['year']) ? (int)$input['year'] : 0;
        $legacySection = trim((string)($input['section'] ?? ''));
        if ($legacyYear > 0 && $legacySection !== '') {
            $legacySectionStmt = $mysqli->prepare('SELECT id FROM sections WHERE year_grade = ? AND name = ? LIMIT 1');
            if ($legacySectionStmt) {
                $legacyYearText = (string)$legacyYear;
                $legacySectionStmt->bind_param('ss', $legacyYearText, $legacySection);
                $legacySectionStmt->execute();
                $legacySectionRow = $legacySectionStmt->get_result()->fetch_assoc();
                $legacySectionStmt->close();
                if ($legacySectionRow) {
                    $sectionId = (int)$legacySectionRow['id'];
                }
            }
        }
    }

    if ($sectionId <= 0) {
        api_response(400, [
            'success' => false,
            'message' => 'Missing required fields: section_id'
        ]);
    }

    $writeMap = [
        'first_name' => $firstName,
        'last_name' => $lastName,
    ];

    if (registerColumnExists($mysqli, 'students', 'middle_initial')) {
        $writeMap['middle_initial'] = $middleInitial;
    }
    if (registerColumnExists($mysqli, 'students', 'extension')) {
        $writeMap['extension'] = $extension;
    }
    if (registerColumnExists($mysqli, 'students', 'section_id')) {
        $writeMap['section_id'] = $sectionId;
    }

    $sectionMetaStmt = $mysqli->prepare('SELECT name, year_grade FROM sections WHERE id = ? LIMIT 1');
    if ($sectionMetaStmt) {
        $sectionMetaStmt->bind_param('i', $sectionId);
        $sectionMetaStmt->execute();
        $sectionMeta = $sectionMetaStmt->get_result()->fetch_assoc();
        $sectionMetaStmt->close();

        if (!$sectionMeta) {
            api_response(404, [
                'success' => false,
                'message' => 'section_id not found'
            ]);
        }

        if (registerColumnExists($mysqli, 'students', 'section')) {
            $writeMap['section'] = (string)$sectionMeta['name'];
        }
        if (registerColumnExists($mysqli, 'students', 'year')) {
            $writeMap['year'] = (int)$sectionMeta['year_grade'];
        }
    }

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

        $duplicateStmt = $mysqli->prepare('SELECT id FROM students WHERE first_name = ? AND last_name = ? AND section_id = ? AND id <> ? LIMIT 1');
        if ($duplicateStmt) {
            $duplicateStmt->bind_param('ssii', $firstName, $lastName, $sectionId, $studentId);
            $duplicateStmt->execute();
            $dupRow = $duplicateStmt->get_result()->fetch_assoc();
            $duplicateStmt->close();
            if ($dupRow) {
                api_response(409, [
                    'success' => false,
                    'message' => 'Duplicate student detected for the selected section.'
                ]);
            }
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
            'student_id' => $studentId,
            'section_id' => $sectionId
        ]);
    }

    $writeMap['status'] = 'active';

    $duplicateStmt = $mysqli->prepare('SELECT id FROM students WHERE first_name = ? AND last_name = ? AND section_id = ? LIMIT 1');
    if ($duplicateStmt) {
        $duplicateStmt->bind_param('ssi', $firstName, $lastName, $sectionId);
        $duplicateStmt->execute();
        $dupRow = $duplicateStmt->get_result()->fetch_assoc();
        $duplicateStmt->close();
        if ($dupRow) {
            api_response(409, [
                'success' => false,
                'message' => 'Duplicate student detected for the selected section.'
            ]);
        }
    }

    $columns = array_keys($writeMap);
    $values = array_values($writeMap);

    $types = '';
    foreach ($values as $value) {
        $types .= bindTypeForValue($value);
    }

    $columnSql = implode(', ', $columns);
    $placeholderSql = rtrim(str_repeat('?, ', count($values)), ', ');
    $stmt = $mysqli->prepare("INSERT INTO students ({$columnSql}) VALUES ({$placeholderSql})");
    $bindParams = [$types];
    foreach ($values as $index => $value) {
        $bindParams[] = &$values[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindParams);
    $stmt->execute();

    api_response(200, [
        'success' => true,
        'message' => 'Student created',
        'student_id' => (int)$mysqli->insert_id,
        'section_id' => $sectionId
    ]);
} catch (Throwable $e) {
    $message = $e->getMessage();
    if (stripos($message, 'Duplicate entry') !== false) {
        api_response(409, [
            'success' => false,
            'message' => userFriendlyDuplicateMessage($message)
        ]);
    }

    api_response(500, [
        'success' => false,
        'message' => 'Unable to save student record. Please try again.'
    ]);
}

/*
 * � 2026 TambyTech.
 * This source code is proprietary and confidential.
 * Any unauthorized use, copying, modification, distribution, or disclosure is strictly prohibited.
 * All rights reserved.
 */