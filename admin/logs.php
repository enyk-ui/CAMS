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
        $name .= ' ' . strtolower(substr($middle, 0, 1)) . '.';
    }
    if ($ext !== '') {
        $name .= ' ' . strtolower($ext);
    }

    return strtolower(trim($name));
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

function fetchAttendanceLogs($mysqli, $startDate, $endDate, $studentFilter = '', $statusFilter = '', $limit = 1000)
{
    $types = 'ss';
    $params = [$startDate, $endDate];

    if (tableExists($mysqli, 'attendance') && tableExists($mysqli, 'students')) {
        $middleInitialExpr = columnExists($mysqli, 'students', 'middle_initial') ? 'COALESCE(s.middle_initial, "")' : '""';
        $extensionExpr = columnExists($mysqli, 'students', 'extension') ? 'COALESCE(s.extension, "")' : '""';
        $sql = "
            SELECT
                s.student_id,
                s.first_name,
                s.last_name,
                {$middleInitialExpr} AS middle_initial,
                {$extensionExpr} AS extension,
                a.attendance_date,
                a.time_in_am,
                a.time_out_am,
                a.time_in_pm,
                a.time_out_pm,
                a.status
            FROM attendance a
            JOIN students s ON a.student_id = s.id
            WHERE a.attendance_date BETWEEN ? AND ?
        ";

        if ($studentFilter !== '') {
            $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ?)";
            $studentLike = '%' . $studentFilter . '%';
            $params[] = $studentLike;
            $params[] = $studentLike;
            $params[] = $studentLike;
            $types .= 'sss';
        }

        if ($statusFilter !== '') {
            $sql .= " AND a.status = ?";
            $params[] = $statusFilter;
            $types .= 's';
        }

        $sql .= " ORDER BY a.attendance_date DESC, a.created_at DESC";
    } else {
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

        if ($idColumn === 'student_id' && $ctx['hasStudents']) {
            $joinClause = 'LEFT JOIN students s ON al.student_id = s.id';
            $studentIdExpr = 'COALESCE(s.student_id, CAST(al.student_id AS CHAR))';
            $firstNameExpr = "COALESCE(s.first_name, CONCAT('Student #', al.student_id))";
            $lastNameExpr = "COALESCE(s.last_name, '')";
            $middleInitialExpr = columnExists($mysqli, 'students', 'middle_initial') ? "COALESCE(s.middle_initial, '')" : "''";
            $extensionExpr = columnExists($mysqli, 'students', 'extension') ? "COALESCE(s.extension, '')" : "''";
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

        if ($statusFilter !== '') {
            if ($statusFilter === 'late') {
                $sql .= " AND 1 = 0";
            } elseif ($statusFilter === 'present') {
                $sql .= " AND al.type = 'IN'";
            } elseif ($statusFilter === 'absent') {
                $sql .= " AND al.type <> 'IN'";
            }
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
$legacy_date = normalizeDateValue($_GET['date'] ?? '', '');

$filter_start_date = normalizeDateValue($_GET['start_date'] ?? '', $default_start_date);
$filter_end_date = normalizeDateValue($_GET['end_date'] ?? '', $default_end_date);

if ($legacy_date !== '' && !isset($_GET['start_date']) && !isset($_GET['end_date'])) {
    $filter_start_date = $legacy_date;
    $filter_end_date = $legacy_date;
}

if ($filter_start_date > $filter_end_date) {
    [$filter_start_date, $filter_end_date] = [$filter_end_date, $filter_start_date];
}

$filter_student = trim($_GET['student'] ?? '');
$filter_status = trim($_GET['status'] ?? '');

if (($_GET['export'] ?? '') === 'csv') {
    $export_logs = fetchAttendanceLogs($mysqli, $filter_start_date, $filter_end_date, $filter_student, $filter_status, null);

    if (ob_get_length() !== false) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="attendance_logs_' . $filter_start_date . '_to_' . $filter_end_date . '.csv"');

    $fp = fopen('php://output', 'w');
    fwrite($fp, "\xEF\xBB\xBF");
    fputcsv($fp, ['Date', 'Student ID', 'Name', 'Time In (AM)', 'Time Out (AM)', 'Time In (PM)', 'Time Out (PM)', 'Status']);

    foreach ($export_logs as $log) {
        fputcsv($fp, [
            $log['attendance_date'],
            $log['student_id'],
            formatAttendanceName($log),
            $log['time_in_am'] ?? '-',
            $log['time_out_am'] ?? '-',
            $log['time_in_pm'] ?? '-',
            $log['time_out_pm'] ?? '-',
            strtoupper($log['status'])
        ]);
    }

    fclose($fp);
    exit;
}

$logs = fetchAttendanceLogs($mysqli, $filter_start_date, $filter_end_date, $filter_student, $filter_status, 1000);
$export_query = http_build_query([
    'start_date' => $filter_start_date,
    'end_date' => $filter_end_date,
    'student' => $filter_student,
    'status' => $filter_status,
], '', '&', PHP_QUERY_RFC3986);
?>

<div class="alert alert-info mb-3">
    <i class="bi bi-mortarboard"></i>
    Active School Year default: <strong><?php echo htmlspecialchars($activeSchoolYear['label'] ?? 'N/A'); ?></strong>
    (<?php echo htmlspecialchars($activeSchoolYear['start_date'] ?? ''); ?> to <?php echo htmlspecialchars($activeSchoolYear['end_date'] ?? ''); ?>)
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>">
            </div>

            <div class="col-md-3">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>">
            </div>

            <div class="col-md-3">
                <label for="student" class="form-label">Student</label>
                <input type="text" class="form-control" id="student" name="student" placeholder="Name or ID" value="<?php echo htmlspecialchars($filter_student); ?>">
            </div>

            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All</option>
                    <option value="present" <?php echo $filter_status === 'present' ? 'selected' : ''; ?>>Present</option>
                    <option value="late" <?php echo $filter_status === 'late' ? 'selected' : ''; ?>>Late</option>
                    <option value="absent" <?php echo $filter_status === 'absent' ? 'selected' : ''; ?>>Absent</option>
                </select>
            </div>

            <div class="col-md-3">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Export Buttons -->
<div class="mb-3">
    <a href="?<?php echo htmlspecialchars($export_query); ?>&export=csv" class="btn btn-outline-success">
        <i class="bi bi-file-earmark-csv"></i> Export CSV
    </a>
</div>

<!-- Logs Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Records (<?php echo count($logs); ?>)</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Time In (AM)</th>
                        <th>Time Out (AM)</th>
                        <th>Time In (PM)</th>
                        <th>Time Out (PM)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No records found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><strong><?php echo date('M d, Y', strtotime($log['attendance_date'])); ?></strong></td>
                            <td><?php echo htmlspecialchars($log['student_id']); ?></td>
                            <td><?php echo htmlspecialchars(formatAttendanceName($log)); ?></td>
                            <td><?php echo $log['time_in_am'] ? date('h:i A', strtotime($log['time_in_am'])) : '-'; ?></td>
                            <td><?php echo $log['time_out_am'] ? date('h:i A', strtotime($log['time_out_am'])) : '-'; ?></td>
                            <td><?php echo $log['time_in_pm'] ? date('h:i A', strtotime($log['time_in_pm'])) : '-'; ?></td>
                            <td><?php echo $log['time_out_pm'] ? date('h:i A', strtotime($log['time_out_pm'])) : '-'; ?></td>
                            <td>
                                <?php
                                $status_class = 'badge bg-secondary';
                                $status_text = $log['status'];

                                if ($log['status'] === 'present') {
                                    $status_class = 'badge bg-success';
                                    $status_text = '✓ Present';
                                } elseif ($log['status'] === 'late') {
                                    $status_class = 'badge bg-warning';
                                    $status_text = '⚠ Late';
                                } elseif ($log['status'] === 'absent') {
                                    $status_class = 'badge bg-danger';
                                    $status_text = '✗ Absent';
                                }
                                ?>
                                <span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
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
        padding: 20px;
    }

    .form-control, .form-select {
        border-radius: 8px;
        border: 1px solid #dee2e6;
    }
</style>

<?php require '../includes/footer.php'; ?>
