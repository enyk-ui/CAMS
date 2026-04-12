<?php
/**
 * Student Registration Page
 * Register new students and enroll fingerprints on the same page.
 */

session_start();
require_once '../config/db.php';
require_once '../helpers/SchoolYearHelper.php';
require '../includes/header.php';

$years = [1, 2, 3, 4];

SchoolYearHelper::ensureSchoolYearSupport($mysqli);
$activeSchoolYear = SchoolYearHelper::getEffectiveSchoolYearRange($mysqli);
$activeSchoolYearLabel = (string)($activeSchoolYear['label'] ?? '');

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

ensureUsersAssignmentColumns($mysqli);
$teacherSectionMap = buildTeacherSectionMap($mysqli, $activeSchoolYearLabel);
?>

<div class="container">
    <div class="row">
        <div class="col-xl-10 offset-xl-1 col-lg-11 offset-lg-0 col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-person-plus"></i> Register Student</h5>
                    <a href="students.php" class="btn btn-sm btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Students
                    </a>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-mortarboard"></i>
                        Active School Year: <strong><?php echo htmlspecialchars($activeSchoolYearLabel !== '' ? $activeSchoolYearLabel : 'N/A'); ?></strong>
                    </div>
                    <div id="alertContainer"></div>
                    <div id="formValidationMessage" class="alert alert-warning" style="display:none;">
                        <i class="bi bi-exclamation-triangle"></i>
                        <span id="validationText"></span>
                    </div>

                    <form id="registrationForm" novalidate>
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label for="firstName" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="firstName" required>
                            </div>
                            <div class="col-md-2">
                                <label for="middleInitial" class="form-label">M.I.</label>
                                <input type="text" maxlength="1" class="form-control" id="middleInitial">
                            </div>
                            <div class="col-md-5">
                                <label for="lastName" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="lastName" required>
                            </div>
                            <div class="col-lg-2 col-md-3">
                                <label for="extension" class="form-label">Ext.</label>
                                <select class="form-select" id="extension">
                                    <option value="">Select ext (optional)</option>
                                    <option value="Jr">Jr</option>
                                    <option value="Sr">Sr</option>
                                    <option value="II">II</option>
                                    <option value="III">III</option>
                                    <option value="IV">IV</option>
                                </select>
                            </div>

                            <div class="col-lg-4 col-md-4">
                                <label for="year" class="form-label">Year</label>
                                <select class="form-select" id="year" required>
                                    <option value="">Select year</option>
                                    <?php foreach ($years as $year): ?>
                                        <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-lg-6 col-md-5">
                                <label for="section" class="form-label">Section</label>
                                <select class="form-select" id="section" required>
                                    <option value="">Select year first</option>
                                </select>
                                <div class="form-text">Sections are loaded from teacher assignments for the selected year level.</div>
                            </div>
                        </div>

                        <hr>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary" id="registerStudentBtn">
                                <i class="bi bi-save"></i> Register Fingerprints
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="fingerprintModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-fingerprint"></i> Enroll Fingerprints</h5>
                <span id="modalModeBadge" class="badge bg-success me-2">Mode: Attendance</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="enrollment-header mb-3">
                    <h5 id="studentName" class="mb-1"></h5>
                    <p class="text-muted mb-0">Single-fingerprint enrollment is enforced. Place your finger on the scanner.</p>
                </div>

                <div id="step1SelectFingers">
                    <div class="alert alert-info mb-0">
                        Enrollment starts automatically.
                    </div>
                </div>

                <div id="step2EnrollmentProgress" style="display:none;">
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
                        <button type="button" class="btn btn-outline-danger" id="btnRetryFinger" style="display:none;">
                            <i class="bi bi-arrow-clockwise"></i> Retry Check
                        </button>
                    </div>
                </div>

                <div id="step3Complete" style="display:none;">
                    <div class="text-center py-4">
                        <i class="bi bi-check-circle-fill text-success" style="font-size:4rem;"></i>
                        <h4 class="text-success mt-3">Fingerprint Enrollment Complete</h4>
                        <p class="text-muted" id="completionMessage">Fingerprints enrolled successfully.</p>
                        <div class="alert alert-warning text-start mt-3" id="savePromptBox">
                            <strong>Save student now?</strong><br>
                            <small>If you retry, the enrolled fingerprints for this session will be removed from scanner and enrollment restarts.</small>
                        </div>
                        <div class="d-flex justify-content-center gap-2 mt-3 flex-wrap">
                            <button type="button" class="btn btn-outline-danger" id="btnRetryEnrollment">
                                <i class="bi bi-arrow-clockwise"></i> Retry Enrollment
                            </button>
                            <button type="button" class="btn btn-primary" id="btnSaveStudent">
                                <i class="bi bi-check2-circle"></i> Save Student
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="postSaveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-check-circle"></i> Registration Complete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Student registration completed with fingerprint enrollment.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="btnAddAnotherStudent">Add Another</button>
                <button type="button" class="btn btn-primary" id="btnDoneToStudents">Done</button>
            </div>
        </div>
    </div>
