<?php
require __DIR__ . '/config/db.php';
$sql = "SELECT id,device_id,mode,status,student_id,finger_index,sensor_id,error_message,created_at,updated_at FROM device_commands ORDER BY id DESC LIMIT 30";
$result = $mysqli->query($sql);
if (!$result) {
    echo "QUERY_ERROR: " . $mysqli->error . PHP_EOL;
    exit(1);
}
while ($row = $result->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
