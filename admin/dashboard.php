<?php
/**
 * Dashboard Page
 * Overview of attendance system with statistics and real-time logs
 */

session_start();
require_once '../config/db.php';
require_once '../helpers/SchoolYearHelper.php';
require '../includes/header.php';

// Get today's date
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

$chartWindowStart = date('Y-m-d', strtotime($reportDate . ' -30 days'));
if ($chartWindowStart < $schoolYearStart) {
    $chartWindowStart = $schoolYearStart;
}
$chartWindowEnd = $reportDate;

function tableExists(mysqli $mysqli, string $tableName): bool
{
    $sql = 'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1';
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('s', $tableName);
    $stmt->execute();
    $result = $stmt->get_result();

    return (bool) ($result && $result->num_rows > 0);
}

function columnExists(mysqli $mysqli, string $tableName, string $columnName): bool
{
    $sql = 'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1';
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ss', $tableName, $columnName);
    $stmt->execute();
    $result = $stmt->get_result();

    return (bool) ($result && $result->num_rows > 0);
}

$hasAttendance = tableExists($mysqli, 'attendance');
$hasAttendanceLogs = tableExists($mysqli, 'attendance_logs');
$hasStudents = tableExists($mysqli, 'students');
$hasUsers = tableExists($mysqli, 'users');

$attendanceLogsUserColumn = null;
$attendanceLogsTimeColumn = null;

if ($hasAttendanceLogs) {
    if (columnExists($mysqli, 'attendance_logs', 'user_id')) {
        $attendanceLogsUserColumn = 'user_id';
    } elseif (columnExists($mysqli, 'attendance_logs', 'student_id')) {
        $attendanceLogsUserColumn = 'student_id';
    }

    if (columnExists($mysqli, 'attendance_logs', 'timestamp')) {
        $attendanceLogsTimeColumn = 'timestamp';
    } elseif (columnExists($mysqli, 'attendance_logs', 'created_at')) {
        $attendanceLogsTimeColumn = 'created_at';
    }
}

// Get statistics
$stats = [
    'total_students' => 0,
    'present' => 0,
    'late' => 0,
    'absent' => 0,
];

if ($hasStudents) {
    $result = $mysqli->query("SELECT COUNT(*) as count FROM students WHERE status = 'active'");
    $stats['total_students'] = (int) ($result->fetch_assoc()['count'] ?? 0);
} elseif ($hasUsers) {
    $result = $mysqli->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
    $stats['total_students'] = (int) ($result->fetch_assoc()['count'] ?? 0);
}

$recent_scans = [];
$dates = [];
$present_data = [];
$late_data = [];
$absent_data = [];

