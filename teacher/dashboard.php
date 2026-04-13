<?php
/**
 * Teacher Dashboard
 * Overview of teacher's class attendance
 */

require_once '../config/db.php';
require_once '../helpers/SchoolYearHelper.php';
require '../includes/header.php';

function studentColumnExists(mysqli $mysqli, string $columnName): bool
{
    $safeColumn = $mysqli->real_escape_string($columnName);
    $result = $mysqli->query("SHOW COLUMNS FROM students LIKE '{$safeColumn}'");
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

function buildIntInPlaceholders(array $ids): string
{
    return implode(',', array_fill(0, count($ids), '?'));
}

function bindDynamicParams(mysqli_stmt $stmt, string $types, array &$params): void
{
    $bind = [$types];
    foreach ($params as $index => $value) {
        $bind[] = &$params[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
}

// Verify teacher role
if ($_SESSION['role'] !== 'teacher') {
    header('Location: ../index.php?error=Unauthorized');
    exit;
}

$section = trim((string)($_SESSION['teacher_section'] ?? ''));
$hasSectionIdColumn = studentColumnExists($mysqli, 'section_id');
$teacherSectionIds = resolveTeacherSectionIds($mysqli);
$teacherAnalyticsSections = [];
if ($hasSectionIdColumn && !empty($teacherSectionIds)) {
    $inClause = buildIntInPlaceholders($teacherSectionIds);
    $sectionMetaSql = "SELECT id, year_grade, name FROM sections WHERE id IN ({$inClause}) ORDER BY CAST(year_grade AS UNSIGNED) ASC, name ASC";
    $sectionMetaStmt = $mysqli->prepare($sectionMetaSql);
    if ($sectionMetaStmt) {
        $sectionMetaTypes = str_repeat('i', count($teacherSectionIds));
        $sectionMetaParams = $teacherSectionIds;
        bindDynamicParams($sectionMetaStmt, $sectionMetaTypes, $sectionMetaParams);
        $sectionMetaStmt->execute();
        $sectionMetaRes = $sectionMetaStmt->get_result();
        while ($sectionMetaRow = $sectionMetaRes->fetch_assoc()) {
            $teacherAnalyticsSections[] = [
                'id' => (int)($sectionMetaRow['id'] ?? 0),
                'label' => trim((string)($sectionMetaRow['year_grade'] ?? '') . ' - ' . (string)($sectionMetaRow['name'] ?? '')),
            ];
        }
        $sectionMetaStmt->close();
    }
}
$sectionScopeLabel = ($hasSectionIdColumn && !empty($teacherSectionIds))
    ? 'Assigned Sections'
    : ($section !== '' ? $section : 'Assigned Sections');
$today = date('Y-m-d');

SchoolYearHelper::ensureSchoolYearSupport($mysqli);
$selectedSchoolYearLabel = SchoolYearHelper::resolveSelectedSchoolYearLabel($mysqli);
$activeSchoolYear = SchoolYearHelper::getEffectiveSchoolYearRange($mysqli);
$schoolYears = SchoolYearHelper::getAllSchoolYears($mysqli);
foreach ($schoolYears as $sy) {
    if ((string)($sy['label'] ?? '') === $selectedSchoolYearLabel) {
        $activeSchoolYear = $sy;
        break;
    }
}
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
if ($hasSectionIdColumn && !empty($teacherSectionIds)) {
    $inClause = buildIntInPlaceholders($teacherSectionIds);

    $countSql = "SELECT COUNT(*) AS count FROM students WHERE status = 'active' AND section_id IN ({$inClause})";
    $countStmt = $mysqli->prepare($countSql);
    if ($countStmt) {
        $countTypes = str_repeat('i', count($teacherSectionIds));
        $countParams = $teacherSectionIds;
        bindDynamicParams($countStmt, $countTypes, $countParams);
        $countStmt->execute();
        $countRow = $countStmt->get_result()->fetch_assoc();
        $stats['total_students'] = (int)($countRow['count'] ?? 0);
        $countStmt->close();
    } else {
        $stats['total_students'] = 0;
    }

    $attendanceSql = "
        SELECT
            COUNT(DISTINCT a.student_id) AS present_count,
            SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) AS late_count,
            SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent_count
        FROM attendance a
        INNER JOIN students s ON a.student_id = s.id
        WHERE a.attendance_date = ? AND s.section_id IN ({$inClause})
    ";
    $attendanceStmt = $mysqli->prepare($attendanceSql);
    if ($attendanceStmt) {
        $attendanceTypes = 's' . str_repeat('i', count($teacherSectionIds));
        $attendanceParams = array_merge([$reportDate], $teacherSectionIds);
        bindDynamicParams($attendanceStmt, $attendanceTypes, $attendanceParams);
        $attendanceStmt->execute();
        $row = $attendanceStmt->get_result()->fetch_assoc() ?: [];
        $attendanceStmt->close();
    } else {
        $row = [];
    }

    $stats['present_today'] = (int)($row['present_count'] ?? 0);
    $stats['late_today'] = (int)($row['late_count'] ?? 0);
    $stats['absent_today'] = (int)($row['absent_count'] ?? 0);
} else {
    $countStmt = $mysqli->prepare('SELECT COUNT(*) AS count FROM students WHERE status = ? AND section = ?');
    if ($countStmt) {
        $active = 'active';
        $countStmt->bind_param('ss', $active, $section);
        $countStmt->execute();
        $countRow = $countStmt->get_result()->fetch_assoc();
        $stats['total_students'] = (int)($countRow['count'] ?? 0);
        $countStmt->close();
    } else {
        $stats['total_students'] = 0;
    }

    $attendanceStmt = $mysqli->prepare(
        'SELECT COUNT(DISTINCT a.student_id) AS present_count,
                SUM(CASE WHEN a.status = "late" THEN 1 ELSE 0 END) AS late_count,
                SUM(CASE WHEN a.status = "absent" THEN 1 ELSE 0 END) AS absent_count
         FROM attendance a
         INNER JOIN students s ON a.student_id = s.id
         WHERE a.attendance_date = ? AND s.section = ?'
    );
    if ($attendanceStmt) {
        $attendanceStmt->bind_param('ss', $reportDate, $section);
        $attendanceStmt->execute();
        $row = $attendanceStmt->get_result()->fetch_assoc() ?: [];
        $attendanceStmt->close();
    } else {
        $row = [];
    }

    $stats['present_today'] = (int)($row['present_count'] ?? 0);
    $stats['late_today'] = (int)($row['late_count'] ?? 0);
    $stats['absent_today'] = (int)($row['absent_count'] ?? 0);
}

// Calculate percentage present
$stats['attendance_rate'] = $stats['total_students'] > 0
    ? round(($stats['present_today'] / $stats['total_students']) * 100, 1)
    : 0;

// Recent attendance records
$recent = [];
if ($hasSectionIdColumn && !empty($teacherSectionIds)) {
    $inClause = buildIntInPlaceholders($teacherSectionIds);
    $recentSql = "
        SELECT
            s.id AS student_pk,
            s.first_name,
            s.last_name,
            a.status,
            a.time_in_am,
            a.time_out_am,
            a.time_in_pm,
            a.time_out_pm,
            a.attendance_date
        FROM attendance a
        INNER JOIN students s ON a.student_id = s.id
        WHERE s.section_id IN ({$inClause})
          AND a.attendance_date BETWEEN ? AND ?
        ORDER BY a.attendance_date DESC, a.created_at DESC
        LIMIT 10
    ";
    $recentStmt = $mysqli->prepare($recentSql);
    if ($recentStmt) {
        $recentTypes = str_repeat('i', count($teacherSectionIds)) . 'ss';
        $recentParams = array_merge($teacherSectionIds, [$schoolYearStart, $schoolYearEnd]);
        bindDynamicParams($recentStmt, $recentTypes, $recentParams);
        $recentStmt->execute();
        $result = $recentStmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $recent[] = $row;
        }
        $recentStmt->close();
    }
} else {
    $recentStmt = $mysqli->prepare(
                                'SELECT s.id AS student_pk, s.first_name, s.last_name, a.status, a.time_in_am, a.time_out_am, a.time_in_pm, a.time_out_pm, a.attendance_date
         FROM attendance a
         INNER JOIN students s ON a.student_id = s.id
         WHERE s.section = ?
           AND a.attendance_date BETWEEN ? AND ?
         ORDER BY a.attendance_date DESC, a.created_at DESC
         LIMIT 10'
    );
    if ($recentStmt) {
        $recentStmt->bind_param('sss', $section, $schoolYearStart, $schoolYearEnd);
        $recentStmt->execute();
        $result = $recentStmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $recent[] = $row;
        }
        $recentStmt->close();
    }
}
?>

