<?php
/**
 * Teacher Student Management
 * Manage students in assigned section (no fingerprint editing)
 */

session_start();

// Role check
if ($_SESSION['role'] !== 'teacher') {
    header('Location: ../index.php?error=Unauthorized');
    exit;
}

require_once '../config/db.php';
require_once '../helpers/SchoolYearHelper.php';

SchoolYearHelper::ensureSchoolYearSupport($mysqli);
$activeSchoolYear = SchoolYearHelper::getEffectiveSchoolYearRange($mysqli);

function resolveTeacherSection(mysqli $mysqli): string
{
    $sessionSection = trim((string)($_SESSION['teacher_section'] ?? ''));
    if ($sessionSection !== '') {
        return $sessionSection;
    }

    $teacherId = $_SESSION['admin_id'] ?? null;
    if ($teacherId !== null) {
        $stmt = $mysqli->prepare("SELECT section FROM users WHERE id = ? AND role = 'teacher' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $teacherId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $resolved = trim((string)($row['section'] ?? ''));
            if ($resolved !== '') {
                $_SESSION['teacher_section'] = $resolved;
                return $resolved;
            }
        }
    }

    $teacherEmail = $_SESSION['admin_email'] ?? '';
    if ($teacherEmail !== '') {
        $stmt = $mysqli->prepare("SELECT section FROM users WHERE email = ? AND role = 'teacher' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $teacherEmail);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $resolved = trim((string)($row['section'] ?? ''));
            if ($resolved !== '') {
                $_SESSION['teacher_section'] = $resolved;
                return $resolved;
            }
        }
    }

    $_SESSION['teacher_section'] = '';
    return '';
}