if ($hasAttendance) {
    $hasExplicitAbsentRowsToday = false;

    $result = $mysqli->query("\n        SELECT status, COUNT(*) as count\n        FROM attendance\n        WHERE attendance_date = '$reportDate'\n        GROUP BY status\n    ");

    while ($row = $result->fetch_assoc()) {
        if ($row['status'] === 'present') $stats['present'] = (int) $row['count'];
        if ($row['status'] === 'late') $stats['late'] = (int) $row['count'];
        if ($row['status'] === 'absent') {
            $stats['absent'] = (int) $row['count'];
            $hasExplicitAbsentRowsToday = true;
        }
    }

    $derivedAbsentToday = max(0, $stats['total_students'] - $stats['present'] - $stats['late']);
    if (!$hasExplicitAbsentRowsToday || $stats['absent'] < $derivedAbsentToday) {
        $stats['absent'] = $derivedAbsentToday;
    }

    if ($hasStudents) {
        $result = $mysqli->query("\n            SELECT\n                s.first_name,\n                s.last_name,\n                a.status,\n                a.time_in_am,\n                a.time_in_pm,\n                a.time_out_am,\n                a.time_out_pm,\n                a.attendance_date\n            FROM attendance a\n            JOIN students s ON a.student_id = s.id\n            WHERE a.attendance_date = '$reportDate'\n            ORDER BY a.updated_at DESC\n            LIMIT 10\n        ");

        while ($row = $result->fetch_assoc()) {
            $recent_scans[] = $row;
        }
    }

    $result = $mysqli->query("\n        SELECT\n            attendance_date,\n            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,\n            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,\n            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent\n        FROM attendance\n        WHERE attendance_date BETWEEN '$chartWindowStart' AND '$chartWindowEnd'\n        GROUP BY attendance_date\n        ORDER BY attendance_date ASC\n    ");

    $attendanceByDate = [];
    while ($row = $result->fetch_assoc()) {
        $dateKey = (string) ($row['attendance_date'] ?? '');
        if ($dateKey === '') {
            continue;
        }

        $attendanceByDate[$dateKey] = [
            'present' => (int) ($row['present'] ?? 0),
            'late' => (int) ($row['late'] ?? 0),
            'absent' => (int) ($row['absent'] ?? 0)
        ];
    }

    $cursor = strtotime($chartWindowStart);
    $endTs = strtotime($chartWindowEnd);
    while ($cursor !== false && $endTs !== false && $cursor <= $endTs) {
        $dateKey = date('Y-m-d', $cursor);
        $presentCount = (int) ($attendanceByDate[$dateKey]['present'] ?? 0);
        $lateCount = (int) ($attendanceByDate[$dateKey]['late'] ?? 0);
        $storedAbsent = (int) ($attendanceByDate[$dateKey]['absent'] ?? 0);
        $derivedAbsent = max(0, $stats['total_students'] - $presentCount - $lateCount);
        $absentCount = max($storedAbsent, $derivedAbsent);

        $dates[] = date('M d', $cursor);
        $present_data[] = $presentCount;
        $late_data[] = $lateCount;
        $absent_data[] = $absentCount;

        $cursor = strtotime('+1 day', $cursor);
    }
} elseif ($hasAttendanceLogs && $attendanceLogsUserColumn && $attendanceLogsTimeColumn) {
    $todayResult = $mysqli->query("\n        SELECT COUNT(DISTINCT {$attendanceLogsUserColumn}) AS present_count\n        FROM attendance_logs\n        WHERE DATE({$attendanceLogsTimeColumn}) = '$reportDate' AND type = 'IN'\n    ");
    $stats['present'] = (int) ($todayResult->fetch_assoc()['present_count'] ?? 0);
    $stats['late'] = 0;
    $stats['absent'] = max(0, $stats['total_students'] - $stats['present']);

    $nameExpr = "CONCAT('ID #', al.{$attendanceLogsUserColumn})";
    $joinClause = '';

    if ($attendanceLogsUserColumn === 'student_id' && $hasStudents) {
        $nameExpr = "COALESCE(CONCAT(s.first_name, ' ', s.last_name), CONCAT('Student #', al.student_id))";
        $joinClause = 'LEFT JOIN students s ON al.student_id = s.id';
    } elseif ($attendanceLogsUserColumn === 'user_id' && $hasUsers) {
        $nameExpr = "COALESCE(u.full_name, CONCAT('User #', al.user_id))";
        $joinClause = 'LEFT JOIN users u ON al.user_id = u.id';
    }

    $result = $mysqli->query("\n        SELECT\n            {$nameExpr} AS first_name,\n            '' AS last_name,\n            CASE WHEN al.type = 'IN' THEN 'present' ELSE 'absent' END AS status,\n            CASE WHEN HOUR(al.{$attendanceLogsTimeColumn}) < 12 THEN TIME(al.{$attendanceLogsTimeColumn}) ELSE NULL END AS time_in_am,\n            CASE WHEN HOUR(al.{$attendanceLogsTimeColumn}) >= 12 THEN TIME(al.{$attendanceLogsTimeColumn}) ELSE NULL END AS time_in_pm,\n            NULL AS time_out_am,\n            NULL AS time_out_pm,\n            DATE(al.{$attendanceLogsTimeColumn}) AS attendance_date\n        FROM attendance_logs al\n        {$joinClause}\n        WHERE DATE(al.{$attendanceLogsTimeColumn}) = '$reportDate'\n        ORDER BY al.{$attendanceLogsTimeColumn} DESC\n        LIMIT 10\n    ");

    while ($row = $result->fetch_assoc()) {
        $recent_scans[] = $row;
    }

    $result = $mysqli->query("\n        SELECT\n            DATE({$attendanceLogsTimeColumn}) AS attendance_date,\n            COUNT(DISTINCT CASE WHEN type = 'IN' THEN {$attendanceLogsUserColumn} END) AS present\n        FROM attendance_logs\n        WHERE DATE({$attendanceLogsTimeColumn}) BETWEEN '$chartWindowStart' AND '$chartWindowEnd'\n        GROUP BY DATE({$attendanceLogsTimeColumn})\n        ORDER BY DATE({$attendanceLogsTimeColumn}) ASC\n    ");

    $presentByDate = [];
    while ($row = $result->fetch_assoc()) {
        $dateKey = (string) ($row['attendance_date'] ?? '');
        if ($dateKey === '') {
            continue;
        }
        $presentByDate[$dateKey] = (int) ($row['present'] ?? 0);
    }

    $cursor = strtotime($chartWindowStart);
    $endTs = strtotime($chartWindowEnd);
    while ($cursor !== false && $endTs !== false && $cursor <= $endTs) {
        $dateKey = date('Y-m-d', $cursor);
        $presentCount = (int) ($presentByDate[$dateKey] ?? 0);

        $dates[] = date('M d', $cursor);
        $present_data[] = $presentCount;
        $late_data[] = 0;
        $absent_data[] = max(0, $stats['total_students'] - $presentCount);

        $cursor = strtotime('+1 day', $cursor);
    }
}

