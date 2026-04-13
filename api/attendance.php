<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SchoolYearHelper.php';

require_method('POST');

const ATTENDANCE_TOGGLE_COOLDOWN_SECONDS = 30;
const ATTENDANCE_MIN_IN_TO_OUT_SECONDS = 300;
const ATTENDANCE_DEFAULT_START_TIME = '08:00:00';
const ATTENDANCE_DEFAULT_END_TIME = '17:00:00';
const ATTENDANCE_DEFAULT_LATE_THRESHOLD_MINUTES = 15;
const ATTENDANCE_WINDOW_BEFORE_IN_MINUTES = 30;
const ATTENDANCE_WINDOW_AFTER_OUT_MINUTES = 30;

function ensureAttendanceTableSchema(mysqli $mysqli): void
{
    $mysqli->query(
        "CREATE TABLE IF NOT EXISTS attendance (
            id INT(11) NOT NULL AUTO_INCREMENT,
            student_id INT(11) NOT NULL,
            attendance_date DATE NOT NULL,
            time_in_am TIME DEFAULT NULL,
            time_out_am TIME DEFAULT NULL,
            time_in_pm TIME DEFAULT NULL,
            time_out_pm TIME DEFAULT NULL,
            status ENUM('present','late','absent','excused') DEFAULT 'absent',
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_attendance_per_day (student_id, attendance_date),
            KEY idx_attendance_date (attendance_date),
            KEY idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $requiredColumns = [
        'id' => 'ALTER TABLE attendance ADD COLUMN id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST',
        'student_id' => 'ALTER TABLE attendance ADD COLUMN student_id INT(11) NOT NULL AFTER id',
        'section_id' => 'ALTER TABLE attendance ADD COLUMN section_id INT(11) DEFAULT NULL AFTER student_id',
        'attendance_date' => 'ALTER TABLE attendance ADD COLUMN attendance_date DATE NOT NULL AFTER student_id',
        'time_in_am' => 'ALTER TABLE attendance ADD COLUMN time_in_am TIME DEFAULT NULL AFTER attendance_date',
        'time_out_am' => 'ALTER TABLE attendance ADD COLUMN time_out_am TIME DEFAULT NULL AFTER time_in_am',
        'time_in_pm' => 'ALTER TABLE attendance ADD COLUMN time_in_pm TIME DEFAULT NULL AFTER time_out_am',
        'time_out_pm' => 'ALTER TABLE attendance ADD COLUMN time_out_pm TIME DEFAULT NULL AFTER time_in_pm',
        'status' => "ALTER TABLE attendance ADD COLUMN status ENUM('present','late','absent','excused') DEFAULT 'absent' AFTER time_out_pm",
        'notes' => 'ALTER TABLE attendance ADD COLUMN notes TEXT DEFAULT NULL AFTER status',
        'created_at' => 'ALTER TABLE attendance ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER notes',
        'updated_at' => 'ALTER TABLE attendance ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
    ];

    foreach ($requiredColumns as $column => $query) {
        $columnMeta = $mysqli->query("SHOW COLUMNS FROM attendance LIKE '{$column}'");
        if (!$columnMeta || $columnMeta->num_rows === 0) {
            $mysqli->query($query);
        }
    }

    $uniqueKeyMeta = $mysqli->query("SHOW INDEX FROM attendance WHERE Key_name = 'unique_attendance_per_day'");
    if ($uniqueKeyMeta && $uniqueKeyMeta->num_rows === 0) {
        $mysqli->query('ALTER TABLE attendance ADD UNIQUE KEY unique_attendance_per_day (student_id, attendance_date)');
    }
}

function ensureTeacherScheduleSchema(mysqli $mysqli): void
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

    $mysqli->query(
        "CREATE TABLE IF NOT EXISTS teacher_sections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            section_id INT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_teacher_section (teacher_id, section_id),
            UNIQUE KEY uniq_section_teacher (section_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function getStudentSchedule(mysqli $mysqli, ?int $sectionId, int $weekday): ?array
{
    if ($sectionId === null || $sectionId <= 0 || $weekday < 1 || $weekday > 7) {
        return null;
    }

    // Preferred: section-specific schedule rows.
    $stmt = $mysqli->prepare(
        'SELECT start_time, end_time, late_threshold_minutes
         FROM teacher_daily_schedules
         WHERE section_id = ? AND day_of_week = ?
         LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ii', $sectionId, $weekday);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Legacy fallback: teacher-day schedules joined via teacher_sections.
    if (!$row) {
        $stmt = $mysqli->prepare(
            'SELECT tds.start_time, tds.end_time, tds.late_threshold_minutes
             FROM teacher_sections ts
             JOIN teacher_daily_schedules tds ON tds.teacher_id = ts.teacher_id
             WHERE ts.section_id = ? AND tds.day_of_week = ?
             LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('ii', $sectionId, $weekday);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (!$row) {
        return null;
    }

    $timeIn = trim((string)($row['start_time'] ?? ''));
    $timeOut = trim((string)($row['end_time'] ?? ''));
    $lateThreshold = (int)($row['late_threshold_minutes'] ?? ATTENDANCE_DEFAULT_LATE_THRESHOLD_MINUTES);

    return [
        'time_in' => $timeIn !== '' ? $timeIn : ATTENDANCE_DEFAULT_START_TIME,
        'time_out' => $timeOut !== '' ? $timeOut : ATTENDANCE_DEFAULT_END_TIME,
        'late_threshold_minutes' => max(0, $lateThreshold),
    ];
}

function ensureAttendanceLogsTableSchema(mysqli $mysqli): void
{
    $mysqli->query(
        "CREATE TABLE IF NOT EXISTS attendance_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT(11) NOT NULL,
            device_id INT(11) NOT NULL,
            type ENUM('IN','OUT') NOT NULL,
            timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function ensureAttendanceLogIdAutoIncrement(mysqli $mysqli): void
{
    $idMeta = $mysqli->query("SHOW COLUMNS FROM attendance_logs LIKE 'id'");
    if (!$idMeta || $idMeta->num_rows === 0) {
        return;
    }

    $idRow = $idMeta->fetch_assoc();
    $isPrimary = !empty($idRow['Key']) && strtoupper((string)$idRow['Key']) === 'PRI';
    $isAutoIncrement = !empty($idRow['Extra']) && stripos((string)$idRow['Extra'], 'auto_increment') !== false;

    if ($isPrimary && $isAutoIncrement) {
        return;
    }

    if ($isPrimary) {
        $mysqli->query("ALTER TABLE attendance_logs MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
        return;
    }

    $hasTempPk = $mysqli->query("SHOW COLUMNS FROM attendance_logs LIKE 'attendance_pk'");
    if (!$hasTempPk || $hasTempPk->num_rows === 0) {
        $mysqli->query("ALTER TABLE attendance_logs ADD COLUMN attendance_pk BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST");
    }

    $mysqli->query("ALTER TABLE attendance_logs DROP COLUMN id");
    $mysqli->query("ALTER TABLE attendance_logs CHANGE COLUMN attendance_pk id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
}

function getLastAttendanceEvent(array $attendanceRow): array
{
    $eventFields = [
        'time_out_pm' => 'OUT (PM)',
        'time_in_pm' => 'IN (PM)',
        'time_out_am' => 'OUT (AM)',
        'time_in_am' => 'IN (AM)',
    ];

    foreach ($eventFields as $field => $label) {
        $value = $attendanceRow[$field] ?? null;
        if ($value !== null && $value !== '') {
            return ['time' => (string)$value, 'label' => $label];
        }
    }

    return ['time' => null, 'label' => null];
}

function canRecordOutEvent(string $currentDate, string $currentTime, ?string $inTime): bool
{
    if ($inTime === null || $inTime === '') {
        return false;
    }

    $inTimestamp = strtotime($currentDate . ' ' . $inTime);
    $nowTimestamp = strtotime($currentDate . ' ' . $currentTime);

    if ($inTimestamp === false || $nowTimestamp === false) {
        return false;
    }

    return ($nowTimestamp - $inTimestamp) >= ATTENDANCE_MIN_IN_TO_OUT_SECONDS;
}

function formatDisplayTime(?string $time): string
{
    if ($time === null || $time === '') {
        return 'Not yet';
    }

    $ts = strtotime($time);
    if ($ts === false) {
        return $time;
    }

    return date('h:i A', $ts);
}

function resolveScanWindow(string $anchorDate, array $schedule): ?array
{
    $timeIn = trim((string)($schedule['time_in'] ?? ''));
    $timeOut = trim((string)($schedule['time_out'] ?? ''));
    if ($timeIn === '' || $timeOut === '') {
        return null;
    }

    $timeInTs = strtotime($anchorDate . ' ' . $timeIn);
    $timeOutTs = strtotime($anchorDate . ' ' . $timeOut);
    if ($timeInTs === false || $timeOutTs === false) {
        return null;
    }

    // Support overnight schedules (e.g., 20:00 to 10:00 on the next day).
    $crossesMidnight = $timeOutTs <= $timeInTs;
    if ($crossesMidnight) {
        $timeOutTs = strtotime('+1 day', $timeOutTs);
        if ($timeOutTs === false) {
            return null;
        }
    }

    $windowStartTs = $timeInTs - (ATTENDANCE_WINDOW_BEFORE_IN_MINUTES * 60);
    $windowEndTs = $timeOutTs + (ATTENDANCE_WINDOW_AFTER_OUT_MINUTES * 60);

    return [
        'start_ts' => $windowStartTs,
        'end_ts' => $windowEndTs,
        'start_time' => date('H:i:s', $windowStartTs),
        'end_time' => date('H:i:s', $windowEndTs),
        'crosses_midnight' => $crossesMidnight,
    ];
}

function buildAttendanceSummary(array $attendanceRow, bool $isPmSession): array
{
    $timeIn = $isPmSession
        ? (($attendanceRow['time_in_pm'] ?? null) ?: null)
        : (($attendanceRow['time_in_am'] ?? null) ?: null);

    $timeOut = $isPmSession
        ? (($attendanceRow['time_out_pm'] ?? null) ?: null)
        : (($attendanceRow['time_out_am'] ?? null) ?: null);

    $totalHours = '-';
    if ($timeIn !== null && $timeOut !== null) {
        $inTs = strtotime($timeIn);
        $outTs = strtotime($timeOut);
        if ($inTs !== false && $outTs !== false && $outTs >= $inTs) {
            $hours = ($outTs - $inTs) / 3600;
            $totalHours = number_format($hours, 2) . ' hrs';
        }
    }

    return [
        'time_in' => formatDisplayTime($timeIn),
        'time_out' => formatDisplayTime($timeOut),
        'total_hours' => $totalHours,
    ];
}

function determineNotificationStatus(string $logType, string $currentDate, string $currentTime, array $schedule): string
{
    $eventTs = strtotime($currentDate . ' ' . $currentTime);
    if ($eventTs === false) {
        return 'Present';
    }

    if ($logType === 'IN') {
        $baseStartTs = strtotime($currentDate . ' ' . (string)$schedule['time_in']);
        $lateThresholdTs = $baseStartTs !== false
            ? ($baseStartTs + (max(0, (int)$schedule['late_threshold_minutes']) * 60))
            : false;
        if ($lateThresholdTs !== false && $eventTs > $lateThresholdTs) {
            return 'Late';
        }
        return 'Present';
    }

    if ($logType === 'OUT') {
        $timeoutThresholdTs = strtotime($currentDate . ' ' . (string)$schedule['time_out']);
        if ($timeoutThresholdTs !== false && $eventTs >= $timeoutThresholdTs) {
            return 'Timeout';
        }
        return 'Present';
    }

    return 'Present';
}

try {
    $transactionStarted = false;
    $input = read_json_body();

    $sensorId = require_positive_int($input, 'sensor_id');
    $deviceId = require_positive_int($input, 'device_id');

    ensureAttendanceTableSchema($mysqli);
    ensureAttendanceLogsTableSchema($mysqli);
    ensureAttendanceLogIdAutoIncrement($mysqli);
    ensureTeacherScheduleSchema($mysqli);
    SchoolYearHelper::ensureSchoolYearSupport($mysqli);

    $fingerprintStmt = $mysqli->prepare('SELECT student_id FROM fingerprints WHERE sensor_id = ? LIMIT 1');
    $fingerprintStmt->bind_param('i', $sensorId);
    $fingerprintStmt->execute();
    $fingerprint = $fingerprintStmt->get_result()->fetch_assoc();

    if (!$fingerprint) {
        api_response(200, [
            'success' => false,
            'message' => 'Not enrolled',
            'error' => 'Invalid sensor_id',
            'sensor_id' => $sensorId,
            'device_id' => $deviceId
        ]);
    }

    $studentId = (int)$fingerprint['student_id'];

    $studentStmt = $mysqli->prepare('SELECT first_name, last_name, section_id FROM students WHERE id = ? LIMIT 1');
    $studentStmt->bind_param('i', $studentId);
    $studentStmt->execute();
    $student = $studentStmt->get_result()->fetch_assoc();
    $studentName = $student ? trim($student['first_name'] . ' ' . $student['last_name']) : 'Student';
    $sectionId = $student && isset($student['section_id']) ? (int)$student['section_id'] : 0;

    $currentDate = date('Y-m-d');
    $currentTime = date('H:i:s');
    $isPmSession = ((int)date('G')) >= 12;
    $weekday = (int)date('N');

    $activeSchoolYear = SchoolYearHelper::getEffectiveSchoolYearRange($mysqli);
    $schoolYearStart = trim((string)($activeSchoolYear['start_date'] ?? ''));
    $schoolYearEnd = trim((string)($activeSchoolYear['end_date'] ?? ''));
    if ($schoolYearStart !== '' && $schoolYearEnd !== '' && ($currentDate < $schoolYearStart || $currentDate > $schoolYearEnd)) {
        api_response(200, [
            'success' => false,
            'message' => 'Attendance not allowed: outside school year range',
            'student_id' => $studentId,
            'student_name' => $studentName,
            'section_id' => $sectionId > 0 ? $sectionId : null,
            'school_year' => [
                'label' => (string)($activeSchoolYear['label'] ?? ''),
                'start_date' => $schoolYearStart,
                'end_date' => $schoolYearEnd,
            ],
            'current_date' => $currentDate,
            'sensor_id' => $sensorId,
            'device_id' => $deviceId
        ]);
    }

    $schedule = getStudentSchedule($mysqli, $sectionId > 0 ? $sectionId : null, $weekday);
    $scanWindow = $schedule !== null ? resolveScanWindow($currentDate, $schedule) : null;

    // If there is no usable schedule for today, allow spillover from yesterday's overnight schedule.
    if ($schedule === null || $scanWindow === null) {
        $previousWeekday = $weekday === 1 ? 7 : ($weekday - 1);
        $previousDate = date('Y-m-d', strtotime($currentDate . ' -1 day'));
        $previousSchedule = getStudentSchedule($mysqli, $sectionId > 0 ? $sectionId : null, $previousWeekday);
        $previousWindow = $previousSchedule !== null ? resolveScanWindow($previousDate, $previousSchedule) : null;
        $eventTs = strtotime($currentDate . ' ' . $currentTime);

        $canUsePreviousOvernight =
            $previousSchedule !== null &&
            $previousWindow !== null &&
            !empty($previousWindow['crosses_midnight']) &&
            $eventTs !== false &&
            $eventTs >= (int)$previousWindow['start_ts'] &&
            $eventTs <= (int)$previousWindow['end_ts'];

        if ($canUsePreviousOvernight) {
            $schedule = $previousSchedule;
            $scanWindow = $previousWindow;
        }
    }

    if ($schedule === null || $scanWindow === null) {
        api_response(200, [
            'success' => false,
            'message' => 'Attendance not allowed: no schedule configured for this section today',
            'student_id' => $studentId,
            'student_name' => $studentName,
            'section_id' => $sectionId > 0 ? $sectionId : null,
            'weekday' => $weekday,
            'sensor_id' => $sensorId,
            'device_id' => $deviceId
        ]);
    }

    $eventTs = strtotime($currentDate . ' ' . $currentTime);
    $outsideWindow = $eventTs === false || $eventTs < (int)$scanWindow['start_ts'] || $eventTs > (int)$scanWindow['end_ts'];
    if ($outsideWindow) {
        api_response(200, [
            'success' => false,
            'message' => 'Attendance not allowed at this time',
            'student_id' => $studentId,
            'student_name' => $studentName,
            'section_id' => $sectionId > 0 ? $sectionId : null,
            'weekday' => $weekday,
            'allowed_window' => [
                'start_time' => (string)$scanWindow['start_time'],
                'end_time' => (string)$scanWindow['end_time']
            ],
            'sensor_id' => $sensorId,
            'device_id' => $deviceId
        ]);
    }

    $attendanceStmt = $mysqli->prepare('SELECT * FROM attendance WHERE student_id = ? AND attendance_date = ? LIMIT 1');
    $attendanceStmt->bind_param('is', $studentId, $currentDate);
    $attendanceStmt->execute();
    $attendance = $attendanceStmt->get_result()->fetch_assoc();

    if (!$attendance) {
        $createAttendanceStmt = $mysqli->prepare('INSERT INTO attendance (student_id, section_id, attendance_date, status) VALUES (?, ?, ?, ?)');
        $defaultStatus = 'absent';
        $createAttendanceStmt->bind_param('iiss', $studentId, $sectionId, $currentDate, $defaultStatus);
        $createAttendanceStmt->execute();

        $attendanceStmt = $mysqli->prepare('SELECT * FROM attendance WHERE id = ? LIMIT 1');
        $attendanceId = (int)$mysqli->insert_id;
        $attendanceStmt->bind_param('i', $attendanceId);
        $attendanceStmt->execute();
        $attendance = $attendanceStmt->get_result()->fetch_assoc();
    }

    $lastEvent = getLastAttendanceEvent($attendance ?: []);
    if ($lastEvent['time'] !== null) {
        $lastTimestamp = strtotime($currentDate . ' ' . (string)$lastEvent['time']);
        $secondsSinceLastEvent = time() - $lastTimestamp;
        if ($secondsSinceLastEvent >= 0 && $secondsSinceLastEvent < ATTENDANCE_TOGGLE_COOLDOWN_SECONDS) {
            api_response(200, [
                'success' => true,
                'message' => 'Already timed ' . $lastEvent['label'],
                'student_id' => $studentId,
                'student_name' => $studentName,
                'type' => str_contains((string)$lastEvent['label'], 'OUT') ? 'OUT' : 'IN',
                'time' => (string)$lastEvent['time'],
                'sensor_id' => $sensorId,
                'device_id' => $deviceId,
                'duplicate' => true
            ]);
        }
    }

    $actionLabel = '';
    $logType = '';
    $updateSql = '';
    $updateParams = [];
    $updateTypes = '';
    if (!$isPmSession) {
        if (empty($attendance['time_in_am'])) {
            $actionLabel = 'IN (AM)';
            $logType = 'IN';
            $updateSql = 'UPDATE attendance SET time_in_am = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?';
            $updateTypes = 'ssi';
            $status = determineNotificationStatus('IN', $currentDate, $currentTime, $schedule) === 'Late' ? 'late' : 'present';
            $updateParams = [$currentTime, $status, (int)$attendance['id']];
        } elseif (empty($attendance['time_out_am']) && canRecordOutEvent($currentDate, $currentTime, $attendance['time_in_am'] ?? null)) {
            $actionLabel = 'OUT (AM)';
            $logType = 'OUT';
            $updateSql = 'UPDATE attendance SET time_out_am = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?';
            $updateTypes = 'si';
            $updateParams = [$currentTime, (int)$attendance['id']];
        } elseif (empty($attendance['time_out_am'])) {
            api_response(200, [
                'success' => true,
                'message' => 'Already timed IN (AM)',
                'student_id' => $studentId,
                'student_name' => $studentName,
                'type' => 'IN',
                'time' => (string)($attendance['time_in_am'] ?? $currentTime),
                'sensor_id' => $sensorId,
                'device_id' => $deviceId,
                'duplicate' => true
            ]);
        }
    } else {
        if (empty($attendance['time_in_pm'])) {
            $actionLabel = 'IN (PM)';
            $logType = 'IN';
            $updateSql = 'UPDATE attendance SET time_in_pm = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?';
            $updateTypes = 'ssi';
            $status = determineNotificationStatus('IN', $currentDate, $currentTime, $schedule) === 'Late' ? 'late' : 'present';
            $updateParams = [$currentTime, $status, (int)$attendance['id']];
        } elseif (empty($attendance['time_out_pm']) && canRecordOutEvent($currentDate, $currentTime, $attendance['time_in_pm'] ?? null)) {
            $actionLabel = 'OUT (PM)';
            $logType = 'OUT';
            $updateSql = 'UPDATE attendance SET time_out_pm = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?';
            $updateTypes = 'si';
            $updateParams = [$currentTime, (int)$attendance['id']];
        } elseif (empty($attendance['time_out_pm'])) {
            api_response(200, [
                'success' => true,
                'message' => 'Already timed IN (PM)',
                'student_id' => $studentId,
                'student_name' => $studentName,
                'type' => 'IN',
                'time' => (string)($attendance['time_in_pm'] ?? $currentTime),
                'sensor_id' => $sensorId,
                'device_id' => $deviceId,
                'duplicate' => true
            ]);
        }
    }

    if ($updateSql === '') {
        $alreadyState = $isPmSession ? 'OUT (PM)' : 'OUT (AM)';
        api_response(200, [
            'success' => true,
            'message' => 'Already timed ' . $alreadyState,
            'student_id' => $studentId,
            'student_name' => $studentName,
            'type' => 'OUT',
            'time' => $currentTime,
            'sensor_id' => $sensorId,
            'device_id' => $deviceId,
            'duplicate' => true
        ]);
    }

    $mysqli->begin_transaction();
    $transactionStarted = true;

    $updateStmt = $mysqli->prepare($updateSql);
    $updateStmt->bind_param($updateTypes, ...$updateParams);
    $updateStmt->execute();

    $insertStmt = $mysqli->prepare('INSERT INTO attendance_logs (student_id, device_id, type) VALUES (?, ?, ?)');
    $insertStmt->bind_param('iis', $studentId, $deviceId, $logType);
    $insertStmt->execute();

    $mysqli->commit();
    $transactionStarted = false;

    $attendanceRefreshStmt = $mysqli->prepare('SELECT * FROM attendance WHERE id = ? LIMIT 1');
    $attendanceId = (int)$attendance['id'];
    $attendanceRefreshStmt->bind_param('i', $attendanceId);
    $attendanceRefreshStmt->execute();
    $updatedAttendance = $attendanceRefreshStmt->get_result()->fetch_assoc() ?: $attendance;

    $notificationStatus = determineNotificationStatus($logType, $currentDate, $currentTime, $schedule);
    $attendanceSummary = buildAttendanceSummary($updatedAttendance, $isPmSession);

    api_response(200, [
        'success' => true,
        'message' => 'Attendance recorded',
        'student_id' => $studentId,
        'student_name' => $studentName,
        'section_id' => $sectionId > 0 ? $sectionId : null,
        'type' => $logType,
        'attendance_action' => $actionLabel,
        'time' => $currentTime,
        'status' => $notificationStatus,
        'outside_window' => $outsideWindow,
        'attendance_summary' => $attendanceSummary,
        'schedule' => [
            'day' => $weekday,
            'time_in' => $schedule['time_in'],
            'time_out' => $schedule['time_out'],
            'late_threshold_minutes' => (int)$schedule['late_threshold_minutes'],
        ],
        'sensor_id' => $sensorId,
        'device_id' => $deviceId
    ]);
} catch (Throwable $e) {
    if (!empty($transactionStarted)) {
        $mysqli->rollback();
    }
    api_response(500, [
        'success' => false,
        'message' => 'Failed to record attendance',
        'error' => $e->getMessage()
    ]);
}

/*
 * � 2026 TambyTech.
 * This source code is proprietary and confidential.
 * Any unauthorized use, copying, modification, distribution, or disclosure is strictly prohibited.
 * All rights reserved.
 */