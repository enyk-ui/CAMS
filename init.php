<?php
/**
 * CAMS Database Initialization & Seeding
 * Run once to setup database and seed initial data
 * Access: http://localhost/CAMS/init.php
 */

$output = [];

// Connect to MySQL (without database first)
$mysqli = new mysqli("localhost", "root", "", "");

if ($mysqli->connect_error) {
    $output[] = "❌ Connection error: " . $mysqli->connect_error;
    die(json_encode($output));
}

// Step 1: Create Database
$output[] = "📊 Creating database...";
if ($mysqli->query("CREATE DATABASE IF NOT EXISTS cams")) {
    $output[] = "✅ Database created/exists";
} else {
    $output[] = "❌ Database creation failed: " . $mysqli->error;
}

// Step 2: Select database
if (!$mysqli->select_db("cams")) {
    $output[] = "❌ Failed to select database: " . $mysqli->error;
    die(json_encode($output));
}

// Step 3: Create tables
$output[] = "\n📋 Creating tables...";

$tables = [
    "students" => "
        CREATE TABLE IF NOT EXISTS students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id VARCHAR(50) NOT NULL UNIQUE,
            first_name VARCHAR(100) NOT NULL,
            middle_initial VARCHAR(10),
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE,
            year INT,
            section VARCHAR(50),
            status ENUM('active', 'inactive', 'graduated') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_student_id (student_id),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    "fingerprints" => "
        CREATE TABLE IF NOT EXISTS fingerprints (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            finger_index INT NOT NULL,
            sensor_id VARCHAR(255) UNIQUE,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            UNIQUE KEY unique_finger_per_student (student_id, finger_index),
            INDEX idx_sensor_id (sensor_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    "attendance" => "
        CREATE TABLE IF NOT EXISTS attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            attendance_date DATE NOT NULL,
            time_in_am TIME,
            time_out_am TIME,
            time_in_pm TIME,
            time_out_pm TIME,
            status ENUM('present', 'late', 'absent', 'excused') DEFAULT 'absent',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            UNIQUE KEY unique_attendance_per_day (student_id, attendance_date),
            INDEX idx_attendance_date (attendance_date),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    "settings" => "
        CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value VARCHAR(255) NOT NULL,
            description TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_setting_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    "notification_queue" => "
        CREATE TABLE IF NOT EXISTS notification_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            attendance_id INT,
            email VARCHAR(100) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
            attempt_count INT DEFAULT 0,
            last_error TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            sent_at TIMESTAMP NULL,
            next_retry_at TIMESTAMP NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (attendance_id) REFERENCES attendance(id) ON DELETE SET NULL,
            INDEX idx_status (status),
            INDEX idx_next_retry (status, next_retry_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    "
];

foreach ($tables as $name => $sql) {
    if ($mysqli->query($sql)) {
        $output[] = "✅ Table '$name' created/exists";
    } else {
        $output[] = "❌ Table '$name' failed: " . $mysqli->error;
    }
}

// Step 4: Seed settings
$output[] = "\n🌱 Seeding settings...";

$settings = [
    ['late_threshold_minutes', '15', 'Minutes past AM start time to mark as late'],
    ['absent_threshold_hours', '2', 'Hours after AM start to mark as absent if no scan'],
    ['am_start_time', '08:00:00', 'AM session start time'],
    ['am_end_time', '12:00:00', 'AM session end time'],
    ['pm_start_time', '13:00:00', 'PM session start time'],
    ['pm_end_time', '17:00:00', 'PM session end time'],
    ['system_name', 'CAMS - Criminology Attendance System', 'System name for display'],
    ['email_from', 'noreply@cams.local', 'Email sender address'],
    ['notification_enabled', 'true', 'Enable/disable email notifications']
];

foreach ($settings as [$key, $value, $desc]) {
    $key = $mysqli->real_escape_string($key);
    $value = $mysqli->real_escape_string($value);
    $desc = $mysqli->real_escape_string($desc);

    $sql = "INSERT IGNORE INTO settings (setting_key, setting_value, description)
            VALUES ('$key', '$value', '$desc')";

    if ($mysqli->query($sql)) {
        $output[] = "✅ Setting '$key' seeded";
    } else {
        $output[] = "⚠️ Setting '$key': " . $mysqli->error;
    }
}

// Step 5: Seed sample student
$output[] = "\n👤 Seeding sample data...";

$sample_student = "INSERT IGNORE INTO students
    (student_id, first_name, last_name, email, year, section, status)
    VALUES ('2024-001', 'Juan', 'Dela Cruz', 'juan@student.edu.ph', 1, 'A', 'active')";

if ($mysqli->query($sample_student)) {
    $output[] = "✅ Sample student created";
} else {
    $output[] = "⚠️ Sample student: " . $mysqli->error;
}

// Step 6: Create Views
$output[] = "\n📊 Creating views...";

$views = [
    "daily_attendance_summary" => "
        CREATE OR REPLACE VIEW daily_attendance_summary AS
        SELECT
            a.attendance_date,
            COUNT(*) as total_students,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) as excused_count
        FROM attendance a
        GROUP BY a.attendance_date
    ",

    "student_attendance_history" => "
        CREATE OR REPLACE VIEW student_attendance_history AS
        SELECT
            s.id,
            s.student_id,
            s.first_name,
            s.last_name,
            s.email,
            a.attendance_date,
            a.time_in_am,
            a.time_out_am,
            a.time_in_pm,
            a.time_out_pm,
            a.status,
            a.notes
        FROM students s
        LEFT JOIN attendance a ON s.id = a.student_id
        WHERE s.status = 'active'
        ORDER BY s.student_id, a.attendance_date DESC
    "
];

foreach ($views as $name => $sql) {
    if ($mysqli->query($sql)) {
        $output[] = "✅ View '$name' created";
    } else {
        $output[] = "⚠️ View '$name': " . $mysqli->error;
    }
}

// Final output
$output[] = "\n✨ Database initialization complete!";

$mysqli->close();

// Return result
header('Content-Type: application/json');
echo json_encode($output, JSON_PRETTY_PRINT);
?>
