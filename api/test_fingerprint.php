<?php
/**
 * Test endpoint for fingerprint API
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'success' => true,
        'message' => 'Fingerprint API is accessible',
        'timestamp' => date('Y-m-d H:i:s'),
        'server_info' => [
            'php_version' => PHP_VERSION,
            'mysqli_available' => extension_loaded('mysqli'),
            'session_support' => function_exists('session_start')
        ]
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    echo json_encode([
        'success' => true,
        'message' => 'POST request received',
        'received_data' => $input,
        'json_error' => json_last_error_msg()
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid method']);
?>