<?php
/**
 * Attendance Logs Page
 * View, filter, and export attendance records
 */

session_start();
require_once 'config/db.php';
require 'includes/header.php';

// Get filter values
$filter_date = $_GET['date'] ?? date('Y-m-d');
$filter_student = $_GET['student'] ?? '';
$filter_status = $_GET['status'] ?? '';

// Build query
$where = [];
$where[] = "a.attendance_date >= DATE_SUB('$filter_date', INTERVAL 30 DAY)";

if ($filter_date) {
    $where[] = "DATE(a.attendance_date) = '$filter_date'";
}

if ($filter_student) {
    $where[] = "(s.first_name LIKE '%$filter_student%' OR s.last_name LIKE '%$filter_student%' OR s.student_id LIKE '%$filter_student%')";
}

if ($filter_status) {
    $where[] = "a.status = '$filter_status'";
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Get attendance records
$query = "
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
    $where_clause
    ORDER BY a.attendance_date DESC, a.created_at DESC
    LIMIT 1000
";

$result = $mysqli->query($query);
$logs = [];

while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}

// Handle export
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];

    if ($export_type === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="attendance_export_' . date('Y-m-d') . '.csv"');

        $fp = fopen('php://output', 'w');
        fputcsv($fp, ['Date', 'Student ID', 'Name', 'Time In (AM)', 'Time Out (AM)', 'Time In (PM)', 'Time Out (PM)', 'Status']);

        foreach ($logs as $log) {
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
}

// Get students for filter dropdown
$students_result = $mysqli->query("SELECT id, student_id, first_name, last_name FROM students ORDER BY first_name");
?>

<h2 class="page-title"><i class="bi bi-clock-history"></i> Attendance Logs</h2>
<p class="page-subtitle">View and manage attendance records</p>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="date" class="form-label">Date</label>
                <input type="date" class="form-control" id="date" name="date" value="<?php echo $filter_date; ?>">
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
    <a href="?date=<?php echo $filter_date; ?>&student=<?php echo $filter_student; ?>&status=<?php echo $filter_status; ?>&export=csv" class="btn btn-outline-success">
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

<?php require 'includes/footer.php'; ?>
