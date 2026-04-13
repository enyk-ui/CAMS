<?php
/**
 * Attendance seeder from fixed name list.
 *
 * What it does:
 * 1. Optionally clears attendance records first.
 * 2. Resolves the active school year's semester date range.
 * 3. Seeds attendance across all scheduled days using teacher schedule time windows.
 * 3. Uses strict P/L/A status codes from the list below.
 *
 * Usage:
 *   php seed_schedule_attendance.php
 */

require_once __DIR__ . '/config/db.php';

date_default_timezone_set('Asia/Manila');

const TARGET_YEAR_LEVEL = 4;
const TARGET_SECTION_NAME = 'Charlie';
const TARGET_SEMESTER = 1; // 1 or 2
const DATE_RANGE_START_OVERRIDE = ''; // Optional YYYY-MM-DD
const DATE_RANGE_END_OVERRIDE = '';   // Optional YYYY-MM-DD
const CLEAR_ATTENDANCE_BEFORE_SEED = true;

// Map input names to existing names in the students table when needed.
// Format: 'Input Name From List' => 'Existing Student Name'
const NAME_MAPPINGS = [
    'Cabbao Jeny Boy' => 'Cabbab Teny Boy',
    'Cavimog Jamesbhard' => 'Calimag James Bhand',
    'Calinggangan Rheven' => 'Calinggangan Rheyven',
    'Dosalla Rummar' => 'Dasalla Renmar',
    'Engracia Jonathan' => 'Engarcial Jonathan',
    'Enoy Kaneshame' => 'Enoy Kaneshane',
    'Esquero Cherry' => 'Esquejo Cherry',
    'Floriladez Mark Anthony' => 'Flotildez Mark Anthony',
    'Garcia Jaita Mae' => 'Garcia Jaira Mae',
    'Llabres John Russel' => 'Llabore John Russel',
    'Mamaw Jayzar' => 'Marinay Jay-ar',
    'Minas Sofia Owens' => 'Mina Sofia Alexis',
    'Morales Trick John' => 'Morales Erick John',
    'Monteh Michelle' => 'Moriente Michelle',
    'Picardal Jemilyn' => 'Picardal Jennilyn',
    'Quilang Rogelio' => 'Quilang Rogieto',
    'Ramirez Jenic' => 'Ramirez Jervic',
    'Sagpavera Mark' => 'Sagadraca Mark',
    'Saguid Jaraigne Clair' => 'Sagucio Jaraigne Claire',
    'Salvador Rochemel' => 'Salvador Rochelle',
    'Tamoni Aaron Javes' => 'Tamani Aaron Javes',
    'Tanap Reomi John' => 'Tanap Reonil John',
    'Tolan Angelica Mae' => 'Telan Angelica Mae',
    'Tomas Haffey Davidson' => 'Tomas Harley Davidson',
    'Vagan Hans Joshua' => 'Vigan Hans Joshua',
];

// For code P only: 80% present, 10% late, 10% absent.
const P_TO_LATE_PERCENT = 10;
const P_TO_ABSENT_PERCENT = 10;

$rows = [
    ['Acusar Adrian', 'P'],
    ['Aguilar Risajane', 'P'],
    ['Alindayu Dolly', 'P'],
    ['Ballesteros Edward', 'L'],
    ['Batara James Christian', 'P'],
    ['Blando Jay-em', 'P'],
    ['Cabbao Jeny Boy', 'A'],
    ['Cavimog Jamesbhard', 'P'],
    ['Calinggangan Rheven', 'L'],
    ['Dosalla Rummar', 'P'],
    ['Engracia Jonathan', 'P'],
    ['Enoy Kaneshame', 'P'],
    ['Esquero Cherry', 'P'],
    ['Farro John Vincent', 'L'],
    ['Floriladez Mark Anthony', 'A'],
    ['Garcia Jaita Mae', 'P'],
    ['Llabres John Russel', 'P'],
    ['Mamaw Jayzar', 'P'],
    ['Mauricio Kenneth Bert', 'A'],
    ['Miguel Julius', 'A'],
    ['Minas Sofia Owens', 'L'],
    ['Molina Joanalene', 'P'],
    ['Morales Trick John', 'P'],
    ['Monteh Michelle', 'L'],
    ['Pascual John Lloyd', 'P'],
    ['Picardal Jemilyn', 'P'],
    ['Quilang Rogelio', 'A'],
    ['Ramirez Jenic', 'P'],
    ['Respicio Liza', 'P'],
    ['Rosete Veronica', 'P'],
    ['Sagpavera Mark', 'P'],
    ['Saguid Jaraigne Clair', 'L'],
    ['Salvador Mary', 'P'],
    ['Salvador Rochemel', 'L'],
    ['Tamoni Aaron Javes', 'L'],
    ['Tanap Reomi John', 'L'],
    ['Tolan Angelica Mae', 'P'],
    ['Tomas Haffey Davidson', 'L'],
    ['Tomas Mary Ann', 'L'],
    ['Torio Ara May', 'P'],
    ['Vagan Hans Joshua', 'A'],
    ['Villanueva Maria Angelica', 'P'],
];