</div>

<script>
const teacherSectionMap = <?php echo json_encode($teacherSectionMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

let updateState = {
    modal: null,
    postSaveModal: null,
    studentId: null,
    studentName: '',
    numFingers: 1,
    currentFinger: 1,
    enrolledFingerprints: [],
    registrationId: null,
    monitorHandle: null,
    lastServerFinger: 1,
    enrollmentCompleted: false,
    studentCreatedInSession: false,
    rollbackInProgress: false,
    debugLines: [],
    pollFailures: 0,
    pendingStudentData: null,
    studentFinalized: false,
    isSavingStudent: false,
    isStartingEnrollment: false,
    startRequestedAt: 0,
    scanStepsPerFinger: 3,
    currentScanStep: 1,
    lastProgressSnapshot: '',
    duplicateNoticeUntil: 0,
    duplicateActive: false,
    statusAutoHideTimer: null
};

function setFingerButtonsDisabled(disabled) {
    document.querySelectorAll('.finger-btn').forEach(btn => {
        btn.disabled = !!disabled;
    });
}

document.addEventListener('DOMContentLoaded', function () {
    updateState.modal = new bootstrap.Modal(document.getElementById('fingerprintModal'));
    updateState.postSaveModal = new bootstrap.Modal(document.getElementById('postSaveModal'));
    updateModeIndicator('attendance');
    renderScanCircles(1);

    const form = document.getElementById('registrationForm');
    if (form) {
        form.addEventListener('submit', submitRegistration);
    }

    const yearSelect = document.getElementById('year');
    const sectionSelect = document.getElementById('section');
    if (yearSelect && sectionSelect) {
        yearSelect.addEventListener('change', function () {
            populateSectionOptions(this.value, '');
        });
        populateSectionOptions(yearSelect.value || '', sectionSelect.value || '');
    }

    document.getElementById('btnCancelEnrollment').addEventListener('click', function () {
        setScannerMode('attendance');
        updateModeIndicator('attendance');
        resetModal();
        updateState.modal.hide();
    });

    const saveBtn = document.getElementById('btnSaveStudent');
    if (saveBtn) {
        saveBtn.addEventListener('click', saveStudentRecord);
    }

    const retryEnrollmentBtn = document.getElementById('btnRetryEnrollment');
    if (retryEnrollmentBtn) {
        retryEnrollmentBtn.addEventListener('click', retryEnrollment);
    }

    const doneBtn = document.getElementById('btnDoneToStudents');
    if (doneBtn) {
        doneBtn.addEventListener('click', function () {
            window.location.href = 'students.php';
        });
    }

    const addAnotherBtn = document.getElementById('btnAddAnotherStudent');
    if (addAnotherBtn) {
        addAnotherBtn.addEventListener('click', function () {
            window.location.reload();
        });
    }

    document.getElementById('fingerprintModal').addEventListener('shown.bs.modal', function () {
        setScannerMode('registration');
        updateModeIndicator('registration');

        if (updateState.studentId && !updateState.registrationId && !updateState.enrollmentCompleted && !updateState.isStartingEnrollment) {
            setTimeout(() => startEnrollment(), 150);
        }
    });

    document.getElementById('fingerprintModal').addEventListener('hidden.bs.modal', function () {
        setScannerMode('attendance');
        updateModeIndicator('attendance');

        if (updateState.studentCreatedInSession && !updateState.studentFinalized && updateState.studentId && !updateState.rollbackInProgress) {
            rollbackIncompleteRegistration();
        }

        if (updateState.enrollmentCompleted) {
            if (updateState.studentFinalized) {
                updateState.studentCreatedInSession = false;
                updateState.studentId = null;
                updateState.studentName = '';
                updateState.pendingStudentData = null;
            }
        }

        resetModal();
    });

    window.addEventListener('beforeunload', function () {
        if (!updateState.studentCreatedInSession || updateState.enrollmentCompleted || !updateState.studentId || updateState.rollbackInProgress) {
            return;
        }

        try {
            const payload = JSON.stringify({
                action: 'rollback',
                student_id: updateState.studentId
            });
            const blob = new Blob([payload], { type: 'application/json' });
            navigator.sendBeacon('../api/register.php', blob);
        } catch (e) {
            // Best-effort cleanup only.
        }
    });
});

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
        sectionSelect.value = selectedSection;
    }
}

