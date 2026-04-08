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
    // Students table - for fingerprint enrollment and attendance
    "
    CREATE TABLE IF NOT EXISTS students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(50) NOT NULL UNIQUE,
        first_name VARCHAR(100) NOT NULL,
        middle_initial VARCHAR(10) DEFAULT NULL,
        last_name VARCHAR(100) NOT NULL,
        extension VARCHAR(20) DEFAULT NULL,
        email VARCHAR(120) NOT NULL,
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

// Insert default admin user if not exists.
$adminCheck = $mysqli->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
if ($adminCheck->num_rows === 0) {
    $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $mysqli->query("INSERT INTO users (username, password, full_name, role) VALUES ('admin', '$defaultPassword', 'Administrator', 'admin')");
    echo "✅ Default admin user created (username: admin, password: admin123)\n";
}

echo "✅ Database setup complete. Tables:\n";
echo "- students (fingerprint users)\n";
echo "- users (admin/teacher accounts)\n";
echo "- fingerprints\n";
echo "- attendance_logs\n";
echo "- devices\n";
echo "- device_commands\n";
?>