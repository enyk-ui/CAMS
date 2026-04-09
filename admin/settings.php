<?php
/**
 * Settings Page
 * Configure attendance defaults and school year management.
 */

session_start();
require_once '../config/db.php';
require_once '../helpers/SchoolYearHelper.php';
require '../includes/header.php';

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

function ensureUsersAdminSchema(mysqli $mysqli): bool
{
    if (!usersTableExists($mysqli)) {
        $createSql = "
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) DEFAULT NULL,
                password VARCHAR(255) DEFAULT NULL,
                full_name VARCHAR(150) DEFAULT NULL,
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
        $alterStatements[] = "ADD COLUMN full_name VARCHAR(150) DEFAULT NULL";
    }
    if (!usersColumnExists($mysqli, 'email')) {
        $alterStatements[] = "ADD COLUMN email VARCHAR(120) DEFAULT NULL";
    }
    if (!usersColumnExists($mysqli, 'role')) {
        $alterStatements[] = "ADD COLUMN role ENUM('admin','teacher') NOT NULL DEFAULT 'teacher'";
    }
    if (!usersColumnExists($mysqli, 'status')) {
        $alterStatements[] = "ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active'";
    }

    foreach ($alterStatements as $statement) {
        if (!$mysqli->query("ALTER TABLE users {$statement}")) {
            return false;
        }
    }

    return true;
}

$message = '';
$message_type = '';

