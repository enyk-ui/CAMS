<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

require_method('GET');

function getModeColumnExists(mysqli $mysqli, string $column): bool
{
    $safe = $mysqli->real_escape_string($column);
    $result = $mysqli->query("SHOW COLUMNS FROM device_commands LIKE '{$safe}'");
    return $result && $result->num_rows > 0;
}

try {
    $requestedRegistrationId = null;
    if (isset($_GET['registration_id']) && $_GET['registration_id'] !== '') {
        $requestedRegistrationId = filter_var($_GET['registration_id'], FILTER_VALIDATE_INT);
        if ($requestedRegistrationId === false || $requestedRegistrationId <= 0) {
            $requestedRegistrationId = null;
        }
    }

    $linkColumn = null;
    if (getModeColumnExists($mysqli, 'student_id')) {
        $linkColumn = 'student_id';
    } elseif (getModeColumnExists($mysqli, 'user_id')) {
        $linkColumn = 'user_id';
    }

    if ($linkColumn === null) {
        api_response(500, [
            'success' => false,
            'message' => 'device_commands must contain student_id or user_id column'
        ]);
    }

    $updatedOrderColumn = getModeColumnExists($mysqli, 'updated_at') ? 'updated_at' : 'id';
    $createdOrderColumn = getModeColumnExists($mysqli, 'created_at') ? 'created_at' : 'id';

    $scanStepExpr = getModeColumnExists($mysqli, 'scan_step') ? 'scan_step' : 'NULL AS scan_step';
    $totalScanStepsExpr = getModeColumnExists($mysqli, 'total_scan_steps') ? 'total_scan_steps' : '3 AS total_scan_steps';

    $lastCompletedStmt = $mysqli->prepare("SELECT sensor_id FROM device_commands WHERE mode = 'ENROLL' AND status = 'COMPLETED' AND sensor_id IS NOT NULL ORDER BY {$updatedOrderColumn} DESC LIMIT 1");
    $lastCompletedStmt->execute();
    $lastCompleted = $lastCompletedStmt->get_result()->fetch_assoc();
    $lastSensorId = $lastCompleted ? (int)$lastCompleted['sensor_id'] : null;

    $stmt = $mysqli->prepare("SELECT id, {$linkColumn} AS student_ref, finger_index, {$scanStepExpr}, {$totalScanStepsExpr}, error_message FROM device_commands WHERE mode = 'ENROLL' AND status IN ('PENDING','IN_PROGRESS') ORDER BY {$createdOrderColumn} DESC LIMIT 1");
    $stmt->execute();
    $command = $stmt->get_result()->fetch_assoc();

    if (!$command) {
        if ($requestedRegistrationId !== null) {
            $failedStmt = $mysqli->prepare("SELECT id, finger_index, error_message FROM device_commands WHERE id = ? AND mode = 'ENROLL' AND status = 'FAILED' LIMIT 1");
            $failedStmt->bind_param('i', $requestedRegistrationId);
            $failedStmt->execute();
            $failedCommand = $failedStmt->get_result()->fetch_assoc();

            if ($failedCommand) {
                $failedMessage = trim((string) ($failedCommand['error_message'] ?? ''));
                if ($failedMessage === '') {
                    $failedMessage = 'Enrollment failed on device';
                }

                api_response(200, [
                    'success' => true,
                    'mode' => 'failed',
                    'registration_id' => (int) $failedCommand['id'],
                    'finger_number' => (int) ($failedCommand['finger_index'] ?? 1),
                    'message' => $failedMessage,
                    'last_sensor_id' => $lastSensorId
                ]);
            }
        }

        api_response(200, [
            'success' => true,
            'mode' => 'attendance',
            'message' => 'No active registration',
            'last_sensor_id' => $lastSensorId
        ]);
    }

    $totalFingers = 1;
    $commandMeta = (string)($command['error_message'] ?? '');
    if ($commandMeta !== '' && preg_match('/total_fingers:(\d+)/', $commandMeta, $m)) {
        $totalFingers = max(1, (int)$m[1]);
    }

    $uiStatus = '';
    if ($commandMeta !== '' && preg_match('/ui_status:([^|]+)/', $commandMeta, $m)) {
        $uiStatus = trim((string)$m[1]);
    }

    $uiMessage = '';
    if ($commandMeta !== '' && preg_match('/ui_message:([^|]+)/', $commandMeta, $m)) {
        $uiMessage = trim((string)$m[1]);
    }

    $scanStep = $command['scan_step'] !== null ? (int)$command['scan_step'] : 0;
    $totalScanSteps = $command['total_scan_steps'] !== null ? (int)$command['total_scan_steps'] : 3;

    api_response(200, [
        'success' => true,
        'mode' => 'registration',
        'registration_id' => (int)$command['id'],
        'finger_number' => (int)$command['finger_index'],
        'total_fingers' => $totalFingers,
        'scan_step' => $scanStep,
        'total_scan_steps' => $totalScanSteps,
        'message' => 'Waiting for scan...',
        'last_sensor_id' => $lastSensorId,
        'ui_status' => $uiStatus,
        'ui_message' => $uiMessage
    ]);
} catch (Throwable $e) {
    api_response(500, [
        'success' => false,
        'message' => 'Failed to get mode',
        'error' => $e->getMessage()
    ]);
}

/*
 * © 2026 TambyTech.
 * This source code is proprietary and confidential.
 * Any unauthorized use, copying, modification, distribution, or disclosure is strictly prohibited.
 * All rights reserved.
 */