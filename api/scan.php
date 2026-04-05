<?php
/**
 * API Endpoint: Fingerprint Scan Handler
 * Receives fingerprint ID from scanner and handles:
 * - Registration mode: link real sensor template ID to student
 * - Attendance mode: log IN/OUT attendance
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../config/db.php';
require_once '../helpers/EmailHelper.php';
require_once '../helpers/NotificationQueueHelper.php';
require_once '../config/mail.php';

function ensureScannerHeartbeatTable($mysqli) {
    $mysqli->query("CREATE TABLE IF NOT EXISTS scanner_heartbeat (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_ip VARCHAR(45) NOT NULL,
        user_agent VARCHAR(255) NULL,
        source VARCHAR(50) NOT NULL DEFAULT 'unknown',
        last_seen TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_last_seen (last_seen),
        INDEX idx_device_ip (device_ip)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function touchScannerHeartbeat($mysqli, $source = 'unknown') {
    ensureScannerHeartbeatTable($mysqli);

    $deviceIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    $stmt = $mysqli->prepare("INSERT INTO scanner_heartbeat (device_ip, user_agent, source, last_seen) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("sss", $deviceIp, $userAgent, $source);
    $stmt->execute();
}

function ensureFingerprintRegistrationTable($mysqli) {
    $mysqli->query("CREATE TABLE IF NOT EXISTS fingerprint_registrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        finger_number INT NOT NULL DEFAULT 1,
        scan_number INT NOT NULL DEFAULT 0,
        total_fingers INT NOT NULL DEFAULT 1,
        status ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status_updated (status, updated_at),
        INDEX idx_student_status (student_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function setCurrentMode($mysqli, $mode) {
    $stmt = $mysqli->prepare("INSERT INTO settings (setting_key, setting_value, description, updated_at) VALUES ('current_mode', ?, 'Current scanner mode', NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
    $stmt->bind_param("s", $mode);
    $stmt->execute();
}

function getSettings($mysqli) {
    $settings = [];
    $result = $mysqli->query("SELECT setting_key, setting_value FROM settings");

    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    if (!isset($settings['late_threshold_minutes'])) {
        $settings['late_threshold_minutes'] = '15';
    }
    if (!isset($settings['am_start_time'])) {
        $settings['am_start_time'] = '08:00:00';
    }
    if (!isset($settings['pm_start_time'])) {
        $settings['pm_start_time'] = '13:00:00';
    }

    return $settings;
}

function sendAttendanceNotification($db, $student_id, $student_email, $student_name, $status, $time, $date) {
    $settings = getSettings($db);
    if (!isset($settings['notification_enabled']) || $settings['notification_enabled'] !== 'true') {
        return;
    }

    if (empty($student_email)) {
        error_log("No email for student $student_id");
        return;
    }

    try {
        $emailHelper = new EmailHelper();

        $displayTime = date('g:i A', strtotime($time));
        $displayDate = date('F j, Y', strtotime($date));

        $emailSent = $emailHelper->sendAttendanceEmail(
            $student_email,
            $student_name,
            $status,
            $displayTime,
            $displayDate
        );

        if ($emailSent) {
            error_log("Email sent successfully to $student_email for student $student_id - Status: $status");
            return;
        }

        $queueHelper = new NotificationQueueHelper($db);

        global $EMAIL_TEMPLATES;
        $templateKey = 'attendance_' . strtolower($status);
        $template = $EMAIL_TEMPLATES[$templateKey] ?? null;

        if ($template) {
            $subject = $template['subject'];
            $body = str_replace(
                ['{STUDENT_NAME}', '{ATTENDANCE_TIME}', '{ATTENDANCE_DATE}'],
                [$student_name, $displayTime, $displayDate],
                $template['body']
            );

            $queueHelper->enqueueNotification(
                $student_id,
                $body,
                $subject,
                $student_email
            );
        }
    } catch (Exception $e) {
        error_log("Notification exception: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$mode = isset($input['mode']) ? trim($input['mode']) : 'attendance';
$sensor_id = isset($input['sensor_id']) ? trim($input['sensor_id']) : '';

try {
    touchScannerHeartbeat($mysqli, 'api/scan.php');

    if ($mode === 'registration') {
        ensureFingerprintRegistrationTable($mysqli);

        $registrationId = isset($input['registration_id']) ? (int)$input['registration_id'] : 0;
        $fingerNumber = isset($input['finger_number']) ? max(1, (int)$input['finger_number']) : 1;

        if ($registrationId <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'No active registration',
                'lcd_display' => 'No Active Reg'
            ]);
            exit;
        }

        if ($sensor_id === '' || $sensor_id === '0') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Missing enrolled sensor_id in registration mode',
                'lcd_display' => 'Enroll Failed'
            ]);
            exit;
        }

        $stmt = $mysqli->prepare("SELECT id, student_id, total_fingers, status FROM fingerprint_registrations WHERE id = ? AND status = 'active' LIMIT 1");
        $stmt->bind_param("i", $registrationId);
        $stmt->execute();
        $registration = $stmt->get_result()->fetch_assoc();

        if (!$registration) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Registration not found',
                'lcd_display' => 'Reg Not Found'
            ]);
            exit;
        }

        $studentId = (int)$registration['student_id'];
        $totalFingers = max(1, (int)$registration['total_fingers']);

        // Prevent same sensor slot from being linked to another student.
        $stmt = $mysqli->prepare("SELECT student_id FROM fingerprints WHERE sensor_id = ? AND is_active = true LIMIT 1");
        $stmt->bind_param("s", $sensor_id);
        $stmt->execute();
        $existingOwner = $stmt->get_result()->fetch_assoc();
        if ($existingOwner && (int)$existingOwner['student_id'] !== $studentId) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'Sensor ID already assigned to another student',
                'lcd_display' => 'ID In Use'
            ]);
            exit;
        }

        // Upsert student fingerprint for this finger index.
        $stmt = $mysqli->prepare("SELECT id FROM fingerprints WHERE student_id = ? AND finger_index = ? LIMIT 1");
        $stmt->bind_param("ii", $studentId, $fingerNumber);
        $stmt->execute();
        $existingFinger = $stmt->get_result()->fetch_assoc();

        if ($existingFinger) {
            $fingerprintId = (int)$existingFinger['id'];
            $stmt = $mysqli->prepare("UPDATE fingerprints SET sensor_id = ?, is_active = true, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $sensor_id, $fingerprintId);
            $stmt->execute();
        } else {
            $stmt = $mysqli->prepare("INSERT INTO fingerprints (student_id, finger_index, sensor_id, is_active) VALUES (?, ?, ?, true)");
            $stmt->bind_param("iis", $studentId, $fingerNumber, $sensor_id);
            $stmt->execute();
        }

        $nextFinger = $fingerNumber + 1;

        if ($nextFinger > $totalFingers) {
            $stmt = $mysqli->prepare("UPDATE fingerprint_registrations SET status = 'completed', finger_number = ?, scan_number = 0, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("ii", $fingerNumber, $registrationId);
            $stmt->execute();

            setCurrentMode($mysqli, 'attendance');

            echo json_encode([
                'success' => true,
                'message' => 'All fingers enrolled successfully',
                'lcd_display' => 'Complete!',
                'registration_complete' => true
            ]);
            exit;
        }

        $nextScan = 0;
        $stmt = $mysqli->prepare("UPDATE fingerprint_registrations SET finger_number = ?, scan_number = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("iii", $nextFinger, $nextScan, $registrationId);
        $stmt->execute();

        echo json_encode([
            'success' => true,
            'message' => 'Finger ' . $fingerNumber . ' enrolled. Proceed to finger ' . $nextFinger,
            'lcd_display' => 'Finger ' . $nextFinger . ' Next',
            'registration_complete' => false,
            'next_finger' => $nextFinger,
            'next_scan' => 0
        ]);
        exit;
    }

    // Attendance mode requires a local sensor match ID.
    if ($sensor_id === '' || $sensor_id === '0') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing sensor_id parameter',
            'lcd_display' => 'Not Found'
        ]);
        exit;
    }

    $stmt = $mysqli->prepare("SELECT f.student_id, s.first_name, s.last_name, s.email FROM fingerprints f JOIN students s ON f.student_id = s.id WHERE f.sensor_id = ? AND f.is_active = true AND s.status = 'active' LIMIT 1");
    $stmt->bind_param("s", $sensor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Fingerprint not registered',
            'lcd_display' => 'Not Registered'
        ]);
        exit;
    }

    $student = $result->fetch_assoc();
    $student_id = (int)$student['student_id'];
    $student_name = $student['first_name'] . ' ' . $student['last_name'];
    $student_email = $student['email'];

    $current_date = date('Y-m-d');
    $current_time = date('H:i:s');

    $settings = getSettings($mysqli);
    $late_threshold = (int)$settings['late_threshold_minutes'];
    $am_start = strtotime($settings['am_start_time']);
    $pm_start = strtotime($settings['pm_start_time']);

    $current_timestamp = strtotime($current_time);
    $is_am_session = $current_timestamp < $pm_start;

    $stmt = $mysqli->prepare("SELECT id FROM attendance WHERE student_id = ? AND attendance_date = ?");
    $stmt->bind_param("is", $student_id, $current_date);
    $stmt->execute();
    $attendance_result = $stmt->get_result();
    $attendance_exists = $attendance_result->num_rows > 0;
    $attendance_id = $attendance_exists ? (int)$attendance_result->fetch_assoc()['id'] : null;

    $status = 'present';
    $message = '';
    $display_status = 'Present';
    $time_diff_minutes = round((strtotime($current_time) - $am_start) / 60);

    if ($is_am_session) {
        if ($attendance_exists) {
            $sql = "UPDATE attendance SET time_out_am = ? WHERE id = ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("si", $current_time, $attendance_id);
            $stmt->execute();
            $message = 'Time Out Recorded';
            $display_status = 'Good Bye';
        } else {
            $status = ($time_diff_minutes > $late_threshold) ? 'late' : 'present';
            $sql = "INSERT INTO attendance (student_id, attendance_date, time_in_am, status) VALUES (?, ?, ?, ?)";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("isss", $student_id, $current_date, $current_time, $status);
            $stmt->execute();
            $attendance_id = $mysqli->insert_id;

            if ($status === 'late') {
                $message = 'Late by ' . ($time_diff_minutes - $late_threshold) . ' minutes';
                $display_status = 'Late';
            } else {
                $message = 'Good Morning, On Time';
                $display_status = 'Present';
            }
        }
    } else {
        if ($attendance_exists) {
            $stmt = $mysqli->prepare("SELECT time_in_pm FROM attendance WHERE id = ?");
            $stmt->bind_param("i", $attendance_id);
            $stmt->execute();
            $attend_record = $stmt->get_result()->fetch_assoc();

            if ($attend_record['time_in_pm'] === null) {
                $sql = "UPDATE attendance SET time_in_pm = ? WHERE id = ?";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param("si", $current_time, $attendance_id);
                $stmt->execute();
                $message = 'Good Afternoon, Welcome Back';
                $display_status = 'PM Checked In';
            } else {
                $sql = "UPDATE attendance SET time_out_pm = ? WHERE id = ?";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param("si", $current_time, $attendance_id);
                $stmt->execute();
                $message = 'See You Tomorrow';
                $display_status = 'Good Bye';
            }
        } else {
            $absent_status = 'absent';
            $sql = "INSERT INTO attendance (student_id, attendance_date, time_in_pm, status) VALUES (?, ?, ?, ?)";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("isss", $student_id, $current_date, $current_time, $absent_status);
            $stmt->execute();
            $attendance_id = $mysqli->insert_id;
            $message = 'Absent in AM, Present in PM';
            $display_status = 'Absent AM';
        }
    }

    sendAttendanceNotification($mysqli, $student_id, $student_email, $student_name, $status, $current_time, $current_date);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'student_name' => $student_name,
        'student_email' => $student_email,
        'timestamp' => date('Y-m-d H:i:s'),
        'status' => $status,
        'message' => $message,
        'session' => $is_am_session ? 'AM' : 'PM',
        'lcd_display' => $display_status,
        'attendance_id' => $attendance_id
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'lcd_display' => 'Error'
    ]);
}
?>