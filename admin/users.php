<?php
/**
 * Admin - Teacher Accounts
 * Add and edit teacher accounts from users table.
 */

require_once '../config/db.php';
require_once '../helpers/SchoolYearHelper.php';
require '../includes/header.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php?error=Unauthorized');
    exit;
}

SchoolYearHelper::ensureSchoolYearSupport($mysqli);
$activeSchoolYear = SchoolYearHelper::getEffectiveSchoolYearRange($mysqli);
$schoolYears = SchoolYearHelper::getAllSchoolYears($mysqli);
$activeSchoolYearLabel = (string)($activeSchoolYear['label'] ?? '');

function usersTableExists(mysqli $mysqli): bool
{
    $result = $mysqli->query("SHOW TABLES LIKE 'users'");
    return $result && $result->num_rows > 0;
}

function usersColumnExists(mysqli $mysqli, string $column): bool
{
    $safe = $mysqli->real_escape_string($column);
    $result = $mysqli->query("SHOW COLUMNS FROM users LIKE '{$safe}'");
    return $result && $result->num_rows > 0;
}

function ensureUsersTeacherSchema(mysqli $mysqli): bool
{
    if (!usersTableExists($mysqli)) {
        $createSql = "
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL,
                password VARCHAR(255) NOT NULL,
                full_name VARCHAR(150) NOT NULL,
                email VARCHAR(120) DEFAULT NULL,
                role ENUM('admin','teacher') NOT NULL DEFAULT 'teacher',
                school_year_label VARCHAR(20) DEFAULT NULL,
                year_level TINYINT UNSIGNED DEFAULT NULL,
                section VARCHAR(50) DEFAULT NULL,
                status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        if (!$mysqli->query($createSql)) {
            return false;
        }
    }

    $alterStatements = [];

    if (!usersColumnExists($mysqli, 'username')) {
        $alterStatements[] = "ADD COLUMN username VARCHAR(50) DEFAULT NULL";
    }
    if (!usersColumnExists($mysqli, 'password')) {
        $alterStatements[] = "ADD COLUMN password VARCHAR(255) DEFAULT NULL";
    }
    if (!usersColumnExists($mysqli, 'full_name')) {
        $alterStatements[] = "ADD COLUMN full_name VARCHAR(150) NOT NULL DEFAULT ''";
    }
    if (!usersColumnExists($mysqli, 'email')) {
        $alterStatements[] = "ADD COLUMN email VARCHAR(120) DEFAULT NULL";
    }
    if (!usersColumnExists($mysqli, 'role')) {
        $alterStatements[] = "ADD COLUMN role ENUM('admin','teacher') NOT NULL DEFAULT 'teacher'";
    }
    if (!usersColumnExists($mysqli, 'school_year_label')) {
        $alterStatements[] = "ADD COLUMN school_year_label VARCHAR(20) DEFAULT NULL";
    }
    if (!usersColumnExists($mysqli, 'year_level')) {
        $alterStatements[] = "ADD COLUMN year_level TINYINT UNSIGNED DEFAULT NULL";
    }
    if (!usersColumnExists($mysqli, 'section')) {
        $alterStatements[] = "ADD COLUMN section VARCHAR(50) DEFAULT NULL";
    }
    if (!usersColumnExists($mysqli, 'status')) {
        $alterStatements[] = "ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active'";
    }
    if (!usersColumnExists($mysqli, 'created_at')) {
        $alterStatements[] = "ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP";
    }
    if (!usersColumnExists($mysqli, 'updated_at')) {
        $alterStatements[] = "ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
    }

    foreach ($alterStatements as $statement) {
        if (!$mysqli->query("ALTER TABLE users {$statement}")) {
            return false;
        }
    }

    return true;
}

function ensureUsersSectionColumn(mysqli $mysqli): bool
{
    if (!ensureUsersTeacherSchema($mysqli)) {
        return false;
    }

    $hasSection = usersColumnExists($mysqli, 'section');
    $hasYearLevel = usersColumnExists($mysqli, 'year_level');
    $hasSchoolYear = usersColumnExists($mysqli, 'school_year_label');
    if ($hasSection && $hasYearLevel && $hasSchoolYear) {
        return true;
    }

    if (!$hasSection && !$mysqli->query("ALTER TABLE users ADD COLUMN section VARCHAR(50) DEFAULT NULL")) {
        return false;
    }
    if (!$hasYearLevel && !$mysqli->query("ALTER TABLE users ADD COLUMN year_level TINYINT UNSIGNED DEFAULT NULL")) {
        return false;
    }
    if (!$hasSchoolYear && !$mysqli->query("ALTER TABLE users ADD COLUMN school_year_label VARCHAR(20) DEFAULT NULL")) {
        return false;
    }

    return true;
}

