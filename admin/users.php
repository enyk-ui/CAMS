<?php
/**
 * Admin - Manage Users
 * Add/edit/delete admin and teacher accounts
 */

require_once '../config/db.php';
require '../includes/header.php';

// Verify admin role
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php?error=Unauthorized');
    exit;
}

$action = $_GET['action'] ?? '';
$message = '';
$error = '';

// Handle add user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? 'teacher';
    $section = $_POST['section'] ?? 'A';

    if (empty($email) || empty($password) || empty($full_name)) {
        $error = 'All fields are required';
    } else {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $email = $mysqli->real_escape_string($email);
        $full_name = $mysqli->real_escape_string($full_name);

        if ($role === 'admin') {
            $sql = "INSERT INTO admins (email, password, full_name, status)
                   VALUES ('$email', '$hashed_password', '$full_name', 'active')";
        } else {
            $section = $mysqli->real_escape_string($section);
            $sql = "INSERT INTO teachers (email, password, full_name, section, status)
                   VALUES ('$email', '$hashed_password', '$full_name', '$section', 'active')";
        }

        if ($mysqli->query($sql)) {
            $message = "User created successfully";
        } else {
            if (strpos($mysqli->error, 'Duplicate entry') !== false) {
                $error = 'Email already exists';
            } else {
                $error = 'Error: ' . $mysqli->error;
            }
        }
    }
}

// Get all admins
$admins = [];
$admin_result = $mysqli->query("SELECT id, email, full_name, status, created_at FROM admins ORDER BY created_at DESC");
while ($admin = $admin_result->fetch_assoc()) {
    $admins[] = $admin;
}

// Get all teachers
$teachers = [];
$teacher_result = $mysqli->query("SELECT id, email, full_name, section, status, created_at FROM teachers ORDER BY created_at DESC");
while ($teacher = $teacher_result->fetch_assoc()) {
    $teachers[] = $teacher;
}
?>

<div class="container-fluid">
    <!-- Messages -->
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

    <!-- Add User Form -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Add New User</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="user@example.com" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" placeholder="Full Name" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-control" id="roleSelect" onchange="updateSectionField()">
                                <option value="teacher">Teacher</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-2" id="sectionField">
                            <label class="form-label">Section</label>
                            <select name="section" class="form-control">
                                <option value="A">Section A</option>
                                <option value="B">Section B</option>
                                <option value="C">Section C</option>
                                <option value="D">Section D</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Password" required>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" name="add_user" class="btn btn-primary w-100">
                                <i class="bi bi-plus-circle"></i> Add User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Admins List -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Administrators</h5>
                </div>
                <div class="card-body">
                    <?php if (count($admins) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Email</th>
                                        <th>Full Name</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($admins as $admin): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                            <td><?php echo htmlspecialchars($admin['full_name']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $admin['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?php echo ucfirst($admin['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> No administrators found
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Teachers List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Teachers</h5>
                </div>
                <div class="card-body">
                    <?php if (count($teachers) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Email</th>
                                        <th>Full Name</th>
                                        <th>Section</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                            <td><?php echo htmlspecialchars($teacher['full_name']); ?></td>
                                            <td><strong><?php echo htmlspecialchars($teacher['section']); ?></strong></td>
                                            <td>
                                                <span class="badge <?php echo $teacher['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?php echo ucfirst($teacher['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($teacher['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> No teachers found
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateSectionField() {
    const roleSelect = document.getElementById('roleSelect');
    const sectionField = document.getElementById('sectionField');
    if (roleSelect.value === 'admin') {
        sectionField.style.display = 'none';
    } else {
        sectionField.style.display = 'block';
    }
}
// Initialize
updateSectionField();
</script>

<?php require '../includes/footer.php'; ?>