function hasColumn(mysqli $db, string $table, string $column): bool
{
    $tableSafe = $db->real_escape_string($table);
    $columnSafe = $db->real_escape_string($column);
    $res = $db->query("SHOW COLUMNS FROM {$tableSafe} LIKE '{$columnSafe}'");
    return $res && $res->num_rows > 0;
}

function resolveSectionId(mysqli $db, int $yearLevel, string $sectionName): int
{
    $yearText = (string)$yearLevel;
    $stmt = $db->prepare('SELECT id FROM sections WHERE name = ? AND year_grade = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare section lookup.');
    }

    $stmt->bind_param('ss', $sectionName, $yearText);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        throw new RuntimeException('Target section not found in sections table.');
    }

    return (int)$row['id'];
}

function resolveActiveSchoolYearRange(mysqli $db): array
{
    $result = $db->query("SELECT start_date, end_date FROM school_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    if (!$result || $result->num_rows === 0) {
        $fallback = $db->query('SELECT start_date, end_date FROM school_years ORDER BY end_date DESC, id DESC LIMIT 1');
        if (!$fallback || $fallback->num_rows === 0) {
            throw new RuntimeException('No school year records found.');
        }
        $row = $fallback->fetch_assoc();
        return [(string)$row['start_date'], (string)$row['end_date']];
    }

    $row = $result->fetch_assoc();
    return [(string)$row['start_date'], (string)$row['end_date']];
}

function resolveSemesterRange(string $schoolYearStart, string $schoolYearEnd, int $semester): array
{
    if ($semester !== 1 && $semester !== 2) {
        $semester = 1;
    }

    $firstStart = $schoolYearStart;
    $firstEnd = date('Y-m-d', strtotime($firstStart . ' +5 months last day of this month'));
    if ($firstEnd > $schoolYearEnd) {
        $firstEnd = $schoolYearEnd;
    }

    $secondStart = date('Y-m-d', strtotime($firstEnd . ' +1 day'));
    $secondEnd = $schoolYearEnd;

    if ($semester === 1) {
        return [$firstStart, $firstEnd];
    }

    return [$secondStart, $secondEnd];
}

function loadSectionScheduleForDate(mysqli $db, int $sectionId, string $date): ?array
{
    $dow = (int)date('N', strtotime($date)); // 1..7

    if (!hasColumn($db, 'teacher_daily_schedules', 'section_id')) {
        return null;
    }

    $stmt = $db->prepare('SELECT start_time, end_time, late_threshold_minutes FROM teacher_daily_schedules WHERE section_id = ? AND day_of_week = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ii', $sectionId, $dow);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    return [
        'start_time' => (string)($row['start_time'] ?? '08:00:00'),
        'end_time' => (string)($row['end_time'] ?? '12:00:00'),
        'late_threshold_minutes' => max(1, (int)($row['late_threshold_minutes'] ?? 15)),
    ];
}

function parseNameAsLastFirst(string $fullName): array
{
    $parts = preg_split('/\s+/', trim($fullName));
    if (!$parts || count($parts) < 2) {
        return ['', trim($fullName)];
    }

    $lastName = array_shift($parts);
    $firstName = trim(implode(' ', $parts));

    return [$firstName, $lastName];
}

