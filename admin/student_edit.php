<?php
/**
 * Student Edit Page
 * Edit student information
 */

session_start();
require_once '../config/db.php';
require_once '../helpers/SchoolYearHelper.php';
require '../includes/header.php';

function studentColumnExists(mysqli $mysqli, string $columnName): bool
{
    $safeColumn = $mysqli->real_escape_string($columnName);
    $result = $mysqli->query("SHOW COLUMNS FROM students LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

function usersColumnExists(mysqli $mysqli, string $columnName): bool
{
    $safeColumn = $mysqli->real_escape_string($columnName);
    $result = $mysqli->query("SHOW COLUMNS FROM users LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

function ensureUsersAssignmentColumns(mysqli $mysqli): void
{
    $usersTable = $mysqli->query("SHOW TABLES LIKE 'users'");
    if (!$usersTable || $usersTable->num_rows === 0) {
        return;
    }

    if (!usersColumnExists($mysqli, 'year_level')) {
        $mysqli->query("ALTER TABLE users ADD COLUMN year_level TINYINT UNSIGNED DEFAULT NULL");
    }

    if (!usersColumnExists($mysqli, 'school_year_label')) {
        $mysqli->query("ALTER TABLE users ADD COLUMN school_year_label VARCHAR(20) DEFAULT NULL");
    }

    if (!usersColumnExists($mysqli, 'section')) {
        $mysqli->query("ALTER TABLE users ADD COLUMN section VARCHAR(50) DEFAULT NULL");
    }
}

function buildTeacherSectionMap(mysqli $mysqli, string $activeSchoolYearLabel): array
{
    $map = [];
    $usersTable = $mysqli->query("SHOW TABLES LIKE 'users'");
    if (!$usersTable || $usersTable->num_rows === 0) {
        return $map;
    }

    if (!usersColumnExists($mysqli, 'year_level') || !usersColumnExists($mysqli, 'section')) {
        return $map;
    }

    $hasSchoolYearLabel = usersColumnExists($mysqli, 'school_year_label');

    $sql = "SELECT year_level, section, full_name FROM users WHERE role = 'teacher' AND status = 'active' AND year_level IS NOT NULL AND section IS NOT NULL AND TRIM(section) <> ''";
    $types = '';
    $params = [];

    if ($hasSchoolYearLabel && $activeSchoolYearLabel !== '') {
        $sql .= " AND (school_year_label = ? OR school_year_label IS NULL OR school_year_label = '')";
        $types .= 's';
        $params[] = $activeSchoolYearLabel;
    }

    $sql .= " ORDER BY year_level ASC, section ASC, full_name ASC";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return $map;
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $year = (string)((int)($row['year_level'] ?? 0));
        $section = trim((string)($row['section'] ?? ''));
        $teacherName = trim((string)($row['full_name'] ?? 'Unassigned'));
        if ($year === '0' || $section === '') {
            continue;
        }

        if (!isset($map[$year])) {
            $map[$year] = [];
        }

        $duplicate = false;
        foreach ($map[$year] as $entry) {
            if (($entry['section'] ?? '') === $section) {
                $duplicate = true;
                break;
            }
        }

        if (!$duplicate) {
            $map[$year][] = [
                'section' => $section,
                'teacher' => $teacherName,
            ];
        }
    }

    return $map;
}

function formatStudentDisplayName(array $student): string
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

// Get student ID from URL
$student_id = $_GET['id'] ?? null;
if (!$student_id) {
    header('Location: students.php?error=Student not found');
    exit;
}

// Get student data
$stmt = $mysqli->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student) {
    header('Location: students.php?error=Student not found');
    exit;
}

$message = '';
$message_type = 'success';
SchoolYearHelper::ensureSchoolYearSupport($mysqli);
$activeSchoolYear = SchoolYearHelper::getEffectiveSchoolYearRange($mysqli);
$activeSchoolYearLabel = (string)($activeSchoolYear['label'] ?? '');
ensureUsersAssignmentColumns($mysqli);
$teacherSectionMap = buildTeacherSectionMap($mysqli, $activeSchoolYearLabel);
$hasMiddleInitialColumn = studentColumnExists($mysqli, 'middle_initial');
$hasExtensionColumn = studentColumnExists($mysqli, 'extension');
$hasUpdatedAtColumn = studentColumnExists($mysqli, 'updated_at');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $middle_initial = trim($_POST['middle_initial'] ?? '');
    $extension = trim($_POST['extension'] ?? '');
    $last_name = trim($_POST['last_name']);
    $year = intval($_POST['year']);
    $section = trim($_POST['section']);
    $status = $_POST['status'];

    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($section)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'danger';
    } else {
        $duplicateStmt = $mysqli->prepare('SELECT id FROM students WHERE first_name = ? AND last_name = ? AND section = ? AND id <> ? LIMIT 1');
        $duplicateStmt->bind_param('sssi', $first_name, $last_name, $section, $student_id);
        $duplicateStmt->execute();
        $duplicateRow = $duplicateStmt->get_result()->fetch_assoc();
        $duplicateStmt->close();

        if ($duplicateRow) {
            $message = 'Duplicate student detected in the selected section.';
            $message_type = 'danger';
        } else {
        // Build update query based on available columns in current schema.
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

        if ($hasUpdatedAtColumn) {
            $setClauses[] = 'updated_at = NOW()';
        }

        $sql = 'UPDATE students SET ' . implode(', ', $setClauses) . ' WHERE id = ?';
        $stmt = $mysqli->prepare($sql);
        $types .= 'i';
        $params[] = $student_id;
        $bindParams = [$types];
        foreach ($params as $index => $value) {
            $bindParams[] = &$params[$index];
        }
        call_user_func_array([$stmt, 'bind_param'], $bindParams);
        
        if ($stmt->execute()) {
            $message = 'Student information updated successfully!';
            $message_type = 'success';
            
            // Refresh student data
            $stmt = $mysqli->prepare("SELECT * FROM students WHERE id = ?");
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $student = $result->fetch_assoc();
        } else {
            $message = 'Error updating student: ' . $mysqli->error;
            $message_type = 'danger';
        }
        }
    }
}
?>

