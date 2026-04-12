<?php
/**
 * Attendance Logs Page
 * View, filter, and export attendance records
 */

session_start();
ob_start();
require_once '../config/db.php';
require_once '../helpers/SchoolYearHelper.php';
require '../includes/header.php';

SchoolYearHelper::ensureSchoolYearSupport($mysqli);
$activeSchoolYear = SchoolYearHelper::getEffectiveSchoolYearRange($mysqli);
$schoolYears = SchoolYearHelper::getAllSchoolYears($mysqli);

function normalizeDateValue($value, $fallback)
{
    if ($value === null || $value === '') {
        return $fallback;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    return ($date && $date->format('Y-m-d') === $value) ? $value : $fallback;
}

function tableExists($mysqli, $tableName)
{
    $stmt = $mysqli->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $tableName);
    $stmt->execute();
    $result = $stmt->get_result();
    return (bool) ($result && $result->num_rows > 0);
}

function columnExists($mysqli, $tableName, $columnName)
{
    $stmt = $mysqli->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $tableName, $columnName);
    $stmt->execute();
    $result = $stmt->get_result();
    return (bool) ($result && $result->num_rows > 0);
}

function formatAttendanceName(array $row): string
{
    $first = trim((string) ($row['first_name'] ?? ''));
    $middle = trim((string) ($row['middle_initial'] ?? ''));
    $last = trim((string) ($row['last_name'] ?? ''));
    $ext = trim((string) ($row['extension'] ?? ''));

    $name = $last;
    if ($first !== '') {
        $name .= ($name !== '' ? ', ' : '') . $first;
    }
    if ($middle !== '') {
        $name .= ' ' . strtoupper(substr($middle, 0, 1)) . '.';
    }
    if ($ext !== '') {
        $name .= ' ' . $ext;
    }

    return trim($name);
}

function getAttendanceThresholds(mysqli $mysqli): array
{
    $settings = [
        'am_start_time' => '08:00:00',
        'am_end_time' => '12:00:00',
        'pm_start_time' => '13:00:00',
        'pm_end_time' => '17:00:00',
        'late_threshold_minutes' => 15,
    ];

    if (!tableExists($mysqli, 'settings')) {
        return $settings;
    }

    $result = $mysqli->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('am_start_time', 'am_end_time', 'pm_start_time', 'pm_end_time', 'late_threshold_minutes')");
    if (!$result) {
        return $settings;
    }

    while ($row = $result->fetch_assoc()) {
        if (($row['setting_key'] ?? '') === 'am_start_time' && !empty($row['setting_value'])) {
            $settings['am_start_time'] = (string)$row['setting_value'];
        }
        if (($row['setting_key'] ?? '') === 'am_end_time' && !empty($row['setting_value'])) {
            $settings['am_end_time'] = (string)$row['setting_value'];
        }
        if (($row['setting_key'] ?? '') === 'pm_start_time' && !empty($row['setting_value'])) {
            $settings['pm_start_time'] = (string)$row['setting_value'];
        }
        if (($row['setting_key'] ?? '') === 'pm_end_time' && !empty($row['setting_value'])) {
            $settings['pm_end_time'] = (string)$row['setting_value'];
        }
        if (($row['setting_key'] ?? '') === 'late_threshold_minutes' && is_numeric($row['setting_value'])) {
            $settings['late_threshold_minutes'] = max(0, (int)$row['setting_value']);
        }
    }

    return $settings;
}

function getTimeTardinessLabel(?string $timeValue, string $session, string $eventType, array $thresholds): string
{
    if ($timeValue === null || $timeValue === '') {
        return '';
    }

    $thresholdSecs = max(0, (int)$thresholds['late_threshold_minutes']) * 60;
    $eventTs = strtotime($timeValue);
    if ($eventTs === false) {
        return '';
    }

    if ($eventType === 'in') {
        $baseline = $session === 'am' ? (string)$thresholds['am_start_time'] : (string)$thresholds['pm_start_time'];
        $baselineTs = strtotime($baseline);
        if ($baselineTs === false) {
            return '';
        }

        if ($eventTs < $baselineTs) {
            return 'Early';
        }
        if ($eventTs > ($baselineTs + $thresholdSecs)) {
            return 'Late';
        }

        return '';
    }

    $baseline = $session === 'am' ? (string)$thresholds['am_end_time'] : (string)$thresholds['pm_end_time'];
    $baselineTs = strtotime($baseline);
    if ($baselineTs === false) {
        return '';
    }

    if ($eventTs < ($baselineTs - $thresholdSecs)) {
        return 'Early';
    }
    if ($eventTs > ($baselineTs + $thresholdSecs)) {
        return 'Late';
    }

    return '';
}

function formatYearSection(array $row): string
{
    $year = trim((string)($row['year_level'] ?? ''));
    $section = trim((string)($row['section'] ?? ''));

    if ($year !== '' && $section !== '') {
        return $year . ' - ' . $section;
    }
    if ($year !== '') {
        return $year;
    }
    if ($section !== '') {
        return $section;
    }

    return '-';
}

function inferAttendanceRemark(array $row, array $thresholds): string
{
    $hasInAm = !empty($row['time_in_am']);
    $hasOutAm = !empty($row['time_out_am']);
    $hasInPm = !empty($row['time_in_pm']);
    $hasOutPm = !empty($row['time_out_pm']);

    $hasAnyRecord = $hasInAm || $hasOutAm || $hasInPm || $hasOutPm;
    if (!$hasAnyRecord) {
        return 'absent';
    }

    $amComplete = $hasInAm && $hasOutAm;
    $pmComplete = $hasInPm && $hasOutPm;
    $hasIncompletePair = ($hasInAm xor $hasOutAm) || ($hasInPm xor $hasOutPm);

    if ($hasIncompletePair) {
        return 'inc_att';
    }

    if ($amComplete && $pmComplete) {
        return 'present';
    }

    if ($amComplete xor $pmComplete) {
        return 'partials';
    }

    return 'inc_att';
}

function sortAttendanceLogs(array &$logs, string $sortBy, string $sortDir, array $thresholds): void
{
    $direction = strtolower($sortDir) === 'asc' ? 1 : -1;

    usort($logs, function (array $a, array $b) use ($sortBy, $direction, $thresholds): int {
        $getName = static function (array $row): string {
            return strtolower(formatAttendanceName($row));
        };

        $aRemark = inferAttendanceRemark($a, $thresholds);
        $bRemark = inferAttendanceRemark($b, $thresholds);

        switch ($sortBy) {
            case 'name':
                $cmp = strcasecmp($getName($a), $getName($b));
                break;
            case 'section':
                $cmp = strcasecmp((string)($a['year_level'] ?? ''), (string)($b['year_level'] ?? ''));
                if ($cmp === 0) {
                    $cmp = strcasecmp((string)($a['section'] ?? ''), (string)($b['section'] ?? ''));
                }
                if ($cmp === 0) {
                    $cmp = strcasecmp($getName($a), $getName($b));
                }
                break;
            case 'remarks':
                $cmp = strcasecmp($aRemark, $bRemark);
                if ($cmp === 0) {
                    $cmp = strcasecmp($getName($a), $getName($b));
                }
                break;
            case 'date':
            default:
                $cmp = strcmp((string)($a['attendance_date'] ?? ''), (string)($b['attendance_date'] ?? ''));
                if ($cmp === 0) {
                    $cmp = strcasecmp($getName($a), $getName($b));
                }
                break;
        }

        return $cmp * $direction;
    });
}

function fetchSections(mysqli $mysqli): array
{
    if (!tableExists($mysqli, 'students') || !columnExists($mysqli, 'students', 'section')) {
        return [];
    }

    $sections = [];
    $result = $mysqli->query("SELECT DISTINCT section FROM students WHERE section IS NOT NULL AND TRIM(section) <> '' ORDER BY section ASC");
    if (!$result) {
        return $sections;
    }

    while ($row = $result->fetch_assoc()) {
        $sections[] = (string)$row['section'];
    }

    return $sections;
}