function ensureSectionManagementSchema(mysqli $mysqli): bool
{
    $createSections = "
        CREATE TABLE IF NOT EXISTS sections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            year_grade VARCHAR(20) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_section_name_year (name, year_grade)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $createTeacherSections = "
        CREATE TABLE IF NOT EXISTS teacher_sections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            section_id INT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_teacher_section (teacher_id, section_id),
            UNIQUE KEY uniq_section_teacher (section_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    return $mysqli->query($createSections) && $mysqli->query($createTeacherSections);
}

function resolveOrCreateSectionId(mysqli $mysqli, int $yearLevel, string $sectionName): int
{
    if ($yearLevel <= 0 || $sectionName === '') {
        return 0;
    }

    $yearText = (string)$yearLevel;
    $selectStmt = $mysqli->prepare('SELECT id FROM sections WHERE year_grade = ? AND name = ? LIMIT 1');
    if (!$selectStmt) {
        return 0;
    }

    $selectStmt->bind_param('ss', $yearText, $sectionName);
    $selectStmt->execute();
    $row = $selectStmt->get_result()->fetch_assoc();
    $selectStmt->close();

    if ($row) {
        return (int)$row['id'];
    }

    $insertStmt = $mysqli->prepare('INSERT INTO sections (name, year_grade) VALUES (?, ?)');
    if (!$insertStmt) {
        return 0;
    }

    $insertStmt->bind_param('ss', $sectionName, $yearText);
    if ($insertStmt->execute()) {
        $newId = (int)$mysqli->insert_id;
        $insertStmt->close();
        return $newId;
    }

    $insertStmt->close();
    return 0;
}

function syncTeacherSections(mysqli $mysqli, int $teacherId, array $sectionIds): void
{
    $deleteStmt = $mysqli->prepare('DELETE FROM teacher_sections WHERE teacher_id = ?');
    if ($deleteStmt) {
        $deleteStmt->bind_param('i', $teacherId);
        $deleteStmt->execute();
        $deleteStmt->close();
    }

    foreach ($sectionIds as $sectionId) {
        $sid = (int)$sectionId;
        if ($sid <= 0) {
            continue;
        }

        $insertStmt = $mysqli->prepare('INSERT IGNORE INTO teacher_sections (teacher_id, section_id) VALUES (?, ?)');
        if ($insertStmt) {
            $insertStmt->bind_param('ii', $teacherId, $sid);
            $insertStmt->execute();
            $insertStmt->close();
        }
    }
}

function findSectionOwnershipConflict(mysqli $mysqli, array $sectionIds, int $excludeTeacherId = 0): ?string
{
    foreach ($sectionIds as $sectionId) {
        $sid = (int)$sectionId;
        if ($sid <= 0) {
            continue;
        }

        $sql = 'SELECT ts.teacher_id, u.full_name, s.year_grade, s.name
                FROM teacher_sections ts
                JOIN users u ON u.id = ts.teacher_id
                JOIN sections s ON s.id = ts.section_id
                WHERE ts.section_id = ?';

        if ($excludeTeacherId > 0) {
            $sql .= ' AND ts.teacher_id <> ?';
        }
        $sql .= ' LIMIT 1';

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            continue;
        }

        if ($excludeTeacherId > 0) {
            $stmt->bind_param('ii', $sid, $excludeTeacherId);
        } else {
            $stmt->bind_param('i', $sid);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $teacherName = trim((string)($row['full_name'] ?? 'another teacher'));
            $yearGrade = trim((string)($row['year_grade'] ?? ''));
            $sectionName = trim((string)($row['name'] ?? ''));
            return 'Section ' . $yearGrade . ' - ' . $sectionName . ' is already assigned to ' . $teacherName . '.';
        }
    }

    return null;
}