<div class="container">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-person-gear"></i> Edit Student</h5>
                    <a href="students.php" class="btn btn-sm btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Students
                    </a>
                </div>
                <div class="card-body">
                    
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="row g-3">
                        <!-- First Name -->
                        <div class="col-md-4">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?php echo htmlspecialchars($student['first_name']); ?>" 
                                   placeholder="Juan" required>
                        </div>

                        <!-- Middle Initial -->
                        <?php if ($hasMiddleInitialColumn): ?>
                            <div class="col-md-2">
                                <label for="middle_initial" class="form-label">M.I.</label>
                                <input type="text" class="form-control" id="middle_initial" name="middle_initial" 
                                       value="<?php echo htmlspecialchars($student['middle_initial'] ?? ''); ?>" 
                                       placeholder="D" maxlength="1">
                            </div>
                        <?php endif; ?>

                        <!-- Last Name -->
                        <div class="col-md-<?php echo $hasMiddleInitialColumn ? '4' : '6'; ?>">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($student['last_name']); ?>" 
                                   placeholder="Dela Cruz" required>
                        </div>

                        <?php if ($hasExtensionColumn): ?>
                            <div class="col-md-<?php echo $hasMiddleInitialColumn ? '2' : '6'; ?>">
                                <label for="extension" class="form-label">Ext.</label>
                                <?php $extensionOptions = ['', 'Jr', 'Sr', 'II', 'III', 'IV']; ?>
                                <?php $currentExtension = (string) ($student['extension'] ?? ''); ?>
                                <select class="form-select" id="extension" name="extension">
                                    <option value="">Select ext (optional)</option>
                                    <?php foreach ($extensionOptions as $extOption): ?>
                                        <?php if ($extOption === '') continue; ?>
                                        <option value="<?php echo htmlspecialchars($extOption); ?>" <?php echo $currentExtension === $extOption ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($extOption); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if ($currentExtension !== '' && !in_array($currentExtension, $extensionOptions, true)): ?>
                                        <option value="<?php echo htmlspecialchars($currentExtension); ?>" selected>
                                            <?php echo htmlspecialchars($currentExtension); ?> (current)
                                        </option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <!-- Year -->
                        <div class="col-md-6">
                            <label for="year" class="form-label">Year *</label>
                            <select class="form-select" id="year" name="year" required>
                                <option value="">Select Year</option>
                                <?php for ($y = 1; $y <= 4; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $student['year'] == $y ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <!-- Section -->
                        <div class="col-md-6">
                            <label for="section" class="form-label">Section *</label>
                            <select class="form-select" id="section" name="section" required>
                                <option value="">Select year first</option>
                            </select>
                        </div>

                        <!-- Status -->
                        <div class="col-md-12">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?php echo $student['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $student['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="graduated" <?php echo $student['status'] === 'graduated' ? 'selected' : ''; ?>>Graduated</option>
                            </select>
                        </div>

                        <!-- Actions -->
                        <div class="col-12">
                            <hr>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <button type="button" class="btn btn-primary" id="updateFingerprintsBtn"
                                            onclick="openFingerprintModal(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars(formatStudentDisplayName($student)); ?>')">
                                        <i class="bi bi-fingerprint"></i> Update Fingerprints
                                    </button>
                                    
                                    <!-- Fingerprint Status Indicator -->
                                    <div id="fingerprintStatus" class="mt-2" style="display: none;">
                                        <div class="alert alert-success py-2 mb-0">
                                            <i class="bi bi-check-circle"></i> 
                                            <span id="fingerprintStatusText">Fingerprints updated</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Fingerprint Warning -->
                                    <div id="fingerprintWarning" class="mt-2" style="display: none;">
                                        <div class="alert alert-warning py-2 mb-0">
                                            <i class="bi bi-exclamation-triangle"></i> 
                                            Fingerprint update is optional. You can save student changes without updating fingerprints.
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary px-4 fw-semibold" id="doneStudentBtn">
                                        <i class="bi bi-check2-circle"></i> Done
                                    </button>
                                </div>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Fingerprint Modal (same as students.php) -->
<div class="modal fade" id="fingerprintModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-fingerprint"></i> Update Fingerprints</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <h6>Student: <span id="studentName" class="text-primary"></span></h6>
                    <p class="text-muted">Single-fingerprint update is enforced and starts automatically.</p>
                </div>

                <div id="step1SelectFingers">
                    <div class="alert alert-info mb-0">
                        Enrollment starts automatically.
                    </div>
                </div>

                <!-- Step 2: Enrollment Progress -->
                <div id="step2EnrollmentProgress" style="display: none;">
                    <div class="text-center mb-4">
                        <h4 id="currentFingerTitle">Finger 1 of 1</h4>
                        <p class="text-muted mb-2" id="scanStepStatus">Scanning finger 1 - 1 of 3</p>
                        <div class="scan-step-indicators mb-2" id="scanStepIndicators"></div>
                        <p class="text-muted mb-0">Place your finger on the scanner and wait for confirmation.</p>
                    </div>

                    <div class="d-flex justify-content-center gap-3 flex-wrap mb-4" id="scanCirclesContainer"></div>

                    <div id="scanStatusInline" class="scan-status waiting mb-3" role="status" aria-live="polite">
                        <i id="scanStatusIcon" class="bi bi-hourglass-split"></i>
                        <span id="scanStatusInlineText">Waiting for next scan...</span>
                    </div>

                    <div class="mb-3">
                        <div class="progress" style="height: 10px;">
                            <div id="enrollProgressBar" class="progress-bar bg-success" role="progressbar" style="width: 0%"></div>
                        </div>
                        <small class="text-muted" id="enrollProgressText">Progress: 0%</small>
                    </div>

                    <div class="alert alert-info mb-4" id="statusMessage">
                        <div class="d-flex align-items-center">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                            <div>
                                <strong>Waiting for scanner</strong><br>
                                <small id="statusText">Waiting for scan...</small>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary" id="btnCancelEnrollment">
                            <i class="bi bi-x-circle"></i> Cancel
                        </button>
                        <button type="button" class="btn btn-outline-danger" id="btnRetryEnrollment" style="display:none;">
                            <i class="bi bi-arrow-clockwise"></i> Retry Enrollment
                        </button>
                    </div>
                </div>

                <!-- Step 3: Completion -->
                <div id="step3Complete" style="display: none;">
                    <div class="text-center py-4">
                        <div class="success-icon mb-3">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                        </div>
                        <h4 class="text-success mb-3">Update Complete!</h4>
                        <p class="text-muted" id="completionMessage">All fingerprints updated successfully</p>
                        <button type="button" class="btn btn-primary mt-3" data-bs-dismiss="modal">
                            <i class="bi bi-check"></i> Done
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<style>
/* Fingerprint Modal Styles */
.finger-selector {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.finger-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    background: white;
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 100px;
}

.finger-btn:hover {
    border-color: #3b82f6;
    background: #f8faff;
    transform: translateY(-2px);
}

.finger-btn.selected {
    border-color: #3b82f6;
    background: #3b82f6;
    color: white;
}

.finger-btn i {
    font-size: 2rem;
    margin-bottom: 8px;
}

.scan-attempts {
    display: flex;
    justify-content: center;
    gap: 15px;
    flex-wrap: wrap;
}

.scan-attempt {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.scan-circle {
    width: 60px;
    height: 60px;
    border: 3px solid #e5e7eb;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f9fafb;
    transition: all 0.3s ease;
}

.scan-circle i {
    font-size: 1.5rem;
    color: #9ca3af;
}

.scan-circle.scanning {
    border-color: #3b82f6;
    background: #eff6ff;
    animation: pulse 2s infinite;
}

.scan-circle.scanning i {
    color: #3b82f6;
}

.scan-circle.success i {
    color: #10b981;
}

.scan-circle.success {
    border-color: #10b981;
    background: #ecfdf5;
}

.scan-circle.success i {
    color: #10b981;
}

.scan-circle.error {
    border-color: #ef4444;
    background: #fef2f2;
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.15);
    animation: duplicatePulse 0.7s infinite alternate;
}

.scan-circle.error i {
    color: #ef4444;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

@keyframes duplicatePulse {
    0% { transform: scale(1); }
    100% { transform: scale(1.08); }
}

.enrollment-header {
    text-align: center;
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 15px;
}

.scan-status {
    display: flex;
    align-items: center;
    gap: 10px;
    border: 2px solid #d1d5db;
    border-radius: 12px;
    padding: 10px 14px;
    font-weight: 600;
}

.scan-status i {
    font-size: 1.05rem;
}

.scan-status.waiting {
    background: #eff6ff;
    border-color: #93c5fd;
    color: #1e40af;
}

.scan-status.success {
    background: #ecfdf5;
    border-color: #86efac;
    color: #065f46;
}

.scan-status.error {
    background: #fef2f2;
    border-color: #fca5a5;
    color: #991b1b;
}

.scan-step-indicators {
    display: flex;
    justify-content: center;
    gap: 8px;
}

.scan-step {
    width: 30px;
    height: 30px;
    border-radius: 999px;
    border: 2px solid #d1d5db;
    color: #6b7280;
    font-size: 0.85rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #fff;
}

.scan-step.active {
    border-color: #3b82f6;
    color: #1d4ed8;
    background: #dbeafe;
}

.scan-step.done {
    border-color: #10b981;
    color: #047857;
    background: #d1fae5;
}
</style>

<script>
const teacherSectionMap = <?php echo json_encode($teacherSectionMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const currentStudentSection = <?php echo json_encode((string)($student['section'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

function populateSectionOptions(yearValue, selectedSection) {
    const sectionSelect = document.getElementById('section');
    if (!sectionSelect) {
        return;
    }

    sectionSelect.innerHTML = '';

    if (!yearValue) {
        sectionSelect.add(new Option('Select year first', ''));
        sectionSelect.value = '';
        return;
    }

    const sections = teacherSectionMap[String(yearValue)] || [];
    if (!sections.length) {
        sectionSelect.add(new Option('No teacher section for selected year', ''));
        sectionSelect.value = '';
        if (selectedSection) {
            sectionSelect.add(new Option(`${selectedSection} (current)`, selectedSection));
            sectionSelect.value = selectedSection;
        }
        return;
    }

    sectionSelect.add(new Option('Select section', ''));
    sections.forEach(entry => {
        const section = String(entry.section || '').trim();
        const teacher = String(entry.teacher || '').trim();
        if (!section) {
            return;
        }

        const label = teacher ? `${section} - ${teacher}` : section;
        sectionSelect.add(new Option(label, section));
    });

    if (selectedSection) {
        const exists = Array.from(sectionSelect.options).some(option => option.value === selectedSection);
        if (!exists) {
            sectionSelect.add(new Option(`${selectedSection} (current)`, selectedSection));
        }
        sectionSelect.value = selectedSection;
    }
}

// Fingerprint Modal State and Form Validation
let updateState = {
    modal: null,
    studentId: null,
    studentName: '',
    numFingers: 1,
    currentFinger: 1,
    enrolledFingerprints: [],
    fingerprintsChanged: false,
    sessionId: null,
    debugLines: [],
    registrationId: null,
    monitorHandle: null,
    lastServerFinger: 1,
    enrollmentCompleted: false,
    pollFailures: 0,
    scanStepsPerFinger: 3,
    currentScanStep: 1,
    lastProgressSnapshot: '',
    duplicateNoticeUntil: 0,
    duplicateActive: false,
    statusAutoHideTimer: null
};

// Initialize modal when page loads
document.addEventListener('DOMContentLoaded', function() {
    const yearSelect = document.getElementById('year');
    if (yearSelect) {
        yearSelect.addEventListener('change', function () {
            populateSectionOptions(this.value, '');
        });
        populateSectionOptions(yearSelect.value || '', currentStudentSection);
    }

    updateState.modal = new bootstrap.Modal(document.getElementById('fingerprintModal'));
    updateModeIndicator('attendance');
    renderScanCircles(1);
    
    // Cancel button
    document.getElementById('btnCancelEnrollment').addEventListener('click', function() {
        setScannerMode('attendance');
        updateModeIndicator('attendance');
        resetModal();
        updateState.modal.hide();
    });

    const retryEnrollmentBtn = document.getElementById('btnRetryEnrollment');
    if (retryEnrollmentBtn) {
        retryEnrollmentBtn.addEventListener('click', function () {
            setTimeout(() => startEnrollment(), 200);
        });
    }

    document.getElementById('fingerprintModal').addEventListener('shown.bs.modal', function() {
        setScannerMode('registration');
        updateModeIndicator('registration');

        if (updateState.studentId && !updateState.registrationId && !updateState.enrollmentCompleted) {
            setTimeout(() => startEnrollment(), 150);
        }
    });
    
    // Reset modal when closed
    document.getElementById('fingerprintModal').addEventListener('hidden.bs.modal', function() {
        setScannerMode('attendance');
        updateModeIndicator('attendance');
        resetModal();
    });
    
    // Add form validation
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            validateFormSubmission(e);
        });
    }
    
    // Monitor form changes
    const formInputs = document.querySelectorAll('input, select');
    formInputs.forEach(input => {
        input.addEventListener('change', function() {
            checkIfFingerprintUpdateNeeded();
        });
    });
});

function openFingerprintModal(studentId, studentName) {
    updateState.studentId = studentId;
    updateState.studentName = studentName;
    
    document.getElementById('studentName').textContent = studentName;
    resetModal();
    updateState.modal.show();
}

function setScannerMode(mode) {
    return fetch('../api/set_mode.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ mode: mode })
    })
    .then(response => response.json())
    .catch(() => null);
}

function updateModeIndicator(mode) {
    const badge = document.getElementById('modalModeBadge');
    if (!badge) {
        return;
    }

    if (mode === 'registration') {
        badge.className = 'd-none';
        badge.textContent = '';
        return;
    }

    badge.className = 'd-none';
    badge.textContent = '';
}

function renderScanCircles(totalFingers) {
    const container = document.getElementById('scanCirclesContainer');
    if (!container) {
        return;
    }

    const safeTotal = Math.max(1, parseInt(totalFingers || 1, 10));
    let html = '';

    for (let i = 1; i <= safeTotal; i++) {
        html += `<div class="scan-circle" id="scan${i}" data-finger="${i}"><i class="bi bi-fingerprint"></i></div>`;
    }

    container.innerHTML = html;
}

function updateScanStepIndicators() {
    const container = document.getElementById('scanStepIndicators');
    if (!container) {
        return;
    }

    let html = '';
    for (let i = 1; i <= updateState.scanStepsPerFinger; i++) {
        let cls = 'scan-step';
        if (i < updateState.currentScanStep) {
            cls += ' done';
        } else if (i === updateState.currentScanStep) {
            cls += ' active';
        }
        html += `<span class="${cls}">${i}</span>`;
    }

    container.innerHTML = html;
}

function setInlineScanStatus(type, message) {
    const statusInline = document.getElementById('scanStatusInline');
    const statusText = document.getElementById('scanStatusInlineText');
    const statusIcon = document.getElementById('scanStatusIcon');

    if (!statusInline || !statusText || !statusIcon) {
        return;
    }

    const safeType = ['waiting', 'success', 'error'].includes(type) ? type : 'waiting';

    if (safeType !== 'error' && updateState.statusAutoHideTimer) {
        clearTimeout(updateState.statusAutoHideTimer);
        updateState.statusAutoHideTimer = null;
    }

    statusInline.className = `scan-status ${safeType} mb-3`;
    statusText.textContent = message || 'Waiting for next scan...';

    if (safeType === 'success') {
        statusIcon.className = 'bi bi-check-circle-fill';
    } else if (safeType === 'error') {
        statusIcon.className = 'bi bi-exclamation-triangle-fill';
    } else {
        statusIcon.className = 'bi bi-hourglass-split';
    }

    if (safeType === 'error') {
        if (updateState.statusAutoHideTimer) {
            clearTimeout(updateState.statusAutoHideTimer);
        }

        updateState.statusAutoHideTimer = setTimeout(() => {
            updateState.duplicateActive = false;
            updateState.duplicateNoticeUntil = 0;

            const topStatus = document.getElementById('statusMessage');
            const topStatusText = document.getElementById('statusText');
            if (topStatus && (topStatus.className.includes('alert-warning') || topStatus.className.includes('alert-danger'))) {
                topStatus.className = 'alert alert-info mb-4';
            }
            if (topStatusText) {
                topStatusText.textContent = 'Waiting for scanner to process enrollment command...';
            }

            setInlineScanStatus('waiting', 'Waiting for next scan...');
            updateProgress();
        }, 5000);
    }
}

function formatEnrollmentUiError(message) {
    const raw = String(message || '').trim();
    const lower = raw.toLowerCase();

    if (!raw) {
        return 'Fingerprint scanning error. Please try again.';
    }

    // Keep scanner internals in debug log only.
    if (
        lower.includes('getimage') ||
        lower.includes('image2tz') ||
        lower.includes('createmodel') ||
        lower.includes('storemodel') ||
        lower.includes('remove finger timeout') ||
        lower.includes('scanner enrollment failed') ||
        lower.includes('code=')
    ) {
        return 'Fingerprint scanning error. Please place your finger properly and try again.';
    }

    return raw;
}

function startEnrollment() {
    document.getElementById('step1SelectFingers').style.display = 'none';
    document.getElementById('step2EnrollmentProgress').style.display = 'block';
    document.getElementById('step3Complete').style.display = 'none';
    
    updateState.currentFinger = 1;
    updateState.enrolledFingerprints = [];
    updateState.pollFailures = 0;
    updateState.lastServerFinger = 1;
    updateState.registrationId = null;
    appendDebug('Starting fingerprint update for student_id=' + String(updateState.studentId));

    renderScanCircles(updateState.numFingers);
    updateProgress();
    
    checkScannerOnline(false).then((isOnline) => {
        if (!isOnline) {
            showWaitingMessage('Scanner status is offline/uncertain. Trying enrollment command anyway...');
        } else {
            showWaitingMessage('Sending registration command to scanner...');
        }
        appendDebug('Calling register API (start action)');
        return fetch('../api/register.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'start',
                student_id: updateState.studentId,
                total_fingers: updateState.numFingers
            })
        });
    })
    .then(response => response ? response.json() : null)
    .then(data => {
        if (!data) {
            return;
        }

        appendDebug('start_registration response', data);

        if (!data.success) {
            const rawError = data.message || 'Failed to initialize enrollment.';
            const uiError = formatEnrollmentUiError(rawError);
            appendDebug('Enrollment start failed', {
                raw_error: rawError,
                ui_error: uiError,
                response: data
            });
            showError(uiError);
            return;
        }

        const registrationId = parseInt(data.registration_id || 0, 10);
        if (!registrationId) {
            showError('Scanner did not return a valid registration session.');
            return;
        }

        updateState.registrationId = registrationId;
        updateState.lastServerFinger = Math.max(1, parseInt(data.finger_number || 1, 10));
        if (data.total_fingers) {
            updateState.numFingers = Math.max(1, parseInt(data.total_fingers, 10));
        }
        updateState.currentFinger = 1;
        updateState.enrollmentCompleted = false;
        updateState.currentScanStep = 1;
        showWaitingMessage('Command queued. Place your finger on the scanner.');
        updateProgress();
        beginEnrollmentMonitor();
    })
    .catch(error => {
        showError('Connection error: ' + error.message);
    });
}

