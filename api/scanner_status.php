<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

require_method('GET');

try {
    $stmt = $mysqli->prepare('SELECT id, name, last_seen, is_active FROM devices ORDER BY last_seen DESC LIMIT 1');
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        api_response(200, [
            'success' => true,
            'scanner' => [
                'online' => false,
                'message' => 'No heartbeat received yet'
            ]
        ]);
    }

    $lastSeen = strtotime((string)$row['last_seen']);
    $online = (time() - $lastSeen) <= 60;

    api_response(200, [
        'success' => true,
        'scanner' => [
            'online' => ($row['is_active'] ? $online : false),
            'last_seen' => $row['last_seen'],
            'device_id' => (int)$row['id'],
            'name' => $row['name'] ?? 'device',
            'message' => $online ? 'Scanner is online' : 'Last heartbeat is stale'
        ]
    ]);
} catch (Throwable $e) {
    api_response(500, [
        'success' => false,
        'message' => 'Failed to get scanner status',
        'error' => $e->getMessage()
    ]);
}

/*
 * © 2026 TambyTech.
 * This source code is proprietary and confidential.
 * Any unauthorized use, copying, modification, distribution, or disclosure is strictly prohibited.
 * All rights reserved.
 */