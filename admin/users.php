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

    $check = $mysqli->query("SHOW COLUMNS FROM users LIKE 'section'");
    if ($check && $check->num_rows > 0) {
        return true;
    }

    return (bool) $mysqli->query("ALTER TABLE users ADD COLUMN section VARCHAR(50) DEFAULT NULL");
}

$setupError = '';
if (!ensureUsersSectionColumn($mysqli)) {
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
        $section = trim($_POST['section'] ?? '');
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        if ($username === '' || $email === '' || $password === '' || $fullName === '' || $section === '') {
            $error = 'All fields are required for adding a teacher.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $mysqli->prepare('INSERT INTO users (username, password, full_name, email, role, section, status) VALUES (?, ?, ?, ?, "teacher", ?, ?)');
            $stmt->bind_param('ssssss', $username, $hash, $fullName, $email, $section, $status);

            if ($stmt->execute()) {
                $message = 'Teacher account created successfully.';
            } else {
                $error = strpos($stmt->error, 'Duplicate entry') !== false
                    ? 'Username or email already exists.'
                    : ('Error creating teacher account: ' . $stmt->error);
            }
            $stmt->close();
        }
    }

    if ($action === 'update_teacher') {
        $teacherId = (int) ($_POST['teacher_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $section = trim($_POST['section'] ?? '');
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        $newPassword = $_POST['new_password'] ?? '';

        if ($teacherId <= 0 || $username === '' || $email === '' || $fullName === '' || $section === '') {
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
                if ($newPassword !== '') {
                    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $stmt = $mysqli->prepare('UPDATE users SET username = ?, email = ?, full_name = ?, section = ?, status = ?, password = ? WHERE id = ? AND role = "teacher"');
                    $stmt->bind_param('ssssssi', $username, $email, $fullName, $section, $status, $hash, $teacherId);
                } else {
                    $stmt = $mysqli->prepare('UPDATE users SET username = ?, email = ?, full_name = ?, section = ?, status = ? WHERE id = ? AND role = "teacher"');
                    $stmt->bind_param('sssssi', $username, $email, $fullName, $section, $status, $teacherId);
                }

                if ($stmt->execute()) {
                    $message = 'Teacher account updated successfully.';
                } else {
                    $error = strpos($stmt->error, 'Duplicate entry') !== false
                        ? 'Username or email already exists.'
                        : ('Error updating teacher account: ' . $stmt->error);
                }
                $stmt->close();
            }
        }
    }
}

$teachers = [];
if ($setupError === '') {
    $result = $mysqli->query("SELECT id, username, email, full_name, section, status, created_at FROM users WHERE role = 'teacher' ORDER BY created_at DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $teachers[] = $row;
        }
    }
}

$sectionOptions = ['Alpha', 'Beta', 'Charlie', 'Delta'];
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
                                <th>Username</th>
                                <th>Email</th>
                                <th>Full Name</th>
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
                                    <td><?php echo htmlspecialchars($teacher['username']); ?></td>
                                    <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                    <td><?php echo htmlspecialchars($teacher['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($teacher['section'] ?? '-'); ?></td>
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
                    <div class="col-md-6">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Section</label>
                        <select name="section" class="form-select" required>
                            <option value="">Select section...</option>
                            <?php foreach ($sectionOptions as $sectionOption): ?>
                                <option value="<?php echo htmlspecialchars($sectionOption); ?>"><?php echo htmlspecialchars($sectionOption); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" required>
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

                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required value="<?php echo htmlspecialchars($teacher['username']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Section</label>
                            <select name="section" class="form-select" required>
                                <option value="">Select section...</option>
                                <?php foreach ($sectionOptions as $sectionOption): ?>
                                    <option value="<?php echo htmlspecialchars($sectionOption); ?>" <?php echo ($teacher['section'] ?? '') === $sectionOption ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sectionOption); ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if (!empty($teacher['section']) && !in_array($teacher['section'], $sectionOptions, true)): ?>
                                    <option value="<?php echo htmlspecialchars($teacher['section']); ?>" selected>
                                        <?php echo htmlspecialchars($teacher['section']); ?> (current)
                                    </option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($teacher['email']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" required value="<?php echo htmlspecialchars($teacher['full_name']); ?>">
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

<?php require '../includes/footer.php'; ?>