function beginEnrollmentMonitor() {
    if (updateState.monitorHandle) {
        clearInterval(updateState.monitorHandle);
    }

    if (!updateState.registrationId) {
        showError('Missing registration session. Please retry enrollment.');
        return;
    }

    showWaitingMessage('Waiting for scanner to process enrollment command...');
    appendDebug('Polling get_mode for registration progress');

    updateState.monitorHandle = setInterval(async () => {
        try {
            const response = await fetch(`../api/get_mode.php?registration_id=${encodeURIComponent(String(updateState.registrationId || ''))}`);
            const data = await response.json();

            if (!data.success) {
                updateState.pollFailures += 1;
                if (updateState.pollFailures >= 3) {
                    showError('Unable to read scanner mode. Check server connection and try again.');
                }
                return;
            }

            updateState.pollFailures = 0;

            if (data.total_fingers) {
                updateState.numFingers = Math.max(1, parseInt(data.total_fingers, 10));
            }

            if (data.registration_id) {
                const activeRegistrationId = String(data.registration_id);
                if (activeRegistrationId !== String(updateState.registrationId || '')) {
                    appendDebug('Active registration command changed to id=' + activeRegistrationId);
                    updateState.registrationId = activeRegistrationId;
                }
            }

            if (data.mode === 'registration' && String(data.registration_id || '') === String(updateState.registrationId || '')) {
                const serverFinger = Math.max(1, parseInt(data.finger_number || 1, 10));
                const serverScanStep = parseInt(data.scan_step || 0, 10);
                const totalScanSteps = parseInt(data.total_scan_steps || 3, 10);
                const uiStatus = String(data.ui_status || '').trim().toLowerCase();
                const uiMessage = String(data.ui_message || '').trim();

                if (uiStatus === 'duplicate') {
                    const duplicateMsg = uiMessage || 'Duplicate finger already enrolled. Use another finger.';
                    updateState.duplicateActive = true;
                    updateState.duplicateNoticeUntil = Date.now() + 5000;
                    const statusDiv = document.getElementById('statusMessage');
                    const statusText = document.getElementById('statusText');
                    if (statusDiv) {
                        statusDiv.className = 'alert alert-warning mb-4';
                    }
                    if (statusText) {
                        statusText.textContent = duplicateMsg;
                    }
                    setInlineScanStatus('error', duplicateMsg);

                    const duplicateCircle = document.getElementById(`scan${serverFinger}`);
                    if (duplicateCircle && !duplicateCircle.classList.contains('success')) {
                        duplicateCircle.classList.remove('scanning');
                        duplicateCircle.classList.add('error');
                        duplicateCircle.innerHTML = '<i class="bi bi-exclamation-lg"></i>';
                    }
                } else {
                    updateState.duplicateActive = false;
                    const statusDiv = document.getElementById('statusMessage');
                    if (statusDiv && statusDiv.className.indexOf('alert-warning') !== -1) {
                        statusDiv.className = 'alert alert-info mb-4';
                    }
                }

                const snapshot = `${String(data.mode)}:${serverFinger}:${serverScanStep}:${String(data.last_sensor_id || '')}`;
                if (snapshot !== updateState.lastProgressSnapshot) {
                    updateState.lastProgressSnapshot = snapshot;
                    appendDebug('Registration status changed', {
                        finger_number: serverFinger,
                        total_fingers: updateState.numFingers,
                        scan_step: serverScanStep,
                        total_scan_steps: totalScanSteps,
                        last_sensor_id: data.last_sensor_id || null
                    });
                }

                if (serverFinger > updateState.lastServerFinger) {
                    markFingerCompleted(updateState.lastServerFinger);
                    updateState.lastServerFinger = serverFinger;
                    if (data.last_sensor_id) {
                        showWaitingMessage(`Registered (ID: ${data.last_sensor_id})`);
                    }
                }

                updateState.currentFinger = Math.min(serverFinger, updateState.numFingers);
                if (serverScanStep > 0) {
                    updateState.currentScanStep = serverScanStep;
                    updateState.scanStepsPerFinger = totalScanSteps;
                }
                updateProgress();
                return;
            }

            if (data.mode === 'failed') {
                if (updateState.monitorHandle) {
                    clearInterval(updateState.monitorHandle);
                    updateState.monitorHandle = null;
                }

                const rawError = data.message || 'Enrollment failed on scanner. Please retry enrollment.';
                const uiError = formatEnrollmentUiError(rawError);

                appendDebug('Device reported enrollment failure', {
                    raw_error: rawError,
                    ui_error: uiError,
                    response: data
                });

                showError(uiError);
                return;
            }

            if (data.mode === 'attendance' && updateState.registrationId) {
                if (updateState.lastServerFinger <= updateState.numFingers) {
                    markFingerCompleted(updateState.lastServerFinger);
                }
                if (data.last_sensor_id) {
                    showWaitingMessage(`Registered (ID: ${data.last_sensor_id})`);
                }
                appendDebug('Mode switched to attendance; registration complete path reached');
                showCompletion();
            }
        } catch (e) {
            appendDebug('get_mode poll error: ' + e.message);
            // Keep polling on transient failures.
        }
    }, 2000);
}