function resolveStudentId(mysqli $db, string $fullName): int
{
    $mappedName = NAME_MAPPINGS[$fullName] ?? $fullName;
    [$firstName, $lastName] = parseNameAsLastFirst($mappedName);
    if ($firstName === '' || $lastName === '') {
        return 0;
    }

    $stmt = $db->prepare('SELECT id FROM students WHERE first_name = ? AND last_name = ? LIMIT 1');
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('ss', $firstName, $lastName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        return (int)$row['id'];
    }

    // Fallback: some rows may be entered First Last in DB.
    $stmt2 = $db->prepare('SELECT id FROM students WHERE first_name = ? AND last_name = ? LIMIT 1');
    if (!$stmt2) {
        return 0;
    }

    $stmt2->bind_param('ss', $lastName, $firstName);
    $stmt2->execute();
    $row2 = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();
    if ($row2) {
        return (int)$row2['id'];
    }

    // Fuzzy fallback: same last name + first token/prefix match.
    $firstToken = trim((string)explode(' ', $firstName)[0]);
    if ($firstToken !== '') {
        $firstLike = $firstToken . '%';
        $stmt3 = $db->prepare(
            'SELECT id
             FROM students
             WHERE last_name = ?
               AND (
                    first_name = ?
                    OR first_name LIKE ?
                    OR ? LIKE CONCAT(first_name, "%")
               )
             ORDER BY id ASC
             LIMIT 1'
        );
        if ($stmt3) {
            $stmt3->bind_param('ssss', $lastName, $firstToken, $firstLike, $firstToken);
            $stmt3->execute();
            $row3 = $stmt3->get_result()->fetch_assoc();
            $stmt3->close();
            if ($row3) {
                return (int)$row3['id'];
            }
        }
    }

    return 0;
}

function resolveStatus(string $code): string
{
    $normalized = strtoupper(trim($code));

    if ($normalized === 'L') {
        return 'late';
    }
    if ($normalized === 'A') {
        return 'absent';
    }

    // Default and P behavior.
    $roll = random_int(1, 100);
    if ($roll <= P_TO_LATE_PERCENT) {
        return 'late';
    }
    if ($roll <= P_TO_LATE_PERCENT + P_TO_ABSENT_PERCENT) {
        return 'absent';
    }

    return 'present';
}

function shiftTime(string $baseTime, int $seconds): string
{
    $t = strtotime('1970-01-01 ' . $baseTime);
    if ($t === false) {
        $t = strtotime('1970-01-01 08:00:00');
    }

    return date('H:i:s', $t + $seconds);
}

function buildAmTimes(string $status, array $schedule): array
{
    if ($status === 'absent') {
        return [null, null, null, null];
    }

    $start = (string)$schedule['start_time'];
    $end = (string)$schedule['end_time'];
    $lateThreshold = max(1, (int)$schedule['late_threshold_minutes']);

    if ($status === 'late') {
        $inAm = shiftTime($start, ($lateThreshold * 60) + random_int(60, 45 * 60));
    } else {
        $inAm = shiftTime($start, random_int(-5 * 60, 5 * 60));
    }

    $outAm = shiftTime($end, random_int(-10 * 60, 10 * 60));

    return [$inAm, $outAm, null, null];
}

$yearLevel = TARGET_YEAR_LEVEL;
$sectionName = TARGET_SECTION_NAME;
$studentsHasSectionId = hasColumn($mysqli, 'students', 'section_id');
$attendanceHasSectionId = hasColumn($mysqli, 'attendance', 'section_id');

$sectionId = resolveSectionId($mysqli, TARGET_YEAR_LEVEL, TARGET_SECTION_NAME);
[$schoolYearStart, $schoolYearEnd] = resolveActiveSchoolYearRange($mysqli);
[$rangeStart, $rangeEnd] = resolveSemesterRange($schoolYearStart, $schoolYearEnd, TARGET_SEMESTER);

if (DATE_RANGE_START_OVERRIDE !== '' && DATE_RANGE_END_OVERRIDE !== '') {
    $rangeStart = DATE_RANGE_START_OVERRIDE;
    $rangeEnd = DATE_RANGE_END_OVERRIDE;
}

if ($rangeStart > $rangeEnd) {
    [$rangeStart, $rangeEnd] = [$rangeEnd, $rangeStart];
}

$updatedStudents = 0;
$seededAttendance = 0;
$missingStudents = [];
$statusCount = ['present' => 0, 'late' => 0, 'absent' => 0];
$scheduledDays = 0;
$skippedNoScheduleDays = 0;

