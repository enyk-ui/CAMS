<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

require_method('GET');

try {
    if (!isset($mysqli) || $mysqli->connect_errno) {
        api_response(500, [
            'success' => false,
            'message' => 'Database connection failed'
        ]);
    }

    api_response(200, [
        'success' => true,
        'message' => 'API is healthy'
    ]);
} catch (Throwable $e) {
    api_response(500, [
        'success' => false,
        'message' => 'Health check failed',
        'error' => $e->getMessage()
    ]);
}