function submitRegistration(e) {
    e.preventDefault();
    document.getElementById('formValidationMessage').style.display = 'none';

    const requiredFields = [
        { id: 'firstName', name: 'First Name' },
        { id: 'lastName', name: 'Last Name' },
        { id: 'year', name: 'Year' },
        { id: 'section', name: 'Section' }
    ];

    let missingFields = [];
    for (let field of requiredFields) {
        const element = document.getElementById(field.id);
        if (!element || !element.value.trim()) {
            missingFields.push(field.name);
        }
    }

    if (missingFields.length > 0) {
        showValidationError('Please fill in: ' + missingFields.join(', '));
        return;
    }

    const studentData = {
        first_name: document.getElementById('firstName').value.trim(),
        middle_initial: document.getElementById('middleInitial').value.trim() || '',
        last_name: document.getElementById('lastName').value.trim(),
        extension: document.getElementById('extension').value.trim() || '',
        year: document.getElementById('year').value,
        section: document.getElementById('section').value.trim()
    };

    showAlert('info', 'Registering student...');

    const submitBtn = document.getElementById('registerStudentBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Preparing...';

    registerStudent(studentData)
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Registration failed');
            }

            updateState.studentId = data.student_id;
            updateState.studentName = formatNameDisplay(studentData);
            updateState.pendingStudentData = studentData;
            updateState.studentCreatedInSession = true;
            updateState.enrollmentCompleted = false;
            updateState.studentFinalized = false;
            document.getElementById('studentName').textContent = updateState.studentName;

            showAlert('warning', 'Temporary enrollment session started. Student info will be saved only after fingerprint enrollment completes.');
            updateState.modal.show();
        })
        .catch(error => {
            showAlert('danger', 'Error: ' + error.message);
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-save"></i> Register Fingerprints';
        });
}

