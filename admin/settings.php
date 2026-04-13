<?php
/**
 * Settings Page
 * Configure attendance defaults and school year management.
 */

session_start();
require_once '../config/db.php';
require_once '../helpers/SchoolYearHelper.php';
require '../includes/header.php';

function usersTableExists(mysqli $mysqli): bool
{
    $result = $mysqli->query("SHOW TABLES LIKE 'users'");
    return $result && $result->num_rows > 0;
}

function usersColumnExists(mysqli $mysqli, string $column): bool
{
    $safe = $mysqli->real_escape_string($column);
    $result = $mysqli->query("SHOW COLUMNS FROM users LIKE '{$safe}'");
    return $result && $result->num_rows > 0;
}

function ensureUsersAdminSchema(mysqli $mysqli): bool
{
    if (!usersTableExists($mysqli)) {
        $createSql = "
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) DEFAULT NULL,
                password VARCHAR(255) DEFAULT NULL,
                full_name VARCHAR(150) DEFAULT NULL,
                email VARCHAR(120) DEFAULT NULL,
                role ENUM('admin','teacher') NOT NULL DEFAULT 'teacher',
                section VARCHAR(50) DEFAULT NULL,
                status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        if (!$mysqli->query($createSql)) {
            return false;
        }
    }

    $alterStatements = [];
    if (!usersColumnExists($mysqli, 'username')) {
        $alterStatements[] = "ADD COLUMN username VARCHAR(50) DEFAULT NULL";
    }
    if (!usersColumnExists($mysqli, 'password')) {
        $alterStatements[] = "ADD COLUMN password VARCHAR(255) DEFAULT NULL";
    }
    if (!usersColumnExists($mysqli, 'full_name')) {
        $alterStatements[] = "ADD COLUMN full_name VARCHAR(150) DEFAULT NULL";
    }
    if (!usersColumnExists($mysqli, 'email')) {
        $alterStatements[] = "ADD COLUMN email VARCHAR(120) DEFAULT NULL";
    }
    if (!usersColumnExists($mysqli, 'role')) {
        $alterStatements[] = "ADD COLUMN role ENUM('admin','teacher') NOT NULL DEFAULT 'teacher'";
    }
    if (!usersColumnExists($mysqli, 'status')) {
        $alterStatements[] = "ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active'";
    }

    foreach ($alterStatements as $statement) {
        if (!$mysqli->query("ALTER TABLE users {$statement}")) {
            return false;
        }
    }

    return true;
}

