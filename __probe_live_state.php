<?php
$mysqli = new mysqli('localhost', 'root', '', 'cams');
if ($mysqli->connect_error) {
    fwrite(STDERR, $mysqli->connect_error . PHP_EOL);
    exit(1);
}

echo "-- COMMANDS (latest 20) --" . PHP_EOL;
$res = $mysqli->query("SELECT id, device_id, mode, student_id, finger_index, sensor_id, status, error_message, created_at, updated_at FROM device_commands ORDER BY id DESC LIMIT 20");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row) . PHP_EOL;
    }
}

echo "-- ACTIVE ENROLL --" . PHP_EOL;
$res = $mysqli->query("SELECT id, device_id, student_id, finger_index, status, scan_step, total_scan_steps, error_message FROM device_commands WHERE mode='ENROLL' AND status IN ('PENDING','IN_PROGRESS') ORDER BY id DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row) . PHP_EOL;
    }
}

echo "-- FINGERPRINT DUP CHECK sensor_id --" . PHP_EOL;
$res = $mysqli->query("SELECT sensor_id, COUNT(*) c FROM fingerprints GROUP BY sensor_id HAVING COUNT(*) > 1 ORDER BY c DESC, sensor_id ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row) . PHP_EOL;
    }
}

echo "-- FINGERPRINT DUP CHECK student+finger --" . PHP_EOL;
$res = $mysqli->query("SELECT student_id, finger_index, COUNT(*) c FROM fingerprints GROUP BY student_id, finger_index HAVING COUNT(*) > 1 ORDER BY c DESC, student_id ASC, finger_index ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row) . PHP_EOL;
    }
}
