<?php
/**
 * Student Management Page
 * CRUD operations for student records
 */

session_start();
require_once '../config/db.php';
require '../includes/header.php';

function studentColumnExists(mysqli $mysqli, string $columnName): bool
{
    $safeColumn = $mysqli->real_escape_string($columnName);
    $result = $mysqli->query("SHOW COLUMNS FROM students LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

function tableColumnExists(mysqli $mysqli, string $tableName, string $columnName): bool
{
    $safeTable = $mysqli->real_escape_string($tableName);
    $safeColumn = $mysqli->real_escape_string($columnName);
    $result = $mysqli->query("SHOW COLUMNS FROM {$safeTable} LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

function formatStudentName(array $student): string
{
    $first = trim((string) ($student['first_name'] ?? ''));
    $middle = trim((string) ($student['middle_initial'] ?? ''));
    $last = trim((string) ($student['last_name'] ?? ''));
    $ext = trim((string) ($student['extension'] ?? ''));

    $name = $last;
    if ($first !== '') {
        $name .= ($name !== '' ? ', ' : '') . $first;
    }
    if ($middle !== '') {
        $name .= ' ' . strtoupper(substr($middle, 0, 1)) . '.';
    }
    if ($ext !== '') {
        $name .= ' ' . $ext;
    }

    return trim($name);
}

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
$hasMiddleInitialColumn = studentColumnExists($mysqli, 'middle_initial');
$hasExtensionColumn = studentColumnExists($mysqli, 'extension');
$middleInitialExpr = $hasMiddleInitialColumn ? 'COALESCE(s.middle_initial, "")' : '""';
$extensionExpr = $hasExtensionColumn ? 'COALESCE(s.extension, "")' : '""';
$fingerprintTableResult = $mysqli->query("SHOW TABLES LIKE 'fingerprints'");
$hasFingerprintTable = $fingerprintTableResult && $fingerprintTableResult->num_rows > 0;
$fingerprintStudentColumn = null;
if ($hasFingerprintTable) {
    if (tableColumnExists($mysqli, 'fingerprints', 'student_id')) {
        $fingerprintStudentColumn = 'student_id';
    } elseif (tableColumnExists($mysqli, 'fingerprints', 'user_id')) {
        $fingerprintStudentColumn = 'user_id';
    }
}

$fingerprintJoinSql = '';
if ($fingerprintStudentColumn !== null) {
    $fingerprintJoinSql = "
    LEFT JOIN (
        SELECT
            fp.{$fingerprintStudentColumn} AS student_ref,
            COUNT(fp.id) AS fingerprint_count,
            GROUP_CONCAT(CONCAT('F', fp.finger_index, ':', fp.sensor_id) ORDER BY fp.finger_index SEPARATOR ', ') AS fingerprint_list
        FROM fingerprints fp
        GROUP BY fp.{$fingerprintStudentColumn}
    ) fp_summary ON fp_summary.student_ref = s.id
    ";
}

$fingerprintCountExpr = $fingerprintStudentColumn !== null ? 'COALESCE(fp_summary.fingerprint_count, 0)' : '0';
$fingerprintListExpr = $fingerprintStudentColumn !== null ? "COALESCE(fp_summary.fingerprint_list, '')" : "''";

$result = $mysqli->query("
    SELECT 
        s.id, 
        s.student_id, 
        s.first_name, 
        s.last_name, 
        {$middleInitialExpr} AS middle_initial,
        {$extensionExpr} AS extension,
        s.email, 
        s.year, 
        s.section, 
        s.status, 
        {$fingerprintCountExpr} AS fingerprint_count,
        {$fingerprintListExpr} AS fingerprint_list
    FROM students s 
    {$fingerprintJoinSql}
    ORDER BY s.created_at DESC
");

while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}
?>

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
                        <th>#</th>
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
                    <?php $count = 1; ?>
                    <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?php echo $count++; ?></td>
                        <td><strong><?php echo htmlspecialchars($student['student_id']); ?></strong></td>
                        <td><?php echo htmlspecialchars(formatStudentName($student)); ?></td>
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

<?php require '../includes/footer.php'; ?>
