<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

require_method('POST');

try {
    $input = read_json_body();
    $userId = require_positive_int($input, 'student_id');

    $cancelCommandStmt = $mysqli->prepare("UPDATE device_commands SET status = 'FAILED', error_message = 'Registration cancelled by admin' WHERE user_id = ? AND mode = 'ENROLL' AND status IN ('PENDING', 'IN_PROGRESS')");
    $cancelCommandStmt->bind_param('i', $userId);
    $cancelCommandStmt->execute();

    $deleteFingerprintStmt = $mysqli->prepare('DELETE FROM fingerprints WHERE user_id = ?');
    $deleteFingerprintStmt->bind_param('i', $userId);
    $deleteFingerprintStmt->execute();

    $deleteUserStmt = $mysqli->prepare('DELETE FROM users WHERE id = ?');
    $deleteUserStmt->bind_param('i', $userId);
    $deleteUserStmt->execute();

    api_response(200, [
        'success' => true,
        'message' => 'Registration rolled back'
    ]);
} catch (Throwable $e) {
    api_response(500, [
        'success' => false,
        'message' => 'Failed to rollback registration',
        'error' => $e->getMessage()
    ]);
}
