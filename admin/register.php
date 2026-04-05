<?php
/**
 * Student Registration Page
 * Register new students and enroll fingerprints on the same page.
 */

session_start();
require_once '../config/db.php';
require '../includes/header.php';

$years = [1, 2, 3, 4];
?>

<div class="container">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-person-plus"></i> Register Student</h5>
                    <a href="students.php" class="btn btn-sm btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Students
                    </a>
                </div>
                <div class="card-body">
                    <div id="alertContainer"></div>
                    <div id="formValidationMessage" class="alert alert-warning" style="display:none;">
                        <i class="bi bi-exclamation-triangle"></i>
                        <span id="validationText"></span>
                    </div>

                    <form id="registrationForm" novalidate>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="studentId" class="form-label">Student ID</label>
                                <input type="text" class="form-control" id="studentId" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" required>
                            </div>

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

                            <div class="col-md-6">
                                <label for="year" class="form-label">Year</label>
                                <select class="form-select" id="year" required>
                                    <option value="">Select year</option>
                                    <?php foreach ($years as $year): ?>
                                        <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="section" class="form-label">Section</label>
                                <input type="text" class="form-control" id="section" required>
                            </div>
                        </div>

                        <hr>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary" id="registerStudentBtn">
                                <i class="bi bi-save"></i> Continue Fingerprint Registration
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
                    <p class="text-muted mb-0">Choose how many fingers to register, then place the finger on the scanner.</p>
                </div>

                <div id="step1SelectFingers">
                    <div class="fingerprint-selector mb-4">
                        <button type="button" class="fingerprint-btn finger-btn" data-fingers="1">1</button>
                        <button type="button" class="fingerprint-btn finger-btn" data-fingers="2">2</button>
                        <button type="button" class="fingerprint-btn finger-btn" data-fingers="3">3</button>
                        <button type="button" class="fingerprint-btn finger-btn" data-fingers="4">4</button>
                        <button type="button" class="fingerprint-btn finger-btn" data-fingers="5">5</button>
                    </div>
                    <div class="alert alert-info mb-0">
                        Select the number of fingers to enroll.
                    </div>
                </div>

                <div id="step2EnrollmentProgress" style="display:none;">
                    <div class="text-center mb-4">
                        <h4 id="currentFingerTitle">Enrolling Finger 1 of 1</h4>
                        <p class="text-muted mb-0">Place your finger on the scanner and wait for confirmation.</p>
                    </div>

                    <div class="d-flex justify-content-center gap-3 flex-wrap mb-4">
                        <div class="scan-circle" id="scan1"><i class="bi bi-fingerprint"></i></div>
                        <div class="scan-circle" id="scan2"><i class="bi bi-fingerprint"></i></div>
                        <div class="scan-circle" id="scan3"><i class="bi bi-fingerprint"></i></div>
                        <div class="scan-circle" id="scan4"><i class="bi bi-fingerprint"></i></div>
                        <div class="scan-circle" id="scan5"><i class="bi bi-fingerprint"></i></div>
                    </div>

                    <div class="alert alert-info mb-4" id="statusMessage">
                        <div class="d-flex align-items-center">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                            <div>
                                <strong>Waiting for scanner</strong><br>
                                <small id="statusText">Initializing enrollment...</small>
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
                        <h4 class="text-success mt-3">Enrollment Complete</h4>
                        <p class="text-muted" id="completionMessage">Fingerprints enrolled successfully.</p>
                        <button type="button" class="btn btn-primary mt-3" data-bs-dismiss="modal">Done</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let updateState = {
    modal: null,
    studentId: null,
    studentName: '',
    numFingers: 0,
    currentFinger: 1,
    currentScan: 1,
    enrolledFingerprints: [],
    registrationId: null,
    monitorHandle: null,
    lastServerFinger: 1,
    enrollmentCompleted: false,
    studentCreatedInSession: false,
    rollbackInProgress: false
};