function registerStudent(studentData) {
    const payload = {
        ...studentData,
        defer_save: true
    };

    return fetch('../api/register.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    }).then(response => response.json());
}

function finalizeStudentRegistration() {
    if (!updateState.pendingStudentData || !updateState.studentId) {
        return Promise.reject(new Error('Missing temporary registration data'));
    }

    const payload = {
        student_id: updateState.studentId,
        first_name: updateState.pendingStudentData.first_name,
        middle_initial: updateState.pendingStudentData.middle_initial || '',
        last_name: updateState.pendingStudentData.last_name,
        extension: updateState.pendingStudentData.extension || '',
        year: updateState.pendingStudentData.year || null,
        section: updateState.pendingStudentData.section || '',
        finalize: true
    };

    return fetch('../api/register.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    }).then(response => response.json());
}

function formatNameDisplay(data) {
    const first = String(data.first_name || '').trim();
    const middle = String(data.middle_initial || '').trim();
    const last = String(data.last_name || '').trim();
    const ext = String(data.extension || '').trim();

    let name = last;
    if (first) {
        name += (name ? ', ' : '') + first;
    }
    if (middle) {
        name += ' ' + middle.charAt(0).toUpperCase() + '.';
    }
    if (ext) {
        name += ' ' + ext;
    }

    return name.trim();
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
        badge.className = 'badge bg-warning text-dark me-2';
        badge.textContent = 'Mode: Registration (Waiting Finger)';
        return;
    }

    badge.className = 'badge bg-success me-2';
    badge.textContent = 'Mode: Attendance';
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

    // Hide low-level scanner internals like getImage/image2Tz/code values from end users.
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

async function startEnrollment() {
    const now = Date.now();
    if (updateState.isStartingEnrollment) {
        appendDebug('Start enrollment ignored: request already in progress');
        return;
    }

    // Debounce accidental double-clicks/taps.
    if (updateState.startRequestedAt > 0 && (now - updateState.startRequestedAt) < 1200) {
        appendDebug('Start enrollment ignored: duplicate trigger');
        return;
    }

    // Do not create another ENROLL session while current one is active.
    if (updateState.registrationId && !updateState.enrollmentCompleted) {
        appendDebug('Start enrollment ignored: active registration already exists', {
            registration_id: updateState.registrationId
        });
        return;
    }

    if (!updateState.studentId) {
        showError('Student registration was not created.');
        return;
    }

    if (!updateState.numFingers) {
        updateState.numFingers = 1;
    }

    document.getElementById('step1SelectFingers').style.display = 'none';
    document.getElementById('step2EnrollmentProgress').style.display = 'block';
    document.getElementById('step3Complete').style.display = 'none';

    updateState.currentFinger = 1;
    updateState.enrolledFingerprints = [];
    updateState.enrollmentCompleted = false;
    updateState.pollFailures = 0;
    updateState.isStartingEnrollment = true;
    updateState.startRequestedAt = now;
    setFingerButtonsDisabled(true);
    appendDebug('Starting enrollment for student_id=' + String(updateState.studentId));

    renderScanCircles(updateState.numFingers);

    updateProgress();

    showWaitingMessage('Sending registration command to scanner...');
    appendDebug('Calling register API (start action)');

    fetch('../api/register.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'start',
            student_id: updateState.studentId,
            total_fingers: updateState.numFingers
        })
    })
    .then(response => response.json())
    .then(data => {
        appendDebug('start_registration response', data);
        if (!data.success) {
            throw new Error(data.message || 'Failed to start registration');
        }

        updateState.registrationId = data.registration_id;
        updateState.lastServerFinger = Math.max(1, parseInt(data.finger_number || 1, 10));
        if (data.total_fingers) {
            updateState.numFingers = Math.max(1, parseInt(data.total_fingers, 10));
        }
        showWaitingMessage('Command queued. Place your finger on the scanner.');
        setInlineScanStatus('waiting', 'Waiting for finger 1 scan...');
        updateState.isStartingEnrollment = false;
        beginEnrollmentMonitor();
    })
    .catch(error => {
        updateState.isStartingEnrollment = false;
        showError('Device offline or API unreachable: ' + error.message);
    });
}

function handleRetryAction(retryBtn) {
    if (retryBtn) {
        retryBtn.disabled = true;
        retryBtn.style.display = 'none';
    }

    if (updateState.isStartingEnrollment) {
        return;
    }

    if (updateState.registrationId) {
        retryEnrollment();
        return;
    }

    startEnrollment();
}

