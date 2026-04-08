<?php
/**
 * Dashboard Page
 * Overview of attendance system with statistics and real-time logs
 */

session_start();
require_once '../config/db.php';
require '../includes/header.php';

// Get today's date
$today = date('Y-m-d');

// Get statistics
$stats = [];

// Total students
$result = $mysqli->query("SELECT COUNT(*) as count FROM students WHERE status = 'active'");
$stats['total_students'] = $result->fetch_assoc()['count'] ?? 0;

// Attendance today
$result = $mysqli->query("
    SELECT
        status,
        COUNT(*) as count
    FROM attendance
    WHERE attendance_date = '$today'
    GROUP BY status
");

$stats['present'] = 0;
$stats['late'] = 0;
$stats['absent'] = 0;

while ($row = $result->fetch_assoc()) {
    if ($row['status'] === 'present') $stats['present'] = $row['count'];
    if ($row['status'] === 'late') $stats['late'] = $row['count'];
    if ($row['status'] === 'absent') $stats['absent'] = $row['count'];
}

// Recent scans
$recent_scans = [];
$result = $mysqli->query("
    SELECT
        s.first_name,
        s.last_name,
        a.status,
        a.time_in_am,
        a.time_in_pm,
        a.time_out_am,
        a.time_out_pm,
        a.attendance_date
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    WHERE a.attendance_date = '$today'
    ORDER BY a.updated_at DESC
    LIMIT 10
");

while ($row = $result->fetch_assoc()) {
    $recent_scans[] = $row;
}

// Get daily attendance for chart (last 30 days)
$chart_data = [];
$result = $mysqli->query("
    SELECT
        attendance_date,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent
    FROM attendance
    WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY attendance_date
    ORDER BY attendance_date ASC
");

$dates = [];
$present_data = [];
$late_data = [];
$absent_data = [];

while ($row = $result->fetch_assoc()) {
    $dates[] = date('M d', strtotime($row['attendance_date']));
    $present_data[] = $row['present'] ?? 0;
    $late_data[] = $row['late'] ?? 0;
    $absent_data[] = $row['absent'] ?? 0;
}

$chart_labels = json_encode($dates);
$chart_present = json_encode($present_data);
$chart_late = json_encode($late_data);
$chart_absent = json_encode($absent_data);
?>

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