$mysqli->begin_transaction();
try {
    if (CLEAR_ATTENDANCE_BEFORE_SEED) {
        $mysqli->query('DELETE FROM attendance');
        $mysqli->query('ALTER TABLE attendance AUTO_INCREMENT = 1');
    }

    $studentMap = [];
    foreach ($rows as [$fullName, $code]) {
        $studentId = resolveStudentId($mysqli, $fullName);
        if ($studentId <= 0) {
            $missingStudents[] = $fullName;
            continue;
        }
        $studentMap[] = ['id' => $studentId, 'code' => $code, 'name' => $fullName];

        if ($studentsHasSectionId) {
            $updStudent = $mysqli->prepare('UPDATE students SET year = ?, section = ?, section_id = ?, status = "active", updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $updStudent->bind_param('isii', $yearLevel, $sectionName, $sectionId, $studentId);
        } else {
            $updStudent = $mysqli->prepare('UPDATE students SET year = ?, section = ?, status = "active", updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $updStudent->bind_param('isi', $yearLevel, $sectionName, $studentId);
        }

        $updStudent->execute();
        $updStudent->close();
        $updatedStudents++;
    }

    $dateCursor = $rangeStart;
    while ($dateCursor <= $rangeEnd) {
        $schedule = loadSectionScheduleForDate($mysqli, $sectionId, $dateCursor);
        if ($schedule === null) {
            $skippedNoScheduleDays++;
            $dateCursor = date('Y-m-d', strtotime($dateCursor . ' +1 day'));
            continue;
        }

        $scheduledDays++;
        foreach ($studentMap as $student) {
            $status = resolveStatus((string)$student['code']);
            [$inAm, $outAm, $inPm, $outPm] = buildAmTimes($status, $schedule);
            $notes = 'Seeded by seed_schedule_attendance.php (' . strtoupper(trim((string)$student['code'])) . ')';

            if ($attendanceHasSectionId) {
                $upsert = $mysqli->prepare(
                    'INSERT INTO attendance (student_id, section_id, attendance_date, time_in_am, time_out_am, time_in_pm, time_out_pm, status, notes)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        section_id = VALUES(section_id),
                        time_in_am = VALUES(time_in_am),
                        time_out_am = VALUES(time_out_am),
                        time_in_pm = VALUES(time_in_pm),
                        time_out_pm = VALUES(time_out_pm),
                        status = VALUES(status),
                        notes = VALUES(notes),
                        updated_at = CURRENT_TIMESTAMP'
                );
                $studentId = (int)$student['id'];
                $upsert->bind_param('iisssssss', $studentId, $sectionId, $dateCursor, $inAm, $outAm, $inPm, $outPm, $status, $notes);
            } else {
                $upsert = $mysqli->prepare(
                    'INSERT INTO attendance (student_id, attendance_date, time_in_am, time_out_am, time_in_pm, time_out_pm, status, notes)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        time_in_am = VALUES(time_in_am),
                        time_out_am = VALUES(time_out_am),
                        time_in_pm = VALUES(time_in_pm),
                        time_out_pm = VALUES(time_out_pm),
                        status = VALUES(status),
                        notes = VALUES(notes),
                        updated_at = CURRENT_TIMESTAMP'
                );
                $studentId = (int)$student['id'];
                $upsert->bind_param('isssssss', $studentId, $dateCursor, $inAm, $outAm, $inPm, $outPm, $status, $notes);
            }

            $upsert->execute();
            $upsert->close();

            $seededAttendance++;
            $statusCount[$status]++;
        }

        $dateCursor = date('Y-m-d', strtotime($dateCursor . ' +1 day'));
    }

    $mysqli->commit();
} catch (Throwable $e) {
    $mysqli->rollback();
    fwrite(STDERR, 'Seeder failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo 'Seeder ready and executed.' . PHP_EOL;
echo 'Semester range: ' . $rangeStart . ' to ' . $rangeEnd . PHP_EOL;
echo 'Section: Year ' . TARGET_YEAR_LEVEL . ' - ' . TARGET_SECTION_NAME . ' (section_id=' . $sectionId . ')' . PHP_EOL;
echo 'Scheduled days used: ' . $scheduledDays . PHP_EOL;
echo 'Days skipped (no schedule): ' . $skippedNoScheduleDays . PHP_EOL;
echo 'Attendance cleared first: ' . (CLEAR_ATTENDANCE_BEFORE_SEED ? 'YES' : 'NO') . PHP_EOL;
echo 'Students updated: ' . $updatedStudents . PHP_EOL;
echo 'Attendance seeded: ' . $seededAttendance . PHP_EOL;
echo 'Status counts => Present: ' . $statusCount['present'] . ', Late: ' . $statusCount['late'] . ', Absent: ' . $statusCount['absent'] . PHP_EOL;

if (!empty($missingStudents)) {
    echo 'Missing students (' . count($missingStudents) . '):' . PHP_EOL;
    foreach ($missingStudents as $name) {
        echo ' - ' . $name . PHP_EOL;
    }
}