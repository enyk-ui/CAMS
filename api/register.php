<?php
/**
 * Student Registration API Endpoint
 * POST /api/register.php
 *
 * Accepts student information and fingerprints array
 * Creates student record and enrolls fingerprints
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config/db.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

try {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }

    // Validate required fields
    $required = ['student_id', 'first_name', 'last_name', 'email', 'year', 'section'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Extract data
    $student_id = trim($input['student_id']);
    $first_name = trim($input['first_name']);
    $middle_initial = !empty($input['middle_initial']) ? trim($input['middle_initial']) : null;
    $last_name = trim($input['last_name']);
    $email = trim($input['email']);
    $year = (int)$input['year'];
    $section = trim($input['section']);
    $sessionId = isset($input['session_id']) ? $input['session_id'] : null;

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email format");
    }

    // Validate year
    if ($year < 1 || $year > 4) {
        throw new Exception("Invalid year. Must be 1-4");
    }

    // Check if student_id already exists
    $stmt = $mysqli->prepare("SELECT id FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception("Student ID already exists");
    }

    // Check if email already exists
    $stmt = $mysqli->prepare("SELECT id FROM students WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception("Email already exists");
    }

    // Start transaction
    $mysqli->begin_transaction();

    try {
        // Step 1: Insert student record
        $stmt = $mysqli->prepare("
            INSERT INTO students (student_id, first_name, middle_initial, last_name, email, year, section, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
        ");

        $stmt->bind_param(
            "sssssss",
            $student_id,
            $first_name,
            $middle_initial,
            $last_name,
            $email,
            $year,
            $section
        );

        $stmt->execute();
        $new_student_id = $mysqli->insert_id;

           // Fingerprints are saved in a separate API call after student creation
           // so they always receive a valid student_id.
           $fingerprintCount = 0;

        // Commit transaction
        $mysqli->commit();

        // Return success response
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Student registered successfully',
            'student_id' => $new_student_id,
            'student_code' => $student_id,
            'name' => $first_name . ' ' . $last_name,
               'session_id' => $sessionId,
            'fingerprints_enrolled' => $fingerprintCount
        ]);

    } catch (Exception $e) {
        // Rollback on error
        $mysqli->rollback();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$mysqli->close();