function findTeacherAssignmentConflict(
    mysqli $mysqli,
    string $schoolYearLabel,
    int $yearLevel,
    string $section,
    int $excludeTeacherId = 0
): ?array {
    $sql = 'SELECT id, full_name, username FROM users
            WHERE role = "teacher"
              AND COALESCE(school_year_label, "") = ?
              AND year_level = ?
              AND section = ?';

    if ($excludeTeacherId > 0) {
        $sql .= ' AND id <> ?';
    }

    $sql .= ' LIMIT 1';
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return null;
    }

    if ($excludeTeacherId > 0) {
        $stmt->bind_param('sisi', $schoolYearLabel, $yearLevel, $section, $excludeTeacherId);
    } else {
        $stmt->bind_param('sis', $schoolYearLabel, $yearLevel, $section);
    }

    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function findTeacherCredentialConflict(
    mysqli $mysqli,
    string $username,
    string $email,
    int $excludeTeacherId = 0
): ?string {
    $sql = 'SELECT id, username, email FROM users
            WHERE role = "teacher"
              AND (username = ? OR email = ?)';

    if ($excludeTeacherId > 0) {
        $sql .= ' AND id <> ?';
    }

    $sql .= ' LIMIT 1';
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return null;
    }

    if ($excludeTeacherId > 0) {
        $stmt->bind_param('ssi', $username, $email, $excludeTeacherId);
    } else {
        $stmt->bind_param('ss', $username, $email);
    }

    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    $sameUsername = isset($row['username']) && strcasecmp((string)$row['username'], $username) === 0;
    $sameEmail = isset($row['email']) && strcasecmp((string)$row['email'], $email) === 0;

    if ($sameUsername && $sameEmail) {
        return 'Username and email already exist.';
    }
    if ($sameUsername) {
        return 'Username already exists.';
    }
    if ($sameEmail) {
        return 'Email already exists.';
    }

    return 'Username or email already exists.';
}

$setupError = '';
if (!ensureUsersSectionColumn($mysqli) || !ensureSectionManagementSchema($mysqli)) {
    $setupError = 'Unable to prepare teacher account schema. Please run update_database.php.';
}

