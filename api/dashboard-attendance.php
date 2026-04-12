<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

require_method('GET');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = strtolower(trim((string)($_SESSION['role'] ?? '')));
if ($role !== 'admin' && $role !== 'teacher') {
    api_response(401, [
        'success' => false,
        'message' => 'Unauthorized'
    ]);
}

$type = strtolower(trim((string)($_GET['type'] ?? 'weekly')));
$allowedTypes = ['daily', 'weekly', 'monthly', 'semester'];
if (!in_array($type, $allowedTypes, true)) {
    $type = 'weekly';
}

$sectionId = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
if ($sectionId < 0) {
    $sectionId = 0;
}

$today = date('Y-m-d');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$semester = isset($_GET['semester']) ? (int)$_GET['semester'] : (((int)date('n') <= 6) ? 1 : 2);
$selectedDate = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['date'])
    ? (string)$_GET['date']
    : $today;

$studentColumns = [];
$studentColRes = $mysqli->query('SHOW COLUMNS FROM students');
if ($studentColRes) {
    while ($col = $studentColRes->fetch_assoc()) {
        $studentColumns[] = strtolower((string)($col['Field'] ?? ''));
    }
}

$attendanceColumns = [];
$attendanceColRes = $mysqli->query('SHOW COLUMNS FROM attendance');
if ($attendanceColRes) {
    while ($col = $attendanceColRes->fetch_assoc()) {
        $attendanceColumns[] = strtolower((string)($col['Field'] ?? ''));
    }
}

$hasStudentSectionId = in_array('section_id', $studentColumns, true);
$hasStudentSection = in_array('section', $studentColumns, true);
$hasAttendanceDate = in_array('attendance_date', $attendanceColumns, true);
$hasAttendanceCreatedAt = in_array('created_at', $attendanceColumns, true);

$dateExpr = $hasAttendanceDate ? 'a.attendance_date' : ($hasAttendanceCreatedAt ? 'DATE(a.created_at)' : 'CURDATE()');
$dailyHourExpr = $hasAttendanceCreatedAt ? 'HOUR(a.created_at)' : '0';

$scopeSectionIds = [];
$scopeSectionName = '';

if ($role === 'teacher') {
    $teacherId = (int)($_SESSION['admin_id'] ?? 0);
    if ($teacherId <= 0) {
        api_response(401, [
            'success' => false,
            'message' => 'Unauthorized'
        ]);
    }

    if ($hasStudentSectionId) {
        $assignedSectionIds = [];
        $teacherSectionStmt = $mysqli->prepare('SELECT section_id FROM teacher_sections WHERE teacher_id = ?');
        if ($teacherSectionStmt) {
            $teacherSectionStmt->bind_param('i', $teacherId);
            $teacherSectionStmt->execute();
            $teacherSectionRes = $teacherSectionStmt->get_result();
            while ($row = $teacherSectionRes->fetch_assoc()) {
                $sid = (int)($row['section_id'] ?? 0);
                if ($sid > 0) {
                    $assignedSectionIds[] = $sid;
                }
            }
            $teacherSectionStmt->close();
        }

        $assignedSectionIds = array_values(array_unique($assignedSectionIds));
        if (empty($assignedSectionIds)) {
            api_response(200, [
                'labels' => [],
                'present' => [],
                'late' => [],
                'absent' => []
            ]);
        }

        if ($sectionId > 0) {
            if (!in_array($sectionId, $assignedSectionIds, true)) {
                api_response(403, [
                    'success' => false,
                    'message' => 'Section not assigned to teacher'
                ]);
            }
            $scopeSectionIds = [$sectionId];
        } else {
            $scopeSectionIds = $assignedSectionIds;
        }
    } elseif ($hasStudentSection) {
        $scopeSectionName = trim((string)($_SESSION['teacher_section'] ?? ''));
        if ($scopeSectionName === '') {
            api_response(200, [
                'labels' => [],
                'present' => [],
                'late' => [],
                'absent' => []
            ]);
        }
    }
} else {
    if ($sectionId > 0) {
        if ($hasStudentSectionId) {
            $scopeSectionIds = [$sectionId];
        } elseif ($hasStudentSection) {
            $nameStmt = $mysqli->prepare('SELECT name FROM sections WHERE id = ? LIMIT 1');
            if ($nameStmt) {
                $nameStmt->bind_param('i', $sectionId);
                $nameStmt->execute();
                $nameRow = $nameStmt->get_result()->fetch_assoc();
                $nameStmt->close();
                $scopeSectionName = trim((string)($nameRow['name'] ?? ''));
            }
        }
    }
}

$labels = [];
$present = [];
$late = [];
$absent = [];
$bucketMap = [];

$sql = '';
$queryTypes = '';
$queryParams = [];

