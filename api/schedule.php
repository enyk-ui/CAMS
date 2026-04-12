<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

function ensureScheduleSchema(mysqli $mysqli): void
{
    $mysqli->query(
        "CREATE TABLE IF NOT EXISTS teacher_daily_schedules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            section_id INT DEFAULT NULL,
            day_of_week TINYINT UNSIGNED NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            late_threshold_minutes INT UNSIGNED NOT NULL DEFAULT 15,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_teacher_section_day (teacher_id, section_id, day_of_week)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $sectionColumn = $mysqli->query("SHOW COLUMNS FROM teacher_daily_schedules LIKE 'section_id'");
    if ($sectionColumn && $sectionColumn->num_rows === 0) {
        $mysqli->query("ALTER TABLE teacher_daily_schedules ADD COLUMN section_id INT DEFAULT NULL AFTER teacher_id");
    }

    $oldUnique = $mysqli->query("SHOW INDEX FROM teacher_daily_schedules WHERE Key_name = 'uniq_teacher_day'");
    if ($oldUnique && $oldUnique->num_rows > 0) {
        $mysqli->query("ALTER TABLE teacher_daily_schedules DROP INDEX uniq_teacher_day");
    }

    $newUnique = $mysqli->query("SHOW INDEX FROM teacher_daily_schedules WHERE Key_name = 'uniq_teacher_section_day'");
    if ($newUnique && $newUnique->num_rows === 0) {
        $mysqli->query("ALTER TABLE teacher_daily_schedules ADD UNIQUE KEY uniq_teacher_section_day (teacher_id, section_id, day_of_week)");
    }
}

function normalizeDayToIndex($value): int
{
    $days = [
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
    ];

    if (is_numeric($value)) {
        $day = (int)$value;
        if ($day >= 1 && $day <= 5) {
            return $day;
        }
    }

    $asText = strtolower(trim((string)$value));
    if (isset($days[$asText])) {
        return $days[$asText];
    }

    api_response(400, [
        'success' => false,
        'message' => 'day must be Monday-Friday or 1-5'
    ]);
}

function resolveTeacherFilter(): ?int
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $sessionRole = (string)($_SESSION['role'] ?? '');
    if ($sessionRole === 'teacher') {
        return (int)($_SESSION['admin_id'] ?? 0);
    }

    return null;
}

ensureScheduleSchema($mysqli);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $teacherId = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;
    $sectionId = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
    $forcedTeacherId = resolveTeacherFilter();

    if ($forcedTeacherId !== null) {
        $teacherId = $forcedTeacherId;
    }

    if ($teacherId <= 0) {
        api_response(400, [
            'success' => false,
            'message' => 'Missing teacher_id'
        ]);
    }

    if ($sectionId > 0) {
        $stmt = $mysqli->prepare(
            'SELECT teacher_id, section_id, day_of_week, start_time AS time_in, end_time AS time_out, late_threshold_minutes
             FROM teacher_daily_schedules
             WHERE teacher_id = ? AND section_id = ?
             ORDER BY day_of_week ASC'
        );
        $stmt->bind_param('ii', $teacherId, $sectionId);
    } else {
        $stmt = $mysqli->prepare(
            'SELECT teacher_id, section_id, day_of_week, start_time AS time_in, end_time AS time_out, late_threshold_minutes
             FROM teacher_daily_schedules
             WHERE teacher_id = ?
             ORDER BY day_of_week ASC'
        );
        $stmt->bind_param('i', $teacherId);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'teacher_id' => (int)$row['teacher_id'],
            'section_id' => isset($row['section_id']) ? (int)$row['section_id'] : null,
            'day' => (int)$row['day_of_week'],
            'time_in' => (string)$row['time_in'],
            'time_out' => (string)$row['time_out'],
            'late_threshold_minutes' => (int)$row['late_threshold_minutes'],
        ];
    }

    api_response(200, [
        'success' => true,
        'schedules' => $rows
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = read_json_body();

    $teacherId = require_positive_int($input, 'teacher_id');
    $sectionId = require_positive_int($input, 'section_id');
    $forcedTeacherId = resolveTeacherFilter();
    if ($forcedTeacherId !== null && $forcedTeacherId !== $teacherId) {
        api_response(403, [
            'success' => false,
            'message' => 'Teachers can only update their own schedules'
        ]);
    }

    $day = normalizeDayToIndex($input['day'] ?? null);
    $timeIn = trim((string)($input['time_in'] ?? ''));
    $timeOut = trim((string)($input['time_out'] ?? ''));
    $lateThresholdMinutes = isset($input['late_threshold_minutes']) ? (int)$input['late_threshold_minutes'] : 15;

    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $timeIn) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $timeOut)) {
        api_response(400, [
            'success' => false,
            'message' => 'time_in and time_out must be valid HH:MM or HH:MM:SS'
        ]);
    }

    if ($lateThresholdMinutes < 0) {
        $lateThresholdMinutes = 0;
    }

    $stmt = $mysqli->prepare(
        "INSERT INTO teacher_daily_schedules (teacher_id, section_id, day_of_week, start_time, end_time, late_threshold_minutes)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             start_time = VALUES(start_time),
             end_time = VALUES(end_time),
             late_threshold_minutes = VALUES(late_threshold_minutes),
             updated_at = CURRENT_TIMESTAMP"
    );
    $stmt->bind_param('iiissi', $teacherId, $sectionId, $day, $timeIn, $timeOut, $lateThresholdMinutes);
    $stmt->execute();

    api_response(200, [
        'success' => true,
        'message' => 'Schedule saved',
        'teacher_id' => $teacherId,
        'section_id' => $sectionId,
        'day' => $day,
        'time_in' => $timeIn,
        'time_out' => $timeOut,
        'late_threshold_minutes' => $lateThresholdMinutes
    ]);
}

api_response(405, [
    'success' => false,
    'message' => 'Method not allowed'
]);
