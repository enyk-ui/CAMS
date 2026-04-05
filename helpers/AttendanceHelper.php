<?php
/**
 * Attendance Helper Class
 * Common functions for attendance operations
 */

class AttendanceHelper {

    /**
     * Get student by fingerprint sensor ID
     */
    public static function getStudentByFingerprint($conn, $sensor_id) {
        $stmt = $conn->prepare("
            SELECT f.student_id, s.id, s.first_name, s.last_name, s.email
            FROM fingerprints f
            JOIN students s ON f.student_id = s.id
            WHERE f.sensor_id = ? AND f.is_active = true AND s.status = 'active'
            LIMIT 1
        ");
        $stmt->bind_param("s", $sensor_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Get today's attendance record for student
     */
    public static function getTodayAttendance($conn, $student_id) {
        $today = date('Y-m-d');
        $stmt = $conn->prepare("
            SELECT * FROM attendance
            WHERE student_id = ? AND attendance_date = ?
        ");
        $stmt->bind_param("is", $student_id, $today);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Determine if current time is AM or PM session
     */
    public static function isAMSession($settings) {
        $current_time = strtotime(date('H:i:s'));
        $pm_start = strtotime($settings['pm_start_time']);
        return $current_time < $pm_start;
    }

    /**
     * Calculate minutes since AM start
     */
    public static function getMinutesSinceAMStart($settings) {
        $current_time = strtotime(date('H:i:s'));
        $am_start = strtotime($settings['am_start_time']);
        return round(($current_time - $am_start) / 60);
    }

    /**
     * Determine attendance status (present/late)
     */
    public static function determineStatus($minutes_since_start, $late_threshold) {
        return ($minutes_since_start > $late_threshold) ? 'late' : 'present';
    }

    /**
     * Create attendance record for AM IN
     */
    public static function recordAMIn($conn, $student_id, $current_date, $current_time, $status) {
        $stmt = $conn->prepare("
            INSERT INTO attendance (student_id, attendance_date, time_in_am, status)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("isss", $student_id, $current_date, $current_time, $status);
        return $stmt->execute() ? $conn->insert_id : false;
    }

    /**
     * Update AM out time
     */
    public static function recordAMOut($conn, $attendance_id, $current_time) {
        $stmt = $conn->prepare("UPDATE attendance SET time_out_am = ? WHERE id = ?");
        $stmt->bind_param("si", $current_time, $attendance_id);
        return $stmt->execute();
    }

    /**
     * Update PM in time
     */
    public static function recordPMIn($conn, $attendance_id, $current_time) {
        $stmt = $conn->prepare("UPDATE attendance SET time_in_pm = ? WHERE id = ?");
        $stmt->bind_param("si", $current_time, $attendance_id);
        return $stmt->execute();
    }

    /**
     * Update PM out time
     */
    public static function recordPMOut($conn, $attendance_id, $current_time) {
        $stmt = $conn->prepare("UPDATE attendance SET time_out_pm = ? WHERE id = ?");
        $stmt->bind_param("si", $current_time, $attendance_id);
        return $stmt->execute();
    }

    /**
     * Get all settings
     */
    public static function getSettings($conn) {
        $settings = [];
        $result = $conn->query("SELECT setting_key, setting_value FROM settings");

        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        return $settings;
    }
}
