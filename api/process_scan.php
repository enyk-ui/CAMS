<?php
/**
 * PROCESS SCAN API
 * Receives fingerprint scan from Arduino
 * Handles both REGISTRATION (5 scans) and ATTENDANCE modes
 */

header('Content-Type: application/json');
require_once '../config/database.php';

$data = json_decode(file_get_contents('php://input'), true);

$sensorId = $data['sensor_id'] ?? null;
$mode = $data['mode'] ?? 'attendance';
$device = $data['device'] ?? 'unknown';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // ===== REGISTRATION MODE =====
    if ($mode === 'registration') {
        $registrationId = $data['registration_id'] ?? '';
        $fingerNumber = $data['finger_number'] ?? 1;
        $scanNumber = $data['scan_number'] ?? 0;
        $totalFingers = $data['total_fingers'] ?? 1;
        
        if (!$registrationId) {
            echo json_encode([
                'success' => false,
                'message' => 'No active registration',
                'lcd_display' => 'No Active Reg'
            ]);
            exit;
        }
        
        // Get registration details
        $stmt = $conn->prepare("
            SELECT * FROM fingerprint_registrations 
            WHERE id = :reg_id AND status = 'active'
        ");
        $stmt->bindParam(':reg_id', $registrationId);
        $stmt->execute();
        $registration = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$registration) {
            echo json_encode([
                'success' => false,
                'message' => 'Registration not found',
                'lcd_display' => 'Reg Not Found'
            ]);
            exit;
        }
        
        $studentId = $registration['student_id'];
        
        // Save this scan
        $stmt = $conn->prepare("
            INSERT INTO fingerprint_scans 
            (registration_id, student_id, finger_number, scan_number, scan_data, quality)
            VALUES (:reg_id, :student_id, :finger_num, :scan_num, :scan_data, :quality)
        ");
        
        $scanData = "TEMPLATE_" . uniqid(); // Replace with actual fingerprint data
        $quality = rand(70, 99);
        
        $stmt->bindParam(':reg_id', $registrationId);
        $stmt->bindParam(':student_id', $studentId);
        $stmt->bindParam(':finger_num', $fingerNumber);
        $stmt->bindParam(':scan_num', $scanNumber);
        $stmt->bindParam(':scan_data', $scanData);
        $stmt->bindParam(':quality', $quality);
        $stmt->execute();
        
        // Update scan progress
        $nextScan = $scanNumber + 1;
        $nextFinger = $fingerNumber;
        
        if ($nextScan >= 5) {
            // Completed 5 scans for this finger
            $nextScan = 0;
            $nextFinger++;
            
            if ($nextFinger > $totalFingers) {
                // ALL FINGERS COMPLETED!
                $stmt = $conn->prepare("
                    UPDATE fingerprint_registrations 
                    SET status = 'completed', scan_number = :scan_num, finger_number = :finger_num
                    WHERE id = :reg_id
                ");
                $stmt->bindParam(':reg_id', $registrationId);
                $stmt->bindParam(':scan_num', $nextScan);
                $stmt->bindParam(':finger_num', $nextFinger);
                $stmt->execute();
                
                // Reset to attendance mode
                $stmt = $conn->prepare("
                    UPDATE system_settings 
                    SET value = 'attendance' 
                    WHERE setting_key = 'current_mode'
                ");
                $stmt->execute();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'All fingers registered successfully!',
                    'lcd_display' => 'Complete!',
                    'registration_complete' => true
                ]);
                exit;
            } else {
                // Move to next finger
                $message = "Finger $fingerNumber done! Scan finger $nextFinger";
            }
        } else {
            $message = "Scan " . ($nextScan + 1) . "/5";
        }
        
        // Update registration progress
        $stmt = $conn->prepare("
            UPDATE fingerprint_registrations 
            SET scan_number = :scan_num, finger_number = :finger_num, updated_at = NOW()
            WHERE id = :reg_id
        ");
        $stmt->bindParam(':reg_id', $registrationId);
        $stmt->bindParam(':scan_num', $nextScan);
        $stmt->bindParam(':finger_num', $nextFinger);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'lcd_display' => $message,
            'scan_complete' => true,
            'next_scan' => $nextScan,
            'next_finger' => $nextFinger
        ]);
        exit;
    }
    
    // ===== ATTENDANCE MODE =====
    if ($mode === 'attendance') {
        if (!$sensorId || $sensorId == 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Unknown fingerprint',
                'lcd_display' => 'Not Found'
            ]);
            exit;
        }
        
        // Look up student by sensor ID
        // You need to map sensor_id to student_id in your database
        $stmt = $conn->prepare("
            SELECT s.student_id, s.name 
            FROM students s
            WHERE s.fingerprint_id = :sensor_id
            LIMIT 1
        ");
        $stmt->bindParam(':sensor_id', $sensorId);
        $stmt->execute();
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            echo json_encode([
                'success' => false,
                'message' => 'Student not found',
                'lcd_display' => 'Not Registered'
            ]);
            exit;
        }
        
        // Record attendance
        $stmt = $conn->prepare("
            INSERT INTO attendance (student_id, scan_time, device)
            VALUES (:student_id, NOW(), :device)
        ");
        $stmt->bindParam(':student_id', $student['student_id']);
        $stmt->bindParam(':device', $device);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Attendance recorded',
            'lcd_display' => 'Welcome ' . $student['name'],
            'student_id' => $student['student_id'],
            'student_name' => $student['name']
        ]);
        exit;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'lcd_display' => 'System Error'
    ]);
}
?>