function fetchYears(mysqli $mysqli): array
{
    if (!tableExists($mysqli, 'students') || !columnExists($mysqli, 'students', 'year')) {
        return [];
    }

    $years = [];
    $result = $mysqli->query("SELECT DISTINCT CAST(year AS CHAR) AS year_level FROM students WHERE year IS NOT NULL AND CAST(year AS CHAR) <> '' ORDER BY year ASC");
    if (!$result) {
        return $years;
    }

    while ($row = $result->fetch_assoc()) {
        $years[] = (string)$row['year_level'];
    }

    return $years;
}

function buildTeacherSectionMapBySchoolYear(mysqli $mysqli): array
{
    $map = [];
    if (!tableExists($mysqli, 'users')) {
        return $map;
    }

    $hasRole = columnExists($mysqli, 'users', 'role');
    $hasStatus = columnExists($mysqli, 'users', 'status');
    $hasSchoolYear = columnExists($mysqli, 'users', 'school_year_label');
    $hasYear = columnExists($mysqli, 'users', 'year_level');
    $hasSection = columnExists($mysqli, 'users', 'section');

    if (!($hasRole && $hasStatus && $hasYear && $hasSection)) {
        return $map;
    }

    $selectSchoolYear = $hasSchoolYear ? 'COALESCE(NULLIF(TRIM(school_year_label), ""), "__ALL__") AS school_year_label' : '"__ALL__" AS school_year_label';
    $sql = "
        SELECT {$selectSchoolYear}, CAST(year_level AS CHAR) AS year_level, TRIM(section) AS section
        FROM users
        WHERE role = 'teacher'
          AND status = 'active'
          AND year_level IS NOT NULL
          AND section IS NOT NULL
          AND TRIM(section) <> ''
        ORDER BY school_year_label ASC, year_level ASC, section ASC
    ";

    $result = $mysqli->query($sql);
    if (!$result) {
        return $map;
    }

    while ($row = $result->fetch_assoc()) {
        $sy = trim((string)($row['school_year_label'] ?? '__ALL__'));
        $year = trim((string)($row['year_level'] ?? ''));
        $section = trim((string)($row['section'] ?? ''));
        if ($year === '' || $section === '') {
            continue;
        }

        if (!isset($map[$sy])) {
            $map[$sy] = [];
        }
        if (!isset($map[$sy][$year])) {
            $map[$sy][$year] = [];
        }
        if (!in_array($section, $map[$sy][$year], true)) {
            $map[$sy][$year][] = $section;
        }
    }

    return $map;
}

function getAttendanceLogsContext($mysqli)
{
    if (!tableExists($mysqli, 'attendance_logs')) {
        return null;
    }

    $idColumn = null;
    $timeColumn = null;

    if (columnExists($mysqli, 'attendance_logs', 'user_id')) {
        $idColumn = 'user_id';
    } elseif (columnExists($mysqli, 'attendance_logs', 'student_id')) {
        $idColumn = 'student_id';
    }

    if (columnExists($mysqli, 'attendance_logs', 'timestamp')) {
        $timeColumn = 'timestamp';
    } elseif (columnExists($mysqli, 'attendance_logs', 'created_at')) {
        $timeColumn = 'created_at';
    }

    if (!$idColumn || !$timeColumn) {
        return null;
    }

    return [
        'idColumn' => $idColumn,
        'timeColumn' => $timeColumn,
        'hasStudents' => tableExists($mysqli, 'students'),
        'hasUsers' => tableExists($mysqli, 'users'),
    ];
}

function buildDateRange(string $startDate, string $endDate): array
{
    $start = DateTime::createFromFormat('Y-m-d', $startDate);
    $end = DateTime::createFromFormat('Y-m-d', $endDate);
    if (!$start || !$end) {
        return [];
    }

    $dates = [];
    $cursor = clone $start;
    while ($cursor <= $end) {
        $dates[] = $cursor->format('Y-m-d');
        $cursor->modify('+1 day');
    }

    return $dates;
}

