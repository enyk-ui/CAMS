<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

require_method('GET');

// Shared key for scanner-originated health checks used during server discovery.
$expectedDeviceKey = getenv('CAMS_DISCOVERY_KEY') ?: 'CAMS_ESP8266';
$providedDeviceKey = isset($_GET['device_key']) ? trim((string)$_GET['device_key']) : '';

if ($providedDeviceKey === '' || !hash_equals((string)$expectedDeviceKey, $providedDeviceKey)) {
    api_response(403, [
        'success' => false,
        'message' => 'Invalid discovery key'
    ]);
}

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