<div class="container-fluid">
    <div class="alert alert-info mb-3">
        <i class="bi bi-mortarboard"></i>
        Active School Year: <strong><?php echo htmlspecialchars($activeSchoolYear['label'] ?? 'N/A'); ?></strong>
        (<?php echo htmlspecialchars($schoolYearStart); ?> to <?php echo htmlspecialchars($schoolYearEnd); ?>)
        | Report date: <strong><?php echo htmlspecialchars($reportDate); ?></strong>
        | Scope: <strong><?php echo htmlspecialchars($sectionScopeLabel); ?></strong>
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

    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> Attendance Analytics</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <?php if (count($teacherAnalyticsSections) > 1): ?>
                            <select id="attendanceAnalyticsSection" class="form-select form-select-sm" style="min-width: 210px;">
                                <option value="">All Assigned Sections</option>
                                <?php foreach ($teacherAnalyticsSections as $sectionOption): ?>
                                    <option value="<?php echo (int)$sectionOption['id']; ?>"><?php echo htmlspecialchars((string)$sectionOption['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div id="attendanceAnalyticsLoading" class="text-muted small mb-2" style="display:none;">
                        Loading attendance analytics...
                    </div>
                    <div id="attendanceAnalyticsEmpty" class="alert alert-light border small" style="display:none;">
                        No attendance data for selected filter.
                    </div>
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <h6 class="mb-2">Daily</h6>
                            <canvas id="attendanceChartDaily"></canvas>
                        </div>
                        <div class="col-lg-6">
                            <h6 class="mb-2">Weekly</h6>
                            <canvas id="attendanceChartWeekly"></canvas>
                        </div>
                        <div class="col-lg-6">
                            <h6 class="mb-2">Monthly</h6>
                            <canvas id="attendanceChartMonthly"></canvas>
                        </div>
                        <div class="col-lg-6">
                            <h6 class="mb-2">Semester</h6>
                            <canvas id="attendanceChartSemester"></canvas>
                        </div>
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
                                        <th>Name</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Event</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent as $record): ?>
                                        <?php
                                            $eventType = '-';
                                            $eventTimeRaw = '';
                                            if (!empty($record['time_out_pm'])) {
                                                $eventType = 'OUT';
                                                $eventTimeRaw = (string)$record['time_out_pm'];
                                            } elseif (!empty($record['time_in_pm'])) {
                                                $eventType = 'IN';
                                                $eventTimeRaw = (string)$record['time_in_pm'];
                                            } elseif (!empty($record['time_out_am'])) {
                                                $eventType = 'OUT';
                                                $eventTimeRaw = (string)$record['time_out_am'];
                                            } elseif (!empty($record['time_in_am'])) {
                                                $eventType = 'IN';
                                                $eventTimeRaw = (string)$record['time_in_am'];
                                            }
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($record['attendance_date'])); ?></td>
                                            <td><?php echo $eventTimeRaw !== '' ? date('H:i', strtotime($eventTimeRaw)) : '-'; ?></td>
                                            <td>
                                                <?php if ($eventType === 'IN'): ?>
                                                    <span class="badge bg-primary">Time In</span>
                                                <?php elseif ($eventType === 'OUT'): ?>
                                                    <span class="badge bg-secondary">Time Out</span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
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