$message = '';
$error = $setupError;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $setupError === '') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_teacher') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $fullName = trim($_POST['full_name'] ?? '');
        $schoolYearLabel = trim($_POST['school_year_label'] ?? $activeSchoolYearLabel);
        $yearLevel = (int)($_POST['year_level'] ?? 0);
        $section = trim($_POST['section'] ?? '');
        $sectionIds = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['section_ids'] ?? [])), static fn($id) => $id > 0)));
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        if (empty($sectionIds) && $section !== '' && $yearLevel > 0) {
            $resolvedSectionId = resolveOrCreateSectionId($mysqli, $yearLevel, $section);
            if ($resolvedSectionId > 0) {
                $sectionIds[] = $resolvedSectionId;
            }
        }

        if ($username === '' || $email === '' || $password === '' || $fullName === '' || empty($sectionIds) || $schoolYearLabel === '') {
            $error = 'All fields are required for adding a teacher.';
        } else {
            $assignmentConflict = findSectionOwnershipConflict($mysqli, $sectionIds);
            if ($assignmentConflict) {
                $error = $assignmentConflict;
            } else {
                $credentialConflict = findTeacherCredentialConflict($mysqli, $username, $email);
                if ($credentialConflict !== null) {
                    $error = $credentialConflict;
                } else {
                    $primarySectionId = (int)$sectionIds[0];
                    $primarySectionStmt = $mysqli->prepare('SELECT year_grade, name FROM sections WHERE id = ? LIMIT 1');
                    $primaryYear = null;
                    $primarySection = null;
                    if ($primarySectionStmt) {
                        $primarySectionStmt->bind_param('i', $primarySectionId);
                        $primarySectionStmt->execute();
                        $primarySectionRow = $primarySectionStmt->get_result()->fetch_assoc();
                        $primarySectionStmt->close();
                        if ($primarySectionRow) {
                            $primaryYear = (int)($primarySectionRow['year_grade'] ?? 0);
                            $primarySection = (string)($primarySectionRow['name'] ?? '');
                        }
                    }

                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $mysqli->prepare('INSERT INTO users (username, password, full_name, email, role, school_year_label, year_level, section, status) VALUES (?, ?, ?, ?, "teacher", ?, ?, ?, ?)');
                    if (!$stmt) {
                        $error = 'Unable to prepare add-teacher request.';
                    } else {
                        $stmt->bind_param('sssssiss', $username, $hash, $fullName, $email, $schoolYearLabel, $primaryYear, $primarySection, $status);
                        try {
                            if ($stmt->execute()) {
                                $teacherId = (int)$mysqli->insert_id;
                                syncTeacherSections($mysqli, $teacherId, $sectionIds);
                                $message = 'Teacher account created successfully.';
                            } else {
                                $error = 'Unable to create teacher account. Please try again.';
                            }
                        } catch (mysqli_sql_exception $e) {
                            $error = stripos($e->getMessage(), 'Duplicate entry') !== false
                                ? 'Username or email already exists.'
                                : 'Unable to create teacher account. Please try again.';
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }

    if ($action === 'update_teacher') {
        $teacherId = (int) ($_POST['teacher_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $schoolYearLabel = trim($_POST['school_year_label'] ?? $activeSchoolYearLabel);
        $yearLevel = (int)($_POST['year_level'] ?? 0);
        $section = trim($_POST['section'] ?? '');
        $sectionIds = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['section_ids'] ?? [])), static fn($id) => $id > 0)));
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        $newPassword = $_POST['new_password'] ?? '';

        if (empty($sectionIds) && $section !== '' && $yearLevel > 0) {
            $resolvedSectionId = resolveOrCreateSectionId($mysqli, $yearLevel, $section);
            if ($resolvedSectionId > 0) {
                $sectionIds[] = $resolvedSectionId;
            }
        }

        if ($teacherId <= 0 || $username === '' || $email === '' || $fullName === '' || empty($sectionIds) || $schoolYearLabel === '') {
            $error = 'Please complete all required teacher fields.';
        } else {
            $checkStmt = $mysqli->prepare('SELECT id FROM users WHERE id = ? AND role = "teacher" LIMIT 1');
            $checkStmt->bind_param('i', $teacherId);
            $checkStmt->execute();
            $checkRes = $checkStmt->get_result();
            $exists = $checkRes && $checkRes->num_rows > 0;
            $checkStmt->close();

            if (!$exists) {
                $error = 'Teacher account not found.';
            } else {
                $assignmentConflict = findSectionOwnershipConflict($mysqli, $sectionIds, $teacherId);
                if ($assignmentConflict) {
                    $error = $assignmentConflict;
                } else {
                    $credentialConflict = findTeacherCredentialConflict($mysqli, $username, $email, $teacherId);
                    if ($credentialConflict !== null) {
                        $error = $credentialConflict;
                    } else {
                        $primarySectionId = (int)$sectionIds[0];
                        $primarySectionStmt = $mysqli->prepare('SELECT year_grade, name FROM sections WHERE id = ? LIMIT 1');
                        $primaryYear = null;
                        $primarySection = null;
                        if ($primarySectionStmt) {
                            $primarySectionStmt->bind_param('i', $primarySectionId);
                            $primarySectionStmt->execute();
                            $primarySectionRow = $primarySectionStmt->get_result()->fetch_assoc();
                            $primarySectionStmt->close();
                            if ($primarySectionRow) {
                                $primaryYear = (int)($primarySectionRow['year_grade'] ?? 0);
                                $primarySection = (string)($primarySectionRow['name'] ?? '');
                            }
                        }

                        if ($newPassword !== '') {
                            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                            $stmt = $mysqli->prepare('UPDATE users SET username = ?, email = ?, full_name = ?, school_year_label = ?, year_level = ?, section = ?, status = ?, password = ? WHERE id = ? AND role = "teacher"');
                        } else {
                            $stmt = $mysqli->prepare('UPDATE users SET username = ?, email = ?, full_name = ?, school_year_label = ?, year_level = ?, section = ?, status = ? WHERE id = ? AND role = "teacher"');
                        }

                        if (!$stmt) {
                            $error = 'Unable to prepare update-teacher request.';
                        } else {
                            if ($newPassword !== '') {
                                $stmt->bind_param('ssssisssi', $username, $email, $fullName, $schoolYearLabel, $primaryYear, $primarySection, $status, $hash, $teacherId);
                            } else {
                                $stmt->bind_param('ssssissi', $username, $email, $fullName, $schoolYearLabel, $primaryYear, $primarySection, $status, $teacherId);
                            }
                            try {
                                if ($stmt->execute()) {
                                    syncTeacherSections($mysqli, $teacherId, $sectionIds);
                                    $message = 'Teacher account updated successfully.';
                                } else {
                                    $error = 'Unable to update teacher account. Please try again.';
                                }
                            } catch (mysqli_sql_exception $e) {
                                $error = stripos($e->getMessage(), 'Duplicate entry') !== false
                                    ? 'Username or email already exists.'
                                    : 'Unable to update teacher account. Please try again.';
                            }
                            $stmt->close();
                        }
                    }
                }
            }
        }
    }
}

