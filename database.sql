-- ============================================================================
-- CAMS: Criminology Attendance Monitoring System - Database Schema
-- ============================================================================
-- Fingerprint-based attendance system with multiple fingerprints per student
-- ============================================================================

-- Create Database
CREATE DATABASE IF NOT EXISTS cams;
USE cams;

-- ============================================================================
-- Table: students
-- ============================================================================
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique student ID',
    first_name VARCHAR(100) NOT NULL,
    middle_initial VARCHAR(10),
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE,
    year INT COMMENT 'Academic year (1-4)',
    section VARCHAR(50) COMMENT 'Class section',
    status ENUM('active', 'inactive', 'graduated') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student_id (student_id),
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: fingerprints
-- Description: Stores multiple fingerprints (up to 5) per student
-- ============================================================================
CREATE TABLE fingerprints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    finger_index INT NOT NULL COMMENT '1-5, representing which finger',
    sensor_id VARCHAR(255) UNIQUE COMMENT 'Template ID from DY50 sensor',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_finger_per_student (student_id, finger_index),
    INDEX idx_sensor_id (sensor_id),
    INDEX idx_student_id (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: attendance
-- Description: Tracks AM/PM in-out times with status classification
-- ============================================================================
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    attendance_date DATE NOT NULL,

    -- AM Session
    time_in_am TIME,
    time_out_am TIME,

    -- PM Session
    time_in_pm TIME,
    time_out_pm TIME,

    -- Status Classification
    status ENUM('present', 'late', 'absent', 'excused') DEFAULT 'absent',
    notes TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance_per_day (student_id, attendance_date),
    INDEX idx_student_id (student_id),
    INDEX idx_attendance_date (attendance_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: settings
-- Description: System configuration for thresholds and schedules
-- ============================================================================
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value VARCHAR(255) NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: notification_queue
-- Description: Email notification queue for failed/pending notifications
-- ============================================================================
CREATE TABLE notification_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    attendance_id INT,
    email VARCHAR(100) NOT NULL COMMENT 'Recipient email address',
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending' COMMENT 'Current status: pending, sent, or failed',
    attempt_count INT DEFAULT 0 COMMENT 'Number of send attempts',
    last_error TEXT COMMENT 'Last error message',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL COMMENT 'When email was successfully sent',
    next_retry_at TIMESTAMP NULL COMMENT 'Next scheduled retry time',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (attendance_id) REFERENCES attendance(id) ON DELETE SET NULL,
    INDEX idx_student_id (student_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_next_retry (status, next_retry_at) COMMENT 'For querying pending notifications'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- INSERT Initial Settings
-- ============================================================================
INSERT INTO settings (setting_key, setting_value, description) VALUES
    ('late_threshold_minutes', '15', 'Minutes past AM start time to mark as late'),
    ('absent_threshold_hours', '2', 'Hours after AM start to mark as absent if no scan'),
    ('am_start_time', '08:00:00', 'AM session start time'),
    ('am_end_time', '12:00:00', 'AM session end time'),
    ('pm_start_time', '13:00:00', 'PM session start time'),
    ('pm_end_time', '17:00:00', 'PM session end time'),
    ('system_name', 'CAMS - Criminology Attendance System', 'System name for display'),
    ('email_from', 'noreply@cams.local', 'Email sender address'),
    ('notification_enabled', 'true', 'Enable/disable email notifications');

-- ============================================================================
-- Table: scan_waiting
-- Description: Temporary table to handle ESP32 fingerprint scan coordination
-- ============================================================================
CREATE TABLE scan_waiting (
    id INT AUTO_INCREMENT PRIMARY KEY,
    waiting_id VARCHAR(255) UNIQUE NOT NULL COMMENT 'Unique waiting session identifier',
    session_id VARCHAR(255) NOT NULL COMMENT 'Enrollment session ID',
    finger_index INT NOT NULL COMMENT 'Which finger (1-5)',
    scan_index INT NOT NULL COMMENT 'Which scan attempt (1-5)',
    scan_data TEXT NULL COMMENT 'Fingerprint template data from ESP32',
    quality INT NULL COMMENT 'Scan quality score (0-100)',
    confidence INT NULL COMMENT 'Scan confidence score (0-100)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL COMMENT 'When this waiting record expires',
    INDEX idx_waiting_id (waiting_id),
    INDEX idx_session_id (session_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- CREATE VIEWS (Optional, for easier queries)
-- ============================================================================

-- Daily Attendance Summary View
CREATE VIEW daily_attendance_summary AS
SELECT
    a.attendance_date,
    COUNT(*) as total_students,
    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
    SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
    SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) as excused_count
FROM attendance a
GROUP BY a.attendance_date;

-- Student Attendance History View
CREATE VIEW student_attendance_history AS
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
ORDER BY s.student_id, a.attendance_date DESC;

-- ============================================================================
-- Additional Indexes for Performance
-- ============================================================================
CREATE INDEX idx_attend_date_status ON attendance(attendance_date, status);
CREATE INDEX idx_notify_status_date ON notification_queue(status, created_at);

-- ============================================================================
-- End of Database Schema
-- ============================================================================
