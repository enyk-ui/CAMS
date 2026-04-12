<?php
/**
 * Database Setup Script
 * Tables:
 * - students: Student information (fingerprint users)
 * - users: Admin and teacher accounts
 * - fingerprints, attendance_logs, devices, device_commands
 */

require_once 'config/db.php';

echo "Setting up database schema...\n";

$createSql = [
    // Academic sections table
    "
    CREATE TABLE IF NOT EXISTS sections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        year_grade VARCHAR(20) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_section_name_year (name, year_grade)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    // Teacher to section assignments (many sections per teacher)
    "
    CREATE TABLE IF NOT EXISTS teacher_sections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        section_id INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_teacher_section (teacher_id, section_id),
        UNIQUE KEY uniq_section_teacher (section_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    // Per-teacher per-day schedule table used by attendance processing
    "
    CREATE TABLE IF NOT EXISTS teacher_daily_schedules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        day_of_week TINYINT UNSIGNED NOT NULL COMMENT '1=Monday ... 5=Friday',
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        late_threshold_minutes INT UNSIGNED NOT NULL DEFAULT 15,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_teacher_day (teacher_id, day_of_week)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    // Students table - for fingerprint enrollment and attendance
    "
    CREATE TABLE IF NOT EXISTS students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        middle_initial VARCHAR(10) DEFAULT NULL,
        last_name VARCHAR(100) NOT NULL,
        extension VARCHAR(20) DEFAULT NULL,
        year TINYINT UNSIGNED DEFAULT NULL,
        section VARCHAR(20) DEFAULT NULL,
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    // Users table - for admin and teacher login accounts
    "
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(150) NOT NULL,
        email VARCHAR(120) DEFAULT NULL UNIQUE,
        role ENUM('admin','teacher') NOT NULL DEFAULT 'teacher',
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    "
    CREATE TABLE IF NOT EXISTS devices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_key VARCHAR(80) NOT NULL UNIQUE,
        name VARCHAR(100) DEFAULT NULL,
        location VARCHAR(100) DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        last_seen TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    // Fingerprints linked to students (not users)
    "
    CREATE TABLE IF NOT EXISTS fingerprints (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        finger_index TINYINT UNSIGNED NOT NULL,
        sensor_id INT NOT NULL,
        device_id INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_sensor_id (sensor_id),
        UNIQUE KEY uniq_student_finger (student_id, finger_index)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    // Daily attendance summary table used by admin and teacher reports
    "
    CREATE TABLE IF NOT EXISTS attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        attendance_date DATE NOT NULL,
        time_in_am TIME DEFAULT NULL,
        time_out_am TIME DEFAULT NULL,
        time_in_pm TIME DEFAULT NULL,
        time_out_pm TIME DEFAULT NULL,
        status ENUM('present','late','absent','excused') DEFAULT 'absent',
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_attendance_per_day (student_id, attendance_date),
        KEY idx_attendance_date (attendance_date),
        KEY idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    // Attendance logs linked to students
    "
    CREATE TABLE IF NOT EXISTS attendance_logs (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        device_id INT NOT NULL,
        type ENUM('IN','OUT') NOT NULL,
        timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    // Device commands reference students for enrollment
    "
    CREATE TABLE IF NOT EXISTS device_commands (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        device_id INT NOT NULL,
        mode ENUM('IDLE','ENROLL','DELETE') NOT NULL DEFAULT 'IDLE',
        student_id INT DEFAULT NULL,
        finger_index TINYINT UNSIGNED DEFAULT NULL,
        sensor_id INT DEFAULT NULL,
        scan_step TINYINT UNSIGNED DEFAULT NULL,
        total_scan_steps TINYINT UNSIGNED DEFAULT 3,
        status ENUM('PENDING','IN_PROGRESS','COMPLETED','FAILED') NOT NULL DEFAULT 'PENDING',
        error_message VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    "
];

foreach ($createSql as $sql) {
    if (!$mysqli->query($sql)) {
        echo "❌ Create table error: {$mysqli->error}\n";
        exit(1);
    }
}

// Ensure students has section_id and drop legacy profile fields.
$studentsSectionIdCol = $mysqli->query("SHOW COLUMNS FROM students LIKE 'section_id'");
if ($studentsSectionIdCol && $studentsSectionIdCol->num_rows === 0) {
    if ($mysqli->query("ALTER TABLE students ADD COLUMN section_id INT NULL AFTER year")) {
        echo "✅ Added students.section_id column\n";
    } else {
        echo "⚠️ Could not add students.section_id: {$mysqli->error}\n";
    }
}

if ($mysqli->query("SHOW COLUMNS FROM students LIKE 'student_id'")?->num_rows > 0) {
    if ($mysqli->query("DROP VIEW IF EXISTS student_attendance_history") === false) {
        echo "⚠️ Could not drop dependent view student_attendance_history: {$mysqli->error}\n";
    }
    if (!$mysqli->query("ALTER TABLE students DROP COLUMN student_id")) {
        echo "⚠️ Could not drop students.student_id: {$mysqli->error}\n";
    } else {
        echo "✅ Dropped students.student_id column\n";
    }
}

if ($mysqli->query("SHOW COLUMNS FROM students LIKE 'email'")?->num_rows > 0) {
    if (!$mysqli->query("ALTER TABLE students DROP COLUMN email")) {
        echo "⚠️ Could not drop students.email: {$mysqli->error}\n";
    } else {
        echo "✅ Dropped students.email column\n";
    }
}

// Ensure attendance has section_id for faster section-scoped queries.
$attendanceSectionIdCol = $mysqli->query("SHOW COLUMNS FROM attendance LIKE 'section_id'");
if ($attendanceSectionIdCol && $attendanceSectionIdCol->num_rows === 0) {
    if ($mysqli->query("ALTER TABLE attendance ADD COLUMN section_id INT NULL AFTER student_id")) {
        echo "✅ Added attendance.section_id column\n";
    } else {
        echo "⚠️ Could not add attendance.section_id: {$mysqli->error}\n";
    }
}

// Ensure legacy students tables get required name fields.
$studentMiddleInitialCol = $mysqli->query("SHOW COLUMNS FROM students LIKE 'middle_initial'");
if ($studentMiddleInitialCol && $studentMiddleInitialCol->num_rows === 0) {
    if ($mysqli->query("ALTER TABLE students ADD COLUMN middle_initial VARCHAR(10) DEFAULT NULL AFTER first_name")) {
        echo "✅ Added students.middle_initial column\n";
    } else {
        echo "⚠️ Could not add students.middle_initial: {$mysqli->error}\n";
    }
}

$studentExtensionCol = $mysqli->query("SHOW COLUMNS FROM students LIKE 'extension'");
if ($studentExtensionCol && $studentExtensionCol->num_rows === 0) {
    if ($mysqli->query("ALTER TABLE students ADD COLUMN extension VARCHAR(20) DEFAULT NULL AFTER last_name")) {
        echo "✅ Added students.extension column\n";
    } else {
        echo "⚠️ Could not add students.extension: {$mysqli->error}\n";
    }
}

// Disable foreign key checks for alterations.
$mysqli->query("SET FOREIGN_KEY_CHECKS = 0");

// Rename user_id to student_id in tables only if user_id column exists.
$tablesToMigrate = [
    'fingerprints' => 'INT NOT NULL',
    'attendance_logs' => 'INT NOT NULL',
    'device_commands' => 'INT DEFAULT NULL'
];

foreach ($tablesToMigrate as $table => $colDef) {
    $checkCol = $mysqli->query("SHOW COLUMNS FROM `$table` LIKE 'user_id'");
    if ($checkCol && $checkCol->num_rows > 0) {
        $mysqli->query("ALTER TABLE `$table` CHANGE COLUMN user_id student_id $colDef");
        echo "✅ Renamed user_id to student_id in $table\n";
    }
}

// Ensure scan progress columns exist for enrollment UI step tracking.
$scanStepCol = $mysqli->query("SHOW COLUMNS FROM device_commands LIKE 'scan_step'");
if ($scanStepCol && $scanStepCol->num_rows === 0) {
    if ($mysqli->query("ALTER TABLE device_commands ADD COLUMN scan_step TINYINT UNSIGNED DEFAULT NULL AFTER sensor_id")) {
        echo "✅ Added device_commands.scan_step column\n";
    } else {
        echo "⚠️ Could not add device_commands.scan_step: {$mysqli->error}\n";
    }
}

$totalScanStepsCol = $mysqli->query("SHOW COLUMNS FROM device_commands LIKE 'total_scan_steps'");
if ($totalScanStepsCol && $totalScanStepsCol->num_rows === 0) {
    if ($mysqli->query("ALTER TABLE device_commands ADD COLUMN total_scan_steps TINYINT UNSIGNED DEFAULT 3 AFTER scan_step")) {
        echo "✅ Added device_commands.total_scan_steps column\n";
    } else {
        echo "⚠️ Could not add device_commands.total_scan_steps: {$mysqli->error}\n";
    }
}

// Enforce only one fingerprint row per student.
if ($mysqli->query("SHOW INDEX FROM fingerprints WHERE Key_name = 'uniq_student_one'")?->num_rows === 0) {
    $dupeCountResult = $mysqli->query("SELECT COUNT(*) AS duplicate_students FROM (SELECT student_id FROM fingerprints GROUP BY student_id HAVING COUNT(*) > 1) d");
    $duplicateStudents = 0;
    if ($dupeCountResult) {
        $dupeCountRow = $dupeCountResult->fetch_assoc();
        $duplicateStudents = (int)($dupeCountRow['duplicate_students'] ?? 0);
    }

    if ($duplicateStudents > 0) {
        if ($mysqli->query("DELETE f_old FROM fingerprints f_old INNER JOIN fingerprints f_keep ON f_old.student_id = f_keep.student_id AND f_old.id < f_keep.id")) {
            echo "✅ Removed duplicate fingerprint rows before unique constraint\n";
        } else {
            echo "⚠️ Could not remove duplicate fingerprint rows: {$mysqli->error}\n";
        }
    }

    try {
        if ($mysqli->query("ALTER TABLE fingerprints ADD UNIQUE KEY uniq_student_one (student_id)")) {
            echo "✅ Enforced one fingerprint per student\n";
        }
    } catch (mysqli_sql_exception $e) {
        echo "⚠️ Could not enforce one fingerprint per student: {$e->getMessage()}\n";
    }
}

// Build sections from legacy students/users data.
$distinctSections = [];
$studentSectionResult = $mysqli->query("SELECT DISTINCT TRIM(COALESCE(section, '')) AS section_name, CAST(COALESCE(year, 0) AS CHAR) AS year_grade FROM students WHERE section IS NOT NULL AND TRIM(section) <> '' AND year IS NOT NULL");
if ($studentSectionResult) {
    while ($row = $studentSectionResult->fetch_assoc()) {
        $name = trim((string)($row['section_name'] ?? ''));
        $year = trim((string)($row['year_grade'] ?? ''));
        if ($name !== '' && $year !== '' && $year !== '0') {
            $distinctSections[$year . '|' . $name] = [$name, $year];
        }
    }
}

$userHasYear = $mysqli->query("SHOW COLUMNS FROM users LIKE 'year_level'");
$userHasSection = $mysqli->query("SHOW COLUMNS FROM users LIKE 'section'");
if ($userHasYear && $userHasYear->num_rows > 0 && $userHasSection && $userHasSection->num_rows > 0) {
    $teacherSectionResult = $mysqli->query("SELECT DISTINCT TRIM(COALESCE(section, '')) AS section_name, CAST(COALESCE(year_level, 0) AS CHAR) AS year_grade FROM users WHERE role = 'teacher' AND section IS NOT NULL AND TRIM(section) <> '' AND year_level IS NOT NULL");
    if ($teacherSectionResult) {
        while ($row = $teacherSectionResult->fetch_assoc()) {
            $name = trim((string)($row['section_name'] ?? ''));
            $year = trim((string)($row['year_grade'] ?? ''));
            if ($name !== '' && $year !== '' && $year !== '0') {
                $distinctSections[$year . '|' . $name] = [$name, $year];
            }
        }
    }
}

foreach ($distinctSections as [$name, $year]) {
    $stmt = $mysqli->prepare("INSERT INTO sections (name, year_grade) VALUES (?, ?) ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP");
    if ($stmt) {
        $stmt->bind_param('ss', $name, $year);
        $stmt->execute();
        $stmt->close();
    }
}

// Backfill students.section_id using legacy year + section columns.
$mysqli->query("UPDATE students s JOIN sections sec ON sec.name = CONVERT(s.section USING utf8mb4) COLLATE utf8mb4_unicode_ci AND sec.year_grade = CONVERT(CAST(s.year AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci SET s.section_id = sec.id WHERE s.section_id IS NULL AND s.section IS NOT NULL AND TRIM(s.section) <> '' AND s.year IS NOT NULL");

// Backfill attendance.section_id from student section mapping.
$mysqli->query("UPDATE attendance a JOIN students s ON s.id = a.student_id SET a.section_id = s.section_id WHERE a.section_id IS NULL");

// Backfill teacher_sections mapping from users.year_level + users.section.
if ($userHasYear && $userHasYear->num_rows > 0 && $userHasSection && $userHasSection->num_rows > 0) {
    $mysqli->query("INSERT IGNORE INTO teacher_sections (teacher_id, section_id) SELECT u.id, sec.id FROM users u JOIN sections sec ON sec.name = CONVERT(u.section USING utf8mb4) COLLATE utf8mb4_unicode_ci AND sec.year_grade = CONVERT(CAST(u.year_level AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci WHERE u.role = 'teacher' AND u.section IS NOT NULL AND TRIM(u.section) <> '' AND u.year_level IS NOT NULL");
}

// Drop old unique key and create new one if needed.
$mysqli->query("ALTER TABLE fingerprints DROP INDEX IF EXISTS uniq_user_finger");
// Only add if not exists
$checkIdx = $mysqli->query("SHOW INDEX FROM fingerprints WHERE Key_name = 'uniq_student_finger'");
if ($checkIdx && $checkIdx->num_rows === 0) {
    $mysqli->query("ALTER TABLE fingerprints ADD UNIQUE KEY uniq_student_finger (student_id, finger_index)");
}

// Drop and recreate users table if it has old schema (student_no column).
$checkUsersSchema = $mysqli->query("SHOW COLUMNS FROM users LIKE 'student_no'");
if ($checkUsersSchema && $checkUsersSchema->num_rows > 0) {
    echo "⚠️ Dropping old users table (had student_no schema)...\n";
    $mysqli->query("DROP TABLE users");
    $mysqli->query("
        CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(150) NOT NULL,
            email VARCHAR(120) DEFAULT NULL UNIQUE,
            role ENUM('admin','teacher') NOT NULL DEFAULT 'teacher',
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

// Re-enable foreign key checks.
$mysqli->query("SET FOREIGN_KEY_CHECKS = 1");

// Repair legacy device_commands.id when it is not a proper AUTO_INCREMENT PK.
$idMeta = $mysqli->query("SHOW COLUMNS FROM device_commands LIKE 'id'");
if ($idMeta && $idMeta->num_rows > 0) {
    $idRow = $idMeta->fetch_assoc();
    $needsRepair = true;

    if (!empty($idRow['Key']) && strtoupper((string)$idRow['Key']) === 'PRI' &&
        !empty($idRow['Extra']) && stripos((string)$idRow['Extra'], 'auto_increment') !== false) {
        $needsRepair = false;
    }

    if ($needsRepair) {
        echo "⚠️ Repairing device_commands.id to AUTO_INCREMENT PRIMARY KEY...\n";

        $hasTempPk = $mysqli->query("SHOW COLUMNS FROM device_commands LIKE 'command_pk'");
        if (!$hasTempPk || $hasTempPk->num_rows === 0) {
            if (!$mysqli->query("ALTER TABLE device_commands ADD COLUMN command_pk BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST")) {
                echo "⚠️ Could not add command_pk: {$mysqli->error}\n";
            }
        }

        $dropOldIdOk = $mysqli->query("ALTER TABLE device_commands DROP COLUMN id");
        if (!$dropOldIdOk) {
            echo "⚠️ Could not drop old device_commands.id: {$mysqli->error}\n";
        }

        $renameOk = $mysqli->query("ALTER TABLE device_commands CHANGE COLUMN command_pk id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
        if (!$renameOk) {
            echo "⚠️ Could not rename command_pk to id: {$mysqli->error}\n";
        } else {
            echo "✅ Repaired device_commands.id as AUTO_INCREMENT PRIMARY KEY\n";
        }
    }
}

// Repair legacy attendance_logs.id when it is not a proper AUTO_INCREMENT PK.
$attendanceIdMeta = $mysqli->query("SHOW COLUMNS FROM attendance_logs LIKE 'id'");
if ($attendanceIdMeta && $attendanceIdMeta->num_rows > 0) {
    $attendanceIdRow = $attendanceIdMeta->fetch_assoc();
    $needsRepair = true;

    if (!empty($attendanceIdRow['Key']) && strtoupper((string)$attendanceIdRow['Key']) === 'PRI' &&
        !empty($attendanceIdRow['Extra']) && stripos((string)$attendanceIdRow['Extra'], 'auto_increment') !== false) {
        $needsRepair = false;
    }

    if ($needsRepair) {
        echo "⚠️ Repairing attendance_logs.id to AUTO_INCREMENT PRIMARY KEY...\n";

        $hasTempPk = $mysqli->query("SHOW COLUMNS FROM attendance_logs LIKE 'log_pk'");
        if (!$hasTempPk || $hasTempPk->num_rows === 0) {
            if (!$mysqli->query("ALTER TABLE attendance_logs ADD COLUMN log_pk BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST")) {
                echo "⚠️ Could not add log_pk: {$mysqli->error}\n";
            }
        }

        $dropOldIdOk = $mysqli->query("ALTER TABLE attendance_logs DROP COLUMN id");
        if (!$dropOldIdOk) {
            echo "⚠️ Could not drop old attendance_logs.id: {$mysqli->error}\n";
        }

        $renameOk = $mysqli->query("ALTER TABLE attendance_logs CHANGE COLUMN log_pk id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
        if (!$renameOk) {
            echo "⚠️ Could not rename log_pk to id: {$mysqli->error}\n";
        } else {
            echo "✅ Repaired attendance_logs.id as AUTO_INCREMENT PRIMARY KEY\n";
        }
    }
}

// Backfill attendance daily rows from attendance_logs when both tables exist.
$attendanceTableCheck = $mysqli->query("SHOW TABLES LIKE 'attendance'");
$attendanceLogsTableCheck = $mysqli->query("SHOW TABLES LIKE 'attendance_logs'");
if ($attendanceTableCheck && $attendanceLogsTableCheck && $attendanceTableCheck->num_rows > 0 && $attendanceLogsTableCheck->num_rows > 0) {
    $backfillSql = "
        INSERT INTO attendance (
            student_id,
            attendance_date,
            time_in_am,
            time_out_am,
            time_in_pm,
            time_out_pm,
            status,
            notes,
            created_at,
            updated_at
        )
        SELECT
            al.student_id,
            DATE(al.timestamp) AS attendance_date,
            MIN(CASE WHEN al.type = 'IN' AND HOUR(al.timestamp) < 12 THEN TIME(al.timestamp) END) AS time_in_am,
            MAX(CASE WHEN al.type = 'OUT' AND HOUR(al.timestamp) < 12 THEN TIME(al.timestamp) END) AS time_out_am,
            MIN(CASE WHEN al.type = 'IN' AND HOUR(al.timestamp) >= 12 THEN TIME(al.timestamp) END) AS time_in_pm,
            MAX(CASE WHEN al.type = 'OUT' AND HOUR(al.timestamp) >= 12 THEN TIME(al.timestamp) END) AS time_out_pm,
            CASE
                WHEN SUM(CASE WHEN al.type = 'IN' THEN 1 ELSE 0 END) > 0 THEN 'present'
                ELSE 'absent'
            END AS status,
            'Backfilled from attendance_logs' AS notes,
            MIN(al.timestamp) AS created_at,
            MAX(al.timestamp) AS updated_at
        FROM attendance_logs al
        INNER JOIN students s ON s.id = al.student_id
        WHERE al.student_id IS NOT NULL
        GROUP BY al.student_id, DATE(al.timestamp)
        ON DUPLICATE KEY UPDATE
            time_in_am = IF(attendance.time_in_am IS NULL, VALUES(time_in_am), attendance.time_in_am),
            time_out_am = IF(attendance.time_out_am IS NULL, VALUES(time_out_am), attendance.time_out_am),
            time_in_pm = IF(attendance.time_in_pm IS NULL, VALUES(time_in_pm), attendance.time_in_pm),
            time_out_pm = IF(attendance.time_out_pm IS NULL, VALUES(time_out_pm), attendance.time_out_pm),
            status = CASE
                WHEN attendance.status = 'absent' AND VALUES(status) = 'present' THEN 'present'
                ELSE attendance.status
            END,
            notes = CASE
                WHEN attendance.notes IS NULL OR attendance.notes = '' THEN VALUES(notes)
                ELSE attendance.notes
            END,
            updated_at = GREATEST(attendance.updated_at, VALUES(updated_at))
    ";

    if ($mysqli->query($backfillSql)) {
        echo "✅ Backfilled attendance rows from attendance_logs\n";
    } else {
        echo "⚠️ Attendance backfill skipped: {$mysqli->error}\n";
    }
}

// Insert default admin user if not exists.
$adminCheck = $mysqli->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
if ($adminCheck->num_rows === 0) {
    $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $mysqli->query("INSERT INTO users (username, password, full_name, email, role) VALUES ('admin', '$defaultPassword', 'Administrator', 'admin@cams.edu.ph', 'admin')");
    echo "✅ Default admin user created (admin@cams.edu.ph / admin123)\n";
}

// Insert default teacher user if not exists.
$teacherCheck = $mysqli->query("SELECT id FROM users WHERE username = 'teacher' LIMIT 1");
if ($teacherCheck->num_rows === 0) {
    $defaultPassword = password_hash('teacher123', PASSWORD_DEFAULT);
    $mysqli->query("INSERT INTO users (username, password, full_name, email, role) VALUES ('teacher', '$defaultPassword', 'Demo Teacher', 'teacher@cams.edu.ph', 'teacher')");
    echo "✅ Default teacher user created (teacher@cams.edu.ph / teacher123)\n";
}

echo "✅ Database setup complete. Tables:\n";
echo "- students (fingerprint users)\n";
echo "- users (admin/teacher accounts)\n";
echo "- fingerprints\n";
echo "- attendance\n";
echo "- attendance_logs\n";
echo "- devices\n";
echo "- device_commands\n";
?>