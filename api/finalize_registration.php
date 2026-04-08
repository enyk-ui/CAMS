<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

require_method('POST');

try {
    $input = read_json_body();

    $userId = require_positive_int($input, 'student_id');
    $studentNo = trim((string)($input['student_no'] ?? $input['student_id_text'] ?? ''));
    $firstName = trim((string)($input['first_name'] ?? ''));
    $middleInitial = trim((string)($input['middle_initial'] ?? ''));
    $lastName = trim((string)($input['last_name'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));

    if ($studentNo === '' || $firstName === '' || $lastName === '' || $email === '') {
        api_response(400, [
            'success' => false,
            'message' => 'Missing required fields for finalization'
        ]);
    }

    $fullName = trim($firstName . ' ' . ($middleInitial !== '' ? $middleInitial . ' ' : '') . $lastName);

    $existsStmt = $mysqli->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
    $existsStmt->bind_param('i', $userId);
    $existsStmt->execute();
    if (!$existsStmt->get_result()->fetch_assoc()) {
        api_response(404, [
            'success' => false,
            'message' => 'Temporary registration user not found'
        ]);
    }

    $updateStmt = $mysqli->prepare("UPDATE users SET student_no = ?, full_name = ?, email = ?, status = 'active' WHERE id = ?");
    $updateStmt->bind_param('sssi', $studentNo, $fullName, $email, $userId);
    $updateStmt->execute();

    api_response(200, [
        'success' => true,
        'message' => 'Student record finalized',
        'student_id' => $userId
    ]);
} catch (Throwable $e) {
    $message = $e->getMessage();
    if (stripos($message, 'Duplicate entry') !== false) {
        $message = 'Student ID or email already exists';
    }

    api_response(500, [
        'success' => false,
        'message' => $message
    ]);
}
