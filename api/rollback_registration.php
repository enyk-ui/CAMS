<?php
/**
 * Rollback student registration if fingerprint enrollment was not completed.
 * POST /api/rollback_registration.php
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$studentId = isset($input['student_id']) ? (int)$input['student_id'] : 0;

if ($studentId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid student_id'
    ]);
    exit;
}

try {
    $mysqli->begin_transaction();

    // If fingerprints already exist, do not rollback.
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM fingerprints WHERE student_id = ? AND is_active = true");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $hasFingerprints = (int)$stmt->get_result()->fetch_assoc()['total'] > 0;

    if ($hasFingerprints) {
        $mysqli->rollback();
        echo json_encode([
            'success' => false,
            'message' => 'Fingerprint enrollment already exists; rollback skipped'
        ]);
        exit;
    }

    // Clear transient registration rows for this student.
    $stmt = $mysqli->prepare("DELETE FROM fingerprint_registrations WHERE student_id = ?");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();

    // Delete student (cascades any child rows if present).
    $stmt = $mysqli->prepare("DELETE FROM students WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();

    $deleted = $stmt->affected_rows > 0;

    if (!$deleted) {
        $mysqli->rollback();
        echo json_encode([
            'success' => false,
            'message' => 'Student not found or already removed'
        ]);
        exit;
    }

    $mysqli->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Student registration rolled back'
    ]);
} catch (Exception $e) {
    if ($mysqli->errno) {
        $mysqli->rollback();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Rollback failed: ' . $e->getMessage()
    ]);
}
