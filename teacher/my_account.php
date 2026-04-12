<?php
/**
 * Teacher Account Management
 * Teachers can manage their own account
 */

session_start();

// Role check
if ($_SESSION['role'] !== 'teacher') {
    header('Location: ../index.php?error=Unauthorized');
    exit;
}

require_once '../config/db.php';
require '../includes/header.php';

$message = '';
$message_type = 'success';
$teacher_id = $_SESSION['admin_id']; // Teacher ID stored in admin_id during login
$teacher_email = $_SESSION['admin_email'] ?? '';

function usersColumnExists(mysqli $mysqli, string $columnName): bool
{
    $safeColumn = $mysqli->real_escape_string($columnName);
    $result = $mysqli->query("SHOW COLUMNS FROM users LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

// Get teacher info
$teacher = null;
$stmt = $mysqli->prepare("SELECT * FROM users WHERE id = ? AND role = 'teacher'");
if ($stmt) {
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $teacher = $result->fetch_assoc();
}

if (!$teacher && $teacher_email !== '') {
    $stmt = $mysqli->prepare("SELECT * FROM users WHERE email = ? AND role = 'teacher'");
    if ($stmt) {
        $stmt->bind_param("s", $teacher_email);
        $stmt->execute();
        $result = $stmt->get_result();
        $teacher = $result->fetch_assoc();
        if ($teacher) {
            $teacher_id = (int)$teacher['id'];
            $_SESSION['admin_id'] = $teacher_id;
        }
    }
}

if (!$teacher) {
    header('Location: dashboard.php?error=Teacher account not configured. Please contact admin.');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate required fields
    if (empty($full_name) || empty($email)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'danger';
    } else {
        // Check if email is unique (excluding current teacher)
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $teacher_id);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $message = 'Email already in use by another teacher.';
            $message_type = 'danger';
        } else {
            // If changing password
            if (!empty($new_password)) {
                // Verify current password
                if (empty($current_password)) {
                    $message = 'Please provide your current password to change it.';
                    $message_type = 'danger';
                } elseif (!password_verify($current_password, $teacher['password'])) {
                    $message = 'Current password is incorrect.';
                    $message_type = 'danger';
                } elseif ($new_password !== $confirm_password) {
                    $message = 'New passwords do not match.';
                    $message_type = 'danger';
                } elseif (strlen($new_password) < 6) {
                    $message = 'New password must be at least 6 characters long.';
                    $message_type = 'danger';
                } else {
                    // Update with new password
                    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                    $stmt = $mysqli->prepare("UPDATE users SET full_name = ?, email = ?, password = ?, updated_at = NOW() WHERE id = ? AND role = 'teacher'");
                    $stmt->bind_param("sssi", $full_name, $email, $hashed_password, $teacher_id);
                    
                    if ($stmt->execute()) {
                        $message = 'Your account has been updated successfully! Please log in again with your new password.';
                        $message_type = 'success';
                        // Redirect to login after 2 seconds
                        echo '<script>setTimeout(function() { window.location.href = "../auth/logout.php"; }, 2000);</script>';
                    } else {
                        $message = 'Error updating account: ' . $mysqli->error;
                        $message_type = 'danger';
                    }
                }
            } else {
                // Update without password change
                $stmt = $mysqli->prepare("UPDATE users SET full_name = ?, email = ?, updated_at = NOW() WHERE id = ? AND role = 'teacher'");
                $stmt->bind_param("ssi", $full_name, $email, $teacher_id);
                
                if ($stmt->execute()) {
                    $message = 'Your account has been updated successfully!';
                    $message_type = 'success';
                    
                    // Refresh teacher data
                    $stmt = $mysqli->prepare("SELECT * FROM users WHERE id = ? AND role = 'teacher'");
                    $stmt->bind_param("i", $teacher_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $teacher = $result->fetch_assoc();
                } else {
                    $message = 'Error updating account: ' . $mysqli->error;
                    $message_type = 'danger';
                }
            }
        }
    }
}
?>

<div class="main-content">
    <div class="container">
        

        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="alert">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5>Account Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($teacher['username']); ?>" disabled>
                                <small class="form-text text-muted">Username cannot be changed</small>
                            </div>

                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($teacher['full_name']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($teacher['email']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="section" class="form-label">Assigned Section</label>
                                <input type="text" class="form-control" id="section" value="<?php echo htmlspecialchars($teacher['section'] ?? 'Not Assigned'); ?>" disabled>
                                <small class="form-text text-muted">Section assignment is managed by admin</small>
                            </div>

                            <hr>

                            <h6 class="mt-4 mb-3">Change Password (Optional)</h6>
                            <p><small class="text-muted">Leave blank if you don't want to change your password</small></p>

                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                            </div>

                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                                <small class="form-text text-muted">Minimum 6 characters</small>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa-solid fa-save"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Account Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Role</strong>
                            <p><span class="badge badge-success">Teacher</span></p>
                        </div>
                        <div class="mb-3">
                            <strong>Status</strong>
                            <p><span class="badge badge-<?php echo $teacher['status'] === 'active' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst(htmlspecialchars($teacher['status'])); ?>
                            </span></p>
                        </div>
                        <div class="mb-3">
                            <strong>Account Created</strong>
                            <p><small><?php echo date('M d, Y H:i', strtotime($teacher['created_at'])); ?></small></p>
                        </div>
                        <div class="mb-3">
                            <strong>Last Updated</strong>
                            <p><small><?php echo $teacher['updated_at'] ? date('M d, Y H:i', strtotime($teacher['updated_at'])) : 'Never'; ?></small></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