<script>
function createTeacherAnalyticsChart(canvasId) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        return null;
    }

    return new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'Present',
                    data: [],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.10)',
                    borderWidth: 2,
                    tension: 0.35,
                    fill: true
                },
                {
                    label: 'Late',
                    data: [],
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.10)',
                    borderWidth: 2,
                    tension: 0.35,
                    fill: true
                },
                {
                    label: 'Absent',
                    data: [],
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.08)',
                    borderWidth: 2,
                    tension: 0.35,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

const teacherAnalyticsCharts = {
    daily: createTeacherAnalyticsChart('attendanceChartDaily'),
    weekly: createTeacherAnalyticsChart('attendanceChartWeekly'),
    monthly: createTeacherAnalyticsChart('attendanceChartMonthly'),
    semester: createTeacherAnalyticsChart('attendanceChartSemester')
};

const teacherAnalyticsSectionSelect = document.getElementById('attendanceAnalyticsSection');
const teacherAnalyticsLoading = document.getElementById('attendanceAnalyticsLoading');
const teacherAnalyticsEmpty = document.getElementById('attendanceAnalyticsEmpty');

function refreshTeacherAttendanceAnalytics() {
    const params = new URLSearchParams();
    if (teacherAnalyticsSectionSelect && teacherAnalyticsSectionSelect.value) {
        params.set('section_id', teacherAnalyticsSectionSelect.value);
    }

    if (teacherAnalyticsLoading) {
        teacherAnalyticsLoading.style.display = 'block';
    }
    if (teacherAnalyticsEmpty) {
        teacherAnalyticsEmpty.style.display = 'none';
    }

    const analyticsTypes = ['daily', 'weekly', 'monthly', 'semester'];
    const requests = analyticsTypes.map((type) => {
        const typeParams = new URLSearchParams(params.toString());
        typeParams.set('type', type);
        return fetch('../api/dashboard-attendance.php?' + typeParams.toString())
            .then(response => response.json())
            .then(data => ({ type, data }))
            .catch(() => ({ type, data: { labels: [], present: [], late: [], absent: [] } }));
    });

    Promise.all(requests)
        .then(results => {
            let hasAnyData = false;

            results.forEach(({ type, data }) => {
                const chart = teacherAnalyticsCharts[type];
                if (!chart) {
                    return;
                }

                const labels = Array.isArray(data.labels) ? data.labels : [];
                const present = Array.isArray(data.present) ? data.present : [];
                const late = Array.isArray(data.late) ? data.late : [];
                const absent = Array.isArray(data.absent) ? data.absent : [];
                const allZeros = [...present, ...late, ...absent].every(v => Number(v || 0) === 0);

                if (labels.length > 0 && !allZeros) {
                    hasAnyData = true;
                }

                chart.data.labels = labels;
                chart.data.datasets[0].data = present;
                chart.data.datasets[1].data = late;
                chart.data.datasets[2].data = absent;
                chart.update();
            });

            if (teacherAnalyticsEmpty) {
                teacherAnalyticsEmpty.style.display = hasAnyData ? 'none' : 'block';
            }
        })
        .finally(() => {
            if (teacherAnalyticsLoading) {
                teacherAnalyticsLoading.style.display = 'none';
            }
        });
}

if (teacherAnalyticsSectionSelect) {
    teacherAnalyticsSectionSelect.addEventListener('change', refreshTeacherAttendanceAnalytics);
}

refreshTeacherAttendanceAnalytics();
</script>

<?php require '../includes/footer.php'; /*
 * � 2026 TambyTech.
 * This source code is proprietary and confidential.
 * Any unauthorized use, copying, modification, distribution, or disclosure is strictly prohibited.
 * All rights reserved.
 */
?>