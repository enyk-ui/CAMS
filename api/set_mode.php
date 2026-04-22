<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

require_method('POST');

try {
    $input = read_json_body();
    $mode = strtolower(trim((string)($input['mode'] ?? '')));

    if ($mode !== 'registration' && $mode !== 'attendance') {
        api_response(400, [
            'success' => false,
            'message' => 'Invalid mode'
        ]);
    }

    if ($mode === 'attendance') {
        $cancelStmt = $mysqli->prepare("UPDATE device_commands SET status = 'FAILED', error_message = 'Mode switched to attendance' WHERE mode = 'ENROLL' AND status IN ('PENDING','IN_PROGRESS')");
        $cancelStmt->execute();
    }

    api_response(200, [
        'success' => true,
        'mode' => $mode
    ]);
} catch (Throwable $e) {
    api_response(500, [
        'success' => false,
        'message' => 'Failed to set mode',
        'error' => $e->getMessage()
    ]);
}

/*
 * © 2026 TambyTech.
 * This source code is proprietary and confidential.
 * Any unauthorized use, copying, modification, distribution, or disclosure is strictly prohibited.
 * All rights reserved.
 */