$teachers = [];
if ($setupError === '') {
    $result = $mysqli->query("SELECT u.id, u.username, u.email, u.full_name, u.school_year_label, u.year_level, u.section, u.status, u.created_at, GROUP_CONCAT(DISTINCT ts.section_id ORDER BY ts.section_id SEPARATOR ',') AS section_ids_csv, GROUP_CONCAT(CONCAT(s.year_grade, ' - ', s.name) ORDER BY CAST(s.year_grade AS UNSIGNED), s.name SEPARATOR ', ') AS section_assignments FROM users u LEFT JOIN teacher_sections ts ON ts.teacher_id = u.id LEFT JOIN sections s ON s.id = ts.section_id WHERE u.role = 'teacher' GROUP BY u.id ORDER BY u.created_at DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $teachers[] = $row;
        }
    }
}

$sectionsCatalog = [];
$sectionResult = $mysqli->query('SELECT id, name, year_grade FROM sections ORDER BY CAST(year_grade AS UNSIGNED) ASC, name ASC');
if ($sectionResult) {
    while ($row = $sectionResult->fetch_assoc()) {
        $sectionsCatalog[] = $row;
    }
}

if (empty($sectionsCatalog)) {
    $fallbackSections = ['Alpha', 'Beta', 'Charlie', 'Delta'];
    foreach ($fallbackSections as $fallbackSection) {
        $newSectionId = resolveOrCreateSectionId($mysqli, 1, $fallbackSection);
        if ($newSectionId > 0) {
            $sectionsCatalog[] = [
                'id' => $newSectionId,
                'name' => $fallbackSection,
                'year_grade' => '1',
            ];
        }
    }
}
?>