if (!SchoolYearHelper::ensureSchoolYearSupport($mysqli)) {
    $message = 'Unable to initialize school year and settings tables. Please run database setup.';
    $message_type = 'danger';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_settings';

    if ($action === 'save_settings') {
        $settings_to_update = [
            'late_threshold_minutes' => $_POST['late_threshold'] ?? '',
            'absent_threshold_hours' => $_POST['absent_threshold'] ?? '',
            'am_start_time' => $_POST['am_start'] ?? '',
            'am_end_time' => $_POST['am_end'] ?? '',
            'pm_start_time' => $_POST['pm_start'] ?? '',
            'pm_end_time' => $_POST['pm_end'] ?? ''
        ];

        foreach ($settings_to_update as $key => $value) {
            if ($value !== '') {
                SchoolYearHelper::upsertSetting($mysqli, $key, (string) $value);
            }
        }

        $activeYear = SchoolYearHelper::getActiveSchoolYear($mysqli);
        if ($activeYear && !empty($activeYear['label'])) {
            SchoolYearHelper::upsertSetting($mysqli, 'school_year', $activeYear['label']);
        }

        $message = 'Settings updated successfully!';
        $message_type = 'success';
    } elseif ($action === 'set_active_school_year') {
        $schoolYearId = (int) ($_POST['school_year_id'] ?? 0);
        if ($schoolYearId > 0 && SchoolYearHelper::setActiveSchoolYear($mysqli, $schoolYearId)) {
            $message = 'Active school year updated successfully.';
            $message_type = 'success';
        } else {
            $message = 'Unable to set active school year.';
            $message_type = 'danger';
        }
    } elseif ($action === 'add_school_year') {
        $label = trim($_POST['school_year_label'] ?? '');
        $startDate = trim($_POST['school_year_start'] ?? '');
        $endDate = trim($_POST['school_year_end'] ?? '');
        $setActive = isset($_POST['school_year_make_active']);

        if (!preg_match('/^\d{4}-\d{4}$/', $label)) {
            $message = 'School year label must follow YYYY-YYYY format.';
            $message_type = 'danger';
        } else {
            if ($startDate === '' || $endDate === '') {
                $range = SchoolYearHelper::labelToRange($label);
                if ($range) {
                    $startDate = $range['start_date'];
                    $endDate = $range['end_date'];
                }
            }

            if ($startDate === '' || $endDate === '' || $startDate > $endDate) {
                $message = 'Please provide a valid school year date range.';
                $message_type = 'danger';
            } elseif (SchoolYearHelper::createSchoolYear($mysqli, $label, $startDate, $endDate, $setActive)) {
                if ($setActive) {
                    SchoolYearHelper::upsertSetting($mysqli, 'school_year', $label);
                }
                $message = 'School year added successfully.';
                $message_type = 'success';
            } else {
                $message = 'Unable to add school year (label may already exist).';
                $message_type = 'danger';
            }
        }
    } elseif ($action === 'update_admin_account') {
        $adminId = (int) ($_SESSION['admin_id'] ?? 0);
        $username = trim($_POST['admin_username'] ?? '');
        $email = trim($_POST['admin_email'] ?? '');
        $fullName = trim($_POST['admin_full_name'] ?? '');
        $newPassword = $_POST['admin_new_password'] ?? '';

        if ($username === '' || $email === '' || $fullName === '') {
            $message = 'Please complete all required admin account fields.';
            $message_type = 'danger';
        } elseif (!ensureUsersAdminSchema($mysqli)) {
            $message = 'Unable to prepare admin account storage in users table.';
            $message_type = 'danger';
        } else {
            $targetAdminId = 0;

            if ($adminId > 0) {
                $lookupStmt = $mysqli->prepare('SELECT id FROM users WHERE id = ? AND role = "admin" LIMIT 1');
                $lookupStmt->bind_param('i', $adminId);
                $lookupStmt->execute();
                $existing = $lookupStmt->get_result()->fetch_assoc();
                $lookupStmt->close();
                if ($existing) {
                    $targetAdminId = (int) $existing['id'];
                }
            }

            if ($targetAdminId === 0) {
                $lookupByEmailStmt = $mysqli->prepare('SELECT id FROM users WHERE email = ? AND role = "admin" LIMIT 1');
                $lookupByEmailStmt->bind_param('s', $email);
                $lookupByEmailStmt->execute();
                $existing = $lookupByEmailStmt->get_result()->fetch_assoc();
                $lookupByEmailStmt->close();
                if ($existing) {
                    $targetAdminId = (int) $existing['id'];
                }
            }

            if ($targetAdminId > 0) {
                if ($newPassword !== '') {
                    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $stmt = $mysqli->prepare('UPDATE users SET username = ?, email = ?, full_name = ?, password = ? WHERE id = ?');
                    $stmt->bind_param('ssssi', $username, $email, $fullName, $passwordHash, $targetAdminId);
                } else {
                    $stmt = $mysqli->prepare('UPDATE users SET username = ?, email = ?, full_name = ? WHERE id = ?');
                    $stmt->bind_param('sssi', $username, $email, $fullName, $targetAdminId);
                }

                if ($stmt->execute()) {
                    $_SESSION['admin_id'] = $targetAdminId;
                    $_SESSION['admin_email'] = $email;
                    $_SESSION['admin_name'] = $fullName;
                    $message = 'Admin account updated successfully.';
                    $message_type = 'success';
                } else {
                    $message = strpos($stmt->error, 'Duplicate entry') !== false
                        ? 'Username or email already exists.'
                        : ('Unable to update admin account: ' . $stmt->error);
                    $message_type = 'danger';
                }
                $stmt->close();
            } else {
                $passwordHash = password_hash($newPassword !== '' ? $newPassword : 'admin123', PASSWORD_BCRYPT);
                $insertStmt = $mysqli->prepare('INSERT INTO users (username, password, full_name, email, role, status) VALUES (?, ?, ?, ?, "admin", "active")');
                $insertStmt->bind_param('ssss', $username, $passwordHash, $fullName, $email);

                if ($insertStmt->execute()) {
                    $newAdminId = (int) $mysqli->insert_id;
                    $_SESSION['admin_id'] = $newAdminId;
                    $_SESSION['admin_email'] = $email;
                    $_SESSION['admin_name'] = $fullName;
                    $message = 'Admin account profile created and updated successfully.';
                    $message_type = 'success';
                } else {
                    $message = strpos($insertStmt->error, 'Duplicate entry') !== false
                        ? 'Username or email already exists.'
                        : ('Unable to create admin account profile: ' . $insertStmt->error);
                    $message_type = 'danger';
                }
                $insertStmt->close();
            }
        }
    }
}