function fetchAttendanceLogs($mysqli, $startDate, $endDate, $studentFilter = '', $sectionFilter = '', $yearFilter = '', $limit = 1000)
{
    if (tableExists($mysqli, 'attendance') && tableExists($mysqli, 'students')) {
        $studentsTypes = '';
        $studentsParams = [];
        $middleInitialExpr = columnExists($mysqli, 'students', 'middle_initial') ? 'COALESCE(s.middle_initial, "")' : '""';
        $extensionExpr = columnExists($mysqli, 'students', 'extension') ? 'COALESCE(s.extension, "")' : '""';
        $sectionExpr = columnExists($mysqli, 'students', 'section') ? 'COALESCE(s.section, "")' : '""';
        $yearExpr = columnExists($mysqli, 'students', 'year') ? 'COALESCE(CAST(s.year AS CHAR), "")' : '""';

        $studentsSql = "
            SELECT
                s.id AS student_pk,
                s.first_name,
                s.last_name,
                {$middleInitialExpr} AS middle_initial,
                {$extensionExpr} AS extension,
                {$sectionExpr} AS section,
                {$yearExpr} AS year_level
            FROM students s
            WHERE 1=1
        ";

        if ($studentFilter !== '') {
            $studentsSql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ?)";
            $studentLike = '%' . $studentFilter . '%';
            $studentsParams[] = $studentLike;
            $studentsParams[] = $studentLike;
            $studentsTypes .= 'ss';
        }

        if ($sectionFilter !== '' && columnExists($mysqli, 'students', 'section')) {
            $studentsSql .= " AND s.section = ?";
            $studentsParams[] = $sectionFilter;
            $studentsTypes .= 's';
        }

        if ($yearFilter !== '' && columnExists($mysqli, 'students', 'year')) {
            $studentsSql .= " AND CAST(s.year AS CHAR) = ?";
            $studentsParams[] = $yearFilter;
            $studentsTypes .= 's';
        }

        $studentsSql .= " ORDER BY s.last_name ASC, s.first_name ASC, s.id ASC";

        $studentsStmt = $mysqli->prepare($studentsSql);
        if (!$studentsStmt) {
            return [];
        }

        if ($studentsTypes !== '') {
            $studentBind = [$studentsTypes];
            foreach ($studentsParams as $index => $value) {
                $studentBind[] = &$studentsParams[$index];
            }
            call_user_func_array([$studentsStmt, 'bind_param'], $studentBind);
        }

        $studentsStmt->execute();
        $studentsResult = $studentsStmt->get_result();
        $students = [];
        while ($studentRow = $studentsResult->fetch_assoc()) {
            $students[] = $studentRow;
        }
        $studentsStmt->close();

        if (empty($students)) {
            return [];
        }

        $attendanceTypes = 'ss';
        $attendanceParams = [$startDate, $endDate];
        $attendanceSql = "
            SELECT
                s.id AS student_pk,
                a.attendance_date,
                a.time_in_am,
                a.time_out_am,
                a.time_in_pm,
                a.time_out_pm,
                a.status
            FROM attendance a
            JOIN students s ON s.id = a.student_id
            WHERE a.attendance_date BETWEEN ? AND ?
        ";

        if ($studentFilter !== '') {
            $attendanceSql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ?)";
            $studentLike = '%' . $studentFilter . '%';
            $attendanceParams[] = $studentLike;
            $attendanceParams[] = $studentLike;
            $attendanceTypes .= 'ss';
        }

        if ($sectionFilter !== '' && columnExists($mysqli, 'students', 'section')) {
            $attendanceSql .= " AND s.section = ?";
            $attendanceParams[] = $sectionFilter;
            $attendanceTypes .= 's';
        }

        if ($yearFilter !== '' && columnExists($mysqli, 'students', 'year')) {
            $attendanceSql .= " AND CAST(s.year AS CHAR) = ?";
            $attendanceParams[] = $yearFilter;
            $attendanceTypes .= 's';
        }

        $attendanceSql .= " ORDER BY a.attendance_date ASC";
        $attendanceStmt = $mysqli->prepare($attendanceSql);
        if (!$attendanceStmt) {
            return [];
        }

        $attendanceBind = [$attendanceTypes];
        foreach ($attendanceParams as $index => $value) {
            $attendanceBind[] = &$attendanceParams[$index];
        }
        call_user_func_array([$attendanceStmt, 'bind_param'], $attendanceBind);
        $attendanceStmt->execute();
        $attendanceResult = $attendanceStmt->get_result();

        $attendanceMap = [];
        while ($attendanceRow = $attendanceResult->fetch_assoc()) {
            $studentPk = (string)($attendanceRow['student_pk'] ?? '');
            $attendanceDate = (string)($attendanceRow['attendance_date'] ?? '');
            if ($studentPk === '' || $attendanceDate === '') {
                continue;
            }
            $attendanceMap[$studentPk][$attendanceDate] = $attendanceRow;
        }
        $attendanceStmt->close();

        $dateList = buildDateRange($startDate, $endDate);
        $logs = [];
        foreach ($dateList as $attendanceDate) {
            foreach ($students as $student) {
                $studentPk = (string)($student['student_pk'] ?? '');
                $existing = $attendanceMap[$studentPk][$attendanceDate] ?? null;

                $logs[] = [
                    'student_id' => (string)($student['student_pk'] ?? ''),
                    'first_name' => $student['first_name'] ?? '',
                    'last_name' => $student['last_name'] ?? '',
                    'middle_initial' => $student['middle_initial'] ?? '',
                    'extension' => $student['extension'] ?? '',
                    'section' => $student['section'] ?? '',
                    'year_level' => $student['year_level'] ?? '',
                    'attendance_date' => $attendanceDate,
                    'time_in_am' => $existing['time_in_am'] ?? null,
                    'time_out_am' => $existing['time_out_am'] ?? null,
                    'time_in_pm' => $existing['time_in_pm'] ?? null,
                    'time_out_pm' => $existing['time_out_pm'] ?? null,
                    'status' => $existing['status'] ?? 'absent',
                ];
            }
        }

        if ($limit !== null) {
            $logs = array_slice($logs, 0, (int)$limit);
        }

        return $logs;
    } else {
        $types = 'ss';
        $params = [$startDate, $endDate];
        $ctx = getAttendanceLogsContext($mysqli);
        if (!$ctx) {
            return [];
        }

        $idColumn = $ctx['idColumn'];
        $timeColumn = $ctx['timeColumn'];
        $joinClause = '';
        $studentIdExpr = "CAST(al.{$idColumn} AS CHAR)";
        $firstNameExpr = "CONCAT('ID #', al.{$idColumn})";
        $lastNameExpr = "''";
        $middleInitialExpr = "''";
        $extensionExpr = "''";
        $sectionExpr = "''";
        $yearExpr = "''";

        if ($idColumn === 'student_id' && $ctx['hasStudents']) {
            $joinClause = 'LEFT JOIN students s ON al.student_id = s.id';
            $studentIdExpr = 'CAST(al.student_id AS CHAR)';
            $firstNameExpr = "COALESCE(s.first_name, CONCAT('Student #', al.student_id))";
            $lastNameExpr = "COALESCE(s.last_name, '')";
            $middleInitialExpr = columnExists($mysqli, 'students', 'middle_initial') ? "COALESCE(s.middle_initial, '')" : "''";
            $extensionExpr = columnExists($mysqli, 'students', 'extension') ? "COALESCE(s.extension, '')" : "''";
            $sectionExpr = columnExists($mysqli, 'students', 'section') ? "COALESCE(s.section, '')" : "''";
            $yearExpr = columnExists($mysqli, 'students', 'year') ? "COALESCE(CAST(s.year AS CHAR), '')" : "''";
        } elseif ($idColumn === 'user_id' && $ctx['hasUsers']) {
            $joinClause = 'LEFT JOIN users u ON al.user_id = u.id';
            $studentIdExpr = 'COALESCE(u.student_no, CAST(al.user_id AS CHAR))';
            $firstNameExpr = "COALESCE(u.full_name, CONCAT('User #', al.user_id))";
            $lastNameExpr = "''";
        }

        $sql = "
            SELECT
                {$studentIdExpr} AS student_id,
                {$firstNameExpr} AS first_name,
                {$lastNameExpr} AS last_name,
                {$middleInitialExpr} AS middle_initial,
                {$extensionExpr} AS extension,
                {$sectionExpr} AS section,
                {$yearExpr} AS year_level,
                DATE(al.{$timeColumn}) AS attendance_date,
                CASE WHEN HOUR(al.{$timeColumn}) < 12 THEN TIME(al.{$timeColumn}) ELSE NULL END AS time_in_am,
                NULL AS time_out_am,
                CASE WHEN HOUR(al.{$timeColumn}) >= 12 THEN TIME(al.{$timeColumn}) ELSE NULL END AS time_in_pm,
                NULL AS time_out_pm,
                CASE WHEN al.type = 'IN' THEN 'present' ELSE 'absent' END AS status
            FROM attendance_logs al
            {$joinClause}
            WHERE DATE(al.{$timeColumn}) BETWEEN ? AND ?
        ";

        if ($studentFilter !== '') {
            $sql .= " AND ({$studentIdExpr} LIKE ? OR {$firstNameExpr} LIKE ? OR {$lastNameExpr} LIKE ?)";
            $studentLike = '%' . $studentFilter . '%';
            $params[] = $studentLike;
            $params[] = $studentLike;
            $params[] = $studentLike;
            $types .= 'sss';
        }

        if ($sectionFilter !== '' && $sectionExpr !== "''") {
            $sql .= " AND {$sectionExpr} = ?";
            $params[] = $sectionFilter;
            $types .= 's';
        }

        if ($yearFilter !== '' && $yearExpr !== "''") {
            $sql .= " AND {$yearExpr} = ?";
            $params[] = $yearFilter;
            $types .= 's';
        }

        $sql .= " ORDER BY al.{$timeColumn} DESC";
    }

        if ($limit !== null) {
            $sql .= " LIMIT " . (int) $limit;
        }

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $bindParams = [$types];
        foreach ($params as $index => $value) {
            $bindParams[] = &$params[$index];
        }

        call_user_func_array([$stmt, 'bind_param'], $bindParams);
        $stmt->execute();

        $result = $stmt->get_result();
        $logs = [];

        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }

        $stmt->close();

        return $logs;
    }