function beginEnrollmentMonitor() {
    if (updateState.monitorHandle) {
        clearInterval(updateState.monitorHandle);
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
                    showError('Device offline or not responding. Check scanner/ESP power and network, then retry.');
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

            if (data.mode === 'registration') {
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
                // Update scan step from server (only if > 0, meaning device reported progress)
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
                    ...data,
                    raw_error: rawError,
                    ui_error: uiError
                });

                showError(uiError);
                showAlert('danger', uiError);
                return;
            }

            if (data.mode === 'attendance' && updateState.registrationId) {
                if (updateState.currentScanStep < updateState.scanStepsPerFinger) {
                    updateState.currentScanStep = updateState.scanStepsPerFinger;
                    updateProgress();
                }

                const finalFinger = Math.min(updateState.lastServerFinger, updateState.numFingers);
                if (finalFinger >= 1 && finalFinger <= updateState.numFingers) {
                    markFingerCompleted(finalFinger);
                }
                if (data.last_sensor_id) {
                    showWaitingMessage(`Registered (ID: ${data.last_sensor_id})`);
                }
                appendDebug('Mode switched to attendance; registration complete path reached');
                showAlert('success', 'Fingerprint enrollment finished. Review and save student record.');
                showCompletion();
            }
        } catch (error) {
            appendDebug('get_mode poll error: ' + error.message);
            // Keep polling on transient failures.
        }
    }, 700);
}

