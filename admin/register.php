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
                                <select class="form-select" id="section" required>
                                    <option value="">Select section</option>
                                    <option value="Alpha">Alpha</option>
                                    <option value="Beta">Beta</option>
                                    <option value="Charlie">Charlie</option>
                                    <option value="Delta">Delta</option>
                                    <option value="Echo">Echo</option>
                                </select>
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
                        <h4 id="currentFingerTitle">Finger 1 of 1</h4>
                        <p class="text-muted mb-0">Place your finger on the scanner and wait for confirmation.</p>
                    </div>

                    <div class="d-flex justify-content-center gap-3 flex-wrap mb-4" id="scanCirclesContainer"></div>

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

                    <div class="alert alert-secondary mb-4" id="debugMessage" style="display:none;">
                        <strong>Debug Log</strong>
                        <div id="debugText" style="font-family: monospace; font-size: 0.85rem; white-space: pre-wrap;"></div>
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

<script>
let updateState = {
    modal: null,
    studentId: null,
    studentName: '',
    numFingers: 0,
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
    scanStepsPerFinger: 3,
    currentScanStep: 1,
    lastProgressSnapshot: ''
};

document.addEventListener('DOMContentLoaded', function () {
    updateState.modal = new bootstrap.Modal(document.getElementById('fingerprintModal'));
    updateModeIndicator('attendance');
    renderScanCircles(1);

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

    const saveBtn = document.getElementById('btnSaveStudent');
    if (saveBtn) {
        saveBtn.addEventListener('click', saveStudentRecord);
    }

    const retryEnrollmentBtn = document.getElementById('btnRetryEnrollment');
    if (retryEnrollmentBtn) {
        retryEnrollmentBtn.addEventListener('click', retryEnrollment);
    }

    document.getElementById('fingerprintModal').addEventListener('shown.bs.modal', function () {
        setScannerMode('registration');
        updateModeIndicator('registration');
    });

    document.getElementById('fingerprintModal').addEventListener('hidden.bs.modal', function () {
        setScannerMode('attendance');
        updateModeIndicator('attendance');

        if (updateState.studentCreatedInSession && !updateState.studentFinalized && updateState.studentId && !updateState.rollbackInProgress) {
            rollbackIncompleteRegistration();
        }

        if (updateState.enrollmentCompleted) {
            if (updateState.studentFinalized) {
                showAlert('success', 'Student registration completed with fingerprint enrollment.');
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
            const payload = new URLSearchParams({
                student_id: String(updateState.studentId)
            });
            navigator.sendBeacon('../api/rollback_registration.php', payload);
        } catch (e) {
            // Best-effort cleanup only.
        }
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
        student_no: updateState.pendingStudentData.student_id,
        first_name: updateState.pendingStudentData.first_name,
        middle_initial: updateState.pendingStudentData.middle_initial || '',
        last_name: updateState.pendingStudentData.last_name,
        email: updateState.pendingStudentData.email
    };

    return fetch('../api/finalize_registration.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
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
    updateState.enrolledFingerprints = [];
    updateState.enrollmentCompleted = false;
    updateState.pollFailures = 0;
    appendDebug('Starting enrollment for student_id=' + String(updateState.studentId));

    renderScanCircles(updateState.numFingers);

    updateProgress();

    showWaitingMessage('Sending registration command to scanner...');
    appendDebug('Calling start_registration API');

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

            if (data.mode === 'registration') {
                const serverFinger = Math.max(1, parseInt(data.finger_number || 1, 10));
                const serverScanStep = parseInt(data.scan_step || 0, 10);
                const totalScanSteps = parseInt(data.total_scan_steps || 3, 10);

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
        } catch (error) {
            appendDebug('get_mode poll error: ' + error.message);
            // Keep polling on transient failures.
        }
    }, 2000);
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
        } else {
            statusText.textContent = `Finger ${fingerNo} registered. Place finger ${fingerNo + 1} of ${updateState.numFingers}.`;
        }
    }

    updateOverallProgress();
}

function updateProgress() {
    document.getElementById('currentFingerTitle').textContent =
        `Finger ${updateState.currentFinger} of ${updateState.numFingers}`;

    document.querySelectorAll('.scan-circle').forEach(circle => {
        if (!circle.classList.contains('success')) {
            circle.classList.remove('scanning', 'error');
            circle.innerHTML = '<i class="bi bi-fingerprint"></i>';
        }
    });

    const currentCircle = document.getElementById(`scan${updateState.currentFinger}`);
    if (currentCircle && !currentCircle.classList.contains('success')) {
        currentCircle.classList.add('scanning');
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

function showWaitingMessage(message) {
    const statusDiv = document.getElementById('statusMessage');
    const statusText = document.getElementById('statusText');

    if (statusText) {
        statusText.textContent = message || 'Waiting for scan...';
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
    appendDebug('ERROR: ' + message);
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
        showAlert('success', 'Student data saved successfully.');
        updateState.modal.hide();
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
        const response = await fetch('../api/retry_registration.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
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

    document.getElementById('step1SelectFingers').style.display = 'block';
    document.getElementById('step2EnrollmentProgress').style.display = 'none';
    document.getElementById('step3Complete').style.display = 'none';

    document.querySelectorAll('.finger-btn').forEach(b => b.classList.remove('selected'));
    renderScanCircles(1);

    updateState.numFingers = 0;
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
    updateState.currentScanStep = 1;
    updateState.lastProgressSnapshot = '';

    updateOverallProgress();

    const debugBox = document.getElementById('debugMessage');
    const debugText = document.getElementById('debugText');
    if (debugBox) {
        debugBox.style.display = 'none';
    }
    if (debugText) {
        debugText.textContent = '';
    }
}

function appendDebug(message, data) {
    const debugBox = document.getElementById('debugMessage');
    const debugText = document.getElementById('debugText');
    if (!debugBox || !debugText) {
        return;
    }

    const ts = new Date().toLocaleTimeString();
    let line = `[${ts}] ${message}`;

    if (typeof data !== 'undefined') {
        try {
            line += `\n${JSON.stringify(data)}`;
        } catch (e) {
            line += `\n${String(data)}`;
        }
    }

    updateState.debugLines.push(line);
    if (updateState.debugLines.length > 4) {
        updateState.debugLines = updateState.debugLines.slice(-4);
    }

    debugText.textContent = updateState.debugLines.join('\n\n');
    debugBox.style.display = 'block';
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