function markFingerCompleted(fingerNo) {
    if (fingerNo < 1 || fingerNo > updateState.numFingers) {
        return;
    }

    const circle = document.getElementById(`scan${fingerNo}`);
    if (circle) {
        circle.classList.remove('scanning', 'error');
        circle.classList.add('success');
        circle.innerHTML = '<i class="bi bi-check-lg"></i>';
    }

    if (!updateState.enrolledFingerprints.find(f => f.finger === fingerNo)) {
        updateState.enrolledFingerprints.push({ finger: fingerNo, scans: 1 });
    }

    if (fingerNo >= updateState.numFingers) {
        setInlineScanStatus('success', `Success: finger ${fingerNo} of ${updateState.numFingers} registered.`);
    } else {
        setInlineScanStatus('success', `Success: finger ${fingerNo} saved. Waiting for next scan...`);
    }

    updateOverallProgress();
}

function updateProgress() {
    document.getElementById('currentFingerTitle').textContent = 
        `Finger ${updateState.currentFinger} of ${updateState.numFingers}`;

    const scanStepStatus = document.getElementById('scanStepStatus');
    if (scanStepStatus) {
        scanStepStatus.textContent = `Scanning finger ${updateState.currentFinger} - ${updateState.currentScanStep} of ${updateState.scanStepsPerFinger}`;
    }

    updateScanStepIndicators();

    document.querySelectorAll('.scan-circle').forEach(circle => {
        if (!circle.classList.contains('success')) {
            const isCurrentCircle = Number(circle.dataset.finger) === Number(updateState.currentFinger);
            if (!(updateState.duplicateActive && isCurrentCircle)) {
                circle.classList.remove('scanning', 'error');
                circle.innerHTML = '<i class="bi bi-fingerprint"></i>';
            }
        }
    });
    
    const currentCircle = document.getElementById(`scan${updateState.currentFinger}`);
    if (currentCircle && !currentCircle.classList.contains('success')) {
        if (updateState.duplicateActive) {
            currentCircle.classList.remove('scanning');
            currentCircle.classList.add('error');
            currentCircle.innerHTML = '<i class="bi bi-exclamation-lg"></i>';
        } else {
            currentCircle.classList.add('scanning');
        }
    }

    const statusText = document.getElementById('statusText');
    if (statusText) {
        statusText.textContent = `Scanning finger ${updateState.currentFinger} ${updateState.currentScanStep} of ${updateState.scanStepsPerFinger}`;
    }

    updateOverallProgress();
}

