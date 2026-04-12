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

function resolveTeacherSectionIds(mysqli $mysqli): array
{
    $teacherId = (int)($_SESSION['admin_id'] ?? 0);
    if ($teacherId <= 0) {
        return [];
    }

    $stmt = $mysqli->prepare('SELECT section_id FROM teacher_sections WHERE teacher_id = ? ORDER BY section_id ASC');
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $teacherId);
    $stmt->execute();
    $result = $stmt->get_result();
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $sid = (int)($row['section_id'] ?? 0);
        if ($sid > 0) {
            $ids[] = $sid;
        }
    }
    $stmt->close();

    return $ids;
}

function resolveTeacherAssignment(mysqli $mysqli): array
{
    $yearLevel = (int)($_SESSION['teacher_year_level'] ?? 0);
    $section = trim((string)($_SESSION['teacher_section'] ?? ''));

    if ($yearLevel > 0 && $section !== '') {
        return ['year_level' => $yearLevel, 'section' => $section];
    }

    $teacherId = $_SESSION['admin_id'] ?? null;
    if ($teacherId !== null) {
        $stmt = $mysqli->prepare("SELECT year_level, section FROM users WHERE id = ? AND role = 'teacher' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $teacherId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $yearLevel = (int)($row['year_level'] ?? 0);
            $section = trim((string)($row['section'] ?? ''));
            if ($yearLevel > 0 && $section !== '') {
                $_SESSION['teacher_year_level'] = $yearLevel;
                $_SESSION['teacher_section'] = $section;
                return ['year_level' => $yearLevel, 'section' => $section];
            }
        }
    }

    $teacherEmail = $_SESSION['admin_email'] ?? '';
    if ($teacherEmail !== '') {
        $stmt = $mysqli->prepare("SELECT year_level, section FROM users WHERE email = ? AND role = 'teacher' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $teacherEmail);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $yearLevel = (int)($row['year_level'] ?? 0);
            $section = trim((string)($row['section'] ?? ''));
            if ($yearLevel > 0 && $section !== '') {
                $_SESSION['teacher_year_level'] = $yearLevel;
                $_SESSION['teacher_section'] = $section;
                return ['year_level' => $yearLevel, 'section' => $section];
            }
        }
    }

    $_SESSION['teacher_year_level'] = 0;
    $_SESSION['teacher_section'] = '';
    return ['year_level' => 0, 'section' => ''];
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

$teacherAssignment = resolveTeacherAssignment($mysqli);
$year = (int)($teacherAssignment['year_level'] ?? 0);
$section = (string)($teacherAssignment['section'] ?? '');
$hasSectionIdColumn = studentColumnExists($mysqli, 'section_id');
$teacherSectionIds = resolveTeacherSectionIds($mysqli);
$teacherSectionIdCsv = implode(',', array_map('intval', $teacherSectionIds));

if ($hasSectionIdColumn && empty($teacherSectionIds) && ($year <= 0 || $section === '')) {
    header('Location: ../index.php?error=Teacher assignment missing');
    exit;
}

if (!$hasSectionIdColumn && ($year <= 0 || $section === '')) {
    header('Location: ../index.php?error=Teacher assignment missing');
    exit;
}

$hasMiddleInitialColumn = studentColumnExists($mysqli, 'middle_initial');
$hasExtensionColumn = studentColumnExists($mysqli, 'extension');
$middleInitialExpr = $hasMiddleInitialColumn ? 'COALESCE(middle_initial, "")' : '""';
$extensionExpr = $hasExtensionColumn ? 'COALESCE(extension, "")' : '""';

// Get all students in section assigned to this teacher (matching year and section)
$students = [];
$sql = "
    SELECT
        id,
        first_name,
        last_name,
        {$middleInitialExpr} AS middle_initial,
        {$extensionExpr} AS extension,
        year,
        status,
        created_at
    FROM students
";

if ($hasSectionIdColumn && $teacherSectionIdCsv !== '') {
    $sql .= " WHERE section_id IN ({$teacherSectionIdCsv}) ORDER BY first_name ASC";
    $stmt = $mysqli->prepare($sql);
} else {
    $sql .= " WHERE section = ? AND year = ? ORDER BY first_name ASC";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('si', $section, $year);
}
$stmt->execute();
$result = $stmt->get_result();

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
                        <h5 class="mb-0">Students in Year <?php echo htmlspecialchars((string)$year); ?> - Section <?php echo htmlspecialchars($section); ?></h5>
                        <span class="badge bg-primary"><?php echo count($students); ?> students</span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (count($students) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Year</th>
                                        <th>Status</th>
                                        <th>Enrolled</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(formatStudentName($student)); ?></td>
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
