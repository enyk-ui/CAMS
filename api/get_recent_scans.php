<?php
/**
 * API: Get Recent Scans Today
 * Used by dashboard for live auto-refresh
 */

header('Content-Type: application/json');

require_once '../config/db.php';

$today = date('Y-m-d');

$result = $mysqli->query("
    SELECT
        s.first_name,
        s.last_name,
        a.status,
        a.time_in_am,
        a.time_in_pm,
        a.updated_at
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    WHERE a.attendance_date = '$today'
    ORDER BY a.updated_at DESC
    LIMIT 10
");

$scans = [];
while ($row = $result->fetch_assoc()) {
    $scans[] = $row;
}

echo json_encode(['scans' => $scans]);
?>
