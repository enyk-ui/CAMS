<?php
/**
 * Student Management Page
 * CRUD operations for student records
 */

session_start();
require_once 'config/db.php';
require 'includes/header.php';

$message = '';
$message_type = '';

// Handle DELETE
if (isset($_GET['delete'])) {
    $student_id = (int)$_GET['delete'];
    $mysqli->query("DELETE FROM students WHERE id = $student_id");
    $message = "Student deleted successfully!";
    $message_type = "success";
}

// Get all students with linked fingerprint summary.
$students = [];
$result = $mysqli->query("SELECT s.id, s.student_id, s.first_name, s.last_name, s.email, s.year, s.section, s.status, COALESCE(fp_summary.fingerprint_count, 0) AS fingerprint_count, COALESCE(fp_summary.fingerprint_list, '') AS fingerprint_list FROM students s LEFT JOIN ( SELECT u.student_no, COUNT(fp.id) AS fingerprint_count, GROUP_CONCAT(CONCAT('F', fp.finger_index, ':', fp.sensor_id) ORDER BY fp.finger_index SEPARATOR ', ') AS fingerprint_list FROM users u LEFT JOIN fingerprints fp ON fp.user_id = u.id GROUP BY u.student_no ) fp_summary ON fp_summary.student_no = s.student_id ORDER BY s.created_at DESC");

while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}
?>

<h2 class="page-title"><i class="bi bi-people"></i> Student Management</h2>
<p class="page-subtitle">Manage student records and fingerprints</p>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">All Students (<?php echo count($students); ?>)</h5>
        <a href="register.php" class="btn btn-sm btn-primary">
            <i class="bi bi-person-plus"></i> Add Student
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Year</th>
                        <th>Section</th>
                        <th>Email</th>
                        <th>Fingerprints</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($student['student_id']); ?></strong></td>
                        <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                        <td><?php echo $student['year']; ?></td>
                        <td><?php echo htmlspecialchars($student['section']); ?></td>
                        <td><small><?php echo htmlspecialchars($student['email']); ?></small></td>
                        <td>
                            <?php if ((int)$student['fingerprint_count'] > 0): ?>
                                <span class="badge bg-primary"><?php echo (int)$student['fingerprint_count']; ?> linked</span>
                                <div class="small text-muted mt-1"><?php echo htmlspecialchars($student['fingerprint_list']); ?></div>
                            <?php else: ?>
                                <span class="badge bg-secondary">No fingerprints</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($student['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?php echo ucfirst($student['status']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="student_view.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-info" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="student_edit.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="?delete=<?php echo $student['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete this student?')">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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

    .table-hover tbody tr:hover {
        background-color: #f8f9fa;
    }

    .btn-sm {
        padding: 0.4rem 0.6rem;
        font-size: 0.85rem;
    }
</style>

<?php require 'includes/footer.php'; ?>
