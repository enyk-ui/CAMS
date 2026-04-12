<?php
/**
 * Student Management Page
 * CRUD operations for student records
 */

session_start();
require_once '../config/db.php';
require_once '../helpers/SchoolYearHelper.php';
require '../includes/header.php';

SchoolYearHelper::ensureSchoolYearSupport($mysqli);
$activeSchoolYear = SchoolYearHelper::getEffectiveSchoolYearRange($mysqli);
$schoolYears = SchoolYearHelper::getAllSchoolYears($mysqli);

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

function usersColumnExists(mysqli $mysqli, string $columnName): bool
{
    $safeColumn = $mysqli->real_escape_string($columnName);
    $result = $mysqli->query("SHOW COLUMNS FROM users LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

function buildTeacherAssignmentMap(mysqli $mysqli): array
{
    $map = [];
    $usersTable = $mysqli->query("SHOW TABLES LIKE 'users'");
    if (!$usersTable || $usersTable->num_rows === 0) {
        return $map;
    }

    if (!usersColumnExists($mysqli, 'school_year_label') || !usersColumnExists($mysqli, 'year_level') || !usersColumnExists($mysqli, 'section')) {
        return $map;
    }

    $result = $mysqli->query("SELECT school_year_label, year_level, section FROM users WHERE role = 'teacher' AND status = 'active' AND school_year_label IS NOT NULL AND school_year_label <> '' AND year_level IS NOT NULL AND section IS NOT NULL AND TRIM(section) <> ''");
    if (!$result) {
        return $map;
    }

    while ($row = $result->fetch_assoc()) {
        $sy = trim((string)($row['school_year_label'] ?? ''));
        $year = (string)((int)($row['year_level'] ?? 0));
        $section = trim((string)($row['section'] ?? ''));
        if ($sy === '' || $year === '0' || $section === '') {
            continue;
        }

        if (!isset($map[$sy])) {
            $map[$sy] = [];
        }
        if (!isset($map[$sy][$year])) {
            $map[$sy][$year] = [];
        }
        $map[$sy][$year][$section] = true;
    }

    return $map;
}

function studentMatchesExportScope(array $student, string $scope, string $schoolYear, string $section, array $teacherAssignmentMap): bool
{
    if ($scope === 'all' || $scope === 'filters') {
        return true;
    }

    $studentYear = (string)((int)($student['year'] ?? 0));
    $studentSection = trim((string)($student['section'] ?? ''));

    if ($scope === 'school_year') {
        if ($schoolYear === '' || $studentYear === '0' || $studentSection === '') {
            return false;
        }
        return !empty($teacherAssignmentMap[$schoolYear][$studentYear][$studentSection]);
    }

    if ($scope === 'section') {
        if ($section === '' || strcasecmp($studentSection, $section) !== 0) {
            return false;
        }

        if ($schoolYear !== '') {
            if ($studentYear === '0') {
                return false;
            }
            return !empty($teacherAssignmentMap[$schoolYear][$studentYear][$studentSection]);
        }

        return true;
    }

    return true;
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

function formatNameByMode(array $student, string $mode): string
{
    $first = trim((string)($student['first_name'] ?? ''));
    $middle = trim((string)($student['middle_initial'] ?? ''));
    $last = trim((string)($student['last_name'] ?? ''));
    $ext = trim((string)($student['extension'] ?? ''));
    $middleToken = $middle !== '' ? strtoupper(substr($middle, 0, 1)) . '.' : '';

    if ($mode === 'full_name') {
        $parts = array_filter([$first, $middleToken, $last, $ext], static fn($part) => $part !== '');
        return trim(implode(' ', $parts));
    }

    $name = $last;
    if ($first !== '') {
        $name .= ($name !== '' ? ', ' : '') . $first;
    }
    if ($middleToken !== '') {
        $name .= ' ' . $middleToken;
    }
    if ($ext !== '') {
        $name .= ' ' . $ext;
    }

    return trim($name);
}

function sortStudentsForExport(array &$students, string $sortBy, string $sortDir): void
{
    $direction = strtolower($sortDir) === 'asc' ? 1 : -1;

    usort($students, function (array $a, array $b) use ($sortBy, $direction): int {
        switch ($sortBy) {
            case 'name':
                $cmp = strcasecmp(formatStudentName($a), formatStudentName($b));
                break;
            case 'year':
                $cmp = ((int)($a['year'] ?? 0)) <=> ((int)($b['year'] ?? 0));
                break;
            case 'section':
                $cmp = strcasecmp((string)($a['section'] ?? ''), (string)($b['section'] ?? ''));
                break;
            case 'fingerprints':
                $cmp = ((int)($a['fingerprint_count'] ?? 0)) <=> ((int)($b['fingerprint_count'] ?? 0));
                break;
            case 'status':
                $cmp = strcasecmp((string)($a['status'] ?? ''), (string)($b['status'] ?? ''));
                break;
            case 'created':
            default:
                $cmp = strcmp((string)($a['id'] ?? ''), (string)($b['id'] ?? ''));
                break;
        }

        return $cmp * $direction;
    });
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
        s.first_name, 
        s.last_name, 
        {$middleInitialExpr} AS middle_initial,
        {$extensionExpr} AS extension,
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

$sections = [];
foreach ($students as $row) {
    $section = trim((string)($row['section'] ?? ''));
    if ($section !== '') {
        $sections[$section] = true;
    }
}
$sections = array_keys($sections);
sort($sections, SORT_NATURAL | SORT_FLAG_CASE);

$studentListSectionFilter = trim((string)($_GET['list_section'] ?? ''));
if ($studentListSectionFilter !== '') {
    $students = array_values(array_filter($students, function (array $row) use ($studentListSectionFilter): bool {
        return strcasecmp(trim((string)($row['section'] ?? '')), $studentListSectionFilter) === 0;
    }));
}

if (($_GET['export'] ?? '') === 'csv') {
    $exportScope = trim((string)($_GET['export_scope'] ?? 'filters'));
    $exportSchoolYear = trim((string)($_GET['export_school_year'] ?? ''));
    $exportSection = trim((string)($_GET['export_section'] ?? ''));
    $exportSortBy = trim((string)($_GET['export_sort_by'] ?? 'name'));
    $exportSortDir = strtolower(trim((string)($_GET['export_sort_dir'] ?? 'desc'))) === 'asc' ? 'asc' : 'desc';
    $exportNameFormat = trim((string)($_GET['export_name_format'] ?? 'last_name_first'));

    $allowedExportSortBy = ['date', 'last_name', 'first_name', 'year_section', 'remark'];
    if (!in_array($exportSortBy, $allowedExportSortBy, true)) {
        $exportSortBy = 'name';
    }

    $allowedNameFormats = ['full_name', 'last_name_first'];
    if (!in_array($exportNameFormat, $allowedNameFormats, true)) {
        $exportNameFormat = 'last_name_first';
    }

    $teacherAssignmentMap = buildTeacherAssignmentMap($mysqli);

    $exportStudents = array_values(array_filter($students, function (array $student) use ($exportScope, $exportSchoolYear, $exportSection, $teacherAssignmentMap): bool {
        return studentMatchesExportScope($student, $exportScope, $exportSchoolYear, $exportSection, $teacherAssignmentMap);
    }));
    if ($exportSortBy === 'date') {
        sortStudentsForExport($exportStudents, 'created', $exportSortDir);
    } elseif ($exportSortBy === 'last_name') {
        usort($exportStudents, function (array $a, array $b) use ($exportSortDir): int {
            $cmp = strcasecmp((string)($a['last_name'] ?? ''), (string)($b['last_name'] ?? ''));
            return ($exportSortDir === 'asc' ? 1 : -1) * ($cmp !== 0 ? $cmp : strcasecmp((string)($a['first_name'] ?? ''), (string)($b['first_name'] ?? '')));
        });
    } elseif ($exportSortBy === 'first_name') {
        usort($exportStudents, function (array $a, array $b) use ($exportSortDir): int {
            $cmp = strcasecmp((string)($a['first_name'] ?? ''), (string)($b['first_name'] ?? ''));
            return ($exportSortDir === 'asc' ? 1 : -1) * ($cmp !== 0 ? $cmp : strcasecmp((string)($a['last_name'] ?? ''), (string)($b['last_name'] ?? '')));
        });
    } elseif ($exportSortBy === 'year_section') {
        sortStudentsForExport($exportStudents, 'section', $exportSortDir);
    } else {
        sortStudentsForExport($exportStudents, 'status', $exportSortDir);
    }

    if (ob_get_length() !== false) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="students_export.csv"');

    $fp = fopen('php://output', 'w');
    fwrite($fp, "\xEF\xBB\xBF");
    fputcsv($fp, ['#', 'Name', 'Year', 'Section', 'Fingerprints', 'Status']);

    $idx = 1;
    foreach ($exportStudents as $student) {
        fputcsv($fp, [
            $idx++,
            formatNameByMode($student, $exportNameFormat),
            $student['year'] ?? '',
            $student['section'] ?? '',
            (int)($student['fingerprint_count'] ?? 0),
            ucfirst((string)($student['status'] ?? '')),
        ]);
    }

    fclose($fp);
    exit;
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
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#smartStudentExportModal">
                <i class="bi bi-file-earmark-csv"></i> Export
            </button>
            <a href="register.php" class="btn btn-sm btn-primary">
                <i class="bi bi-person-plus"></i> Add Student
            </a>
        </div>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-4 col-sm-6">
                <label for="list_section" class="form-label">Section Filter</label>
                <select class="form-select" id="list_section" name="list_section">
                    <option value="">All Sections</option>
                    <?php foreach ($sections as $section): ?>
                        <option value="<?php echo htmlspecialchars($section); ?>" <?php echo strcasecmp($studentListSectionFilter, $section) === 0 ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($section); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 col-sm-3">
                <button type="submit" class="btn btn-primary w-100">Apply</button>
            </div>
            <div class="col-md-2 col-sm-3">
                <a href="students.php" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Year</th>
                        <th>Section</th>
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
                        <td><?php echo htmlspecialchars(formatStudentName($student)); ?></td>
                        <td><?php echo $student['year']; ?></td>
                        <td><?php echo htmlspecialchars($student['section']); ?></td>
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

<div class="modal fade" id="smartStudentExportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-funnel"></i> Smart Export Filter</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="GET">
                <div class="modal-body">
                    <input type="hidden" name="export" value="csv">

                    <div class="mb-3">
                        <label for="student_export_scope" class="form-label">Export Scope</label>
                        <select class="form-select" id="student_export_scope" name="export_scope">
                            <option value="all" selected>All Students</option>
                            <option value="school_year">By School Year (teacher assignments)</option>
                            <option value="section">By Section (optional SY)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="student_export_sort_by" class="form-label">Sort By</label>
                        <div class="row g-2">
                            <div class="col-8">
                                <select class="form-select" id="student_export_sort_by" name="export_sort_by">
                                    <option value="date">Date</option>
                                    <option value="last_name" selected>Last name</option>
                                    <option value="first_name">First name</option>
                                    <option value="year_section">Year/Section</option>
                                    <option value="remark">Remark</option>
                                </select>
                            </div>
                            <div class="col-4">
                                <select class="form-select" id="student_export_sort_dir" name="export_sort_dir">
                                    <option value="asc">Asc</option>
                                    <option value="desc" selected>Desc</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="student_export_name_format" class="form-label">Name Format</label>
                        <select class="form-select" id="student_export_name_format" name="export_name_format">
                            <option value="full_name">Full name (First name MI Last name Ext)</option>
                            <option value="last_name_first" selected>Last name, First name MI Ext</option>
                        </select>
                    </div>

                    <div class="mb-3" id="studentExportSchoolYearGroup" style="display:none;">
                        <label for="student_export_school_year" class="form-label">School Year</label>
                        <select class="form-select" id="student_export_school_year" name="export_school_year">
                            <option value="">Select school year</option>
                            <?php foreach ($schoolYears as $sy): ?>
                                <option value="<?php echo htmlspecialchars((string)$sy['label']); ?>" <?php echo ((string)$sy['label'] === (string)($activeSchoolYear['label'] ?? '')) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string)$sy['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-0" id="studentExportSectionGroup" style="display:none;">
                        <label for="student_export_section" class="form-label">Section</label>
                        <select class="form-select" id="student_export_section" name="export_section">
                            <option value="">Select section</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?php echo htmlspecialchars($section); ?>"><?php echo htmlspecialchars($section); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-download"></i> Export</button>
                </div>
            </form>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const exportScope = document.getElementById('student_export_scope');
    const exportSection = document.getElementById('student_export_section');
    const schoolYearGroup = document.getElementById('studentExportSchoolYearGroup');
    const sectionGroup = document.getElementById('studentExportSectionGroup');
    const sortByGroup = document.getElementById('student_export_sort_by')?.closest('.mb-3');
    const nameFormatGroup = document.getElementById('student_export_name_format')?.closest('.mb-3');

    if (!exportScope || !exportSection || !schoolYearGroup || !sectionGroup || !sortByGroup || !nameFormatGroup) {
        return;
    }

    const syncExportScope = () => {
        const mode = exportScope.value;
        schoolYearGroup.style.display = (mode === 'school_year' || mode === 'section') ? '' : 'none';
        sectionGroup.style.display = mode === 'section' ? '' : 'none';

        const requireSectionFirst = (mode === 'section');
        const canShowTailOptions = !requireSectionFirst || (exportSection.value.trim() !== '');
        sortByGroup.style.display = canShowTailOptions ? '' : 'none';
        nameFormatGroup.style.display = canShowTailOptions ? '' : 'none';
    };

    exportScope.addEventListener('change', syncExportScope);
    exportSection.addEventListener('change', syncExportScope);
    syncExportScope();
});
</script>

<?php require '../includes/footer.php'; ?>
