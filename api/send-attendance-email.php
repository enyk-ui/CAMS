<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../app/services/Mailer.php';

/**
 * @param array<string,mixed> $input
 * @return array<string,mixed>
 */
function send_attendance_email_via_api(array $input): array
{
    $email = trim((string)($input['email'] ?? ''));
    $name = trim((string)($input['name'] ?? ''));
    $type = strtolower(trim((string)($input['type'] ?? '')));
    $time = trim((string)($input['time'] ?? ''));
    $status = trim((string)($input['status'] ?? ''));
    $attendanceSummary = $input['attendance_summary'] ?? null;

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid or missing email'];
    }

    if ($name === '') {
        return ['success' => false, 'message' => 'Missing required field: name'];
    }

    if (!in_array($type, ['time_in', 'time_out'], true)) {
        return ['success' => false, 'message' => 'Invalid type. Allowed: time_in, time_out'];
    }

    if ($time === '' || strtotime($time) === false) {
        return ['success' => false, 'message' => 'Invalid or missing time'];
    }

    if ($status === '') {
        return ['success' => false, 'message' => 'Missing required field: status'];
    }

    if (!is_array($attendanceSummary)) {
        return ['success' => false, 'message' => 'attendance_summary must be an object'];
    }

    $timeIn = trim((string)($attendanceSummary['time_in'] ?? ''));
    $timeOut = trim((string)($attendanceSummary['time_out'] ?? ''));
    $totalHours = trim((string)($attendanceSummary['total_hours'] ?? ''));

    if ($timeIn === '' || $timeOut === '' || $totalHours === '') {
        return [
            'success' => false,
            'message' => 'attendance_summary requires: time_in, time_out, total_hours'
        ];
    }

    try {
        $mailer = new Mailer();
        $sent = $mailer->sendAttendanceNotification([
            'email' => $email,
            'name' => $name,
            'type' => $type,
            'time' => $time,
            'status' => $status,
            'attendance_summary' => [
                'time_in' => $timeIn,
                'time_out' => $timeOut,
                'total_hours' => $totalHours,
            ],
        ]);

        if (!$sent) {
            return ['success' => false, 'message' => 'Failed to send email'];
        }

        return ['success' => true, 'message' => 'Email sent'];
    } catch (Throwable $e) {
        error_log('send-attendance-email error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to send email'];
    }
}

if (basename((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === basename(__FILE__)) {
    require_method('POST');
    $input = read_json_body();
    $result = send_attendance_email_via_api($input);

    $statusCode = 200;
    if (($result['success'] ?? false) !== true) {
        $statusCode = 400;
    }

    api_response($statusCode, $result);
}

/*
 * © 2026 TambyTech.
 * This source code is proprietary and confidential.
 * Any unauthorized use, copying, modification, distribution, or disclosure is strictly prohibited.
 * All rights reserved.
 */