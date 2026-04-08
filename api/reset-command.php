<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

require_method('GET');

try {
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

    if ($deviceId === null) {
        $deviceStmt = $mysqli->prepare('SELECT id FROM devices WHERE is_active = 1 ORDER BY id ASC LIMIT 1');
        $deviceStmt->execute();
        $device = $deviceStmt->get_result()->fetch_assoc();
        $deviceId = $device ? (int)$device['id'] : 1;
    }

    $cancelSql = "
        UPDATE device_commands
        SET status = 'FAILED', error_message = 'Reset to IDLE command issued'
        WHERE status IN ('PENDING', 'IN_PROGRESS')
    ";

    if ($deviceId !== null) {
        $cancelSql .= ' AND device_id = ?';
    }

    $cancelStmt = $mysqli->prepare($cancelSql);
    if ($deviceId !== null) {
        $cancelStmt->bind_param('i', $deviceId);
    }
    $cancelStmt->execute();

    $insertStmt = $mysqli->prepare("INSERT INTO device_commands (device_id, mode, status) VALUES (?, 'IDLE', 'PENDING')");
    $insertStmt->bind_param('i', $deviceId);
    $insertStmt->execute();

    api_response(200, [
        'success' => true,
        'message' => 'Device reset command queued',
        'mode' => 'IDLE',
        'device_id' => $deviceId
    ]);
} catch (Throwable $e) {
    api_response(500, [
        'success' => false,
        'message' => 'Failed to reset command',
        'error' => $e->getMessage()
    ]);
}
