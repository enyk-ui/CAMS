<?php
/**
 * CAMS Database Manager
 * Browser utility to create, update, and migrate database from cams.sql
 */

declare(strict_types=1);

const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'cams';
const DB_PORT = 3306;

$sqlFile = __DIR__ . '/cams.sql';
$results = [];
$summary = [
    'executed' => 0,
    'success' => 0,
    'failed' => 0,
    'mode' => null,
    'duration_ms' => 0
];
$errorMessage = '';

function openConnection(bool $withDatabase): mysqli
{
    $database = $withDatabase ? DB_NAME : null;
    mysqli_report(MYSQLI_REPORT_OFF);

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, $database, DB_PORT);
    if ($conn->connect_error) {
        throw new RuntimeException('Connection failed: ' . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}

function normalizeSqlForUpdate(string $sql): string
{
    // Make table creation more idempotent for update runs.
    $sql = preg_replace('/\bCREATE\s+TABLE\s+`/i', 'CREATE TABLE IF NOT EXISTS `', $sql);

    // Prevent duplicate key errors on data insert for update mode.
    $sql = preg_replace('/\bINSERT\s+INTO\s+`/i', 'INSERT IGNORE INTO `', $sql);

    return $sql;
}

function splitSqlStatements(string $sql): array
{
    // Remove block comments.
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

    $lines = preg_split('/\r\n|\r|\n/', $sql);
    $clean = [];

    foreach ($lines as $line) {
        $trim = ltrim($line);
        if ($trim === '') {
            $clean[] = $line;
            continue;
        }

        if (str_starts_with($trim, '--') || str_starts_with($trim, '#')) {
            continue;
        }

        $clean[] = $line;
    }

    $sql = implode("\n", $clean);

    $statements = [];
    $buffer = '';
    $inSingleQuote = false;
    $inDoubleQuote = false;
    $inBacktick = false;
    $escaped = false;

    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $buffer .= $char;

        if ($escaped) {
            $escaped = false;
            continue;
        }

        if ($char === '\\') {
            $escaped = true;
            continue;
        }

        if ($char === "'" && !$inDoubleQuote && !$inBacktick) {
            $inSingleQuote = !$inSingleQuote;
            continue;
        }

        if ($char === '"' && !$inSingleQuote && !$inBacktick) {
            $inDoubleQuote = !$inDoubleQuote;
            continue;
        }

        if ($char === '`' && !$inSingleQuote && !$inDoubleQuote) {
            $inBacktick = !$inBacktick;
            continue;
        }

        if ($char === ';' && !$inSingleQuote && !$inDoubleQuote && !$inBacktick) {
            $statement = trim($buffer);
            if ($statement !== ';' && $statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
        }
    }

    $tail = trim($buffer);
    if ($tail !== '') {
        $statements[] = $tail;
    }

    return $statements;
}

function executeStatements(mysqli $conn, array $statements): array
{
    $results = [];

    foreach ($statements as $index => $statement) {
        $label = preg_replace('/\s+/', ' ', substr($statement, 0, 120));

        if (shouldSkipStatement($statement)) {
            $results[] = [
                'index' => $index + 1,
                'ok' => true,
                'message' => 'SKIPPED (legacy incompatible statement)',
                'statement' => $label
            ];
            continue;
        }

        $ok = $conn->query($statement);

        if ($ok) {
            $results[] = [
                'index' => $index + 1,
                'ok' => true,
                'message' => 'OK',
                'statement' => $label
            ];
            continue;
        }

        $results[] = [
            'index' => $index + 1,
            'ok' => false,
            'message' => $conn->error,
            'statement' => $label
        ];
    }

    return $results;
}

function shouldSkipStatement(string $statement): bool
{
    $normalized = strtolower(preg_replace('/\s+/', ' ', $statement));

    $patterns = [
        'alter table `fingerprint_registrations`',
        'modify `id` int(11) not null auto_increment, auto_increment=4',
        'add key `idx_status_updated` (`status`,`updated_at`)',
        'alter table `fingerprints` add primary key (`id`), add unique key `unique_finger_per_student` (`student_id`,`finger_index`)',
        'alter table `fingerprints` add constraint `fingerprints_ibfk_1` foreign key (`student_id`) references `students` (`id`)',
    ];

    foreach ($patterns as $pattern) {
        if (str_contains($normalized, $pattern)) {
            return true;
        }
    }

    return false;
}

function executeRepairQueries(mysqli $conn): array
{
    $queries = [
        // Keep the users table for the active API.
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_no VARCHAR(50) NOT NULL UNIQUE,
            full_name VARCHAR(150) NOT NULL,
            email VARCHAR(120) DEFAULT NULL UNIQUE,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        // If legacy students exists, map it into users (safe no-op if already copied).
        "INSERT IGNORE INTO users (id, student_no, full_name, email, status, created_at, updated_at)
         SELECT id,
                student_id,
                CONCAT(first_name, ' ', IFNULL(CONCAT(middle_initial, ' '), ''), last_name),
                email,
                CASE WHEN status = 'active' THEN 'active' ELSE 'inactive' END,
                created_at,
                updated_at
         FROM students",

        // Ensure fingerprints schema aligns with active API.
        "ALTER TABLE fingerprints CHANGE COLUMN student_id user_id INT(11) NOT NULL",
        "ALTER TABLE fingerprints ADD PRIMARY KEY (id)",
        "ALTER TABLE fingerprints MODIFY id INT(11) NOT NULL AUTO_INCREMENT",
        "CREATE UNIQUE INDEX uniq_fingerprints_user_finger ON fingerprints(user_id, finger_index)",
        "CREATE UNIQUE INDEX uniq_fingerprints_sensor ON fingerprints(sensor_id)",

        // Ensure devices table exists and has a default scanner.
        "CREATE TABLE IF NOT EXISTS devices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_key VARCHAR(50) NOT NULL UNIQUE,
            name VARCHAR(100) DEFAULT NULL,
            location VARCHAR(100) DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            last_seen TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        "INSERT IGNORE INTO devices (id, device_key, name, is_active, created_at)
         VALUES (1, 'CAMS_ESP8266', 'Default Scanner', 1, NOW())",

        // Ensure attendance table exists in daily summary format.
        "CREATE TABLE IF NOT EXISTS attendance (
            id INT(11) NOT NULL AUTO_INCREMENT,
            student_id INT(11) NOT NULL,
            attendance_date DATE NOT NULL,
            time_in_am TIME DEFAULT NULL,
            time_out_am TIME DEFAULT NULL,
            time_in_pm TIME DEFAULT NULL,
            time_out_pm TIME DEFAULT NULL,
            status ENUM('present','late','absent','excused') DEFAULT 'absent',
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_attendance_per_day (student_id, attendance_date),
            KEY idx_attendance_date (attendance_date),
            KEY idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        // Ensure attendance_logs exists in the format used by API.
        "CREATE TABLE IF NOT EXISTS attendance_logs (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            device_id INT(11) NOT NULL,
            type ENUM('IN','OUT') NOT NULL,
            timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        // Ensure device_commands exists in the format used by API.
        "CREATE TABLE IF NOT EXISTS device_commands (
            id INT(11) NOT NULL AUTO_INCREMENT,
            device_id INT(11) NOT NULL,
            mode ENUM('IDLE','ENROLL','DELETE') DEFAULT 'IDLE',
            user_id INT(11) DEFAULT NULL,
            finger_index TINYINT(4) DEFAULT NULL,
            sensor_id INT(11) DEFAULT NULL,
            status ENUM('PENDING','IN_PROGRESS','COMPLETED','FAILED') DEFAULT 'PENDING',
            error_message VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    ];

    $results = [];
    foreach ($queries as $query) {
        $label = preg_replace('/\s+/', ' ', substr($query, 0, 120));
        $ok = $conn->query($query);
        $results[] = [
            'index' => 0,
            'ok' => (bool)$ok,
            'message' => $ok ? 'OK (repair)' : ('IGNORED (repair): ' . $conn->error),
            'statement' => $label
        ];
    }

    return $results;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $summary['mode'] = $action;
    $startTime = microtime(true);

    try {
        if (!file_exists($sqlFile)) {
            throw new RuntimeException('cams.sql not found at project root.');
        }

        $rawSql = file_get_contents($sqlFile);
        if ($rawSql === false || trim($rawSql) === '') {
            throw new RuntimeException('cams.sql is empty or unreadable.');
        }

        if ($action === 'create') {
            $conn = openConnection(false);
            $conn->query('DROP DATABASE IF EXISTS `' . DB_NAME . '`');
            $conn->query('CREATE DATABASE `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
            $conn->select_db(DB_NAME);

            $statements = splitSqlStatements($rawSql);
            $results = executeStatements($conn, $statements);
            $repairResults = executeRepairQueries($conn);
            $results = array_merge($results, $repairResults);
            $conn->close();
        } elseif ($action === 'update') {
            $conn = openConnection(true);
            $sql = normalizeSqlForUpdate($rawSql);
            $statements = splitSqlStatements($sql);
            $results = executeStatements($conn, $statements);
            $repairResults = executeRepairQueries($conn);
            $results = array_merge($results, $repairResults);
            $conn->close();
        } elseif ($action === 'migrate') {
            $conn = openConnection(true);
            $statements = splitSqlStatements($rawSql);
            $results = executeStatements($conn, $statements);
            $repairResults = executeRepairQueries($conn);
            $results = array_merge($results, $repairResults);
            $conn->close();
        } else {
            throw new RuntimeException('Invalid action selected.');
        }

        foreach ($results as $i => $row) {
            $results[$i]['index'] = $i + 1;
        }

        $summary['executed'] = count($results);
        foreach ($results as $row) {
            if ($row['ok']) {
                $summary['success']++;
            } else {
                $summary['failed']++;
            }
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }

    $summary['duration_ms'] = (int)round((microtime(true) - $startTime) * 1000);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CAMS Database Manager</title>
    <style>
        body {
            font-family: Segoe UI, Arial, sans-serif;
            max-width: 1050px;
            margin: 24px auto;
            padding: 0 16px;
            color: #222;
        }

        h1 {
            margin-bottom: 6px;
        }

        .muted {
            color: #666;
            margin-bottom: 20px;
        }

        .panel {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 8px;
        }

        button {
            border: 1px solid #111;
            background: #fff;
            color: #111;
            border-radius: 6px;
            padding: 10px 14px;
            cursor: pointer;
            font-weight: 600;
        }

        button.primary {
            background: #111;
            color: #fff;
        }

        button.warn {
            border-color: #b63;
            color: #b63;
        }

        .error {
            background: #fff4f4;
            border: 1px solid #e4bcbc;
            color: #8d1d1d;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 8px;
            margin-top: 8px;
        }

        .metric {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 10px;
            background: #fafafa;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            font-size: 14px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f3f3f3;
        }

        .ok {
            color: #136d13;
            font-weight: 700;
        }

        .fail {
            color: #a91f1f;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <h1>CAMS Database Manager</h1>
    <div class="muted">Create, update, or migrate database using cams.sql</div>

    <div class="panel">
        <strong>SQL source:</strong> <?php echo htmlspecialchars($sqlFile); ?><br>
        <strong>Database:</strong> <?php echo htmlspecialchars(DB_NAME); ?>

        <form method="post" class="actions">
            <button class="warn" type="submit" name="action" value="create" onclick="return confirm('This will DROP and recreate the database. Continue?');">Create (Fresh)</button>
            <button type="submit" name="action" value="update">Update (Idempotent)</button>
            <button class="primary" type="submit" name="action" value="migrate">Migrate (Run All)</button>
        </form>

        <?php if ($errorMessage !== ''): ?>
            <div class="error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>
    </div>

    <?php if ($summary['mode'] !== null): ?>
        <div class="panel">
            <h3>Run Summary</h3>
            <div class="summary">
                <div class="metric"><strong>Mode</strong><br><?php echo htmlspecialchars((string)$summary['mode']); ?></div>
                <div class="metric"><strong>Executed</strong><br><?php echo (int)$summary['executed']; ?></div>
                <div class="metric"><strong>Success</strong><br><?php echo (int)$summary['success']; ?></div>
                <div class="metric"><strong>Failed</strong><br><?php echo (int)$summary['failed']; ?></div>
                <div class="metric"><strong>Duration</strong><br><?php echo (int)$summary['duration_ms']; ?> ms</div>
            </div>
        </div>

        <div class="panel">
            <h3>Execution Log</h3>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Status</th>
                        <th>Statement</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row): ?>
                        <tr>
                            <td><?php echo (int)$row['index']; ?></td>
                            <td class="<?php echo $row['ok'] ? 'ok' : 'fail'; ?>">
                                <?php echo $row['ok'] ? 'OK' : 'FAILED'; ?>
                            </td>
                            <td><?php echo htmlspecialchars((string)$row['statement']); ?></td>
                            <td><?php echo htmlspecialchars((string)$row['message']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</body>
</html>

<?php
/*
 * © 2026 TambyTech.
 * This source code is proprietary and confidential.
 * Any unauthorized use, copying, modification, distribution, or disclosure is strictly prohibited.
 * All rights reserved.
 */
?>