<div class="container-fluid">
    <div class="alert alert-info mb-3">
        <i class="bi bi-mortarboard"></i>
        Active School Year: <strong><?php echo htmlspecialchars($activeSchoolYear['label'] ?? 'N/A'); ?></strong>
        (<?php echo htmlspecialchars($activeSchoolYear['start_date'] ?? ''); ?> to <?php echo htmlspecialchars($activeSchoolYear['end_date'] ?? ''); ?>)
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Teacher Accounts</h5>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                <i class="bi bi-plus-circle"></i> Add Teacher
            </button>
        </div>
        <div class="card-body">
            <?php if (count($teachers) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover teachers-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>School Year</th>
                                <th>Year</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Section</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $count = 1; ?>
                            <?php foreach ($teachers as $teacher): ?>
                                <tr>
                                    <td><?php echo $count++; ?></td>
                                    <td><?php echo htmlspecialchars((string)($teacher['school_year_label'] ?? '-')); ?></td>
                                    <td><?php echo htmlspecialchars((string)($teacher['year_level'] ?? '-')); ?></td>
                                    <td><?php echo htmlspecialchars($teacher['username']); ?></td>
                                    <td><?php echo htmlspecialchars($teacher['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                    <td><?php echo htmlspecialchars($teacher['section_assignments'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge <?php echo $teacher['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo ucfirst($teacher['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($teacher['created_at'])); ?></td>
                                    <td>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editTeacherModal<?php echo (int) $teacher['id']; ?>"
                                        >
                                            Edit
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info mb-0">No teacher accounts found.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Teacher Modal -->
<div class="modal fade" id="addTeacherModal" tabindex="-1" aria-labelledby="addTeacherModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTeacherModalLabel"><i class="bi bi-person-plus"></i> Add Teacher Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" class="row g-3" id="addTeacherForm">
                    <input type="hidden" name="action" value="add_teacher">
                    <div class="col-md-4">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">School Year</label>
                        <select name="school_year_label" class="form-select" required>
                            <option value="">Select school year...</option>
                            <?php foreach ($schoolYears as $sy): ?>
                                <?php $label = (string)($sy['label'] ?? ''); ?>
                                <option value="<?php echo htmlspecialchars($label); ?>" <?php echo $label === $activeSchoolYearLabel ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Year / Grade</label>
                        <select name="year_level" class="form-select" required>
                            <option value="">Select year...</option>
                            <?php for ($y = 1; $y <= 4; $y++): ?>
                                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Sections</label>
                        <select name="section_ids[]" class="form-select" multiple size="6" required>
                            <?php foreach ($sectionsCatalog as $sectionRow): ?>
                                <option value="<?php echo (int)$sectionRow['id']; ?>" data-year="<?php echo htmlspecialchars((string)$sectionRow['year_grade']); ?>">
                                    <?php echo htmlspecialchars((string)$sectionRow['year_grade'] . ' - ' . (string)$sectionRow['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Use Ctrl/Cmd + click to select multiple sections.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addTeacherForm" class="btn btn-primary">Save Teacher</button>
            </div>
        </div>
    </div>
</div>

<?php foreach ($teachers as $teacher): ?>
    <?php $teacherSectionIds = array_values(array_filter(array_map('intval', explode(',', (string)($teacher['section_ids_csv'] ?? ''))))); ?>
    <div class="modal fade" id="editTeacherModal<?php echo (int) $teacher['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-gear"></i> Edit Teacher Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" class="row g-3" id="editTeacherForm<?php echo (int) $teacher['id']; ?>">
                        <input type="hidden" name="action" value="update_teacher">
                        <input type="hidden" name="teacher_id" value="<?php echo (int) $teacher['id']; ?>">

                        <div class="col-md-4">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required value="<?php echo htmlspecialchars($teacher['username']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" required value="<?php echo htmlspecialchars($teacher['full_name']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($teacher['email']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">School Year</label>
                            <select name="school_year_label" class="form-select" required>
                                <option value="">Select school year...</option>
                                <?php foreach ($schoolYears as $sy): ?>
                                    <?php $label = (string)($sy['label'] ?? ''); ?>
                                    <option value="<?php echo htmlspecialchars($label); ?>" <?php echo ((string)($teacher['school_year_label'] ?? '') === $label) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if (!empty($teacher['school_year_label']) && !array_filter($schoolYears, static fn($sy) => ($sy['label'] ?? '') === $teacher['school_year_label'])): ?>
                                    <option value="<?php echo htmlspecialchars((string)$teacher['school_year_label']); ?>" selected>
                                        <?php echo htmlspecialchars((string)$teacher['school_year_label']); ?> (current)
                                    </option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Year / Grade</label>
                            <select name="year_level" class="form-select" required>
                                <option value="">Select year...</option>
                                <?php for ($y = 1; $y <= 4; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo ((int)($teacher['year_level'] ?? 0) === $y) ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Sections</label>
                            <select name="section_ids[]" class="form-select" multiple size="6" required>
                                <?php foreach ($sectionsCatalog as $sectionRow): ?>
                                    <?php $sid = (int)$sectionRow['id']; ?>
                                    <option value="<?php echo $sid; ?>" data-year="<?php echo htmlspecialchars((string)$sectionRow['year_grade']); ?>" <?php echo in_array($sid, $teacherSectionIds, true) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars((string)$sectionRow['year_grade'] . ' - ' . (string)$sectionRow['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Use Ctrl/Cmd + click to select multiple sections.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?php echo $teacher['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $teacher['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">New Password (optional)</label>
                            <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current password">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="editTeacherForm<?php echo (int) $teacher['id']; ?>" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<style>
    .teachers-table thead th {
        background: transparent !important;
        color: #000000 !important;
        border-bottom: 1px solid #000000 !important;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const applySectionFilter = function (form) {
        if (!form) {
            return;
        }

        const yearSelect = form.querySelector('select[name="year_level"]');
        const sectionSelect = form.querySelector('select[name="section_ids[]"]');
        if (!yearSelect || !sectionSelect) {
            return;
        }

        const selectedYear = String(yearSelect.value || '').trim();
        const hasYear = selectedYear !== '';
        sectionSelect.disabled = !hasYear;

        Array.from(sectionSelect.options).forEach(function (option) {
            const optionYear = String(option.getAttribute('data-year') || '').trim();
            const visible = !hasYear || optionYear === selectedYear;

            option.hidden = !visible;
            option.disabled = !visible;
            if (!visible) {
                option.selected = false;
            }
        });
    };

    const wireForm = function (formId) {
        const form = document.getElementById(formId);
        if (!form) {
            return;
        }

        const yearSelect = form.querySelector('select[name="year_level"]');
        if (!yearSelect) {
            return;
        }

        yearSelect.addEventListener('change', function () {
            applySectionFilter(form);
        });

        applySectionFilter(form);
    };

    wireForm('addTeacherForm');
    document.querySelectorAll('form[id^="editTeacherForm"]').forEach(function (form) {
        wireForm(form.id);
    });
});
</script>

<?php require '../includes/footer.php'; /*
 * © 2026 TambyTech.
 * This source code is proprietary and confidential.
 * Any unauthorized use, copying, modification, distribution, or disclosure is strictly prohibited.
 * All rights reserved.
 */
?>