function updateOverallProgress() {
    const progressBar = document.getElementById('enrollProgressBar');
    const progressText = document.getElementById('enrollProgressText');

    if (!progressBar || !progressText) {
        return;
    }

    const completed = updateState.enrolledFingerprints.length;
    const total = Math.max(1, updateState.numFingers || 1);
    const percent = Math.round((completed / total) * 100);

    progressBar.style.width = `${percent}%`;
    progressBar.setAttribute('aria-valuenow', String(percent));
    progressText.textContent = `Progress: ${completed}/${total} fingers (${percent}%)`;
}

async function checkScannerOnline(showStatus = false) {
    const statusDiv = document.getElementById('statusMessage');

    try {
        const response = await fetch('../api/scanner_status.php');
        const data = await response.json();
        const isOnline = !!(response.ok && data.success && data.scanner && data.scanner.online);
        appendDebug('scanner_status online=' + String(isOnline), data);

        if (showStatus && !isOnline) {
            statusDiv.className = 'alert alert-danger mb-4';
            statusDiv.innerHTML = `
                    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-wifi-off me-2"></i>
                            <div>
                                <strong>Scanner Offline</strong><br>
                                <small>${(data.scanner && data.scanner.message) ? data.scanner.message : 'No recent heartbeat from scanner'}</small>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="startEnrollment()">
                            <i class="bi bi-arrow-clockwise"></i> Retry Check
                        </button>
                    </div>
            `;
        }

        return isOnline;
    } catch (error) {
        appendDebug('scanner_status error: ' + error.message);
        if (showStatus) {
            statusDiv.className = 'alert alert-danger mb-4';
            statusDiv.innerHTML = `
                    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-wifi-off me-2"></i>
                            <div>
                                <strong>Scanner Offline</strong><br>
                                <small>Status check failed. Verify scanner and server connection.</small>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="startEnrollment()">
                            <i class="bi bi-arrow-clockwise"></i> Retry Check
                        </button>
                    </div>
            `;
        }

        return false;
    }
}

