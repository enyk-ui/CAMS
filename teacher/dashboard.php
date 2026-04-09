<?php
/**
 * Teacher Dashboard
 * Overview of teacher's class attendance
 */

require_once '../config/db.php';
require_once '../helpers/SchoolYearHelper.php';
require '../includes/header.php';

// Verify teacher role
if ($_SESSION['role'] !== 'teacher') {
    header('Location: ../index.php?error=Unauthorized');
    exit;
}

$section = $_SESSION['teacher_section'];
$today = date('Y-m-d');

SchoolYearHelper::ensureSchoolYearSupport($mysqli);
$activeSchoolYear = SchoolYearHelper::getEffectiveSchoolYearRange($mysqli);
$schoolYearStart = $activeSchoolYear['start_date'] ?? date('Y-01-01');
$schoolYearEnd = $activeSchoolYear['end_date'] ?? date('Y-12-31');

$reportDate = $today;
if ($reportDate < $schoolYearStart) {
    $reportDate = $schoolYearStart;
} elseif ($reportDate > $schoolYearEnd) {
    $reportDate = $schoolYearEnd;
}

// Get statistics for teacher's section
$stats = [];

// Total students in section
$result = $mysqli->query("
    SELECT COUNT(*) as count FROM students
    WHERE status = 'active' AND section = '$section'
");
$stats['total_students'] = $result->fetch_assoc()['count'] ?? 0;

// Today's attendance in section
$result = $mysqli->query("
    SELECT
        COUNT(DISTINCT a.student_id) as present_count,
        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count
    FROM attendance a
    INNER JOIN students s ON a.student_id = s.id
    WHERE a.attendance_date = '$reportDate' AND s.section = '$section'
");

$row = $result->fetch_assoc();
$stats['present_today'] = $row['present_count'] ?? 0;
$stats['late_today'] = $row['late_count'] ?? 0;
$stats['absent_today'] = $row['absent_count'] ?? 0;

// Calculate percentage present
$stats['attendance_rate'] = $stats['total_students'] > 0
    ? round(($stats['present_today'] / $stats['total_students']) * 100, 1)
    : 0;

// Recent attendance records
$recent = [];
$result = $mysqli->query("
    SELECT
        s.student_id,
        s.first_name,
        s.last_name,
        a.status,
        a.time_in_am,
        a.attendance_date
    FROM attendance a
    INNER JOIN students s ON a.student_id = s.id
    WHERE s.section = '$section'
    AND a.attendance_date BETWEEN '$schoolYearStart' AND '$schoolYearEnd'
    ORDER BY a.attendance_date DESC, a.created_at DESC
    LIMIT 10
");

while ($row = $result->fetch_assoc()) {
    $recent[] = $row;
}
?>

<div class="container-fluid">
    <div class="alert alert-info mb-3">
        <i class="bi bi-mortarboard"></i>
        Active School Year: <strong><?php echo htmlspecialchars($activeSchoolYear['label'] ?? 'N/A'); ?></strong>
        (<?php echo htmlspecialchars($schoolYearStart); ?> to <?php echo htmlspecialchars($schoolYearEnd); ?>)
        | Report date: <strong><?php echo htmlspecialchars($reportDate); ?></strong>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-0" style="font-size: 0.85rem;">Total Students</p>
                            <h3 class="mb-0" style="color: #3b82f6;"><?php echo $stats['total_students']; ?></h3>
                        </div>
                        <i class="bi bi-people" style="font-size: 2rem; color: #3b82f6; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-0" style="font-size: 0.85rem;">Present Today</p>
                            <h3 class="mb-0" style="color: #10b981;"><?php echo $stats['present_today']; ?></h3>
                        </div>
                        <i class="bi bi-check-circle" style="font-size: 2rem; color: #10b981; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-0" style="font-size: 0.85rem;">Late Today</p>
                            <h3 class="mb-0" style="color: #f59e0b;"><?php echo $stats['late_today']; ?></h3>
                        </div>
                        <i class="bi bi-clock" style="font-size: 2rem; color: #f59e0b; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-0" style="font-size: 0.85rem;">Attendance Rate</p>
                            <h3 class="mb-0" style="color: #2563eb;"><?php echo $stats['attendance_rate']; ?>%</h3>
                        </div>
                        <i class="bi bi-percent" style="font-size: 2rem; color: #2563eb; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Attendance -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Attendance Records</h5>
                </div>
                <div class="card-body">
                    <?php if (count($recent) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Date</th>
                                        <th>Time In</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent as $record): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($record['student_id']); ?></td>
                                            <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($record['attendance_date'])); ?></td>
                                            <td><?php echo $record['time_in_am'] ? date('H:i', strtotime($record['time_in_am'])) : '-'; ?></td>
                                            <td>
                                                <span class="badge <?php
                                                    if ($record['status'] === 'present') echo 'badge-success';
                                                    elseif ($record['status'] === 'late') echo 'badge-warning';
                                                    else echo 'badge-danger';
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
                            <i class="bi bi-info-circle"></i> No attendance records yet
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
