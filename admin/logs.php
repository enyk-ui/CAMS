<?php
/**
 * Attendance Logs Page
 * View, filter, and export attendance records
 */

session_start();
ob_start();
require_once '../config/db.php';
require '../includes/header.php';

function normalizeDateValue($value, $fallback)
{
    if ($value === null || $value === '') {
        return $fallback;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    return ($date && $date->format('Y-m-d') === $value) ? $value : $fallback;
}

function fetchAttendanceLogs($mysqli, $startDate, $endDate, $studentFilter = '', $statusFilter = '', $limit = 1000)
{
    $sql = "
        SELECT
            s.student_id,
            s.first_name,
            s.last_name,
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

    $types = 'ss';
    $params = [$startDate, $endDate];

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

$default_start_date = date('Y-m-d', strtotime('-30 days'));
$default_end_date = date('Y-m-d');
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
            $log['first_name'] . ' ' . $log['last_name'],
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
                            <td><?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?></td>
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