function showWaitingMessage(message) {
    const statusDiv = document.getElementById('statusMessage');
    const statusText = document.getElementById('statusText');

    if (statusText) {
        statusText.textContent = message || 'Waiting for scan...';
    }

    if (statusDiv) {
        statusDiv.className = 'alert alert-info mb-4';
    }

    if (!updateState.duplicateActive) {
        setInlineScanStatus('waiting', message || 'Waiting for next scan...');
    }
}

function showError(message) {
    const statusDiv = document.getElementById('statusMessage');
    statusDiv.className = 'alert alert-danger mb-4';
    statusDiv.innerHTML = `
        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
            <div>
                <i class="bi bi-exclamation-triangle me-2"></i>${message}
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="handleRetryFromError(this)">
                <i class="bi bi-arrow-clockwise me-1"></i>Retry
            </button>
        </div>
    `;
    appendDebug('ERROR: ' + message);
    setInlineScanStatus('error', `Error: ${message}. Try again.`);
}

function handleRetryFromError(retryBtn) {
    if (retryBtn) {
        retryBtn.disabled = true;
        retryBtn.style.display = 'none';
    }

    startEnrollment();
}

function resetModal() {
    if (updateState.monitorHandle) {
        clearInterval(updateState.monitorHandle);
    }
    if (updateState.statusAutoHideTimer) {
        clearTimeout(updateState.statusAutoHideTimer);
        updateState.statusAutoHideTimer = null;
    }

    document.getElementById('step1SelectFingers').style.display = 'none';
    document.getElementById('step2EnrollmentProgress').style.display = 'none';
    document.getElementById('step3Complete').style.display = 'none';

    renderScanCircles(1);

    updateState.numFingers = 1;
    updateState.currentFinger = 1;
    updateState.enrolledFingerprints = [];
    updateState.fingerprintsChanged = false;
    updateState.sessionId = null;
    updateState.registrationId = null;
    updateState.monitorHandle = null;
    updateState.lastServerFinger = 1;
    updateState.enrollmentCompleted = false;
    updateState.pollFailures = 0;
    updateState.currentScanStep = 1;
    updateState.lastProgressSnapshot = '';
    updateState.duplicateNoticeUntil = 0;
    updateState.duplicateActive = false;
    updateState.debugLines = [];

    updateOverallProgress();
    updateScanStepIndicators();

    setInlineScanStatus('waiting', 'Waiting for next scan...');
}