function markFingerCompleted(fingerNo) {
    if (fingerNo < 1 || fingerNo > updateState.numFingers) {
        return;
    }

    if (!updateState.enrolledFingerprints.find(f => f.finger === fingerNo)) {
        updateState.enrolledFingerprints.push({ finger: fingerNo, scans: 1 });
    }

    const circle = document.getElementById(`scan${fingerNo}`);
    if (circle) {
        circle.classList.remove('scanning', 'error');
        circle.classList.add('success');
        circle.innerHTML = '<i class="bi bi-check-lg"></i>';
    }

    const statusText = document.getElementById('statusText');
    if (statusText) {
        if (fingerNo >= updateState.numFingers) {
            statusText.textContent = `Finger ${fingerNo} of ${updateState.numFingers} registered`;
            setInlineScanStatus('success', `Success: finger ${fingerNo} of ${updateState.numFingers} registered.`);
        } else {
            statusText.textContent = `Finger ${fingerNo} registered. Place finger ${fingerNo + 1} of ${updateState.numFingers}.`;
            setInlineScanStatus('success', `Success: finger ${fingerNo} saved. Waiting for finger ${fingerNo + 1} scan...`);
        }
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

    if (!updateState.duplicateActive) {
        setInlineScanStatus('waiting', `Waiting: scan finger ${updateState.currentFinger} (${updateState.currentScanStep} of ${updateState.scanStepsPerFinger})...`);
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
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="handleRetryAction(this)">
                <i class="bi bi-arrow-clockwise me-1"></i>Retry
            </button>
        </div>
    `;
    appendDebug('ERROR: ' + message);
    setInlineScanStatus('error', `Error: ${message}. Try again.`);
}

async function showCompletion() {
    if (updateState.monitorHandle) {
        clearInterval(updateState.monitorHandle);
        updateState.monitorHandle = null;
    }

    document.getElementById('step2EnrollmentProgress').style.display = 'none';
    document.getElementById('step3Complete').style.display = 'block';
    document.getElementById('completionMessage').textContent =
        `${updateState.numFingers} fingerprint(s) enrolled successfully for ${updateState.studentName}. Confirm to save student data.`;

    updateState.enrollmentCompleted = true;
    updateState.studentFinalized = false;
    appendDebug('Enrollment marked complete');
}

async function saveStudentRecord() {
    if (updateState.isSavingStudent || updateState.studentFinalized) {
        return;
    }

    updateState.isSavingStudent = true;
    const saveBtn = document.getElementById('btnSaveStudent');
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Saving...';
    }

    try {
        const finalizeResult = await finalizeStudentRegistration();
        appendDebug('finalize_registration response', finalizeResult);
        if (!finalizeResult.success) {
            throw new Error(finalizeResult.message || 'Failed to finalize student record');
        }

        updateState.studentFinalized = true;
        updateState.studentCreatedInSession = false;
        updateState.modal.hide();
        if (updateState.postSaveModal) {
            updateState.postSaveModal.show();
        }
    } catch (error) {
        showAlert('danger', 'Failed to save student data: ' + error.message);
        appendDebug('Save student failed: ' + error.message);
    } finally {
        updateState.isSavingStudent = false;
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="bi bi-check2-circle"></i> Save Student';
        }
    }
}

async function retryEnrollment() {
    if (!updateState.studentId) {
        showAlert('danger', 'No registration session found to retry.');
        return;
    }

    try {
        const response = await fetch('../api/register.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'retry',
                student_id: updateState.studentId,
                total_fingers: updateState.numFingers
            })
        });
        const data = await response.json();
        appendDebug('retry_registration response', data);

        if (!data.success) {
            throw new Error(data.message || 'Retry failed');
        }

        document.getElementById('step3Complete').style.display = 'none';
        document.getElementById('step2EnrollmentProgress').style.display = 'block';

        updateState.currentFinger = 1;
        updateState.lastServerFinger = 1;
        updateState.enrolledFingerprints = [];
        updateState.enrollmentCompleted = false;
        updateState.studentFinalized = false;
        updateState.currentScanStep = 1;
        updateState.lastProgressSnapshot = '';
        updateState.isStartingEnrollment = false;
        updateState.startRequestedAt = 0;

        renderScanCircles(updateState.numFingers);
        updateProgress();
        showWaitingMessage('Retry queued. Scanner will clear previous fingerprints and start scanning again.');
        beginEnrollmentMonitor();
    } catch (error) {
        showAlert('danger', 'Retry failed: ' + error.message);
    }
}

function rollbackIncompleteRegistration() {
    updateState.rollbackInProgress = true;

    fetch('../api/register.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'rollback',
            student_id: updateState.studentId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('warning', 'Enrollment session canceled. Student form values are kept; restart fingerprint enrollment when ready.');
            updateState.studentCreatedInSession = false;
            updateState.studentId = null;
            updateState.studentName = '';
            updateState.pendingStudentData = null;
            updateState.studentFinalized = false;
        } else {
            showAlert('danger', data.message || 'Failed to rollback incomplete registration');
        }
    })
    .catch(error => {
        showAlert('danger', 'Rollback error: ' + error.message);
    })
    .finally(() => {
        updateState.rollbackInProgress = false;
    });
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
    updateState.registrationId = null;
    updateState.monitorHandle = null;
    updateState.lastServerFinger = 1;
    updateState.enrollmentCompleted = false;
    updateState.debugLines = [];
    updateState.pollFailures = 0;
    updateState.pendingStudentData = null;
    updateState.studentFinalized = false;
    updateState.isSavingStudent = false;
    updateState.isStartingEnrollment = false;
    updateState.startRequestedAt = 0;
    updateState.currentScanStep = 1;
    updateState.lastProgressSnapshot = '';
    updateState.duplicateNoticeUntil = 0;
    updateState.duplicateActive = false;
    setInlineScanStatus('waiting', 'Waiting for next scan...');

    updateOverallProgress();
    updateScanStepIndicators();

}

function appendDebug(message, data) {
    // Debug panel intentionally removed from UI.
    return;
}

function showValidationError(message) {
    const validationDiv = document.getElementById('formValidationMessage');
    const validationText = document.getElementById('validationText');
    validationText.textContent = message;
    validationDiv.style.display = 'block';

    setTimeout(() => {
        validationDiv.style.display = 'none';
    }, 5000);
}

function showAlert(type, message) {
    const alertDiv = document.getElementById('alertContainer');
    alertDiv.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
}
</script>

<style>
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

<style>
.fingerprint-selector {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 10px;
}

.fingerprint-btn {
    border: 2px solid #d1d5db;
    border-radius: 12px;
    background: #fff;
    padding: 14px 0;
    font-weight: 700;
    transition: all 0.2s ease;
}

.fingerprint-btn.selected {
    background: #2563eb;
    color: #fff;
    border-color: #2563eb;
}

.scan-circle {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    border: 3px solid #d1d5db;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    color: #9ca3af;
}

.scan-circle.scanning {
    border-color: #2563eb;
    color: #2563eb;
    background: #eff6ff;
}

.scan-circle.success {
    border-color: #10b981;
    color: #10b981;
    background: #ecfdf5;
}

.scan-circle.error {
    border-color: #ef4444;
    color: #ef4444;
    background: #fef2f2;
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.15);
    animation: duplicatePulse 0.7s infinite alternate;
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
</style>

<?php require '../includes/footer.php'; ?>
