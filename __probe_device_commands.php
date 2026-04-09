<?php
$mysqli = new mysqli('localhost', 'root', '', 'cams');
if ($mysqli->connect_error) {
    fwrite(STDERR, $mysqli->connect_error . PHP_EOL);
    exit(1);
}

echo "-- COLUMNS device_commands --" . PHP_EOL;
$res = $mysqli->query("SHOW COLUMNS FROM device_commands");
if (!$res) {
    fwrite(STDERR, "SHOW COLUMNS error: " . $mysqli->error . PHP_EOL);
    exit(1);
}
while ($row = $res->fetch_assoc()) {
    echo implode('|', [
        $row['Field'],
        $row['Type'],
        $row['Null'],
        $row['Key'],
        (string)($row['Default'] ?? ''),
        $row['Extra']
    ]) . PHP_EOL;
}

echo "-- LAST ROWS device_commands --" . PHP_EOL;
$res = $mysqli->query("SELECT * FROM device_commands ORDER BY id DESC LIMIT 10");
if (!$res) {
    fwrite(STDERR, "SELECT error: " . $mysqli->error . PHP_EOL);
    exit(1);
}
while ($row = $res->fetch_assoc()) {
    echo json_encode($row) . PHP_EOL;
}