function ensureSectionTeacherScheduleSchema(mysqli $mysqli): void
{
    $mysqli->query(
        "CREATE TABLE IF NOT EXISTS sections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            year_grade VARCHAR(20) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_section_name_year (name, year_grade)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $mysqli->query(
        "CREATE TABLE IF NOT EXISTS teacher_sections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            section_id INT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_teacher_section (teacher_id, section_id),
            UNIQUE KEY uniq_section_teacher (section_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $mysqli->query(
        "CREATE TABLE IF NOT EXISTS teacher_daily_schedules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            section_id INT DEFAULT NULL,
            day_of_week TINYINT UNSIGNED NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            late_threshold_minutes INT UNSIGNED NOT NULL DEFAULT 15,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_teacher_section_day (teacher_id, section_id, day_of_week)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $sectionIdColumn = $mysqli->query("SHOW COLUMNS FROM teacher_daily_schedules LIKE 'section_id'");
    if ($sectionIdColumn && $sectionIdColumn->num_rows === 0) {
        $mysqli->query("ALTER TABLE teacher_daily_schedules ADD COLUMN section_id INT DEFAULT NULL AFTER teacher_id");
    }

    $oldUnique = $mysqli->query("SHOW INDEX FROM teacher_daily_schedules WHERE Key_name = 'uniq_teacher_day'");
    if ($oldUnique && $oldUnique->num_rows > 0) {
        $mysqli->query("ALTER TABLE teacher_daily_schedules DROP INDEX uniq_teacher_day");
    }

    $newUnique = $mysqli->query("SHOW INDEX FROM teacher_daily_schedules WHERE Key_name = 'uniq_teacher_section_day'");
    if ($newUnique && $newUnique->num_rows === 0) {
        $mysqli->query("ALTER TABLE teacher_daily_schedules ADD UNIQUE KEY uniq_teacher_section_day (teacher_id, section_id, day_of_week)");
    }

    // Backfill section_id for legacy rows using teacher_sections mapping.
    $mysqli->query(
        "UPDATE teacher_daily_schedules tds
         JOIN teacher_sections ts ON ts.teacher_id = tds.teacher_id
         SET tds.section_id = ts.section_id
         WHERE tds.section_id IS NULL"
    );
}

function fetchTeacherSectionAssignments(mysqli $mysqli): array
{
    $rows = [];
    $sql = "
        SELECT
            ts.teacher_id,
            ts.section_id,
            COALESCE(u.full_name, CONCAT('Teacher #', ts.teacher_id)) AS teacher_name,
            COALESCE(u.email, '') AS teacher_email,
            COALESCE(s.year_grade, '') AS year_grade,
            COALESCE(s.name, '') AS section_name
        FROM teacher_sections ts
        LEFT JOIN users u ON u.id = ts.teacher_id
        LEFT JOIN sections s ON s.id = ts.section_id
        ORDER BY CAST(COALESCE(s.year_grade, '0') AS UNSIGNED) ASC, s.name ASC, teacher_name ASC
    ";
    $result = $mysqli->query($sql);
    if (!$result) {
        return $rows;
    }

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

function fetchSectionTeacherSchedules(mysqli $mysqli): array
{
    $rows = [];
    $sql = "
        SELECT
            tds.id,
            tds.teacher_id,
            tds.section_id,
            tds.day_of_week,
            tds.start_time,
            tds.end_time,
            tds.late_threshold_minutes,
            COALESCE(u.full_name, CONCAT('Teacher #', tds.teacher_id)) AS teacher_name,
            COALESCE(s.year_grade, '') AS year_grade,
            COALESCE(s.name, '') AS section_name
        FROM teacher_daily_schedules tds
        LEFT JOIN users u ON u.id = tds.teacher_id
        LEFT JOIN sections s ON s.id = tds.section_id
        ORDER BY CAST(COALESCE(s.year_grade, '0') AS UNSIGNED) ASC, s.name ASC, tds.day_of_week ASC
    ";

    $result = $mysqli->query($sql);
    if (!$result) {
        return $rows;
    }

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

function resolveResetDeviceId(mysqli $mysqli): int
{
    $stmt = $mysqli->prepare('SELECT id FROM devices WHERE is_active = 1 ORDER BY id ASC LIMIT 1');
    if ($stmt) {
        $stmt->execute();
        $device = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($device) {
            return (int)$device['id'];
        }
    }

    return 1;
}

$message = '';
$message_type = '';

if (!SchoolYearHelper::ensureSchoolYearSupport($mysqli)) {
    $message = 'Unable to initialize school year and settings tables. Please run database setup.';
    $message_type = 'danger';
}

ensureSectionTeacherScheduleSchema($mysqli);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_section_schedule';

    if ($action === 'save_section_schedule') {
        $scheduleId = (int)($_POST['schedule_id'] ?? 0);
        $sectionId = (int)($_POST['section_id'] ?? 0);
        $dayOfWeek = (int)($_POST['day_of_week'] ?? 0);
        $startTime = trim((string)($_POST['start_time'] ?? ''));
        $endTime = trim((string)($_POST['end_time'] ?? ''));
        $lateThreshold = (int)($_POST['late_threshold_minutes'] ?? 15);

        if ($sectionId <= 0 || $dayOfWeek < 1 || $dayOfWeek > 7 || $startTime === '' || $endTime === '') {
            $message = 'Please complete section, day, and time fields.';
            $message_type = 'danger';
        } elseif (!preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
            $message = 'Time in and time out must be valid HH:MM values.';
            $message_type = 'danger';
        } else {
            $mapStmt = $mysqli->prepare('SELECT teacher_id FROM teacher_sections WHERE section_id = ? LIMIT 1');
            $mapStmt->bind_param('i', $sectionId);
            $mapStmt->execute();
            $mapRow = $mapStmt->get_result()->fetch_assoc();
            $mapStmt->close();

            if (!$mapRow) {
                $message = 'Selected section has no assigned teacher. Assign teacher in Teachers page first.';
                $message_type = 'danger';
            } else {
                $teacherId = (int)$mapRow['teacher_id'];
                $lateThreshold = max(0, $lateThreshold);
                $startTimeFull = $startTime . ':00';
                $endTimeFull = $endTime . ':00';

                if ($scheduleId > 0) {
                    $stmt = $mysqli->prepare(
                        'UPDATE teacher_daily_schedules
                         SET teacher_id = ?, section_id = ?, day_of_week = ?, start_time = ?, end_time = ?, late_threshold_minutes = ?, updated_at = CURRENT_TIMESTAMP
                         WHERE id = ?
                         LIMIT 1'
                    );
                    $stmt->bind_param('iiissii', $teacherId, $sectionId, $dayOfWeek, $startTimeFull, $endTimeFull, $lateThreshold, $scheduleId);
                    $savedMessage = 'Section-teacher schedule updated successfully.';
                } else {
                    $stmt = $mysqli->prepare(
                        "INSERT INTO teacher_daily_schedules (teacher_id, section_id, day_of_week, start_time, end_time, late_threshold_minutes)
                         VALUES (?, ?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE
                            start_time = VALUES(start_time),
                            end_time = VALUES(end_time),
                            late_threshold_minutes = VALUES(late_threshold_minutes),
                            updated_at = CURRENT_TIMESTAMP"
                    );
                    $stmt->bind_param('iiissi', $teacherId, $sectionId, $dayOfWeek, $startTimeFull, $endTimeFull, $lateThreshold);
                    $savedMessage = 'Section-teacher schedule saved successfully.';
                }

                if ($stmt->execute()) {
                    $message = $savedMessage;
                    $message_type = 'success';
                } else {
                    $message = 'Unable to save schedule: ' . $stmt->error;
                    $message_type = 'danger';
                }
                $stmt->close();
            }
        }
    } elseif ($action === 'delete_section_schedule') {
        $scheduleId = (int)($_POST['schedule_id'] ?? 0);
        if ($scheduleId <= 0) {
            $message = 'Invalid schedule id.';
            $message_type = 'danger';
        } else {
            $stmt = $mysqli->prepare('DELETE FROM teacher_daily_schedules WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $scheduleId);
            if ($stmt->execute()) {
                $message = 'Schedule deleted successfully.';
                $message_type = 'success';
            } else {
                $message = 'Unable to delete schedule.';
                $message_type = 'danger';
            }
            $stmt->close();
        }
    } elseif ($action === 'reset_sensor_records') {
        $deviceId = resolveResetDeviceId($mysqli);

        $mysqli->begin_transaction();
        try {
            $cancelEnrollStmt = $mysqli->prepare("UPDATE device_commands SET status = 'FAILED', error_message = 'Sensor reset requested' WHERE status IN ('PENDING', 'IN_PROGRESS') AND mode = 'ENROLL' AND device_id = ?");
            $cancelEnrollStmt->bind_param('i', $deviceId);
            $cancelEnrollStmt->execute();
            $cancelEnrollStmt->close();

            $deleteStmt = $mysqli->prepare("INSERT INTO device_commands (device_id, mode, sensor_id, status, error_message) VALUES (?, 'DELETE', 0, 'PENDING', 'Reset all sensor fingerprints')");
            $deleteStmt->bind_param('i', $deviceId);
            $deleteStmt->execute();
            $deleteStmt->close();

            $deleteAllStmt = $mysqli->prepare('DELETE FROM fingerprints');
            $deleteAllStmt->execute();
            $deleteAllStmt->close();

            $mysqli->commit();
            $message = 'Sensor reset queued. The scanner will clear all stored templates.';
            $message_type = 'success';
        } catch (Throwable $resetError) {
            $mysqli->rollback();
            $message = 'Unable to reset sensor records: ' . $resetError->getMessage();
            $message_type = 'danger';
        }
    } elseif ($action === 'set_active_school_year') {
        $schoolYearId = (int) ($_POST['school_year_id'] ?? 0);
        if ($schoolYearId > 0 && SchoolYearHelper::setActiveSchoolYear($mysqli, $schoolYearId)) {
            $message = 'Active school year updated successfully.';
            $message_type = 'success';
        } else {
            $message = 'Unable to set active school year.';
            $message_type = 'danger';
        }
    } elseif ($action === 'add_school_year') {
        $label = trim($_POST['school_year_label'] ?? '');
        $startDate = trim($_POST['school_year_start'] ?? '');
        $endDate = trim($_POST['school_year_end'] ?? '');
        $setActive = isset($_POST['school_year_make_active']);

        if (!preg_match('/^\d{4}-\d{4}$/', $label)) {
            $message = 'School year label must follow YYYY-YYYY format.';
            $message_type = 'danger';
        } else {
            if ($startDate === '' || $endDate === '') {
                $range = SchoolYearHelper::labelToRange($label);
                if ($range) {
                    $startDate = $range['start_date'];
                    $endDate = $range['end_date'];
                }
            }

            if ($startDate === '' || $endDate === '' || $startDate > $endDate) {
                $message = 'Please provide a valid school year date range.';
                $message_type = 'danger';
            } elseif (SchoolYearHelper::createSchoolYear($mysqli, $label, $startDate, $endDate, $setActive)) {
                if ($setActive) {
                    SchoolYearHelper::upsertSetting($mysqli, 'school_year', $label);
                }
                $message = 'School year added successfully.';
                $message_type = 'success';
            } else {
                $message = 'Unable to add school year (label may already exist).';
                $message_type = 'danger';
            }
        }
    } elseif ($action === 'update_admin_account') {
        $adminId = (int) ($_SESSION['admin_id'] ?? 0);
        $username = trim($_POST['admin_username'] ?? '');
        $email = trim($_POST['admin_email'] ?? '');
        $fullName = trim($_POST['admin_full_name'] ?? '');
        $newPassword = $_POST['admin_new_password'] ?? '';

        if ($username === '' || $email === '' || $fullName === '') {
            $message = 'Please complete all required admin account fields.';
            $message_type = 'danger';
        } elseif (!ensureUsersAdminSchema($mysqli)) {
            $message = 'Unable to prepare admin account storage in users table.';
            $message_type = 'danger';
        } else {
            $targetAdminId = 0;

            if ($adminId > 0) {
                $lookupStmt = $mysqli->prepare('SELECT id FROM users WHERE id = ? AND role = "admin" LIMIT 1');
                $lookupStmt->bind_param('i', $adminId);
                $lookupStmt->execute();
                $existing = $lookupStmt->get_result()->fetch_assoc();
                $lookupStmt->close();
                if ($existing) {
                    $targetAdminId = (int) $existing['id'];
                }
            }

            if ($targetAdminId === 0) {
                $lookupByEmailStmt = $mysqli->prepare('SELECT id FROM users WHERE email = ? AND role = "admin" LIMIT 1');
                $lookupByEmailStmt->bind_param('s', $email);
                $lookupByEmailStmt->execute();
                $existing = $lookupByEmailStmt->get_result()->fetch_assoc();
                $lookupByEmailStmt->close();
                if ($existing) {
                    $targetAdminId = (int) $existing['id'];
                }
            }

            if ($targetAdminId > 0) {
                if ($newPassword !== '') {
                    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $stmt = $mysqli->prepare('UPDATE users SET username = ?, email = ?, full_name = ?, password = ? WHERE id = ?');
                    $stmt->bind_param('ssssi', $username, $email, $fullName, $passwordHash, $targetAdminId);
                } else {
                    $stmt = $mysqli->prepare('UPDATE users SET username = ?, email = ?, full_name = ? WHERE id = ?');
                    $stmt->bind_param('sssi', $username, $email, $fullName, $targetAdminId);
                }

                if ($stmt->execute()) {
                    $_SESSION['admin_id'] = $targetAdminId;
                    $_SESSION['admin_email'] = $email;
                    $_SESSION['admin_name'] = $fullName;
                    $message = 'Admin account updated successfully.';
                    $message_type = 'success';
                } else {
                    $message = strpos($stmt->error, 'Duplicate entry') !== false
                        ? 'Username or email already exists.'
                        : ('Unable to update admin account: ' . $stmt->error);
                    $message_type = 'danger';
                }
                $stmt->close();
            } else {
                $passwordHash = password_hash($newPassword !== '' ? $newPassword : 'admin123', PASSWORD_BCRYPT);
                $insertStmt = $mysqli->prepare('INSERT INTO users (username, password, full_name, email, role, status) VALUES (?, ?, ?, ?, "admin", "active")');
                $insertStmt->bind_param('ssss', $username, $passwordHash, $fullName, $email);

                if ($insertStmt->execute()) {
                    $newAdminId = (int) $mysqli->insert_id;
                    $_SESSION['admin_id'] = $newAdminId;
                    $_SESSION['admin_email'] = $email;
                    $_SESSION['admin_name'] = $fullName;
                    $message = 'Admin account profile created and updated successfully.';
                    $message_type = 'success';
                } else {
                    $message = strpos($insertStmt->error, 'Duplicate entry') !== false
                        ? 'Username or email already exists.'
                        : ('Unable to create admin account profile: ' . $insertStmt->error);
                    $message_type = 'danger';
                }
                $insertStmt->close();
            }
        }
    }
}

$activeSchoolYear = SchoolYearHelper::getEffectiveSchoolYearRange($mysqli);
$schoolYears = SchoolYearHelper::getAllSchoolYears($mysqli);
$teacherSectionAssignments = fetchTeacherSectionAssignments($mysqli);
$sectionTeacherSchedules = fetchSectionTeacherSchedules($mysqli);
$dayLabels = [
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday',
    7 => 'Sunday',
];

$sessionEmail = trim((string) ($_SESSION['admin_email'] ?? ''));
$sessionName = trim((string) ($_SESSION['admin_name'] ?? ''));
$adminLookupId = (int) ($_SESSION['admin_id'] ?? 0);
$sessionUsername = $sessionEmail !== '' && strpos($sessionEmail, '@') !== false
    ? (string) strtok($sessionEmail, '@')
    : 'admin';

$currentAdmin = [
    'id' => $adminLookupId,
    'username' => $sessionUsername,
    'email' => $sessionEmail,
    'full_name' => $sessionName !== '' ? $sessionName : 'Administrator',
    'is_session_fallback' => true,
];

if (ensureUsersAdminSchema($mysqli)) {
    if ($adminLookupId > 0) {
        $stmt = $mysqli->prepare("SELECT id, username, email, full_name FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
        $stmt->bind_param('i', $adminLookupId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $currentAdmin = $result->fetch_assoc();
            $currentAdmin['is_session_fallback'] = false;
        }
        $stmt->close();
    }

    if (!empty($currentAdmin['is_session_fallback'])) {
        $fallbackEmail = $sessionEmail;
        if ($fallbackEmail !== '') {
            $fallbackStmt = $mysqli->prepare("SELECT id, username, email, full_name FROM users WHERE email = ? AND role = 'admin' ORDER BY id ASC LIMIT 1");
            $fallbackStmt->bind_param('s', $fallbackEmail);
            $fallbackStmt->execute();
            $fallbackRes = $fallbackStmt->get_result();
            if ($fallbackRes && $fallbackRes->num_rows > 0) {
                $currentAdmin = $fallbackRes->fetch_assoc();
                $currentAdmin['is_session_fallback'] = false;
            }
            $fallbackStmt->close();
        }
    }
}
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="alert alert-info mb-4">
    <i class="bi bi-mortarboard"></i>
    Active School Year: <strong><?php echo htmlspecialchars($activeSchoolYear['label'] ?? 'N/A'); ?></strong>
    (<?php echo htmlspecialchars($activeSchoolYear['start_date'] ?? ''); ?> to <?php echo htmlspecialchars($activeSchoolYear['end_date'] ?? ''); ?>)
</div>

<div class="row">
    <div class="col-lg-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-calendar2-week"></i> Section-Teacher Scheduling</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="save_section_schedule">

                    <div class="col-md-4">
                        <label for="section_id" class="form-label">Section (Teacher Assigned)</label>
                        <select class="form-select" id="section_id" name="section_id" required>
                            <option value="">Select section</option>
                            <?php foreach ($teacherSectionAssignments as $assignment): ?>
                                <?php
                                $sectionLabel = trim((string)($assignment['year_grade'] ?? '')) . ' - ' . trim((string)($assignment['section_name'] ?? ''));
                                $teacherLabel = trim((string)($assignment['teacher_name'] ?? 'Teacher'));
                                ?>
                                <option value="<?php echo (int)$assignment['section_id']; ?>">
                                    <?php echo htmlspecialchars($sectionLabel . ' | ' . $teacherLabel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="day_of_week" class="form-label">Day</label>
                        <select class="form-select" id="day_of_week" name="day_of_week" required>
                            <option value="1">Monday</option>
                            <option value="2">Tuesday</option>
                            <option value="3">Wednesday</option>
                            <option value="4">Thursday</option>
                            <option value="5">Friday</option>
                            <option value="6">Saturday</option>
                            <option value="7">Sunday</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="start_time" class="form-label">Time In</label>
                        <input type="time" class="form-control" id="start_time" name="start_time" value="08:00" required>
                    </div>

                    <div class="col-md-2">
                        <label for="end_time" class="form-label">Time Out</label>
                        <input type="time" class="form-control" id="end_time" name="end_time" value="17:00" required>
                    </div>

                    <div class="col-md-2">
                        <label for="late_threshold_minutes" class="form-label">Late (mins)</label>
                        <input type="number" class="form-control" id="late_threshold_minutes" name="late_threshold_minutes" value="15" min="0" max="120" required>
                    </div>

                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-circle"></i> Save Schedule
                        </button>
                    </div>
                </form>

                <hr>

                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Section</th>
                                <th>Teacher</th>
                                <th>Day</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Late (mins)</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sectionTeacherSchedules)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-3">No section-teacher schedules configured.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($sectionTeacherSchedules as $index => $row): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars(trim((string)$row['year_grade']) . ' - ' . trim((string)$row['section_name'])); ?></td>
                                        <td><?php echo htmlspecialchars((string)$row['teacher_name']); ?></td>
                                        <td><?php echo htmlspecialchars($dayLabels[(int)$row['day_of_week']] ?? ('Day ' . (int)$row['day_of_week'])); ?></td>
                                        <td><?php echo htmlspecialchars(substr((string)$row['start_time'], 0, 5)); ?></td>
                                        <td><?php echo htmlspecialchars(substr((string)$row['end_time'], 0, 5)); ?></td>
                                        <td><?php echo (int)$row['late_threshold_minutes']; ?></td>
                                        <td>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary me-2 edit-schedule-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editScheduleModal"
                                                data-schedule-id="<?php echo (int)$row['id']; ?>"
                                                data-section-id="<?php echo (int)$row['section_id']; ?>"
                                                data-day-of-week="<?php echo (int)$row['day_of_week']; ?>"
                                                data-start-time="<?php echo htmlspecialchars(substr((string)$row['start_time'], 0, 5)); ?>"
                                                data-end-time="<?php echo htmlspecialchars(substr((string)$row['end_time'], 0, 5)); ?>"
                                                data-late-threshold="<?php echo (int)$row['late_threshold_minutes']; ?>"
                                            >
                                                Edit
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this schedule?');">
                                                <input type="hidden" name="action" value="delete_section_schedule">
                                                <input type="hidden" name="schedule_id" value="<?php echo (int)$row['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editScheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Update Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="save_section_schedule">
                    <input type="hidden" name="schedule_id" id="edit_schedule_id" value="0">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit_section_id" class="form-label">Section (Teacher Assigned)</label>
                            <select class="form-select" id="edit_section_id" name="section_id" required>
                                <option value="">Select section</option>
                                <?php foreach ($teacherSectionAssignments as $assignment): ?>
                                    <?php
                                    $sectionLabel = trim((string)($assignment['year_grade'] ?? '')) . ' - ' . trim((string)($assignment['section_name'] ?? ''));
                                    $teacherLabel = trim((string)($assignment['teacher_name'] ?? 'Teacher'));
                                    ?>
                                    <option value="<?php echo (int)$assignment['section_id']; ?>">
                                        <?php echo htmlspecialchars($sectionLabel . ' | ' . $teacherLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="edit_day_of_week" class="form-label">Day</label>
                            <select class="form-select" id="edit_day_of_week" name="day_of_week" required>
                                <option value="1">Monday</option>
                                <option value="2">Tuesday</option>
                                <option value="3">Wednesday</option>
                                <option value="4">Thursday</option>
                                <option value="5">Friday</option>
                                <option value="6">Saturday</option>
                                <option value="7">Sunday</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="edit_start_time" class="form-label">Time In</label>
                            <input type="time" class="form-control" id="edit_start_time" name="start_time" value="08:00" required>
                        </div>

                        <div class="col-md-4">
                            <label for="edit_end_time" class="form-label">Time Out</label>
                            <input type="time" class="form-control" id="edit_end_time" name="end_time" value="17:00" required>
                        </div>

                        <div class="col-md-4">
                            <label for="edit_late_threshold_minutes" class="form-label">Late (mins)</label>
                            <input type="number" class="form-control" id="edit_late_threshold_minutes" name="late_threshold_minutes" value="15" min="0" max="120" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Update Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-lg-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-mortarboard"></i> School Year Management</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Label</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($schoolYears)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-3">No school years found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($schoolYears as $index => $sy): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars($sy['label']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($sy['start_date']); ?></td>
                                        <td><?php echo htmlspecialchars($sy['end_date']); ?></td>
                                        <td>
                                            <?php if ((int) $sy['is_active'] === 1): ?>
                                                <span class="badge bg-success">Active / Current</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ((int) $sy['is_active'] !== 1): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="set_active_school_year">
                                                    <input type="hidden" name="school_year_id" value="<?php echo (int) $sy['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">Set Active</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-success small">Default</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <hr>

                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="add_school_year">
                    <div class="col-md-3">
                        <label class="form-label">School Year Label</label>
                        <input type="text" name="school_year_label" class="form-control" placeholder="2026-2027" required pattern="\d{4}-\d{4}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="school_year_start" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" name="school_year_end" class="form-control">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="school_year_make_active" name="school_year_make_active">
                            <label class="form-check-label" for="school_year_make_active">Set as active/current</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="bi bi-plus-circle"></i> Add School Year
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-lg-12 mb-4">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-arrow-repeat"></i> Sensor Finger Records</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">Queues delete commands for every stored fingerprint, removes the saved fingerprint rows, and returns the scanner to IDLE.</p>
                <form method="POST" onsubmit="return confirm('Reset all sensor fingerprint records? This will clear stored fingerprints and queue delete commands to the scanner.');">
                    <input type="hidden" name="action" value="reset_sensor_records">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash3"></i> Reset All Sensor Fingerprints
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-lg-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person-gear"></i> Admin Account</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($currentAdmin['is_session_fallback'])): ?>
                    <div class="alert alert-info">
                        You are currently logged in via session credentials. Saving this form will create or link an admin profile row in users table.
                    </div>
                <?php endif; ?>

                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="update_admin_account">
                    <div class="col-md-4">
                        <label class="form-label">Username</label>
                        <input type="text" name="admin_username" class="form-control" required value="<?php echo htmlspecialchars((string) ($currentAdmin['username'] ?? '')); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input type="email" name="admin_email" class="form-control" required value="<?php echo htmlspecialchars((string) ($currentAdmin['email'] ?? '')); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="admin_full_name" class="form-control" required value="<?php echo htmlspecialchars((string) ($currentAdmin['full_name'] ?? 'Administrator')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">New Password (optional)</label>
                        <input type="password" name="admin_new_password" class="form-control" placeholder="Leave blank to keep current password">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-outline-dark">
                            <i class="bi bi-check-circle"></i> Update Current Admin Information
                        </button>
                    </div>
                </form>
            </div>
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

    .btn-lg {
        padding: 12px 30px;
    }

    .table thead th {
        white-space: nowrap;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const editScheduleId = document.getElementById('edit_schedule_id');
    const editSectionSelect = document.getElementById('edit_section_id');
    const editDaySelect = document.getElementById('edit_day_of_week');
    const editStartInput = document.getElementById('edit_start_time');
    const editEndInput = document.getElementById('edit_end_time');
    const editLateInput = document.getElementById('edit_late_threshold_minutes');

    document.querySelectorAll('.edit-schedule-btn').forEach((button) => {
        button.addEventListener('click', function () {
            if (editScheduleId) editScheduleId.value = this.dataset.scheduleId || '0';
            if (editSectionSelect) editSectionSelect.value = this.dataset.sectionId || '';
            if (editDaySelect) editDaySelect.value = this.dataset.dayOfWeek || '1';
            if (editStartInput) editStartInput.value = this.dataset.startTime || '08:00';
            if (editEndInput) editEndInput.value = this.dataset.endTime || '17:00';
            if (editLateInput) editLateInput.value = this.dataset.lateThreshold || '15';
        });
    });
});
</script>

<?php require '../includes/footer.php'; /*
 * © 2026 TambyTech.
 * This source code is proprietary and confidential.
 * Any unauthorized use, copying, modification, distribution, or disclosure is strictly prohibited.
 * All rights reserved.
 */
?>