<?php
/**
 * Teacher Attendance Report
 * Attendance reports filtered for teacher's section
 */

require_once '../config/db.php';
require '../includes/header.php';

if ($_SESSION['role'] !== 'teacher') {
    header('Location: ../index.php?error=Unauthorized');
    exit;
}

$section = $_SESSION['teacher_section'];

// Filter parameters
$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Build query
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
        a.status,
        a.notes
    FROM students s
    LEFT JOIN attendance a ON s.id = a.student_id
    WHERE s.section = '$section' AND s.status = 'active'
";

if ($filter_date) {
    $query .= " AND (a.attendance_date = '$filter_date' OR a.attendance_date IS NULL)";
}

if ($filter_status) {
    $query .= " AND a.status = '$filter_status'";
}

$query .= " ORDER BY s.first_name, a.attendance_date DESC";

$records = [];
$result = $mysqli->query($query);

while ($row = $result->fetch_assoc()) {
    $records[] = $row;
}

// Get summary
$summary = [];
$seg_result = $mysqli->query("
    SELECT
        a.status,
        COUNT(*) as count
    FROM attendance a
    INNER JOIN students s ON a.student_id = s.id
    WHERE s.section = '$section'
    AND a.attendance_date = '$filter_date'
    GROUP BY a.status
");

while ($row = $seg_result->fetch_assoc()) {
    $summary[$row['status']] = $row['count'];
}
?>

<div class="container-fluid">
    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" class="form-control" value="<?php echo $filter_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="">All</option>
                                <option value="present" <?php echo $filter_status === 'present' ? 'selected' : ''; ?>>Present</option>
                                <option value="late" <?php echo $filter_status === 'late' ? 'selected' : ''; ?>>Late</option>
                                <option value="absent" <?php echo $filter_status === 'absent' ? 'selected' : ''; ?>>Absent</option>
                                <option value="excused" <?php echo $filter_status === 'excused' ? 'selected' : ''; ?>>Excused</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Stats -->
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

    <!-- Records Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Attendance Records</h5>
                </div>
                <div class="card-body">
                    <?php if (count($records) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
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
                                    <?php foreach ($records as $record):
                                        if (!$record['attendance_date']) continue;
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($record['student_id']); ?></td>
                                            <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($record['attendance_date'])); ?></td>
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

<?php require '../includes/footer.php'; ?>
