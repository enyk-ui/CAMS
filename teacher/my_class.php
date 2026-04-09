<?php
/**
 * My Class - Teacher View
 * List of students in teacher's section
 */

require_once '../config/db.php';
require '../includes/header.php';

function studentColumnExists(mysqli $mysqli, string $columnName): bool
{
    $safeColumn = $mysqli->real_escape_string($columnName);
    $result = $mysqli->query("SHOW COLUMNS FROM students LIKE '{$safeColumn}'");
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

if ($_SESSION['role'] !== 'teacher') {
    header('Location: ../index.php?error=Unauthorized');
    exit;
}

$section = $_SESSION['teacher_section'];
$hasMiddleInitialColumn = studentColumnExists($mysqli, 'middle_initial');
$hasExtensionColumn = studentColumnExists($mysqli, 'extension');
$middleInitialExpr = $hasMiddleInitialColumn ? 'COALESCE(middle_initial, "")' : '""';
$extensionExpr = $hasExtensionColumn ? 'COALESCE(extension, "")' : '""';

// Get all students in section
$students = [];
$result = $mysqli->query("
    SELECT
        id,
        student_id,
        first_name,
        last_name,
        {$middleInitialExpr} AS middle_initial,
        {$extensionExpr} AS extension,
        email,
        year,
        status,
        created_at
    FROM students
    WHERE section = '$section'
    ORDER BY first_name ASC
");

while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Students in Section <?php echo htmlspecialchars($section); ?></h5>
                        <span class="badge bg-primary"><?php echo count($students); ?> students</span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (count($students) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Year</th>
                                        <th>Status</th>
                                        <th>Enrolled</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($student['student_id']); ?></strong></td>
                                            <td><?php echo htmlspecialchars(formatStudentName($student)); ?></td>
                                            <td><?php echo htmlspecialchars($student['email'] ?? '-'); ?></td>
                                            <td><?php echo $student['year'] ?? '-'; ?></td>
                                            <td>
                                                <span class="badge <?php echo $student['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?php echo ucfirst($student['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($student['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> No students in this section yet
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