$chart_labels = json_encode($dates);
$chart_present = json_encode($present_data);
$chart_late = json_encode($late_data);
$chart_absent = json_encode($absent_data);
?>

<div class="alert alert-info mb-3">
    <i class="bi bi-mortarboard"></i>
    Active School Year: <strong><?php echo htmlspecialchars($activeSchoolYear['label'] ?? 'N/A'); ?></strong>
    (<?php echo htmlspecialchars($schoolYearStart); ?> to <?php echo htmlspecialchars($schoolYearEnd); ?>)
    | Report date: <strong><?php echo htmlspecialchars($reportDate); ?></strong>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card card-stat">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label">Total Students</p>
                        <h3 class="stat-number"><?php echo $stats['total_students']; ?></h3>
                    </div>
                    <i class="bi bi-people stat-icon" style="color: #667eea;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card card-stat">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label">Present Today</p>
                        <h3 class="stat-number" style="color: #000000;"><?php echo $stats['present']; ?></h3>
                    </div>
                    <i class="bi bi-check-circle stat-icon" style="color: #ff0000;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card card-stat">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label">Late Today</p>
                        <h3 class="stat-number" style="color: #ff0000;"><?php echo $stats['late']; ?></h3>
                    </div>
                    <i class="bi bi-exclamation-circle stat-icon" style="color: #000000;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card card-stat">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label">Absent Today</p>
                        <h3 class="stat-number" style="color: #000000;"><?php echo $stats['absent']; ?></h3>
                    </div>
                    <i class="bi bi-x-circle stat-icon" style="color: #ff0000;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-graph-up"></i> Last 30 Days Attendance</h5>
            </div>
            <div class="card-body">
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Today's Distribution</h5>
            </div>
            <div class="card-body">
                <canvas id="distributionChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Recent Scans -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Scans (Auto-refreshing)</h5>
        <span class="badge bg-info">Live <span class="blink"
            style="display:inline-block; width:8px; height:8px; background:#ff0000; border-radius:50%; animation:blink 1s infinite;"></span></span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="recentScansTable">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Time</th>
                        <th>Session</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_scans)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                            No scans today yet
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($recent_scans as $scan): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($scan['first_name'] . ' ' . $scan['last_name']); ?></strong>
                        </td>
                        <td>
                            <?php
                                    $status_class = 'badge bg-secondary';
                                    $status_text = $scan['status'];

                                    if ($scan['status'] === 'present') {
                                        $status_class = 'badge bg-success';
                                        $status_text = '✓ Present';
                                    } elseif ($scan['status'] === 'late') {
                                        $status_class = 'badge bg-warning';
                                        $status_text = '⚠ Late';
                                    } elseif ($scan['status'] === 'absent') {
                                        $status_class = 'badge bg-danger';
                                        $status_text = '✗ Absent';
                                    }
                                    ?>
                            <span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                        </td>
                        <td>
                            <?php
                                    $time = $scan['time_in_am'] ?? $scan['time_in_pm'] ?? 'N/A';
                                    echo date('h:i A', strtotime($time));
                                    ?>
                        </td>
                        <td>
                            <?php
                                    if (!empty($scan['time_in_am'])) {
                                        echo 'AM';
                                    } elseif (!empty($scan['time_in_pm'])) {
                                        echo 'PM';
                                    } else {
                                        echo '-';
                                    }
                                    ?>
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
.card-stat {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    transition: all 0.3s;
}