if ($type === 'daily') {
    for ($h = 0; $h <= 23; $h++) {
        $labels[] = str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00';
        $present[] = 0;
        $late[] = 0;
        $absent[] = 0;
    }

    $sql = "SELECT {$dailyHourExpr} AS bucket,
                   SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present,
                   SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) AS late,
                   SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent
            FROM attendance a
            INNER JOIN students s ON s.id = a.student_id
            WHERE {$dateExpr} = ?";
    $queryTypes = 's';
    $queryParams[] = $selectedDate;
} elseif ($type === 'weekly') {
    $baseTs = strtotime($selectedDate);
    if ($baseTs === false) {
        $baseTs = strtotime($today);
    }
    $dayOfWeek = (int)date('N', $baseTs);
    $mondayTs = strtotime('-' . ($dayOfWeek - 1) . ' days', $baseTs);
    $fridayTs = strtotime('+4 days', $mondayTs);

    $weekStart = date('Y-m-d', $mondayTs);
    $weekEnd = date('Y-m-d', $fridayTs);

    $labels = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $present = [0, 0, 0, 0, 0];
    $late = [0, 0, 0, 0, 0];
    $absent = [0, 0, 0, 0, 0];

    $sql = "SELECT WEEKDAY({$dateExpr}) AS bucket,
                   SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present,
                   SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) AS late,
                   SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent
            FROM attendance a
            INNER JOIN students s ON s.id = a.student_id
            WHERE {$dateExpr} BETWEEN ? AND ?
              AND WEEKDAY({$dateExpr}) BETWEEN 0 AND 4";
    $queryTypes = 'ss';
    $queryParams[] = $weekStart;
    $queryParams[] = $weekEnd;
} elseif ($type === 'monthly') {
    if ($year < 2000 || $year > 2100) {
        $year = (int)date('Y');
    }
    if ($month < 1 || $month > 12) {
        $month = (int)date('n');
    }

    $monthStart = sprintf('%04d-%02d-01', $year, $month);
    $monthEnd = date('Y-m-t', strtotime($monthStart));
    $daysInMonth = (int)date('t', strtotime($monthStart));

    for ($d = 1; $d <= $daysInMonth; $d++) {
        $labels[] = (string)$d;
        $present[] = 0;
        $late[] = 0;
        $absent[] = 0;
    }

    $sql = "SELECT DAY({$dateExpr}) AS bucket,
                   SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present,
                   SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) AS late,
                   SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent
            FROM attendance a
            INNER JOIN students s ON s.id = a.student_id
            WHERE {$dateExpr} BETWEEN ? AND ?";
    $queryTypes = 'ss';
    $queryParams[] = $monthStart;
    $queryParams[] = $monthEnd;
} else {
    if ($year < 2000 || $year > 2100) {
        $year = (int)date('Y');
    }
    if ($semester !== 1 && $semester !== 2) {
        $semester = ((int)date('n') <= 6) ? 1 : 2;
    }

    if ($semester === 1) {
        $semStart = sprintf('%04d-01-01', $year);
        $semEnd = sprintf('%04d-06-30', $year);
        $monthNumbers = [1, 2, 3, 4, 5, 6];
    } else {
        $semStart = sprintf('%04d-07-01', $year);
        $semEnd = sprintf('%04d-12-31', $year);
        $monthNumbers = [7, 8, 9, 10, 11, 12];
    }

    foreach ($monthNumbers as $m) {
        $labels[] = date('F', mktime(0, 0, 0, $m, 1));
        $present[] = 0;
        $late[] = 0;
        $absent[] = 0;
    }

    $sql = "SELECT MONTH({$dateExpr}) AS bucket,
                   SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present,
                   SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) AS late,
                   SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent
            FROM attendance a
            INNER JOIN students s ON s.id = a.student_id
            WHERE {$dateExpr} BETWEEN ? AND ?";
    $queryTypes = 'ss';
    $queryParams[] = $semStart;
    $queryParams[] = $semEnd;
}

if (!empty($scopeSectionIds)) {
    $inClause = implode(',', array_fill(0, count($scopeSectionIds), '?'));
    $sql .= " AND s.section_id IN ({$inClause})";
    $queryTypes .= str_repeat('i', count($scopeSectionIds));
    foreach ($scopeSectionIds as $sid) {
        $queryParams[] = $sid;
    }
} elseif ($scopeSectionName !== '' && $hasStudentSection) {
    $sql .= ' AND s.section = ?';
    $queryTypes .= 's';
    $queryParams[] = $scopeSectionName;
}

$sql .= ' GROUP BY bucket ORDER BY bucket ASC';

$stmt = $mysqli->prepare($sql);
if ($stmt) {
    if ($queryTypes !== '') {
        $bind = [$queryTypes];
        foreach ($queryParams as $index => $value) {
            $bind[] = &$queryParams[$index];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $bucket = (int)($row['bucket'] ?? -1);
        if ($bucket < 0) {
            continue;
        }

        if ($type === 'daily') {
            $bucketIndex = $bucket;
        } elseif ($type === 'weekly') {
            $bucketIndex = $bucket;
        } elseif ($type === 'monthly') {
            $bucketIndex = $bucket - 1;
        } else {
            $bucketIndex = ($semester === 1) ? ($bucket - 1) : ($bucket - 7);
        }

        if ($bucketIndex < 0) {
            continue;
        }

        $bucketMap[$bucketIndex] = [
            'present' => (int)($row['present'] ?? 0),
            'late' => (int)($row['late'] ?? 0),
            'absent' => (int)($row['absent'] ?? 0)
        ];
    }
    $stmt->close();
}

for ($i = 0; $i < count($labels); $i++) {
    if (isset($bucketMap[$i])) {
        $present[$i] = $bucketMap[$i]['present'];
        $late[$i] = $bucketMap[$i]['late'];
        $absent[$i] = $bucketMap[$i]['absent'];
    }
}

api_response(200, [
    'labels' => $labels,
    'present' => $present,
    'late' => $late,
    'absent' => $absent
]);
