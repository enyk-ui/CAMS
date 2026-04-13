<?php
/**
 * Teacher Attendance Report
 * Attendance reports filtered for a teacher's assigned year and section
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db.php';
require_once '../helpers/SchoolYearHelper.php';

function studentColumnExists(mysqli $mysqli, string $columnName): bool
{
    $safeColumn = $mysqli->real_escape_string($columnName);
    $result = $mysqli->query("SHOW COLUMNS FROM students LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

function resolveTeacherAssignment(mysqli $mysqli): array
{
    $yearLevel = (int)($_SESSION['teacher_year_level'] ?? 0);
    $section = trim((string)($_SESSION['teacher_section'] ?? ''));

    if ($yearLevel > 0 && $section !== '') {
        return ['year_level' => $yearLevel, 'section' => $section];
    }

    $teacherId = $_SESSION['admin_id'] ?? null;
    if ($teacherId !== null) {
        $stmt = $mysqli->prepare("SELECT year_level, section FROM users WHERE id = ? AND role = 'teacher' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $teacherId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $yearLevel = (int)($row['year_level'] ?? 0);
            $section = trim((string)($row['section'] ?? ''));
            if ($yearLevel > 0 && $section !== '') {
                $_SESSION['teacher_year_level'] = $yearLevel;
                $_SESSION['teacher_section'] = $section;
                return ['year_level' => $yearLevel, 'section' => $section];
            }
        }
    }

    $teacherEmail = $_SESSION['admin_email'] ?? '';
    if ($teacherEmail !== '') {
        $stmt = $mysqli->prepare("SELECT year_level, section FROM users WHERE email = ? AND role = 'teacher' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $teacherEmail);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $yearLevel = (int)($row['year_level'] ?? 0);
            $section = trim((string)($row['section'] ?? ''));
            if ($yearLevel > 0 && $section !== '') {
                $_SESSION['teacher_year_level'] = $yearLevel;
                $_SESSION['teacher_section'] = $section;
                return ['year_level' => $yearLevel, 'section' => $section];
            }
        }
    }

    $_SESSION['teacher_year_level'] = 0;
    $_SESSION['teacher_section'] = '';
    return ['year_level' => 0, 'section' => ''];
}

function resolveTeacherSectionIds(mysqli $mysqli): array
{
    $teacherId = (int)($_SESSION['admin_id'] ?? 0);
    if ($teacherId <= 0) {
        return [];
    }

    $stmt = $mysqli->prepare('SELECT section_id FROM teacher_sections WHERE teacher_id = ? ORDER BY section_id ASC');
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $teacherId);
    $stmt->execute();
    $result = $stmt->get_result();
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $sid = (int)($row['section_id'] ?? 0);
        if ($sid > 0) {
            $ids[] = $sid;
        }
    }
    $stmt->close();

    return $ids;
}

function fetchTeacherSectionCatalog(mysqli $mysqli, array $sectionIds): array
{
    if (empty($sectionIds)) {
        return [];
    }

    $inClause = implode(',', array_fill(0, count($sectionIds), '?'));
    $sql = "SELECT id, name, year_grade FROM sections WHERE id IN ({$inClause}) ORDER BY CAST(year_grade AS UNSIGNED) ASC, name ASC";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $types = str_repeat('i', count($sectionIds));
    $bind = [$types];
    foreach ($sectionIds as $index => $value) {
        $bind[] = &$sectionIds[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $sid = (int)($row['id'] ?? 0);
        $name = trim((string)($row['name'] ?? ''));
        $year = trim((string)($row['year_grade'] ?? ''));
        $rows[] = [
            'id' => $sid,
            'name' => $name,
            'year_grade' => $year,
            'label' => trim($year . ' - ' . $name),
        ];
    }
    $stmt->close();

    return $rows;
}

function formatAttendanceHistoryName(array $record): string
{
    $first = trim((string) ($record['first_name'] ?? ''));
    $middle = trim((string) ($record['middle_initial'] ?? ''));
    $last = trim((string) ($record['last_name'] ?? ''));
    $ext = trim((string) ($record['extension'] ?? ''));

    $name = $last;
    if ($first !== '') {
        $name .= ($name !== '' ? ', ' : '') . $first;
    }
    if ($middle !== '') {
        $name .= ' ' . strtolower(substr($middle, 0, 1)) . '.';
    }
    if ($ext !== '') {
        $name .= ' ' . strtolower($ext);
    }

    return strtolower(trim($name));
}

function findSchoolYearByLabel(array $schoolYears, string $label): ?array
{
    foreach ($schoolYears as $schoolYear) {
        if ((string)($schoolYear['label'] ?? '') === $label) {
            return $schoolYear;
        }
    }

    return null;
}

function buildSchoolYearSemesterRanges(array $schoolYear): array
{
    $startDate = trim((string)($schoolYear['start_date'] ?? ''));
    $endDate = trim((string)($schoolYear['end_date'] ?? ''));

    if ($startDate === '' || $endDate === '') {
        return [];
    }

    try {
        $semesterOneStart = new DateTime($startDate);
        $semesterOneEnd = (clone $semesterOneStart)->modify('+5 months')->modify('last day of this month');
        $semesterTwoStart = (clone $semesterOneEnd)->modify('+1 day');
        $schoolYearEnd = new DateTime($endDate);

        return [
            1 => [
                'start' => $semesterOneStart->format('Y-m-d'),
                'end' => min($semesterOneEnd->format('Y-m-d'), $schoolYearEnd->format('Y-m-d')),
                'label' => 'Semester 1',
            ],
            2 => [
                'start' => $semesterTwoStart->format('Y-m-d'),
                'end' => $schoolYearEnd->format('Y-m-d'),
                'label' => 'Semester 2',
            ],
        ];
    } catch (Throwable $e) {
        return [];
    }
}

function buildSchoolYearMonthOptions(array $schoolYear): array
{
    $startDate = trim((string)($schoolYear['start_date'] ?? ''));
    $endDate = trim((string)($schoolYear['end_date'] ?? ''));

    if ($startDate === '' || $endDate === '') {
        return [];
    }

    try {
        $current = new DateTime($startDate);
        $lastDate = new DateTime($endDate);
        $options = [];

        while ($current <= $lastDate) {
            $monthStart = (clone $current)->modify('first day of this month');
            $options[] = [
                'value' => $monthStart->format('Y-m-d'),
                'label' => $monthStart->format('F Y'),
            ];
            $current->modify('+1 month');
        }

        return $options;
    } catch (Throwable $e) {
        return [];
    }
}

if (($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: ../index.php?error=Unauthorized');
    exit;
}

$teacherAssignment = resolveTeacherAssignment($mysqli);
$teacherYear = (int)($teacherAssignment['year_level'] ?? 0);
$section = (string)($teacherAssignment['section'] ?? '');
$hasSectionIdColumn = studentColumnExists($mysqli, 'section_id');
$teacherSectionIds = resolveTeacherSectionIds($mysqli);
$sectionCatalog = ($hasSectionIdColumn && !empty($teacherSectionIds))
    ? fetchTeacherSectionCatalog($mysqli, $teacherSectionIds)
    : [];

$filterSectionId = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
if ($filterSectionId > 0 && !in_array($filterSectionId, $teacherSectionIds, true)) {
    $filterSectionId = 0;
}

if ($hasSectionIdColumn && empty($teacherSectionIds) && ($teacherYear <= 0 || $section === '')) {
    header('Location: ../index.php?error=Teacher assignment missing');
    exit;
}

if (!$hasSectionIdColumn && ($teacherYear <= 0 || $section === '')) {
    header('Location: ../index.php?error=Teacher assignment missing');
    exit;
}

$scopeLabel = $section !== '' ? ('Year ' . $teacherYear . ' - ' . $section) : 'Assigned Sections';
if ($hasSectionIdColumn && !empty($teacherSectionIds)) {
    if ($filterSectionId > 0) {
        foreach ($sectionCatalog as $catalogRow) {
            if ((int)$catalogRow['id'] === $filterSectionId) {
                $scopeLabel = (string)$catalogRow['label'];
                break;
            }
        }
    } else {
        $scopeLabel = 'All Assigned Sections';
    }
}

SchoolYearHelper::ensureSchoolYearSupport($mysqli);
$hasMiddleInitial = studentColumnExists($mysqli, 'middle_initial');
$hasExtension = studentColumnExists($mysqli, 'extension');
$activeSchoolYear = SchoolYearHelper::getEffectiveSchoolYearRange($mysqli);
$schoolYears = SchoolYearHelper::getAllSchoolYears($mysqli);
$syStartDate = $activeSchoolYear['start_date'] ?? date('Y-01-01');
$syEndDate = $activeSchoolYear['end_date'] ?? date('Y-12-31');

$defaultDate = date('Y-m-d');
if ($defaultDate < $syStartDate) {
    $defaultDate = $syStartDate;
} elseif ($defaultDate > $syEndDate) {
    $defaultDate = $syEndDate;
}

// Get date range from filters (default to single current date)
$filter_start_date = isset($_GET['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date']) ? $_GET['start_date'] : $defaultDate;
$filter_end_date = isset($_GET['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date']) ? $_GET['end_date'] : $defaultDate;

// Ensure start date <= end date
if ($filter_start_date > $filter_end_date) {
    [$filter_start_date, $filter_end_date] = [$filter_end_date, $filter_start_date];
}

// Clamp to school year boundaries
if ($filter_start_date < $syStartDate) {
    $filter_start_date = $syStartDate;
}
if ($filter_end_date > $syEndDate) {
    $filter_end_date = $syEndDate;
}

$filter_status = trim((string)($_GET['status'] ?? ''));
$selectedExportPeriod = trim((string)($_GET['export_period'] ?? 'semester'));
if (!in_array($selectedExportPeriod, ['daily', 'weekly', 'monthly', 'semester'], true)) {
    $selectedExportPeriod = 'semester';
}

$selectedExportSchoolYearLabel = trim((string)($_GET['export_school_year'] ?? ($activeSchoolYear['label'] ?? '')));
$selectedExportDate = normalizeDateValue($_GET['export_date'] ?? $defaultDate, $defaultDate);
$selectedExportWeekStart = normalizeDateValue($_GET['export_week_start'] ?? $defaultDate, $defaultDate);
$selectedExportMonthStart = normalizeDateValue($_GET['export_month_start'] ?? $defaultDate, $defaultDate);
$selectedExportSemester = isset($_GET['semester']) ? (int)$_GET['semester'] : 1;
if (!in_array($selectedExportSemester, [1, 2], true)) {
    $selectedExportSemester = 1;
}

// Handle export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportPeriod = trim((string)($_GET['export_period'] ?? 'semester'));
    $allowedExportPeriods = ['daily', 'weekly', 'monthly', 'semester'];
    if (!in_array($exportPeriod, $allowedExportPeriods, true)) {
        $exportPeriod = 'semester';
    }

    $exportSchoolYearLabel = trim((string)($_GET['export_school_year'] ?? ($activeSchoolYear['label'] ?? '')));
    $exportSchoolYear = findSchoolYearByLabel($schoolYears, $exportSchoolYearLabel);
    if (!$exportSchoolYear) {
        $exportSchoolYear = $activeSchoolYear;
        $exportSchoolYearLabel = (string)($activeSchoolYear['label'] ?? '');
    }

    $exportDate = normalizeDateValue($_GET['export_date'] ?? $defaultDate, $defaultDate);
    $exportWeekStart = normalizeDateValue($_GET['export_week_start'] ?? $defaultDate, $defaultDate);
    $exportMonthStart = normalizeDateValue($_GET['export_month_start'] ?? $defaultDate, $defaultDate);
    $exportSemester = isset($_GET['semester']) ? (int)$_GET['semester'] : 1;
    $exportSortBy = trim((string)($_GET['export_sort_by'] ?? 'date'));
    $exportNameFormat = trim((string)($_GET['export_name_format'] ?? 'last_name_first'));

    // Validate sort_by
    $allowedSortBy = ['date', 'name'];
    if (!in_array($exportSortBy, $allowedSortBy, true)) {
        $exportSortBy = 'date';
    }

    // Validate name format
    $allowedNameFormats = ['full_name', 'last_name_first'];
    if (!in_array($exportNameFormat, $allowedNameFormats, true)) {
        $exportNameFormat = 'last_name_first';
    }

    $exportStartDate = $defaultDate;
    $exportEndDate = $defaultDate;
    $exportLabel = 'Semester';

    $semesterRanges = buildSchoolYearSemesterRanges($exportSchoolYear);
    $monthOptions = buildSchoolYearMonthOptions($exportSchoolYear);

    if ($exportPeriod === 'daily') {
        $exportStartDate = $exportDate;
        $exportEndDate = $exportDate;
        $exportLabel = 'Daily';
    } elseif ($exportPeriod === 'weekly') {
        $weekStartTs = strtotime($exportWeekStart . ' monday this week');
        if ($weekStartTs === false) {
            $weekStartTs = strtotime($exportWeekStart);
        }
        $exportStartDate = date('Y-m-d', $weekStartTs ?: strtotime($exportWeekStart));
        $exportEndDate = date('Y-m-d', strtotime($exportStartDate . ' +6 days'));
        $exportLabel = 'Weekly';
    } elseif ($exportPeriod === 'monthly') {
        $exportStartDate = date('Y-m-01', strtotime($exportMonthStart));
        $exportEndDate = date('Y-m-t', strtotime($exportStartDate));
        $exportLabel = 'Monthly';
    } elseif ($exportPeriod === 'semester') {
        if (!in_array($exportSemester, [1, 2], true)) {
            $exportSemester = 1;
        }

        if (!empty($semesterRanges[$exportSemester])) {
            $exportStartDate = (string)$semesterRanges[$exportSemester]['start'];
            $exportEndDate = (string)$semesterRanges[$exportSemester]['end'];
            $exportLabel = (string)$semesterRanges[$exportSemester]['label'];
        }
    }

    if ($exportStartDate < $syStartDate) {
        $exportStartDate = $syStartDate;
    }
    if ($exportEndDate > $syEndDate) {
        $exportEndDate = $syEndDate;
    }

    $exportMonthLabel = date('F Y', strtotime($exportStartDate));
    $exportRangeLabel = $exportStartDate . ' to ' . $exportEndDate;

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    // Build export query for date range
    $exportSql = "
        SELECT
            s.id,
            s.id AS student_pk,
            s.first_name,
            s.last_name,
            " . ($hasMiddleInitial ? "COALESCE(s.middle_initial, '')" : "''") . " AS middle_initial,
            " . ($hasExtension ? "COALESCE(s.extension, '')" : "''") . " AS extension,
            a.attendance_date,
            a.time_in_am,
            a.time_out_am,
            a.time_in_pm,
            a.time_out_pm,
            COALESCE(a.status, 'absent') AS status,
            a.notes
        FROM students s
        LEFT JOIN attendance a ON s.id = a.student_id AND a.attendance_date BETWEEN ? AND ?
        WHERE s.status = 'active'
    ";

    $types = 'ss';
    $params = [$exportStartDate, $exportEndDate];

    if ($hasSectionIdColumn && !empty($teacherSectionIds)) {
        $scopedIds = $filterSectionId > 0 ? [$filterSectionId] : $teacherSectionIds;
        $inClause = implode(',', array_fill(0, count($scopedIds), '?'));
        $exportSql .= " AND s.section_id IN ({$inClause})";
        $types .= str_repeat('i', count($scopedIds));
        $params = array_merge($params, $scopedIds);
    } else {
        $exportSql .= ' AND s.year = ? AND s.section = ?';
        $types .= 'is';
        $params[] = $teacherYear;
        $params[] = $section;
    }

    if ($filter_status !== '') {
        $exportSql .= " AND COALESCE(a.status, 'absent') = ?";
        $types .= 's';
        $params[] = $filter_status;
    }

    $exportSql .= " ORDER BY a.attendance_date ASC, s.last_name ASC, s.first_name ASC";
    $stmt = $mysqli->prepare($exportSql);
    $bindParams = [$types];
    foreach ($params as $index => $value) {
        $bindParams[] = &$params[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindParams);
    $stmt->execute();
    $exportResult = $stmt->get_result();
    
    // Collect export data and sort as needed
    $exportData = [];
    while ($row = $exportResult->fetch_assoc()) {
        $exportData[] = $row;
    }

    // Sort export data
    if ($exportSortBy === 'name') {
        usort($exportData, function($a, $b) {
            $nameA = formatAttendanceHistoryName($a);
            $nameB = formatAttendanceHistoryName($b);
            $cmp = strcasecmp($nameA, $nameB);
            return $cmp !== 0 ? $cmp : ((int)($a['student_pk'] ?? 0) <=> (int)($b['student_pk'] ?? 0));
        });
    }

    // Format name based on format selection
    $formatName = function($row) use ($exportNameFormat) {
        $first = trim((string)($row['first_name'] ?? ''));
        $middle = trim((string)($row['middle_initial'] ?? ''));
        $last = trim((string)($row['last_name'] ?? ''));
        $ext = trim((string)($row['extension'] ?? ''));
        $middleToken = $middle !== '' ? strtoupper(substr($middle, 0, 1)) . '.' : '';

        if ($exportNameFormat === 'full_name') {
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
    };

    $scopeFileToken = preg_replace('/[^a-z0-9_-]+/i', '_', strtolower($scopeLabel));
    $scopeFileToken = $scopeFileToken !== '' ? $scopeFileToken : 'teacher_scope';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . rawurlencode($scopeFileToken) . '_attendance_' . $exportPeriod . '_' . $exportStartDate . '_to_' . $exportEndDate . '.csv"');

    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Attendance Export']);
    fputcsv($output, ['Period', ucfirst($exportPeriod)]);
    fputcsv($output, ['School Year', $exportSchoolYearLabel !== '' ? $exportSchoolYearLabel : '-']);
    fputcsv($output, ['Coverage', $exportLabel]);
    fputcsv($output, ['Date Range', $exportRangeLabel]);
    fputcsv($output, ['Scope', $scopeLabel]);
    fputcsv($output, ['Sorted By', ucfirst($exportSortBy)]);
    fputcsv($output, []);
    fputcsv($output, ['#', 'Name', 'Date', 'AM In', 'AM Out', 'PM In', 'PM Out', 'Status', 'Notes']);

    $rowNum = 1;
    foreach ($exportData as $row) {
        $date = $row['attendance_date'] ?? null;
        if (!$date) {
            $date = $exportStartDate;
        }
        fputcsv($output, [
            $rowNum++,
            $formatName($row),
            $date,
            $row['time_in_am'] ?: '',
            $row['time_out_am'] ?: '',
            $row['time_in_pm'] ?: '',
            $row['time_out_pm'] ?: '',
            $row['status'],
            $row['notes'] ?? ''
        ]);
    }

    fclose($output);
    exit;
}

// Fetch records for display - for the initial display, show records for the date range
$query = "
    SELECT
        s.id,
        s.id AS student_pk,
        s.first_name,
        s.last_name,
        " . ($hasMiddleInitial ? "COALESCE(s.middle_initial, '')" : "''") . " AS middle_initial,
        " . ($hasExtension ? "COALESCE(s.extension, '')" : "''") . " AS extension,
        a.attendance_date,
        a.time_in_am,
        a.time_out_am,
        a.time_in_pm,
        a.time_out_pm,
        COALESCE(a.status, 'absent') AS status,
        a.notes
    FROM students s
    LEFT JOIN attendance a ON s.id = a.student_id AND a.attendance_date BETWEEN ? AND ?
    WHERE s.status = 'active'
";

$types = 'ss';
$params = [$filter_start_date, $filter_end_date];

if ($hasSectionIdColumn && !empty($teacherSectionIds)) {
    $scopedIds = $filterSectionId > 0 ? [$filterSectionId] : $teacherSectionIds;
    $inClause = implode(',', array_fill(0, count($scopedIds), '?'));
    $query .= " AND s.section_id IN ({$inClause})";
    $types .= str_repeat('i', count($scopedIds));
    $params = array_merge($params, $scopedIds);
} else {
    $query .= ' AND s.year = ? AND s.section = ?';
    $types .= 'is';
    $params[] = $teacherYear;
    $params[] = $section;
}

if ($filter_status !== '') {
    $query .= " AND COALESCE(a.status, 'absent') = ?";
    $types .= 's';
    $params[] = $filter_status;
}

$query .= " ORDER BY a.attendance_date DESC, s.last_name ASC, s.first_name ASC";

$records = [];
$stmt = $mysqli->prepare($query);
if ($stmt) {
    $bindParams = [$types];
    foreach ($params as $index => $value) {
        $bindParams[] = &$params[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindParams);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    $stmt->close();
}

// Summary for the date range
$summary = [];
$summarySql = "
    SELECT COALESCE(a.status, 'absent') AS status, COUNT(*) AS count
    FROM students s
    LEFT JOIN attendance a ON s.id = a.student_id AND a.attendance_date BETWEEN ? AND ?
    WHERE s.status = 'active'
    GROUP BY COALESCE(a.status, 'absent')
";
$summaryTypes = 'ss';
$summaryParams = [$filter_start_date, $filter_end_date];

if ($hasSectionIdColumn && !empty($teacherSectionIds)) {
    $scopedIds = $filterSectionId > 0 ? [$filterSectionId] : $teacherSectionIds;
    $inClause = implode(',', array_fill(0, count($scopedIds), '?'));
    $summarySql = str_replace('GROUP BY', "AND s.section_id IN ({$inClause}) GROUP BY", $summarySql);
    $summaryTypes .= str_repeat('i', count($scopedIds));
    $summaryParams = array_merge($summaryParams, $scopedIds);
} else {
    $summarySql = str_replace('GROUP BY', 'AND s.year = ? AND s.section = ? GROUP BY', $summarySql);
    $summaryTypes .= 'is';
    $summaryParams[] = $teacherYear;
    $summaryParams[] = $section;
}

$summaryStmt = $mysqli->prepare($summarySql);
if ($summaryStmt) {
    $bind = [$summaryTypes];
    foreach ($summaryParams as $index => $value) {
        $bind[] = &$summaryParams[$index];
    }
    call_user_func_array([$summaryStmt, 'bind_param'], $bind);
    $summaryStmt->execute();
    $seg_result = $summaryStmt->get_result();
    while ($row = $seg_result->fetch_assoc()) {
        $summary[$row['status']] = $row['count'];
    }
    $summaryStmt->close();
}

require '../includes/header.php';
?>

<div class="container-fluid">
    <div class="alert alert-info mb-3 py-2 small">
        <i class="bi bi-mortarboard"></i>
        Active School Year: <strong><?php echo htmlspecialchars($activeSchoolYear['label'] ?? 'N/A'); ?></strong>
        (<?php echo htmlspecialchars($syStartDate); ?> to <?php echo htmlspecialchars($syEndDate); ?>)
        | Scope: <strong><?php echo htmlspecialchars($scopeLabel); ?></strong>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body py-3">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-2 col-6">
                            <label class="form-label form-label-sm mb-1">From Date</label>
                            <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_start_date); ?>" min="<?php echo htmlspecialchars($syStartDate); ?>" max="<?php echo htmlspecialchars($syEndDate); ?>">
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="form-label form-label-sm mb-1">To Date</label>
                            <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_end_date); ?>" min="<?php echo htmlspecialchars($syStartDate); ?>" max="<?php echo htmlspecialchars($syEndDate); ?>">
                        </div>
                        <div class="col-md-2 col-6">
                            <label class="form-label form-label-sm mb-1">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">All</option>
                                <option value="present" <?php echo $filter_status === 'present' ? 'selected' : ''; ?>>Present</option>
                                <option value="late" <?php echo $filter_status === 'late' ? 'selected' : ''; ?>>Late</option>
                                <option value="absent" <?php echo $filter_status === 'absent' ? 'selected' : ''; ?>>Absent</option>
                                <option value="excused" <?php echo $filter_status === 'excused' ? 'selected' : ''; ?>>Excused</option>
                            </select>
                        </div>
                        <?php if ($hasSectionIdColumn && !empty($sectionCatalog)): ?>
                            <div class="col-md-3 col-6">
                                <label class="form-label form-label-sm mb-1">Section Scope</label>
                                <select name="section_id" class="form-select form-select-sm">
                                    <option value="0" <?php echo $filterSectionId === 0 ? 'selected' : ''; ?>>All Assigned Sections</option>
                                    <?php foreach ($sectionCatalog as $catalogRow): ?>
                                        <option value="<?php echo (int)$catalogRow['id']; ?>" <?php echo $filterSectionId === (int)$catalogRow['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars((string)$catalogRow['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="col-md-2 col-6">
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="bi bi-search"></i> Filter
                            </button>
                        </div>
                        <div class="col-md-3 col-12 text-md-end">
                            <button type="button" class="btn btn-outline-success btn-sm w-100" data-bs-toggle="modal" data-bs-target="#exportModal">
                                <i class="bi bi-download"></i> Export CSV
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-muted tiny">Present</h5>
                    <h3 style="color: #10b981;"><?php echo $summary['present'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-muted tiny">Late</h5>
                    <h3 style="color: #f59e0b;"><?php echo $summary['late'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-muted tiny">Absent</h5>
                    <h3 style="color: #ef4444;"><?php echo $summary['absent'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-muted tiny">Excused</h5>
                    <h3 style="color: #8b5cf6;"><?php echo $summary['excused'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Attendance Records</h5>
                </div>
                <div class="card-body">
                    <?php if (count($records) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Name</th>
                                        <th>Date</th>
                                        <th>AM In</th>
                                        <th>AM Out</th>
                                        <th>PM In</th>
                                        <th>PM Out</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $rowNumber = 1; foreach ($records as $record): ?>
                                        <tr>
                                            <td><?php echo $rowNumber++; ?></td>
                                            <td><?php echo htmlspecialchars(formatAttendanceHistoryName($record)); ?></td>
                                            <td><?php echo date('M d, Y', strtotime((string)$record['attendance_date'])); ?></td>
                                            <td><?php echo $record['time_in_am'] ? date('H:i', strtotime($record['time_in_am'])) : '-'; ?></td>
                                            <td><?php echo $record['time_out_am'] ? date('H:i', strtotime($record['time_out_am'])) : '-'; ?></td>
                                            <td><?php echo $record['time_in_pm'] ? date('H:i', strtotime($record['time_in_pm'])) : '-'; ?></td>
                                            <td><?php echo $record['time_out_pm'] ? date('H:i', strtotime($record['time_out_pm'])) : '-'; ?></td>
                                            <td>
                                                <span class="badge <?php
                                                    if ($record['status'] === 'present') echo 'badge-success';
                                                    elseif ($record['status'] === 'late') echo 'badge-warning';
                                                    elseif ($record['status'] === 'absent') echo 'badge-danger';
                                                    else echo 'badge-secondary';
                                                ?>">
                                                    <?php echo ucfirst($record['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> No attendance records found
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-download"></i> Export Attendance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="GET">
                <div class="modal-body">
                    <input type="hidden" name="export" value="csv">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
                    <input type="hidden" name="section_id" value="<?php echo (int)$filterSectionId; ?>">

                    <div class="row g-3">
                        <div class="col-12">
                            <h6 class="mb-3"><i class="bi bi-calendar-range"></i> Coverage</h6>

                            <div class="mb-3">
                                <label for="export_period" class="form-label">Export Period</label>
                                <select class="form-select" id="export_period" name="export_period">
                                    <option value="daily" <?php echo $selectedExportPeriod === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                    <option value="weekly" <?php echo $selectedExportPeriod === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                    <option value="monthly" <?php echo $selectedExportPeriod === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                    <option value="semester" <?php echo $selectedExportPeriod === 'semester' ? 'selected' : ''; ?>>Per Semester</option>
                                </select>
                            </div>

                            <div class="mb-3" id="exportSchoolYearGroup">
                                <label for="export_school_year" class="form-label">School Year</label>
                                <select class="form-select" id="export_school_year" name="export_school_year">
                                    <?php foreach ($schoolYears as $schoolYear): ?>
                                        <?php $schoolYearLabel = (string)($schoolYear['label'] ?? ''); ?>
                                        <option value="<?php echo htmlspecialchars($schoolYearLabel); ?>" <?php echo $selectedExportSchoolYearLabel === $schoolYearLabel ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($schoolYearLabel); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3" id="exportDailyGroup" style="display:none;">
                                <label for="export_date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="export_date" name="export_date" value="<?php echo htmlspecialchars($selectedExportDate); ?>">
                            </div>

                            <div class="mb-3" id="exportWeeklyGroup" style="display:none;">
                                <label for="export_week_start" class="form-label">Week Start</label>
                                <input type="date" class="form-control" id="export_week_start" name="export_week_start" value="<?php echo htmlspecialchars($selectedExportWeekStart); ?>">
                                <small class="text-muted">Export will include the 7-day span starting from this date.</small>
                            </div>

                            <div class="mb-3" id="exportMonthlyGroup" style="display:none;">
                                <label for="export_month_start" class="form-label">Month</label>
                                <select class="form-select" id="export_month_start" name="export_month_start">
                                    <?php foreach (buildSchoolYearMonthOptions($activeSchoolYear) as $monthOption): ?>
                                        <option value="<?php echo htmlspecialchars($monthOption['value']); ?>" <?php echo $selectedExportMonthStart === $monthOption['value'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($monthOption['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3" id="exportSemesterGroup" style="display:none;">
                                <label for="semester" class="form-label">Semester</label>
                                <select class="form-select" id="semester" name="semester">
                                    <option value="1" <?php echo $selectedExportSemester === 1 ? 'selected' : ''; ?>>Semester 1</option>
                                    <option value="2" <?php echo $selectedExportSemester === 2 ? 'selected' : ''; ?>>Semester 2</option>
                                </select>
                                <small class="text-muted d-block mt-2">Semester dates are derived from the selected school year in the database.</small>
                            </div>

                            <small class="text-muted d-block mt-2">
                                Export period is validated against the selected school year from the database.
                            </small>
                        </div>

                        <div class="col-12">
                            <hr class="my-2">
                        </div>

                        <div class="col-12">
                            <h6 class="mb-3"><i class="bi bi-sort-down"></i> Sort Options</h6>
                            
                            <div class="mb-3">
                                <label for="export_sort_by" class="form-label">Sort By</label>
                                <select class="form-select" id="export_sort_by" name="export_sort_by">
                                    <option value="date">Date (Default)</option>
                                    <option value="name">Student Name</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="export_name_format" class="form-label">Name Format</label>
                                <select class="form-select" id="export_name_format" name="export_name_format">
                                    <option value="last_name_first">Last Name, First Name</option>
                                    <option value="full_name">First Name Last Name</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-download"></i> Export CSV</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const periodSelect = document.getElementById('export_period');
    const schoolYearGroup = document.getElementById('exportSchoolYearGroup');
    const dailyGroup = document.getElementById('exportDailyGroup');
    const weeklyGroup = document.getElementById('exportWeeklyGroup');
    const monthlyGroup = document.getElementById('exportMonthlyGroup');
    const semesterGroup = document.getElementById('exportSemesterGroup');

    if (!periodSelect) {
        return;
    }

    const updatePeriodControls = () => {
        const period = periodSelect.value;

        if (schoolYearGroup) {
            schoolYearGroup.style.display = '';
        }
        if (dailyGroup) {
            dailyGroup.style.display = period === 'daily' ? '' : 'none';
        }
        if (weeklyGroup) {
            weeklyGroup.style.display = period === 'weekly' ? '' : 'none';
        }
        if (monthlyGroup) {
            monthlyGroup.style.display = period === 'monthly' ? '' : 'none';
        }
        if (semesterGroup) {
            semesterGroup.style.display = period === 'semester' ? '' : 'none';
        }
    };

    periodSelect.addEventListener('change', updatePeriodControls);
    updatePeriodControls();
});
</script>

<?php require '../includes/footer.php'; /*
 * © 2026 TambyTech.
 * This source code is proprietary and confidential.
 * Any unauthorized use, copying, modification, distribution, or disclosure is strictly prohibited.
 * All rights reserved.
 */
?>