.card-stat:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 10px;
    text-transform: uppercase;
    font-size: 0.8rem;
    font-weight: 600;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: #333;
    margin: 0;
}

.stat-icon {
    font-size: 2.5rem;
    opacity: 0.2;
}

.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    margin-bottom: 20px;
}

.card-header {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    border-radius: 12px 12px 0 0;
    padding: 20px;
}

.card-header h5 {
    color: #333;
}

.card-body {
    padding: 20px;
}

.table-hover tbody tr:hover {
    background-color: #f8f9fa;
}

.blink {
    animation: blink 1s infinite;
}

@keyframes blink {

    0%,
    50% {
        opacity: 1;
    }

    51%,
    100% {
        opacity: 0.3;
    }
}
</style>

<script>
// Attendance Chart (Last 30 days)
const ctx = document.getElementById('attendanceChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo $chart_labels; ?>,
        datasets: [{
                label: 'Present',
                data: <?php echo $chart_present; ?>,
                borderColor: '#000000',
                backgroundColor: 'rgba(0, 0, 0, 0.08)',
                borderWidth: 2,
                tension: 0.4,
                fill: true
            },
            {
                label: 'Late',
                data: <?php echo $chart_late; ?>,
                borderColor: '#ff0000',
                backgroundColor: 'rgba(255, 0, 0, 0.08)',
                borderWidth: 2,
                tension: 0.4,
                fill: true
            },
            {
                label: 'Absent',
                data: <?php echo $chart_absent; ?>,
                borderColor: '#000000',
                backgroundColor: 'rgba(255, 255, 255, 0.85)',
                borderDash: [6, 4],
                borderWidth: 2,
                tension: 0.4,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    usePointStyle: true,
                    padding: 20
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 5
                }
            }
        }
    }
});

// Distribution Chart (Today's pie chart)
const ctx2 = document.getElementById('distributionChart').getContext('2d');
new Chart(ctx2, {
    type: 'doughnut',
    data: {
        labels: ['Present', 'Late', 'Absent'],
        datasets: [{
            data: [<?php echo $stats['present'] . ', ' . $stats['late'] . ', ' . $stats['absent']; ?>],
            backgroundColor: ['#000000', '#ff0000', '#ffffff'],
            borderColor: '#000000',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    usePointStyle: true,
                    padding: 20
                }
            }
        }
    }
});

// Auto-refresh recent scans every 5 seconds
setInterval(function() {
    fetch('api/get_recent_scans.php')
        .then(response => response.json())
        .then(data => {
            const tbody = document.querySelector('#recentScansTable tbody');
            tbody.innerHTML = '';

            if (data.scans.length === 0) {
                tbody.innerHTML =
                    '<tr><td colspan="4" class="text-center text-muted py-4">No scans today yet</td></tr>';
            } else {
                data.scans.forEach(scan => {
                    const statusClass = scan.status === 'present' ? 'bg-success' : scan.status ===
                        'late' ? 'bg-warning' : 'bg-danger';
                    const statusText = scan.status === 'present' ? '✓ Present' : scan.status ===
                        'late' ? '⚠ Late' : '✗ Absent';
                    const session = scan.time_in_am ? 'AM' : 'PM';
                    const time_obj = scan.time_in_am ? scan.time_in_am : scan.time_in_pm;
                    const time = new Date('1970-01-01T' + time_obj).toLocaleTimeString('en-US', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });

                    const row = `
                            <tr>
                                <td><strong>${scan.first_name} ${scan.last_name}</strong></td>
                                <td><span class="badge ${statusClass}">${statusText}</span></td>
                                <td>${time}</td>
                                <td>${session}</td>
                            </tr>
                        `;
                    tbody.innerHTML += row;
                });
            }
        })
        .catch(error => console.log('Refresh error:', error));
}, 5000);
</script>

<?php require '../includes/footer.php'; ?>