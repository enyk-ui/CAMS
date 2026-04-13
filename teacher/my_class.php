<?php
/**
 * My Class - Teacher View
 * List of students in teacher's section
 */

require_once '../config/db.php';
require '../includes/header.php';

header('Location: attendance_report.php');
exit;

function studentColumnExists(mysqli $mysqli, string $columnName): bool
{
    $safeColumn = $mysqli->real_escape_string($columnName);
    $result = $mysqli->query("SHOW COLUMNS FROM students LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

function tableExists(mysqli $mysqli, string $tableName): bool
{
    $safeTable = $mysqli->real_escape_string($tableName);
    $result = $mysqli->query("SHOW TABLES LIKE '{$safeTable}'");
    return $result && $result->num_rows > 0;
}

function columnExists(mysqli $mysqli, string $tableName, string $columnName): bool
{
    $safeTable = $mysqli->real_escape_string($tableName);
    $safeColumn = $mysqli->real_escape_string($columnName);
    $result = $mysqli->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
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

    $ids = array_values(array_map('intval', $sectionIds));
    $inClause = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT id, name, year_grade FROM sections WHERE id IN ({$inClause}) ORDER BY CAST(year_grade AS UNSIGNED) ASC, name ASC";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $types = str_repeat('i', count($ids));
    $bind = [$types];
    foreach ($ids as $index => $value) {
        $bind[] = &$ids[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);

    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => (int)($row['id'] ?? 0),
            'name' => trim((string)($row['name'] ?? '')),
            'year_grade' => trim((string)($row['year_grade'] ?? '')),
        ];
    }
    $stmt->close();

    return $rows;
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

function formatStudentName(array $student): string
{
    $first = trim((string) ($student['first_name'] ?? ''));
    $middle = trim((string) ($student['middle_initial'] ?? ''));
    $last = trim((string) ($student['last_name'] ?? ''));
    $ext = trim((string) ($student['extension'] ?? ''));

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

function formatDisplayTime(?string $timeValue): string
{
    $timeValue = trim((string)$timeValue);
    if ($timeValue === '' || $timeValue === '00:00:00') {
        return '-';
    }

    $timestamp = strtotime($timeValue);
    if ($timestamp === false) {
        return '-';
    }

    return date('h:i A', $timestamp);
}

function resolveDisplayTimeIn(array $record): string
{
    $am = trim((string)($record['time_in_am'] ?? ''));
    $pm = trim((string)($record['time_in_pm'] ?? ''));
    if ($am !== '' && $am !== '00:00:00') {
        return formatDisplayTime($am);
    }
    if ($pm !== '' && $pm !== '00:00:00') {
        return formatDisplayTime($pm);
    }

    return '-';
}

function resolveDisplayTimeOut(array $record): string
{
    $am = trim((string)($record['time_out_am'] ?? ''));
    $pm = trim((string)($record['time_out_pm'] ?? ''));
    if ($pm !== '' && $pm !== '00:00:00') {
        return formatDisplayTime($pm);
    }
    if ($am !== '' && $am !== '00:00:00') {
        return formatDisplayTime($am);
    }

    return '-';
}

function resolveTargetAttendanceDate(mysqli $mysqli, array $sectionIds, int $selectedSectionId, string $legacySection, int $legacyYear): array
{
    $today = date('Y-m-d');
    $todayDow = (int)date('N');
    $scheduleTableReady = tableExists($mysqli, 'teacher_daily_schedules')
        && columnExists($mysqli, 'teacher_daily_schedules', 'section_id')
        && columnExists($mysqli, 'teacher_daily_schedules', 'day_of_week');

    $scopeSectionIds = $selectedSectionId > 0 ? [$selectedSectionId] : $sectionIds;
    $scopeHasSectionIds = !empty($scopeSectionIds);
    $scopeCsv = $scopeHasSectionIds ? implode(',', array_map('intval', $scopeSectionIds)) : '';

    $hasTodaySchedule = false;
    if ($scheduleTableReady && $scopeHasSectionIds) {
        $scheduleSql = "SELECT id FROM teacher_daily_schedules WHERE section_id IN ({$scopeCsv}) AND day_of_week = {$todayDow} LIMIT 1";
        $scheduleResult = $mysqli->query($scheduleSql);
        $hasTodaySchedule = (bool)($scheduleResult && $scheduleResult->fetch_assoc());
    }

    $todayRecordSql = "
        SELECT a.attendance_date
        FROM attendance a
        JOIN students s ON s.id = a.student_id
        WHERE a.attendance_date = ?
    ";
    $types = 's';
    $params = [$today];
    if ($scopeHasSectionIds && columnExists($mysqli, 'students', 'section_id')) {
        $todayRecordSql .= " AND s.section_id IN ({$scopeCsv})";
    } else {
        $todayRecordSql .= " AND s.section = ? AND s.year = ?";
        $types .= 'si';
        $params[] = $legacySection;
        $params[] = $legacyYear;
    }
    $todayRecordSql .= ' LIMIT 1';
    $todayStmt = $mysqli->prepare($todayRecordSql);
    if ($todayStmt) {
        $bind = [$types];
        foreach ($params as $index => $value) {
            $bind[] = &$params[$index];
        }
        call_user_func_array([$todayStmt, 'bind_param'], $bind);
        $todayStmt->execute();
        $hasTodayRecords = (bool)$todayStmt->get_result()->fetch_assoc();
        $todayStmt->close();
    } else {
        $hasTodayRecords = false;
    }

    if ($hasTodaySchedule && $hasTodayRecords) {
        return ['date' => $today, 'is_today' => true];
    }

    $latestSql = "
        SELECT MAX(a.attendance_date) AS target_date
        FROM attendance a
        JOIN students s ON s.id = a.student_id
        WHERE a.attendance_date <= ?
    ";
    $latestTypes = 's';
    $latestParams = [$today];

    if ($scopeHasSectionIds && columnExists($mysqli, 'students', 'section_id')) {
        $latestSql .= " AND s.section_id IN ({$scopeCsv})";
        if ($scheduleTableReady) {
            $latestSql .= "
                AND EXISTS (
                    SELECT 1
                    FROM teacher_daily_schedules tds
                    WHERE tds.section_id = s.section_id
                      AND tds.day_of_week = (WEEKDAY(a.attendance_date) + 1)
                )
            ";
        }
    } else {
        $latestSql .= ' AND s.section = ? AND s.year = ?';
        $latestTypes .= 'si';
        $latestParams[] = $legacySection;
        $latestParams[] = $legacyYear;
    }

    $latestStmt = $mysqli->prepare($latestSql);
    $targetDate = '';
    if ($latestStmt) {
        $latestBind = [$latestTypes];
        foreach ($latestParams as $index => $value) {
            $latestBind[] = &$latestParams[$index];
        }
        call_user_func_array([$latestStmt, 'bind_param'], $latestBind);
        $latestStmt->execute();
        $latestRow = $latestStmt->get_result()->fetch_assoc();
        $targetDate = trim((string)($latestRow['target_date'] ?? ''));
        $latestStmt->close();
    }

    if ($targetDate !== '') {
        return ['date' => $targetDate, 'is_today' => false];
    }

    return ['date' => $today, 'is_today' => true];
}

if ($_SESSION['role'] !== 'teacher') {
    header('Location: ../index.php?error=Unauthorized');
    exit;
}

$teacherAssignment = resolveTeacherAssignment($mysqli);
$year = (int)($teacherAssignment['year_level'] ?? 0);
$section = (string)($teacherAssignment['section'] ?? '');
$hasSectionIdColumn = studentColumnExists($mysqli, 'section_id');
$teacherSectionIds = resolveTeacherSectionIds($mysqli);
$sectionCatalog = ($hasSectionIdColumn && !empty($teacherSectionIds))
    ? fetchTeacherSectionCatalog($mysqli, $teacherSectionIds)
    : [];
$selectedSectionId = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
if ($selectedSectionId > 0 && !in_array($selectedSectionId, $teacherSectionIds, true)) {
    $selectedSectionId = 0;
}
$scopeLabel = 'Year ' . $year . ' - Section ' . $section;
if ($hasSectionIdColumn && !empty($sectionCatalog)) {
    $scopeLabel = 'All Assigned Sections';
    if ($selectedSectionId > 0) {
        foreach ($sectionCatalog as $catalogRow) {
            if ((int)$catalogRow['id'] === $selectedSectionId) {
                $scopeLabel = trim((string)$catalogRow['year_grade'] . ' - ' . (string)$catalogRow['name']);
                break;
            }
        }
    }
}
$teacherSectionIdCsv = implode(',', array_map('intval', $teacherSectionIds));

if ($hasSectionIdColumn && empty($teacherSectionIds) && ($year <= 0 || $section === '')) {
    header('Location: ../index.php?error=Teacher assignment missing');
    exit;
}

if (!$hasSectionIdColumn && ($year <= 0 || $section === '')) {
    header('Location: ../index.php?error=Teacher assignment missing');
    exit;
}

$hasMiddleInitialColumn = studentColumnExists($mysqli, 'middle_initial');
$hasExtensionColumn = studentColumnExists($mysqli, 'extension');
$middleInitialExpr = $hasMiddleInitialColumn ? 'COALESCE(middle_initial, "")' : '""';
$extensionExpr = $hasExtensionColumn ? 'COALESCE(extension, "")' : '""';
$targetDateMeta = resolveTargetAttendanceDate($mysqli, $teacherSectionIds, $selectedSectionId, $section, $year);
$targetDate = (string)($targetDateMeta['date'] ?? date('Y-m-d'));
$isTodayTarget = (bool)($targetDateMeta['is_today'] ?? false);

// Get attendance records for the resolved target date.
$attendanceRecords = [];
$sql = "
    SELECT
        s.id,
        s.first_name,
        s.last_name,
        {$middleInitialExpr} AS middle_initial,
        {$extensionExpr} AS extension,
        s.year,
        s.status,
        a.attendance_date,
        a.time_in_am,
        a.time_out_am,
        a.time_in_pm,
        a.time_out_pm,
        a.status AS attendance_status
    FROM students s
    JOIN attendance a ON a.student_id = s.id
";

if ($hasSectionIdColumn && $teacherSectionIdCsv !== '') {
    $sectionIdsForQuery = $selectedSectionId > 0 ? [(int)$selectedSectionId] : $teacherSectionIds;
    $sectionCsv = implode(',', array_map('intval', $sectionIdsForQuery));
    $sql .= " WHERE s.section_id IN ({$sectionCsv}) AND a.attendance_date = ? ORDER BY s.last_name ASC, s.first_name ASC";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('s', $targetDate);
} else {
    $sql .= " WHERE s.section = ? AND s.year = ? AND a.attendance_date = ? ORDER BY s.last_name ASC, s.first_name ASC";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('sis', $section, $year, $targetDate);
}
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $attendanceRecords[] = $row;
}
$stmt->close();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Attendance in <?php echo htmlspecialchars($scopeLabel); ?></h5>
                        <span class="badge bg-primary"><?php echo count($attendanceRecords); ?> records</span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($hasSectionIdColumn && !empty($sectionCatalog)): ?>
                        <form method="GET" class="row g-2 align-items-end mb-3">
                            <div class="col-md-4 col-sm-6">
                                <label for="section_id" class="form-label">Filter Section</label>
                                <select class="form-select" id="section_id" name="section_id">
                                    <option value="0" <?php echo $selectedSectionId === 0 ? 'selected' : ''; ?>>All Assigned Sections</option>
                                    <?php foreach ($sectionCatalog as $catalogRow): ?>
                                        <option value="<?php echo (int)$catalogRow['id']; ?>" <?php echo $selectedSectionId === (int)$catalogRow['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(trim((string)$catalogRow['year_grade'] . ' - ' . (string)$catalogRow['name'])); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 col-sm-4">
                                <button type="submit" class="btn btn-primary w-100">Apply</button>
                            </div>
                            <div class="col-md-2 col-sm-4">
                                <a href="my_class.php" class="btn btn-outline-secondary w-100">Reset</a>
                            </div>
                        </form>
                    <?php endif; ?>

                    <div class="alert alert-info py-2">
                        <?php if ($isTodayTarget): ?>
                            Showing today's scheduled records (<?php echo htmlspecialchars(date('M d, Y', strtotime($targetDate))); ?>)
                        <?php else: ?>
                            Showing <?php echo htmlspecialchars(date('M d, Y', strtotime($targetDate))); ?> records
                        <?php endif; ?>
                    </div>

                    <?php if (count($attendanceRecords) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Year</th>
                                        <th>Status</th>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendanceRecords as $student): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(formatStudentName($student)); ?></td>
                                            <td><?php echo $student['year'] ?? '-'; ?></td>
                                            <td>
                                                <?php $attendanceStatus = strtolower((string)($student['attendance_status'] ?? '')); ?>
                                                <span class="badge <?php echo $attendanceStatus === 'present' ? 'bg-success' : ($attendanceStatus === 'late' ? 'bg-warning text-dark' : 'bg-secondary'); ?>">
                                                    <?php echo ucfirst($attendanceStatus !== '' ? $attendanceStatus : 'unknown'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars(resolveDisplayTimeIn($student)); ?></td>
                                            <td><?php echo htmlspecialchars(resolveDisplayTimeOut($student)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> 
                            <strong>No attendance records found</strong> for <?php echo htmlspecialchars(date('M d, Y', strtotime($targetDate))); ?> in this section.
                            <br><small class="text-muted">This may indicate: (1) No attendance data has been recorded for this date, or (2) No students are enrolled in this section for the selected time period.</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; /*
 * � 2026 TambyTech.
 * This source code is proprietary and confidential.
 * Any unauthorized use, copying, modification, distribution, or disclosure is strictly prohibited.
 * All rights reserved.
 */
?>