function appendDebug(message, data) {
    // Debug panel intentionally removed from UI.
    return;
}

function validateFormSubmission(e) {
    // Fingerprint updates are optional; show reminder only.
    if (updateState.fingerprintsChanged && !updateState.enrollmentCompleted) {
        document.getElementById('fingerprintWarning').style.display = 'block';
        document.getElementById('fingerprintStatus').style.display = 'none';

        // Keep visual cue but do not block save.
        const fingerprintBtn = document.getElementById('updateFingerprintsBtn');
        fingerprintBtn.classList.add('btn-warning');
        fingerprintBtn.classList.remove('btn-primary');
        
        setTimeout(() => {
            fingerprintBtn.classList.remove('btn-warning');
            fingerprintBtn.classList.add('btn-primary');
        }, 3000);
    }

    return true;
}

function checkIfFingerprintUpdateNeeded() {
    // Mark that form data has changed, might need fingerprint update
    updateState.fingerprintsChanged = true;
    updateState.enrollmentCompleted = false;
    
    // Hide any existing status messages
    document.getElementById('fingerprintStatus').style.display = 'none';
    document.getElementById('fingerprintWarning').style.display = 'none';
}

function showCompletion() {
    if (updateState.monitorHandle) {
        clearInterval(updateState.monitorHandle);
        updateState.monitorHandle = null;
    }

    document.getElementById('step2EnrollmentProgress').style.display = 'none';
    document.getElementById('step3Complete').style.display = 'block';
    document.getElementById('completionMessage').textContent =
        `Updated ${updateState.numFingers} fingerprint(s) for ${updateState.studentName}`;

    // Mark fingerprints as successfully updated
    updateState.fingerprintsChanged = false;
    updateState.enrollmentCompleted = true;

    // Show success status on form
    document.getElementById('fingerprintStatus').style.display = 'block';
    document.getElementById('fingerprintWarning').style.display = 'none';
    document.getElementById('fingerprintStatusText').textContent =
        `${updateState.numFingers} fingerprint(s) updated successfully`;

    // Update button state
    const fingerprintBtn = document.getElementById('updateFingerprintsBtn');
    fingerprintBtn.innerHTML = '<i class="bi bi-check-circle"></i> Fingerprints Updated';
    fingerprintBtn.classList.add('btn-success');
    fingerprintBtn.classList.remove('btn-primary');
}
</script>

<?php require '../includes/footer.php'; /*
 * � 2026 TambyTech.
 * This source code is proprietary and confidential.
 * Any unauthorized use, copying, modification, distribution, or disclosure is strictly prohibited.
 * All rights reserved.
 */
?>