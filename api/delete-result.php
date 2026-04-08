<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

require_method('POST');

try {
    $input = read_json_body();

    $sensorId = require_positive_int($input, 'sensor_id');
    $ok = isset($input['success']) ? (bool)$input['success'] : true;
    $errorMessage = trim((string)($input['error_message'] ?? ''));

    $status = $ok ? 'COMPLETED' : 'FAILED';
    $finalError = $ok ? null : ($errorMessage !== '' ? $errorMessage : 'Delete failed on scanner');

    $stmt = $mysqli->prepare("UPDATE device_commands SET status = ?, error_message = ? WHERE mode = 'DELETE' AND sensor_id = ? AND status = 'IN_PROGRESS' ORDER BY created_at ASC LIMIT 1");
    $stmt->bind_param('ssi', $status, $finalError, $sensorId);
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