$default_start_date = $activeSchoolYear['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$default_end_date = $activeSchoolYear['end_date'] ?? date('Y-m-d');
$today = date('Y-m-d');
if ($default_end_date > $today) {
    $default_end_date = $today;
}
$activeSchoolYearStart = $activeSchoolYear['start_date'] ?? $default_start_date;
$activeSchoolYearEnd = $activeSchoolYear['end_date'] ?? $default_end_date;
$headerSelectedDate = normalizeDateValue($_GET['date'] ?? '', $today);
if ($headerSelectedDate < $activeSchoolYearStart) {
    $headerSelectedDate = $activeSchoolYearStart;
}
if ($headerSelectedDate > $activeSchoolYearEnd) {
    $headerSelectedDate = $activeSchoolYearEnd;
}
$legacy_date = normalizeDateValue($_GET['date'] ?? '', '');

$filter_start_date = normalizeDateValue($_GET['start_date'] ?? '', $default_start_date);
$filter_end_date = normalizeDateValue($_GET['end_date'] ?? '', $default_end_date);

if (!isset($_GET['date']) && !isset($_GET['start_date']) && !isset($_GET['end_date'])) {
    $filter_start_date = $today;
    $filter_end_date = $today;
    $headerSelectedDate = $today;
}

if ($legacy_date !== '') {
    $filter_start_date = $legacy_date;
    $filter_end_date = $legacy_date;
}

if ($filter_start_date > $filter_end_date) {
    [$filter_start_date, $filter_end_date] = [$filter_end_date, $filter_start_date];
}

if ($filter_start_date < $activeSchoolYearStart) {
    $filter_start_date = $activeSchoolYearStart;
}
if ($filter_end_date > $activeSchoolYearEnd) {
    $filter_end_date = $activeSchoolYearEnd;
}
if ($filter_start_date > $filter_end_date) {
    $filter_start_date = $activeSchoolYearStart;
    $filter_end_date = $activeSchoolYearEnd;
}

$filter_student = trim($_GET['student'] ?? '');
$filter_year = trim($_GET['year'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$filter_section = trim($_GET['section'] ?? '');
$sort_by = trim($_GET['sort_by'] ?? 'name');
$sort_dir = strtolower(trim($_GET['sort_dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

$allowedSortBy = ['date', 'name', 'section', 'remarks'];
if (!in_array($sort_by, $allowedSortBy, true)) {
    $sort_by = 'name';
}

$selected_export_sort_by = trim((string)($_GET['export_sort_by'] ?? 'date'));
if (!in_array($selected_export_sort_by, ['date', 'last_name', 'first_name', 'year_section', 'remark'], true)) {
    $selected_export_sort_by = 'date';
}

$selected_export_sort_dir = strtolower(trim((string)($_GET['export_sort_dir'] ?? $sort_dir))) === 'asc' ? 'asc' : 'desc';
$selected_export_name_format = trim((string)($_GET['export_name_format'] ?? 'last_name_first'));
if (!in_array($selected_export_name_format, ['full_name', 'last_name_first'], true)) {
    $selected_export_name_format = 'last_name_first';
}

$selected_export_scope = trim((string)($_GET['export_scope'] ?? 'filters'));
if (!in_array($selected_export_scope, ['filters', 'all', 'school_year', 'section'], true)) {
    $selected_export_scope = 'filters';
}
$selected_export_school_year = trim((string)($_GET['export_school_year'] ?? ''));
$selected_export_year_level = trim((string)($_GET['export_year_level'] ?? ''));
$selected_export_section = trim((string)($_GET['export_section'] ?? ''));
$selected_export_semester = (int)($_GET['semester'] ?? (((int)date('n') >= 7) ? 2 : 1));
if (!in_array($selected_export_semester, [1, 2], true)) {
    $selected_export_semester = 1;
}

$selected_export_month = (int)($_GET['month'] ?? (int)date('n'));
if ($selected_export_month < 1 || $selected_export_month > 12) {
    $selected_export_month = (int)date('n');
}

$thresholds = getAttendanceThresholds($mysqli);
$sections = fetchSections($mysqli);
$years = fetchYears($mysqli);
$teacherSectionMapBySchoolYear = buildTeacherSectionMapBySchoolYear($mysqli);

function buildSortQuery(array $overrides = []): string
{
    global $filter_start_date, $filter_end_date, $filter_student, $filter_year, $filter_status, $filter_section, $sort_by, $sort_dir;

    $query = [
        'start_date' => $filter_start_date,
        'end_date' => $filter_end_date,
        'student' => $filter_student,
        'year' => $filter_year,
        'status' => $filter_status,
        'section' => $filter_section,
        'sort_by' => $sort_by,
        'sort_dir' => $sort_dir,
    ];

    foreach ($overrides as $key => $value) {
        $query[$key] = $value;
    }

    return http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

function renderSortHeader(string $label, string $column): string
{
    global $sort_by, $sort_dir;

    $isActive = $sort_by === $column;
    $nextDir = $isActive && $sort_dir === 'asc' ? 'desc' : 'asc';
    $arrow = '';

    if ($isActive) {
        $arrow = $sort_dir === 'asc' ? ' ▲' : ' ▼';
    }

    $query = buildSortQuery([
        'sort_by' => $column,
        'sort_dir' => $nextDir,
    ]);

    return '<a class="sort-link" href="?' . htmlspecialchars($query) . '">' . htmlspecialchars($label . $arrow) . '</a>';
}

function getRemarksText(array $row, array $thresholds): string
{
    $remark = inferAttendanceRemark($row, $thresholds);
    if ($remark === 'present') {
        return 'Present';
    }
    if ($remark === 'absent') {
        return 'Absent';
    }
    if ($remark === 'partials') {
        return 'Partials (Half day)';
    }

    return 'INC ATT';
}

function formatAttendanceExportName(array $row, string $mode): string
{
    $first = trim((string)($row['first_name'] ?? ''));
    $middle = trim((string)($row['middle_initial'] ?? ''));
    $last = trim((string)($row['last_name'] ?? ''));
    $ext = trim((string)($row['extension'] ?? ''));
    $middleToken = $middle !== '' ? strtoupper(substr($middle, 0, 1)) . '.' : '';

    if ($mode === 'full_name') {
        $parts = array_filter([$first, $middleToken, $last, $ext], static fn($part) => $part !== '');
        return trim(implode(' ', $parts));
    }

    $name = $last;
    if ($first !== '') {
        $name .= ($name !== '' ? ', ' : '') . $first;
    }
    if ($middleToken !== '') {
        $name .= ' ' . $middleToken;
    }
    if ($ext !== '') {
        $name .= ' ' . $ext;
    }

    return trim($name);
}

function getSchoolYearDateRangeByLabel(mysqli $mysqli, string $label): ?array
{
    if ($label === '') {
        return null;
    }

    $stmt = $mysqli->prepare('SELECT start_date, end_date FROM school_years WHERE label = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $label);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || empty($row['start_date']) || empty($row['end_date'])) {
        return null;
    }

    return [
        'start_date' => (string)$row['start_date'],
        'end_date' => (string)$row['end_date'],
    ];
}

function labelExportScope(string $scope): string
{
    return match ($scope) {
        'all' => 'All Records',
        'school_year' => 'School Year',
        'section' => 'Section',
        default => 'Current Filters',
    };
}

function labelSortBy(string $sortBy): string
{
    return match ($sortBy) {
        'last_name' => 'Last name',
        'first_name' => 'First name',
        'year_section' => 'Year/Section',
        'remark' => 'Remark',
        default => 'Date',
    };
}

if (($_GET['export'] ?? '') === 'csv') {
    $exportScope = trim((string)($_GET['export_scope'] ?? 'filters'));
    $exportSchoolYear = trim((string)($_GET['export_school_year'] ?? ''));
    $exportYearLevel = trim((string)($_GET['export_year_level'] ?? ''));
    $exportSection = trim((string)($_GET['export_section'] ?? ''));
    $exportSemester = isset($_GET['semester']) ? (int)$_GET['semester'] : 1;
    $exportMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
    $exportSortBy = trim((string)($_GET['export_sort_by'] ?? 'date'));
    $exportSortDir = strtolower(trim((string)($_GET['export_sort_dir'] ?? $sort_dir))) === 'asc' ? 'asc' : 'desc';
    $exportNameFormat = trim((string)($_GET['export_name_format'] ?? 'last_name_first'));

    $allowedExportSortBy = ['date', 'last_name', 'first_name', 'year_section', 'remark'];
    if (!in_array($exportSortBy, $allowedExportSortBy, true)) {
        $exportSortBy = 'date';
    }

    $allowedNameFormats = ['full_name', 'last_name_first'];
    if (!in_array($exportNameFormat, $allowedNameFormats, true)) {
        $exportNameFormat = 'last_name_first';
    }

    if (!in_array($exportSemester, [1, 2], true)) {
        $exportSemester = 1;
    }
    if ($exportMonth < 1 || $exportMonth > 12) {
        $exportMonth = (int)date('n');
    }
    $semesterMonths = $exportSemester === 1 ? range(1, 6) : range(7, 12);
    if (!in_array($exportMonth, $semesterMonths, true)) {
        $exportMonth = $semesterMonths[0];
    }

    $exportYear = (int)date('Y');
    $exportStartDate = sprintf('%04d-%02d-01', $exportYear, $exportMonth);
    $exportEndDate = date('Y-m-t', strtotime($exportStartDate));
    $exportSectionFilter = $filter_section;
    $exportYearFilter = $filter_year;

    if ($exportScope === 'all') {
        $exportSectionFilter = '';
        $exportYearFilter = '';
    } elseif ($exportScope === 'school_year') {
        $exportSectionFilter = '';
        $exportYearFilter = '';
    } elseif ($exportScope === 'section') {
        $exportSectionFilter = $exportSection;
        $exportYearFilter = $exportYearLevel !== '' ? $exportYearLevel : $filter_year;
    }

    if ($exportStartDate > $exportEndDate) {
        [$exportStartDate, $exportEndDate] = [$exportEndDate, $exportStartDate];
    }

    $export_logs = fetchAttendanceLogs($mysqli, $exportStartDate, $exportEndDate, $filter_student, $exportSectionFilter, $exportYearFilter, null);
    if ($filter_status !== '') {
        $export_logs = array_values(array_filter($export_logs, function (array $row) use ($filter_status, $thresholds): bool {
            return inferAttendanceRemark($row, $thresholds) === $filter_status;
        }));
    }
    usort($export_logs, function (array $a, array $b) use ($exportSortBy, $exportSortDir, $thresholds): int {
        $dateA = (string)($a['attendance_date'] ?? '');
        $dateB = (string)($b['attendance_date'] ?? '');

        // Date range priority always first.
        $dateCmp = strcmp($dateA, $dateB);
        if ($dateCmp !== 0) {
            return $dateCmp;
        }

        $direction = $exportSortDir === 'asc' ? 1 : -1;
        $cmp = 0;

        if ($exportSortBy === 'last_name') {
            $cmp = strcasecmp((string)($a['last_name'] ?? ''), (string)($b['last_name'] ?? ''));
            if ($cmp === 0) {
                $cmp = strcasecmp((string)($a['first_name'] ?? ''), (string)($b['first_name'] ?? ''));
            }
        } elseif ($exportSortBy === 'first_name') {
            $cmp = strcasecmp((string)($a['first_name'] ?? ''), (string)($b['first_name'] ?? ''));
            if ($cmp === 0) {
                $cmp = strcasecmp((string)($a['last_name'] ?? ''), (string)($b['last_name'] ?? ''));
            }
        } elseif ($exportSortBy === 'year_section') {
            $cmp = strcasecmp((string)($a['year_level'] ?? ''), (string)($b['year_level'] ?? ''));
            if ($cmp === 0) {
                $cmp = strcasecmp((string)($a['section'] ?? ''), (string)($b['section'] ?? ''));
            }
            if ($cmp === 0) {
                $cmp = strcasecmp(formatAttendanceName($a), formatAttendanceName($b));
            }
        } elseif ($exportSortBy === 'remark') {
            $cmp = strcasecmp(inferAttendanceRemark($a, $thresholds), inferAttendanceRemark($b, $thresholds));
            if ($cmp === 0) {
                $cmp = strcasecmp(formatAttendanceName($a), formatAttendanceName($b));
            }
        } else {
            $cmp = strcasecmp(formatAttendanceName($a), formatAttendanceName($b));
        }

        if ($cmp === 0) {
            $cmp = strcasecmp(formatAttendanceName($a), formatAttendanceName($b));
        }

        return $cmp * $direction;
    });

    if (ob_get_length() !== false) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="attendance_logs_' . $exportStartDate . '_to_' . $exportEndDate . '.csv"');

    $fp = fopen('php://output', 'w');
    fwrite($fp, "\xEF\xBB\xBF");

    $activeFilters = [];
    if ($filter_student !== '') {
        $activeFilters[] = 'Student=' . $filter_student;
    }
    if ($filter_year !== '') {
        $activeFilters[] = 'Year=' . $filter_year;
    }
    if ($exportYearFilter !== '' && $exportYearFilter !== $filter_year) {
        $activeFilters[] = 'Year=' . $exportYearFilter;
    }
    if ($exportSectionFilter !== '') {
        $activeFilters[] = 'Section=' . $exportSectionFilter;
    }
    if ($filter_status !== '') {
        $activeFilters[] = 'Remarks=' . $filter_status;
    }

    fputcsv($fp, ['Attendance Logs Export']);
    fputcsv($fp, ['Export Scope', labelExportScope($exportScope)]);
    fputcsv($fp, ['Date Range', $exportStartDate . ' to ' . $exportEndDate]);
    fputcsv($fp, ['Sort', 'Date priority, then ' . labelSortBy($exportSortBy) . ' (' . strtoupper($exportSortDir) . ')']);
    fputcsv($fp, ['Active Filters', empty($activeFilters) ? 'None' : implode(' | ', $activeFilters)]);
    fputcsv($fp, []);
    fputcsv($fp, ['Date', 'Name', 'Year', 'Section', 'Time In (AM)', 'Time Out (AM)', 'Time In (PM)', 'Time Out (PM)', 'Remarks']);

    foreach ($export_logs as $log) {
        fputcsv($fp, [
            !empty($log['attendance_date']) ? date('n/j/Y', strtotime((string)$log['attendance_date'])) : '-',
            formatAttendanceExportName($log, $exportNameFormat),
            (string)($log['year_level'] ?? '-'),
            (string)($log['section'] ?? '-'),
            $log['time_in_am'] ?? '-',
            $log['time_out_am'] ?? '-',
            $log['time_in_pm'] ?? '-',
            $log['time_out_pm'] ?? '-',
            getRemarksText($log, $thresholds)
        ]);
    }

    fclose($fp);
    exit;
}

$logs = fetchAttendanceLogs($mysqli, $filter_start_date, $filter_end_date, $filter_student, $filter_section, $filter_year, 1000);
if ($filter_status !== '') {
    $logs = array_values(array_filter($logs, function (array $row) use ($filter_status, $thresholds): bool {
        return inferAttendanceRemark($row, $thresholds) === $filter_status;
    }));
}
sortAttendanceLogs($logs, $sort_by, $sort_dir, $thresholds);

$export_query = http_build_query([
    'start_date' => $filter_start_date,
    'end_date' => $filter_end_date,
    'student' => $filter_student,
    'year' => $filter_year,
    'status' => $filter_status,
    'section' => $filter_section,
    'sort_by' => $sort_by,
    'sort_dir' => $sort_dir,
], '', '&', PHP_QUERY_RFC3986);
?>

<div class="alert alert-info mb-3">
    <i class="bi bi-mortarboard"></i>
    Active School Year default: <strong><?php echo htmlspecialchars($activeSchoolYear['label'] ?? 'N/A'); ?></strong>
    (<?php echo htmlspecialchars($activeSchoolYear['start_date'] ?? ''); ?> to <?php echo htmlspecialchars($activeSchoolYear['end_date'] ?? ''); ?>)
</div>

<!-- Filters -->
<div class="card mb-3 filter-card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3 py-2 px-3">
        <strong>Filters</strong>
    </div>
    <div class="card-body py-2 px-3">
        <form id="attendance-filters" method="GET" class="row g-2 align-items-end">
            <div class="col-xl-2 col-lg-3 col-md-6">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>" min="<?php echo htmlspecialchars($activeSchoolYearStart); ?>" max="<?php echo htmlspecialchars($activeSchoolYearEnd); ?>">
            </div>

            <div class="col-xl-2 col-lg-3 col-md-6">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>" min="<?php echo htmlspecialchars($activeSchoolYearStart); ?>" max="<?php echo htmlspecialchars($activeSchoolYearEnd); ?>">
            </div>

            <div class="col-xl-2 col-lg-3 col-md-6">
                <label for="student" class="form-label">Student</label>
                <input type="text" class="form-control" id="student" name="student" value="<?php echo htmlspecialchars($filter_student); ?>" placeholder="Name">
            </div>

            <div class="col-xl-1 col-lg-3 col-md-6">
                <label for="year" class="form-label">Year</label>
                <select class="form-select" id="year" name="year">
                    <option value="">All</option>
                    <?php foreach ($years as $yearOption): ?>
                        <option value="<?php echo htmlspecialchars($yearOption); ?>" <?php echo $filter_year === $yearOption ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($yearOption); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-xl-2 col-lg-3 col-md-6">
                <label for="section" class="form-label">Section</label>
                <select class="form-select" id="section" name="section">
                    <option value="">All</option>
                    <?php foreach ($sections as $section): ?>
                        <option value="<?php echo htmlspecialchars($section); ?>" <?php echo $filter_section === $section ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($section); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-xl-1 col-lg-3 col-md-6">
                <label for="status" class="form-label">Remarks</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All</option>
                    <option value="present" <?php echo $filter_status === 'present' ? 'selected' : ''; ?>>Present</option>
                    <option value="absent" <?php echo $filter_status === 'absent' ? 'selected' : ''; ?>>Absent</option>
                    <option value="partials" <?php echo $filter_status === 'partials' ? 'selected' : ''; ?>>Partials (Half day)</option>
                    <option value="inc_att" <?php echo $filter_status === 'inc_att' ? 'selected' : ''; ?>>INC ATT</option>
                </select>
            </div>

            <div class="col-xl-1 col-lg-3 col-md-6">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>



<div class="modal fade" id="smartExportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-funnel"></i> Smart Export Filter</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="GET">
                <div class="modal-body">
                    <input type="hidden" name="export" value="csv">
                    <input type="hidden" name="student" value="<?php echo htmlspecialchars($filter_student); ?>">
                    <input type="hidden" name="section" value="<?php echo htmlspecialchars($filter_section); ?>">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6 class="mb-3">Filtering</h6>
                            <div class="mb-3">
                                <label for="export_scope" class="form-label">Export Scope</label>
                                <select class="form-select" id="export_scope" name="export_scope">
                                    <option value="filters" <?php echo $selected_export_scope === 'filters' ? 'selected' : ''; ?>>Current Filters</option>
                                    <option value="all" <?php echo $selected_export_scope === 'all' ? 'selected' : ''; ?>>All Records</option>
                                    <option value="school_year" <?php echo $selected_export_scope === 'school_year' ? 'selected' : ''; ?>>By School Year</option>
                                    <option value="section" <?php echo $selected_export_scope === 'section' ? 'selected' : ''; ?>>By Section (optional SY)</option>
                                </select>
                            </div>

                            <div class="mb-3" id="exportSchoolYearGroup" style="display:none;">
                                <label for="export_school_year" class="form-label">School Year</label>
                                <select class="form-select" id="export_school_year" name="export_school_year">
                                    <option value="">Select school year</option>
                                    <?php foreach ($schoolYears as $sy): ?>
                                        <option value="<?php echo htmlspecialchars((string)$sy['label']); ?>" <?php echo $selected_export_school_year === (string)$sy['label'] ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$sy['label']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3" id="exportYearGroup" style="display:none;">
                                <label for="export_year_level" class="form-label">Year</label>
                                <select class="form-select" id="export_year_level" name="export_year_level">
                                    <option value="">Select year</option>
                                </select>
                            </div>

                            <div class="mb-0" id="exportSectionGroup" style="display:none;">
                                <label for="export_section" class="form-label">Section</label>
                                <select class="form-select" id="export_section" name="export_section">
                                    <option value="">Select year first</option>
                                </select>
                            </div>

                            <div class="row g-2 mt-1">
                                <div class="col-6">
                                    <label for="semester" class="form-label">Semester</label>
                                    <select class="form-select" id="semester" name="semester">
                                        <option value="1" <?php echo $selected_export_semester === 1 ? 'selected' : ''; ?>>Semester 1 (Jan-Jun)</option>
                                        <option value="2" <?php echo $selected_export_semester === 2 ? 'selected' : ''; ?>>Semester 2 (Jul-Dec)</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label for="month" class="form-label">Month</label>
                                    <select class="form-select" id="month" name="month">
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                            <option value="<?php echo $m; ?>" <?php echo $selected_export_month === $m ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars(date('F', mktime(0, 0, 0, $m, 1))); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6" id="exportSortingColumn" style="display:none;">
                            <h6 class="mb-3">Sorting</h6>

                            <div class="mb-3" id="exportSortGroup" style="display:none;">
                                <label for="export_sort_by" class="form-label">Sort By</label>
                                <div class="row g-2">
                                    <div class="col-8">
                                        <select class="form-select" id="export_sort_by" name="export_sort_by">
                                            <option value="date" <?php echo $selected_export_sort_by === 'date' ? 'selected' : ''; ?>>Date</option>
                                            <option value="last_name" <?php echo $selected_export_sort_by === 'last_name' ? 'selected' : ''; ?>>Last name</option>
                                            <option value="first_name" <?php echo $selected_export_sort_by === 'first_name' ? 'selected' : ''; ?>>First name</option>
                                            <option value="year_section" <?php echo $selected_export_sort_by === 'year_section' ? 'selected' : ''; ?>>Year/Section</option>
                                            <option value="remark" <?php echo $selected_export_sort_by === 'remark' ? 'selected' : ''; ?>>Remark</option>
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <select class="form-select" id="export_sort_dir" name="export_sort_dir">
                                            <option value="asc" <?php echo $selected_export_sort_dir === 'asc' ? 'selected' : ''; ?>>Asc</option>
                                            <option value="desc" <?php echo $selected_export_sort_dir === 'desc' ? 'selected' : ''; ?>>Desc</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-0" id="exportNameFormatGroup" style="display:none;">
                                <label for="export_name_format" class="form-label">Name Format</label>
                                <select class="form-select" id="export_name_format" name="export_name_format">
                                    <option value="full_name" <?php echo $selected_export_name_format === 'full_name' ? 'selected' : ''; ?>>Full name (First name MI Last name Ext)</option>
                                    <option value="last_name_first" <?php echo $selected_export_name_format === 'last_name_first' ? 'selected' : ''; ?>>Last name, First name MI Ext</option>
                                </select>
                            </div>

                            <small class="text-muted d-block mt-2" id="exportSortHint" style="display:none;">
                                Select a section first to enable sorting options.
                            </small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-download"></i> Export</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Logs Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <h5 class="mb-0">Records (<?php echo count($logs); ?>) <span class="header-date-chip">
                    <?php echo htmlspecialchars(date('M j, Y', strtotime($headerSelectedDate))); ?>
                </span></h5>
        <div class="records-header-tools d-flex align-items-center gap-2 flex-wrap justify-content-end">
            <form id="headerDateFilter" method="GET" class="d-flex align-items-center gap-1 mb-0">
                <input type="hidden" name="student" value="<?php echo htmlspecialchars($filter_student); ?>">
                <input type="hidden" name="year" value="<?php echo htmlspecialchars($filter_year); ?>">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
                <input type="hidden" name="section" value="<?php echo htmlspecialchars($filter_section); ?>">
                <input type="hidden" id="headerSortByHidden" name="sort_by" value="<?php echo htmlspecialchars($sort_by); ?>">
                <input type="hidden" id="headerSortDirHidden" name="sort_dir" value="<?php echo htmlspecialchars($sort_dir); ?>">
                <input type="date" class="form-control form-control-sm" id="headerDatePicker" name="date" value="<?php echo htmlspecialchars($headerSelectedDate); ?>" min="<?php echo htmlspecialchars($activeSchoolYearStart); ?>" max="<?php echo htmlspecialchars($activeSchoolYearEnd); ?>" style="min-width: 132px; max-width: 136px;">
                
            </form>

            <div class="d-flex align-items-center gap-1">
                <label for="sort_by" class="mb-0 small text-muted">Sort</label>
                <select class="form-select form-select-sm" id="sort_by" name="sort_by" form="attendance-filters" style="min-width: 130px;">
                    <option value="section" <?php echo $sort_by === 'section' ? 'selected' : ''; ?>>Year/Section</option>
                    <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Name</option>
                    <option value="date" <?php echo $sort_by === 'date' ? 'selected' : ''; ?>>Date</option>
                    <option value="remarks" <?php echo $sort_by === 'remarks' ? 'selected' : ''; ?>>Remarks</option>
                </select>
                <select class="form-select form-select-sm" id="sort_dir" name="sort_dir" form="attendance-filters" style="min-width: 90px;">
                    <option value="asc" <?php echo $sort_dir === 'asc' ? 'selected' : ''; ?>>Asc</option>
                    <option value="desc" <?php echo $sort_dir === 'desc' ? 'selected' : ''; ?>>Desc</option>
                </select>
            </div>

            <button type="button" class="btn btn-export-csv btn-sm" data-bs-toggle="modal" data-bs-target="#smartExportModal">
                <i class="bi bi-file-earmark-arrow-down"></i> Export CSV
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th><?php echo renderSortHeader('Date', 'date'); ?></th>
                        <th><?php echo renderSortHeader('Name', 'name'); ?></th>
                        <th><?php echo renderSortHeader('Year/Section', 'section'); ?></th>
                        <th>Time In (AM)</th>
                        <th>Time Out (AM)</th>
                        <th>Time In (PM)</th>
                        <th>Time Out (PM)</th>
                        <th><?php echo renderSortHeader('Remarks', 'remarks'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No records found</td>
                        </tr>
                    <?php else: ?>
                        <?php $rowCounter = 1; ?>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo $rowCounter++; ?></td>
                            <td><strong><?php echo !empty($log['attendance_date']) ? date('M d, Y', strtotime($log['attendance_date'])) : '-'; ?></strong></td>
                            <td><?php echo htmlspecialchars(formatAttendanceName($log)); ?></td>
                            <td><?php echo htmlspecialchars(formatYearSection($log)); ?></td>
                            <td>
                                <?php if (!empty($log['time_in_am'])): ?>
                                    <?php echo date('h:i A', strtotime($log['time_in_am'])); ?>
                                    <?php $tag = getTimeTardinessLabel($log['time_in_am'], 'am', 'in', $thresholds); ?>
                                    <?php if ($tag !== ''): ?>
                                        <span class="badge-time <?php echo $tag === 'Late' ? 'badge-time-late' : 'badge-time-early'; ?>"><?php echo $tag; ?></span>
                                    <?php endif; ?>
                                <?php else: ?>-
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($log['time_out_am'])): ?>
                                    <?php echo date('h:i A', strtotime($log['time_out_am'])); ?>
                                    <?php $tag = getTimeTardinessLabel($log['time_out_am'], 'am', 'out', $thresholds); ?>
                                    <?php if ($tag !== ''): ?>
                                        <span class="badge-time <?php echo $tag === 'Late' ? 'badge-time-late' : 'badge-time-early'; ?>"><?php echo $tag; ?></span>
                                    <?php endif; ?>
                                <?php else: ?>-
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($log['time_in_pm'])): ?>
                                    <?php echo date('h:i A', strtotime($log['time_in_pm'])); ?>
                                    <?php $tag = getTimeTardinessLabel($log['time_in_pm'], 'pm', 'in', $thresholds); ?>
                                    <?php if ($tag !== ''): ?>
                                        <span class="badge-time <?php echo $tag === 'Late' ? 'badge-time-late' : 'badge-time-early'; ?>"><?php echo $tag; ?></span>
                                    <?php endif; ?>
                                <?php else: ?>-
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($log['time_out_pm'])): ?>
                                    <?php echo date('h:i A', strtotime($log['time_out_pm'])); ?>
                                    <?php $tag = getTimeTardinessLabel($log['time_out_pm'], 'pm', 'out', $thresholds); ?>
                                    <?php if ($tag !== ''): ?>
                                        <span class="badge-time <?php echo $tag === 'Late' ? 'badge-time-late' : 'badge-time-early'; ?>"><?php echo $tag; ?></span>
                                    <?php endif; ?>
                                <?php else: ?>-
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars(getRemarksText($log, $thresholds)); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<style>
    .card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .card-header {
        background: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        border-radius: 12px 12px 0 0;
        padding: 8px 12px;
    }

    .card-header h5 {
        font-size: 0.98rem;
    }

    .form-control, .form-select {
        border-radius: 8px;
        border: 1px solid #dee2e6;
    }

    .filter-card .form-label {
        margin-bottom: 4px;
        font-size: 0.82rem;
        font-weight: 600;
    }

    .header-date-chip {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        padding: 0 7px;
        border-radius: 999px;
        border: 1px solid #dee2e6;
        background: #f8f9fa;
        color: #495057;
        font-size: 0.72rem;
        line-height: 1;
        white-space: nowrap;
    }

    .records-header-tools {
        gap: 6px !important;
        row-gap: 4px;
    }

    .records-header-tools .form-control-sm,
    .records-header-tools .form-select-sm {
        height: 32px;
        font-size: 0.86rem;
        padding-top: 4px;
        padding-bottom: 4px;
    }

    .btn-export-csv {
        border: 1px solid #0f5132;
        border-radius: 8px;
        padding: 4px 10px;
        color: #ffffff;
        background: linear-gradient(135deg, #198754 0%, #0f5132 100%);
        font-weight: 700;
        font-size: 0.82rem;
        letter-spacing: 0.01em;
        box-shadow: 0 3px 8px rgba(15, 81, 50, 0.18);
    }

    .btn-export-csv:hover,
    .btn-export-csv:focus {
        color: #ffffff;
        background: linear-gradient(135deg, #157347 0%, #0b3b24 100%);
        border-color: #0b3b24;
    }

    .sort-link {
        color: inherit;
        text-decoration: none;
        font-weight: 600;
    }

    .sort-link:hover {
        text-decoration: underline;
    }

    .table {
        border-collapse: separate;
        border-spacing: 0;
    }

    .table thead th:first-child {
        border-top-left-radius: 8px;
    }

    .table thead th:last-child {
        border-top-right-radius: 8px;
    }

    .badge-time {
        display: inline-block;
        margin-left: 6px;
        padding: 2px 7px;
        border-radius: 999px;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        vertical-align: middle;
    }

    .badge-time-early {
        color: #0a4b78;
        background: #d7ecff;
    }

    .badge-time-late {
        color: #8a4300;
        background: #ffe1c7;
    }
</style>

<?php
$schoolYearRangeMap = [];
foreach ($schoolYears as $sy) {
    $label = trim((string)($sy['label'] ?? ''));
    $startDate = trim((string)($sy['start_date'] ?? ''));
    $endDate = trim((string)($sy['end_date'] ?? ''));
    if ($label === '' || $startDate === '' || $endDate === '') {
        continue;
    }
    $schoolYearRangeMap[$label] = [
        'start_date' => $startDate,
        'end_date' => $endDate,
    ];
}
$teacherSectionMapJs = $teacherSectionMapBySchoolYear;
?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const schoolYearRanges = <?php echo json_encode($schoolYearRangeMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const teacherSectionMapBySchoolYear = <?php echo json_encode($teacherSectionMapJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const selectedExportYearLevel = '<?php echo htmlspecialchars($selected_export_year_level); ?>';
    const selectedExportSection = '<?php echo htmlspecialchars($selected_export_section); ?>';
    const activeSchoolYearStart = '<?php echo htmlspecialchars($activeSchoolYearStart); ?>';
    const activeSchoolYearEnd = '<?php echo htmlspecialchars($activeSchoolYearEnd); ?>';

    const headerDateFilter = document.getElementById('headerDateFilter');
    const headerDatePicker = document.getElementById('headerDatePicker');
    const headerSortByHidden = document.getElementById('headerSortByHidden');
    const headerSortDirHidden = document.getElementById('headerSortDirHidden');
    const filterStartDateInput = document.getElementById('start_date');
    const filterEndDateInput = document.getElementById('end_date');
    const sortBySelect = document.getElementById('sort_by');
    const sortDirSelect = document.getElementById('sort_dir');
    const exportScope = document.getElementById('export_scope');
    const exportSchoolYear = document.getElementById('export_school_year');
    const exportYearLevel = document.getElementById('export_year_level');
    const exportSection = document.getElementById('export_section');
    const exportSemesterInput = document.getElementById('semester');
    const exportMonthInput = document.getElementById('month');
    const schoolYearGroup = document.getElementById('exportSchoolYearGroup');
    const yearGroup = document.getElementById('exportYearGroup');
    const sectionGroup = document.getElementById('exportSectionGroup');
    const sortingColumn = document.getElementById('exportSortingColumn');
    const sortGroup = document.getElementById('exportSortGroup');
    const nameFormatGroup = document.getElementById('exportNameFormatGroup');
    const sortHint = document.getElementById('exportSortHint');
    const sortByInput = document.getElementById('export_sort_by');
    const sortDirInput = document.getElementById('export_sort_dir');
    const nameFormatInput = document.getElementById('export_name_format');

    if (headerDatePicker) {
        headerDatePicker.addEventListener('change', function () {
            if (headerSortByHidden && sortBySelect) {
                headerSortByHidden.value = sortBySelect.value;
            }
            if (headerSortDirHidden && sortDirSelect) {
                headerSortDirHidden.value = sortDirSelect.value;
            }
            if (headerDateFilter) {
                headerDateFilter.submit();
            }
        });
    }

    const submitHeaderControls = () => {
        if (!headerDateFilter) {
            return;
        }
        if (headerSortByHidden && sortBySelect) {
            headerSortByHidden.value = sortBySelect.value;
        }
        if (headerSortDirHidden && sortDirSelect) {
            headerSortDirHidden.value = sortDirSelect.value;
        }
        headerDateFilter.submit();
    };

    if (sortBySelect) {
        sortBySelect.addEventListener('change', submitHeaderControls);
    }
    if (sortDirSelect) {
        sortDirSelect.addEventListener('change', submitHeaderControls);
    }

    const applyDateBounds = (startInput, endInput, minDate, maxDate) => {
        if (!startInput || !endInput) {
            return;
        }

        if (minDate) {
            startInput.min = minDate;
            endInput.min = minDate;
        }
        if (maxDate) {
            startInput.max = maxDate;
            endInput.max = maxDate;
        }

        if (startInput.value && minDate && startInput.value < minDate) {
            startInput.value = minDate;
        }
        if (startInput.value && maxDate && startInput.value > maxDate) {
            startInput.value = maxDate;
        }
        if (endInput.value && minDate && endInput.value < minDate) {
            endInput.value = minDate;
        }
        if (endInput.value && maxDate && endInput.value > maxDate) {
            endInput.value = maxDate;
        }

        if (startInput.value && endInput.value && startInput.value > endInput.value) {
            endInput.value = startInput.value;
        }
    };

    applyDateBounds(filterStartDateInput, filterEndDateInput, activeSchoolYearStart, activeSchoolYearEnd);

    if (!exportScope || !exportSection || !exportYearLevel || !schoolYearGroup || !yearGroup || !sectionGroup || !sortingColumn || !sortGroup || !nameFormatGroup || !sortHint || !sortByInput || !sortDirInput || !nameFormatInput) {
        return;
    }

    const getYearsForSchoolYear = (schoolYearValue) => {
        if (schoolYearValue && teacherSectionMapBySchoolYear[schoolYearValue]) {
            return Object.keys(teacherSectionMapBySchoolYear[schoolYearValue]);
        }

        const fallbackYears = new Set();
        Object.keys(teacherSectionMapBySchoolYear).forEach((sy) => {
            Object.keys(teacherSectionMapBySchoolYear[sy] || {}).forEach((year) => fallbackYears.add(year));
        });
        return Array.from(fallbackYears).sort((a, b) => String(a).localeCompare(String(b), undefined, {numeric: true}));
    };

    const populateExportYears = (selectedYear = '') => {
        const years = getYearsForSchoolYear(exportSchoolYear ? exportSchoolYear.value : '');
        exportYearLevel.innerHTML = '';
        exportYearLevel.add(new Option('Select year', ''));
        years.forEach((year) => {
            exportYearLevel.add(new Option(year, year));
        });

        if (selectedYear && years.includes(selectedYear)) {
            exportYearLevel.value = selectedYear;
        } else {
            exportYearLevel.value = '';
        }
    };

    const getSectionsForYear = (schoolYearValue, yearValue) => {
        if (!yearValue) {
            return [];
        }

        if (schoolYearValue && teacherSectionMapBySchoolYear[schoolYearValue] && teacherSectionMapBySchoolYear[schoolYearValue][yearValue]) {
            return teacherSectionMapBySchoolYear[schoolYearValue][yearValue];
        }

        const merged = new Set();
        Object.keys(teacherSectionMapBySchoolYear).forEach((sy) => {
            const yearMap = teacherSectionMapBySchoolYear[sy] || {};
            (yearMap[yearValue] || []).forEach((section) => merged.add(section));
        });

        return Array.from(merged).sort((a, b) => String(a).localeCompare(String(b)));
    };

    const populateExportSections = (selectedSectionValue = '') => {
        const sections = getSectionsForYear(exportSchoolYear ? exportSchoolYear.value : '', exportYearLevel.value);
        exportSection.innerHTML = '';

        if (!exportYearLevel.value) {
            exportSection.add(new Option('Select year first', ''));
            exportSection.value = '';
            return;
        }

        exportSection.add(new Option('Select section', ''));
        sections.forEach((section) => {
            exportSection.add(new Option(section, section));
        });

        if (selectedSectionValue && sections.includes(selectedSectionValue)) {
            exportSection.value = selectedSectionValue;
        } else {
            exportSection.value = '';
        }
    };

    const populateSemesterMonths = () => {
        if (!exportSemesterInput || !exportMonthInput) {
            return;
        }

        const monthNames = {
            1: 'January',
            2: 'February',
            3: 'March',
            4: 'April',
            5: 'May',
            6: 'June',
            7: 'July',
            8: 'August',
            9: 'September',
            10: 'October',
            11: 'November',
            12: 'December'
        };

        const previousMonth = Number(exportMonthInput.value || 0);
        const isSemOne = exportSemesterInput.value === '1';
        const start = isSemOne ? 1 : 7;
        const end = isSemOne ? 6 : 12;

        exportMonthInput.innerHTML = '';
        for (let m = start; m <= end; m++) {
            exportMonthInput.add(new Option(monthNames[m], String(m)));
        }

        if (previousMonth >= start && previousMonth <= end) {
            exportMonthInput.value = String(previousMonth);
        } else {
            exportMonthInput.value = String(start);
        }
    };

    const syncExportScope = () => {
        const mode = exportScope.value;
        schoolYearGroup.style.display = (mode === 'school_year' || mode === 'section') ? '' : 'none';
        yearGroup.style.display = mode === 'section' ? '' : 'none';
        sectionGroup.style.display = mode === 'section' ? '' : 'none';

        const requireSectionFirst = (mode === 'section');
        const canShowTailOptions = !requireSectionFirst || (exportSection.value.trim() !== '');

        sortingColumn.style.display = '';
        sortGroup.style.display = canShowTailOptions ? '' : 'none';
        nameFormatGroup.style.display = canShowTailOptions ? '' : 'none';
        sortHint.style.display = canShowTailOptions ? 'none' : '';

        sortByInput.disabled = !canShowTailOptions;
        sortDirInput.disabled = !canShowTailOptions;
        nameFormatInput.disabled = !canShowTailOptions;

        populateSemesterMonths();
    };

    exportScope.addEventListener('change', syncExportScope);
    if (exportSchoolYear) {
        exportSchoolYear.addEventListener('change', function () {
            populateExportYears(exportYearLevel.value);
            populateExportSections('');
            syncExportScope();
        });
    }
    exportYearLevel.addEventListener('change', function () {
        populateExportSections('');
        syncExportScope();
    });
    exportSection.addEventListener('change', syncExportScope);
    if (exportSemesterInput) {
        exportSemesterInput.addEventListener('change', syncExportScope);
    }

    populateExportYears(selectedExportYearLevel);
    populateExportSections(selectedExportSection);
    populateSemesterMonths();
    syncExportScope();
});
</script>

<?php require '../includes/footer.php'; ?>