$section = resolveTeacherSection($mysqli);

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

    $mysqli->query("CREATE TABLE IF NOT EXISTS teacher_sections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        section_id INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_teacher_section (teacher_id, section_id),
        UNIQUE KEY uniq_section_teacher (section_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

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

$hasMiddleInitialColumn = studentColumnExists($mysqli, 'middle_initial');
$hasExtensionColumn = studentColumnExists($mysqli, 'extension');

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

$teacherAssignment = resolveTeacherAssignment($mysqli);
$teacherYear = (int)($teacherAssignment['year_level'] ?? 0);
$section = (string)($teacherAssignment['section'] ?? '');
$hasSectionIdColumn = studentColumnExists($mysqli, 'section_id');
$teacherSectionIds = resolveTeacherSectionIds($mysqli);
$teacherSectionIdCsv = implode(',', array_map('intval', $teacherSectionIds));

if ($hasSectionIdColumn && empty($teacherSectionIds) && ($teacherYear <= 0 || $section === '')) {
    header('Location: ../index.php?error=Teacher assignment missing');
    exit;
}

if (!$hasSectionIdColumn && ($teacherYear <= 0 || $section === '')) {
    header('Location: ../index.php?error=Teacher assignment missing');
    exit;
}

// Handle student update
$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'edit') {
        $student_id_db = intval($_POST['student_id_db']);
        $first_name = trim($_POST['first_name']);
        $middle_initial = trim($_POST['middle_initial'] ?? '');
        $extension = trim($_POST['extension'] ?? '');
        $last_name = trim($_POST['last_name']);
        $year = $teacherYear;
        $status = $_POST['status'];

        // Verify student belongs to this teacher's section
        if ($hasSectionIdColumn && $teacherSectionIdCsv !== '') {
            $verifySql = "SELECT id FROM students WHERE id = ? AND section_id IN ({$teacherSectionIdCsv})";
            $stmt = $mysqli->prepare($verifySql);
            $stmt->bind_param('i', $student_id_db);
        } else {
            $stmt = $mysqli->prepare("SELECT id FROM students WHERE id = ? AND section = ?");
            $stmt->bind_param("is", $student_id_db, $section);
        }
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            $message = 'Unauthorized: This student is not in your section.';
            $message_type = 'danger';
        } elseif (empty($first_name) || empty($last_name)) {
            $message = 'Please fill in all required fields.';
            $message_type = 'danger';
        } else {
            // Build update query based on available columns
            $setClauses = [
                'first_name = ?',
                'last_name = ?',
                'year = ?',
                'section = ?',
                'status = ?'
            ];
            $types = 'ssiss';
            $params = [$first_name, $last_name, $year, $section, $status];

            if ($hasMiddleInitialColumn) {
                array_splice($setClauses, 1, 0, 'middle_initial = ?');
                $types = 'sssiss';
                $params = [$first_name, $middle_initial, $last_name, $year, $section, $status];
            }

            if ($hasExtensionColumn) {
                $insertIndex = $hasMiddleInitialColumn ? 3 : 2;
                array_splice($setClauses, $insertIndex, 0, 'extension = ?');
                if ($hasMiddleInitialColumn) {
                    $types = 'ssssiss';
                    $params = [$first_name, $middle_initial, $last_name, $extension, $year, $section, $status];
                } else {
                    $types = 'sssiss';
                    $params = [$first_name, $last_name, $extension, $year, $section, $status];
                }
            }

            $setClauses[] = 'updated_at = NOW()';
            $sql = 'UPDATE students SET ' . implode(', ', $setClauses) . ' WHERE id = ?';
            $stmt = $mysqli->prepare($sql);
            $types .= 'i';
            $params[] = $student_id_db;
            
            $bindParams = [$types];
            foreach ($params as $index => $value) {
                $bindParams[] = &$params[$index];
            }
            call_user_func_array([$stmt, 'bind_param'], $bindParams);
            
            if ($stmt->execute()) {
                $message = 'Student information updated successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error updating student: ' . $mysqli->error;
                $message_type = 'danger';
            }
        }
    }
}

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $sort = $_GET['sort'] ?? 'last_name';
    $order = ($_GET['order'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
    
    $validSortColumns = ['last_name', 'first_name', 'year', 'status', 'created_at'];
    if (!in_array($sort, $validSortColumns)) {
        $sort = 'last_name';
    }

    if ($hasSectionIdColumn && $teacherSectionIdCsv !== '') {
        $sql = "SELECT * FROM students WHERE section_id IN ({$teacherSectionIdCsv}) ORDER BY {$sort} {$order}";
        $stmt = $mysqli->prepare($sql);
    } else {
        $sql = "SELECT * FROM students WHERE year = ? AND section = ? ORDER BY {$sort} {$order}";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("is", $teacherYear, $section);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    ob_end_clean();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="section_' . urlencode($section) . '_students_export.csv"');
    
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');

    $header = ['#', 'First Name', 'Last Name'];
    if ($hasMiddleInitialColumn) array_push($header, 'Middle Initial');
    if ($hasExtensionColumn) array_push($header, 'Extension');
    $header = array_merge($header, ['Year', 'Section', 'Status', 'Join Date']);
    
    fputcsv($output, $header);

    $rowNum = 1;
    while ($row = $result->fetch_assoc()) {
        $csvRow = [
            $rowNum++,
            $row['first_name'],
            $row['last_name']
        ];
        if ($hasMiddleInitialColumn) array_push($csvRow, $row['middle_initial'] ?? '');
        if ($hasExtensionColumn) array_push($csvRow, $row['extension'] ?? '');
        $csvRow = array_merge($csvRow, [
            $row['year'],
            $row['section'],
            $row['status'],
            $row['created_at']
        ]);
        fputcsv($output, $csvRow);
    }

    fclose($output);
    exit;
}

if ($hasSectionIdColumn && $teacherSectionIdCsv !== '') {
    $sql = "SELECT s.* FROM students s WHERE s.section_id IN ({$teacherSectionIdCsv}) ORDER BY s.last_name ASC, s.first_name ASC";
    $stmt = $mysqli->prepare($sql);
} else {
    $sql = "SELECT s.* FROM students s WHERE s.year = ? AND s.section = ? ORDER BY s.last_name ASC, s.first_name ASC";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("is", $teacherYear, $section);
}
$stmt->execute();
$result = $stmt->get_result();
$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

require '../includes/header.php';
?>

<style>
.teacher-students-page {
    padding-top: 0.25rem;
}

.teacher-students-page .students-toolbar {
    border: 1px solid rgba(255, 0, 0, 0.16);
    border-radius: 14px;
    background: linear-gradient(135deg, #ffffff 0%, #fff6f6 100%);
}

.teacher-students-page .class-label {
    font-size: 0.72rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #6b7280;
    font-weight: 700;
    margin-bottom: 2px;
}

.teacher-students-page .class-value {
    font-size: 1rem;
    font-weight: 700;
    color: #111827;
}

.teacher-students-page .students-table-card {
    border: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 14px;
    overflow: hidden;
}

.teacher-students-page .students-table-card .card-header {
    background: #f9fafb;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}

.teacher-students-page .students-table th {
    white-space: nowrap;
    font-size: 0.8rem;
}

.teacher-students-page .students-table td {
    vertical-align: middle;
}

.teacher-students-page .student-id {
    font-weight: 700;
    letter-spacing: 0.01em;
}

.teacher-students-page .student-name {
    font-weight: 600;
}
</style>

<div class="main-content">
    <div class="container teacher-students-page">
        <div class="card students-toolbar shadow-sm mb-3">
            <div class="card-body py-2 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <div class="class-label">Assigned Class</div>
                    <div class="class-value">Year <?php echo htmlspecialchars((string)$teacherYear); ?> - Section <?php echo htmlspecialchars($section); ?></div>
                </div>
                <div>
                    <a href="?export=csv&sort=last_name&order=asc" class="btn btn-primary btn-sm px-3">
                        <i class="fa-solid fa-download"></i> Export CSV
                    </a>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="alert">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="card students-table-card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <h6 class="mb-0 fw-semibold">Student List</h6>
                <span class="small text-muted"><?php echo count($students); ?> student<?php echo count($students) === 1 ? '' : 's'; ?></span>
            </div>
            <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 students-table">
                    <thead>
                        <tr>
                                <th style="width: 70px;">No.</th>
                            <th>Name</th>
                            <th>Year</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">No students in your section</td>
                            </tr>
                        <?php else: ?>
                            <?php $rowNumber = 1; foreach ($students as $student): ?>
                                <tr>
                                    <td><?php echo $rowNumber++; ?></td>
                                    <td>
                                        <?php 
                                        $nameParts = [htmlspecialchars($student['last_name']), htmlspecialchars($student['first_name'])];
                                        $name = implode(', ', array_filter($nameParts, static fn($part) => $part !== ''));
                                        if ($hasMiddleInitialColumn && !empty($student['middle_initial'] ?? '')) {
                                            $name .= ' ' . htmlspecialchars($student['middle_initial']) . '.';
                                        }
                                        if ($hasExtensionColumn && !empty($student['extension'] ?? '')) {
                                            $name .= ' ' . htmlspecialchars($student['extension']);
                                        }
                                        echo '<span class="student-name">' . $name . '</span>';
                                        ?>
                                    </td>
                                    <td>Year <?php echo htmlspecialchars($student['year']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $student['status'] === 'active' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst(htmlspecialchars($student['status'])); ?>
                                        </span>
                                    </td>
                                    <td><small><?php echo date('M d, Y', strtotime($student['created_at'])); ?></small></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" onclick='openEditModal(<?php echo json_encode($student, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                            <i class="fa-solid fa-edit"></i> Edit
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="student_id_db" id="student_id_db">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>

                    <?php if ($hasMiddleInitialColumn): ?>
                        <div class="col-md-2">
                            <label for="middle_initial" class="form-label">Middle Initial</label>
                            <input type="text" class="form-control" id="middle_initial" name="middle_initial" maxlength="1">
                        </div>
                    <?php endif; ?>

                        <div class="col-md-<?php echo $hasMiddleInitialColumn ? '3' : '4'; ?>">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>

                    <?php if ($hasExtensionColumn): ?>
                        <div class="col-md-<?php echo $hasMiddleInitialColumn ? '3' : '2'; ?>">
                            <label for="extension" class="form-label">Extension</label>
                            <select class="form-select" id="extension" name="extension">
                                <option value="">Select ext (optional)</option>
                                <option value="Jr">Jr</option>
                                <option value="Sr">Sr</option>
                                <option value="II">II</option>
                                <option value="III">III</option>
                                <option value="IV">IV</option>
                            </select>
                        </div>
                    <?php endif; ?>

                        <div class="col-md-4">
                            <label class="form-label">Year Level</label>
                            <input type="text" class="form-control" value="Year <?php echo htmlspecialchars((string)$teacherYear); ?>" disabled>
                            <input type="hidden" name="year" id="year" value="<?php echo (int)$teacherYear; ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Section</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($section); ?>" disabled>
                            <small class="text-muted">Section is fixed to your assigned section.</small>
                        </div>

                        <div class="col-md-4">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="graduated">Graduated</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditModal(student) {
    document.getElementById('student_id_db').value = student.id;
    document.getElementById('first_name').value = student.first_name;
    document.getElementById('last_name').value = student.last_name;
    <?php if ($hasMiddleInitialColumn): ?>
        document.getElementById('middle_initial').value = student.middle_initial || '';
    <?php endif; ?>
    <?php if ($hasExtensionColumn): ?>
        document.getElementById('extension').value = student.extension || '';
    <?php endif; ?>
    document.getElementById('year').value = <?php echo (int)$teacherYear; ?>;
    document.getElementById('status').value = student.status;
    
    const modal = new bootstrap.Modal(document.getElementById('editModal'));
    modal.show();
}
</script>

<?php require '../includes/footer.php'; ?>
