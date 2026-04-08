<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

require_method('POST');

try {
    $input = read_json_body();

    $deferSave = isset($input['defer_save']) ? (bool)$input['defer_save'] : false;

    if ($deferSave) {
        $tempStudentId = 'TMP-' . strtoupper(bin2hex(random_bytes(6)));
        $tempFirstName = 'PENDING';
        $tempLastName = 'REGISTRATION';

        $stmt = $mysqli->prepare("INSERT INTO students (student_id, first_name, last_name, status) VALUES (?, ?, ?, 'inactive')");
        $stmt->bind_param('sss', $tempStudentId, $tempFirstName, $tempLastName);
        $stmt->execute();

        api_response(200, [
            'success' => true,
            'message' => 'Temporary registration created',
            'student_id' => (int)$mysqli->insert_id,
            'deferred' => true
        ]);
    }

    $studentNo = trim((string)($input['student_id'] ?? ''));
    $firstName = trim((string)($input['first_name'] ?? ''));
    $middleInitial = trim((string)($input['middle_initial'] ?? ''));
    $lastName = trim((string)($input['last_name'] ?? ''));
    $extension = trim((string)($input['extension'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $year = isset($input['year']) ? (int)$input['year'] : null;
    $section = trim((string)($input['section'] ?? ''));

    if ($studentNo === '' || $firstName === '' || $lastName === '' || $middleInitial === '' || $email === '' || $extension === '') {
        api_response(400, [
            'success' => false,
            'message' => 'Missing required fields: student_id, first_name, last_name, middle_initial, email, extension'
        ]);
    }

    $stmt = $mysqli->prepare("INSERT INTO students (student_id, first_name, middle_initial, last_name, extension, email, year, section, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
    $sectionVal = $section !== '' ? $section : null;
    $stmt->bind_param('ssssssis', $studentNo, $firstName, $middleInitial, $lastName, $extension, $email, $year, $sectionVal);
    $stmt->execute();

    api_response(200, [
        'success' => true,
        'message' => 'Student created',
        'student_id' => (int)$mysqli->insert_id
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