$settings = [];
if ($result = $mysqli->query('SELECT setting_key, setting_value FROM settings')) {
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

$activeSchoolYear = SchoolYearHelper::getEffectiveSchoolYearRange($mysqli);
$schoolYears = SchoolYearHelper::getAllSchoolYears($mysqli);

$late_threshold = $settings['late_threshold_minutes'] ?? '15';
$absent_threshold = $settings['absent_threshold_hours'] ?? '2';
$am_start = $settings['am_start_time'] ?? '08:00:00';
$am_end = $settings['am_end_time'] ?? '12:00:00';
$pm_start = $settings['pm_start_time'] ?? '13:00:00';
$pm_end = $settings['pm_end_time'] ?? '17:00:00';

$sessionEmail = trim((string) ($_SESSION['admin_email'] ?? ''));
$sessionName = trim((string) ($_SESSION['admin_name'] ?? ''));
$adminLookupId = (int) ($_SESSION['admin_id'] ?? 0);
$sessionUsername = $sessionEmail !== '' && strpos($sessionEmail, '@') !== false
    ? (string) strtok($sessionEmail, '@')
    : 'admin';

$currentAdmin = [
    'id' => $adminLookupId,
    'username' => $sessionUsername,
    'email' => $sessionEmail,
    'full_name' => $sessionName !== '' ? $sessionName : 'Administrator',
    'is_session_fallback' => true,
];

if (ensureUsersAdminSchema($mysqli)) {
    if ($adminLookupId > 0) {
        $stmt = $mysqli->prepare("SELECT id, username, email, full_name FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
        $stmt->bind_param('i', $adminLookupId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $currentAdmin = $result->fetch_assoc();
            $currentAdmin['is_session_fallback'] = false;
        }
        $stmt->close();
    }

    if (!empty($currentAdmin['is_session_fallback'])) {
        $fallbackEmail = $sessionEmail;
        if ($fallbackEmail !== '') {
            $fallbackStmt = $mysqli->prepare("SELECT id, username, email, full_name FROM users WHERE email = ? AND role = 'admin' ORDER BY id ASC LIMIT 1");
            $fallbackStmt->bind_param('s', $fallbackEmail);
            $fallbackStmt->execute();
            $fallbackRes = $fallbackStmt->get_result();
            if ($fallbackRes && $fallbackRes->num_rows > 0) {
                $currentAdmin = $fallbackRes->fetch_assoc();
                $currentAdmin['is_session_fallback'] = false;
            }
            $fallbackStmt->close();
        }
    }
}
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="alert alert-info mb-4">
    <i class="bi bi-mortarboard"></i>
    Active School Year: <strong><?php echo htmlspecialchars($activeSchoolYear['label'] ?? 'N/A'); ?></strong>
    (<?php echo htmlspecialchars($activeSchoolYear['start_date'] ?? ''); ?> to <?php echo htmlspecialchars($activeSchoolYear['end_date'] ?? ''); ?>)
</div>

<form method="POST" class="row">
    <input type="hidden" name="action" value="save_settings">

    <div class="col-lg-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-calendar2-week"></i> Attendance Configuration</h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <h6 class="mb-3"><i class="bi bi-clock"></i> Attendance Thresholds</h6>
                        <div class="mb-3">
                            <label for="late_threshold" class="form-label">Late Threshold (Minutes)</label>
                            <p class="form-text text-muted small">Minutes after AM start time to mark as late</p>
                            <input type="number" class="form-control" id="late_threshold" name="late_threshold"
                                   value="<?php echo htmlspecialchars($late_threshold); ?>" min="0" max="60">
                        </div>

                        <div class="mb-0">
                            <label for="absent_threshold" class="form-label">Absent Threshold (Hours)</label>
                            <p class="form-text text-muted small">Hours after AM start to mark as absent if no scan</p>
                            <input type="number" class="form-control" id="absent_threshold" name="absent_threshold"
                                   value="<?php echo htmlspecialchars($absent_threshold); ?>" min="0" max="12">
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <h6 class="mb-3"><i class="bi bi-calendar-event"></i> Session Schedule</h6>
                        <div class="row mb-3">
                            <div class="col-6">
                                <label for="am_start" class="form-label">AM Start Time</label>
                                <input type="time" class="form-control" id="am_start" name="am_start"
                                       value="<?php echo htmlspecialchars(substr($am_start, 0, 5)); ?>">
                            </div>
                            <div class="col-6">
                                <label for="am_end" class="form-label">AM End Time</label>
                                <input type="time" class="form-control" id="am_end" name="am_end"
                                       value="<?php echo htmlspecialchars(substr($am_end, 0, 5)); ?>">
                            </div>
                        </div>

                        <div class="row mb-0">
                            <div class="col-6">
                                <label for="pm_start" class="form-label">PM Start Time</label>
                                <input type="time" class="form-control" id="pm_start" name="pm_start"
                                       value="<?php echo htmlspecialchars(substr($pm_start, 0, 5)); ?>">
                            </div>
                            <div class="col-6">
                                <label for="pm_end" class="form-label">PM End Time</label>
                                <input type="time" class="form-control" id="pm_end" name="pm_end"
                                       value="<?php echo htmlspecialchars(substr($pm_end, 0, 5)); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-12">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-check-circle"></i> Save Settings
        </button>
    </div>
</form>

<div class="row mt-4">
    <div class="col-lg-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-mortarboard"></i> School Year Management</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Label</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($schoolYears)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">No school years found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($schoolYears as $sy): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($sy['label']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($sy['start_date']); ?></td>
                                        <td><?php echo htmlspecialchars($sy['end_date']); ?></td>
                                        <td>
                                            <?php if ((int) $sy['is_active'] === 1): ?>
                                                <span class="badge bg-success">Active / Current</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ((int) $sy['is_active'] !== 1): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="set_active_school_year">
                                                    <input type="hidden" name="school_year_id" value="<?php echo (int) $sy['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">Set Active</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-success small">Default</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <hr>

                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="add_school_year">
                    <div class="col-md-3">
                        <label class="form-label">School Year Label</label>
                        <input type="text" name="school_year_label" class="form-control" placeholder="2026-2027" required pattern="\d{4}-\d{4}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="school_year_start" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" name="school_year_end" class="form-control">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="school_year_make_active" name="school_year_make_active">
                            <label class="form-check-label" for="school_year_make_active">Set as active/current</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="bi bi-plus-circle"></i> Add School Year
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-lg-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person-gear"></i> Admin Account</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($currentAdmin['is_session_fallback'])): ?>
                    <div class="alert alert-info">
                        You are currently logged in via session credentials. Saving this form will create or link an admin profile row in users table.
                    </div>
                <?php endif; ?>

                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="update_admin_account">
                    <div class="col-md-4">
                        <label class="form-label">Username</label>
                        <input type="text" name="admin_username" class="form-control" required value="<?php echo htmlspecialchars((string) ($currentAdmin['username'] ?? '')); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input type="email" name="admin_email" class="form-control" required value="<?php echo htmlspecialchars((string) ($currentAdmin['email'] ?? '')); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="admin_full_name" class="form-control" required value="<?php echo htmlspecialchars((string) ($currentAdmin['full_name'] ?? 'Administrator')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">New Password (optional)</label>
                        <input type="password" name="admin_new_password" class="form-control" placeholder="Leave blank to keep current password">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-outline-dark">
                            <i class="bi bi-check-circle"></i> Update Current Admin Information
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .card-header {
        background: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        border-radius: 12px 12px 0 0;
        padding: 20px;
    }

    .form-control, .form-select {
        border-radius: 8px;
        border: 1px solid #dee2e6;
    }

    .btn-lg {
        padding: 12px 30px;
    }
</style>

<?php require '../includes/footer.php'; ?>