document.addEventListener('DOMContentLoaded', function () {
    updateState.modal = new bootstrap.Modal(document.getElementById('fingerprintModal'));
    updateModeIndicator('attendance');

    const form = document.getElementById('registrationForm');
    if (form) {
        form.addEventListener('submit', submitRegistration);
    }

    document.querySelectorAll('.finger-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.finger-btn').forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            updateState.numFingers = parseInt(this.dataset.fingers, 10);
            setTimeout(() => startEnrollment(), 250);
        });
    });

    document.getElementById('btnCancelEnrollment').addEventListener('click', function () {
        setScannerMode('attendance');
        updateModeIndicator('attendance');
        resetModal();
        updateState.modal.hide();
    });

    document.getElementById('fingerprintModal').addEventListener('shown.bs.modal', function () {
        setScannerMode('registration');
        updateModeIndicator('registration');
    });

    document.getElementById('fingerprintModal').addEventListener('hidden.bs.modal', function () {
        setScannerMode('attendance');
        updateModeIndicator('attendance');

        if (updateState.studentCreatedInSession && !updateState.enrollmentCompleted && updateState.studentId && !updateState.rollbackInProgress) {
            rollbackIncompleteRegistration();
        }

        if (updateState.enrollmentCompleted) {
            showAlert('success', 'Student registration completed with fingerprint enrollment.');
            document.getElementById('registrationForm').reset();
            updateState.studentCreatedInSession = false;
            updateState.studentId = null;
            updateState.studentName = '';
        }

        resetModal();
    });
});

function submitRegistration(e) {
    e.preventDefault();
    document.getElementById('formValidationMessage').style.display = 'none';

    const requiredFields = [
        { id: 'studentId', name: 'Student ID' },
        { id: 'firstName', name: 'First Name' },
        { id: 'lastName', name: 'Last Name' },
        { id: 'email', name: 'Email' },
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
        student_id: document.getElementById('studentId').value.trim(),
        first_name: document.getElementById('firstName').value.trim(),
        middle_initial: document.getElementById('middleInitial').value.trim() || '',
        last_name: document.getElementById('lastName').value.trim(),
        email: document.getElementById('email').value.trim(),
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
            updateState.studentName = studentData.first_name + ' ' + studentData.last_name;
            updateState.studentCreatedInSession = true;
            updateState.enrollmentCompleted = false;
            document.getElementById('studentName').textContent = updateState.studentName;

            showAlert('warning', 'Student record is temporary until fingerprint enrollment is completed.');
            updateState.modal.show();
        })
        .catch(error => {
            showAlert('danger', 'Error: ' + error.message);
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-save"></i> Continue Fingerprint Registration';
        });
}

function registerStudent(studentData) {
    return fetch('../api/register.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(studentData)
    }).then(response => response.json());
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
        badge.textContent = 'Mode: Registration (Busy)';
        return;
    }

    badge.className = 'badge bg-success me-2';
    badge.textContent = 'Mode: Attendance';
}

async function startEnrollment() {
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
    updateState.currentScan = 1;
    updateState.enrolledFingerprints = [];
    updateState.enrollmentCompleted = false;

    updateProgress();

    const isOnline = await checkScannerOnline(true);
    if (!isOnline) {
        showWaitingMessage('Scanner heartbeat is stale. Starting registration mode anyway...');
    }

    showWaitingMessage('Starting registration mode...');

    fetch('../api/start_registration.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            student_id: updateState.studentId,
            total_fingers: updateState.numFingers
        })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.message || 'Failed to start registration');
        }

        updateState.registrationId = data.registration_id;
        updateState.lastServerFinger = 1;
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

    showWaitingMessage('Waiting for scan...');

    updateState.monitorHandle = setInterval(async () => {
        try {
            const response = await fetch('../api/get_mode.php');
            const data = await response.json();

            if (!data.success) {
                return;
            }

            if (data.mode === 'registration' && String(data.registration_id || '') === String(updateState.registrationId || '')) {
                const serverFinger = Math.max(1, parseInt(data.finger_number || 1, 10));
                if (serverFinger > updateState.lastServerFinger) {
                    markFingerCompleted(updateState.lastServerFinger);
                    updateState.lastServerFinger = serverFinger;
                }

                updateState.currentFinger = Math.min(serverFinger, updateState.numFingers);
                updateProgress();
                showWaitingMessage();
                return;
            }

            if (data.mode === 'attendance' && updateState.registrationId) {
                showCompletion();
            }
        } catch (error) {
            // Keep polling on transient failures.
        }
    }, 2000);
}

function markFingerCompleted(fingerNo) {
    if (!updateState.enrolledFingerprints.find(f => f.finger === fingerNo)) {
        updateState.enrolledFingerprints.push({ finger: fingerNo, scans: 1 });
    }

    document.querySelectorAll('.scan-circle').forEach(circle => {
        circle.classList.remove('scanning', 'error');
        circle.classList.add('success');
    });
}

function updateProgress() {
    document.getElementById('currentFingerTitle').textContent =
        `Enrolling Finger ${updateState.currentFinger} of ${updateState.numFingers}`;

    document.querySelectorAll('.scan-circle').forEach(circle => {
        circle.classList.remove('scanning', 'success', 'error');
    });

    const currentCircle = document.getElementById(`scan${updateState.currentScan}`);
    if (currentCircle) {
        currentCircle.classList.add('scanning');
    }
}

async function checkScannerOnline(showStatus = false) {
    const statusDiv = document.getElementById('statusMessage');

    try {
        const response = await fetch('../api/scanner_status.php');
        const data = await response.json();
        const isOnline = !!(response.ok && data.success && data.scanner && data.scanner.online);

        if (showStatus && !isOnline) {
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
                        <i class="bi bi-arrow-clockwise me-1"></i>Retry Check
                    </button>
                </div>
            `;
        }

        return isOnline;
    } catch (error) {
        if (showStatus) {
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
                        <i class="bi bi-arrow-clockwise me-1"></i>Retry Check
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
        statusText.textContent = message || `Enroll finger ${updateState.currentFinger} of ${updateState.numFingers}`;
    }

    if (statusDiv) {
        statusDiv.className = 'alert alert-info mb-4';
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
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="startEnrollment()">
                <i class="bi bi-arrow-clockwise me-1"></i>Retry
            </button>
        </div>
    `;
}

function showCompletion() {
    if (updateState.monitorHandle) {
        clearInterval(updateState.monitorHandle);
        updateState.monitorHandle = null;
    }

    document.getElementById('step2EnrollmentProgress').style.display = 'none';
    document.getElementById('step3Complete').style.display = 'block';
    document.getElementById('completionMessage').textContent =
        `${updateState.numFingers} fingerprint(s) enrolled successfully for ${updateState.studentName}.`;

    updateState.enrollmentCompleted = true;
}

function rollbackIncompleteRegistration() {
    updateState.rollbackInProgress = true;

    fetch('../api/rollback_registration.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            student_id: updateState.studentId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('warning', 'Registration canceled: student record was removed because fingerprint enrollment was not completed.');
            document.getElementById('registrationForm').reset();
            updateState.studentCreatedInSession = false;
            updateState.studentId = null;
            updateState.studentName = '';
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

    document.getElementById('step1SelectFingers').style.display = 'block';
    document.getElementById('step2EnrollmentProgress').style.display = 'none';
    document.getElementById('step3Complete').style.display = 'none';

    document.querySelectorAll('.finger-btn').forEach(b => b.classList.remove('selected'));
    document.querySelectorAll('.scan-circle').forEach(circle => {
        circle.classList.remove('scanning', 'success', 'error');
        circle.innerHTML = '<i class="bi bi-fingerprint"></i>';
    });

    updateState.numFingers = 0;
    updateState.currentFinger = 1;
    updateState.currentScan = 1;
    updateState.enrolledFingerprints = [];
    updateState.registrationId = null;
    updateState.monitorHandle = null;
    updateState.lastServerFinger = 1;
    updateState.enrollmentCompleted = false;
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
}

.enrollment-header {
    text-align: center;
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 15px;
}
</style>

<?php require '../includes/footer.php'; ?>
