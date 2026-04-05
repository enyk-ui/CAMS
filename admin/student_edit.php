<?php
/**
 * Student Edit Page
 * Edit student information
 */

session_start();
require_once '../config/db.php';
require '../includes/header.php';

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id_input = trim($_POST['student_id']);
    $first_name = trim($_POST['first_name']);
    $middle_initial = trim($_POST['middle_initial']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $year = intval($_POST['year']);
    $section = trim($_POST['section']);
    $status = $_POST['status'];

    // Validate required fields
    if (empty($student_id_input) || empty($first_name) || empty($last_name) || empty($email) || empty($section)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'danger';
    } else {
        // Check if student ID is unique (excluding current student)
        $stmt = $mysqli->prepare("SELECT id FROM students WHERE student_id = ? AND id != ?");
        $stmt->bind_param("si", $student_id_input, $student_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        if ($existing) {
            $message = 'Student ID already exists. Please use a unique ID.';
            $message_type = 'danger';
        } else {
            // Update student
            $stmt = $mysqli->prepare("UPDATE students SET student_id = ?, first_name = ?, middle_initial = ?, last_name = ?, email = ?, year = ?, section = ?, status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("sssssissi", $student_id_input, $first_name, $middle_initial, $last_name, $email, $year, $section, $status, $student_id);
            
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
                        
                        <!-- Student ID -->
                        <div class="col-md-6">
                            <label for="student_id" class="form-label">Student ID *</label>
                            <input type="text" class="form-control" id="student_id" name="student_id" 
                                   value="<?php echo htmlspecialchars($student['student_id']); ?>" 
                                   placeholder="e.g., 2024-001" required>
                            <div class="form-text">Unique identifier for the student</div>
                        </div>

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

                        <!-- First Name -->
                        <div class="col-md-4">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?php echo htmlspecialchars($student['first_name']); ?>" 
                                   placeholder="Juan" required>
                        </div>

                        <!-- Middle Initial -->
                        <div class="col-md-2">
                            <label for="middle_initial" class="form-label">M.I.</label>
                            <input type="text" class="form-control" id="middle_initial" name="middle_initial" 
                                   value="<?php echo htmlspecialchars($student['middle_initial'] ?? ''); ?>" 
                                   placeholder="D" maxlength="1">
                        </div>

                        <!-- Last Name -->
                        <div class="col-md-6">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($student['last_name']); ?>" 
                                   placeholder="Dela Cruz" required>
                        </div>

                        <!-- Email -->
                        <div class="col-md-8">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($student['email']); ?>" 
                                   placeholder="juan@example.com" required>
                        </div>

                        <!-- Section -->
                        <div class="col-md-4">
                            <label for="section" class="form-label">Section *</label>
                            <input type="text" class="form-control" id="section" name="section" 
                                   value="<?php echo htmlspecialchars($student['section']); ?>" 
                                   placeholder="e.g., A" required>
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
                                            onclick="openFingerprintModal(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')">
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
                                            Please update fingerprints before saving student changes
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-check-lg"></i> Update Student
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
                <span id="modalModeBadge" class="badge bg-success me-2">Mode: Attendance</span>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <h6>Student: <span id="studentName" class="text-primary"></span></h6>
                    <p class="text-muted">Update fingerprint data for this student</p>
                </div>

                <!-- Step 1: Select Number of Fingers -->
                <div id="step1SelectFingers">
                    <h5 class="mb-4 text-center">How many fingers do you want to enroll?</h5>
                    <p class="text-muted text-center mb-4">Each finger will be scanned <strong>5 times</strong> for accuracy</p>
                    
                    <div class="finger-selector">
                        <button type="button" class="finger-btn" data-fingers="1">
                            <i class="bi bi-hand-index-thumb"></i>
                            <span>1 Finger</span>
                        </button>
                        <button type="button" class="finger-btn" data-fingers="2">
                            <i class="bi bi-hand-index-thumb"></i>
                            <span>2 Fingers</span>
                        </button>
                        <button type="button" class="finger-btn" data-fingers="3">
                            <i class="bi bi-hand-index-thumb"></i>
                            <span>3 Fingers</span>
                        </button>
                        <button type="button" class="finger-btn" data-fingers="4">
                            <i class="bi bi-hand-index-thumb"></i>
                            <span>4 Fingers</span>
                        </button>
                        <button type="button" class="finger-btn" data-fingers="5">
                            <i class="bi bi-hand-index-thumb"></i>
                            <span>5 Fingers</span>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Enrollment Progress -->
                <div id="step2EnrollmentProgress" style="display: none;">
                    <div class="enrollment-header mb-4">
                        <h5 id="currentFingerTitle">Enrolling Finger 1 of 1</h5>
                        <p class="text-muted mb-0">Place your finger on the scanner and wait for confirmation.</p>
                    </div>

                    <div id="scanProgress" class="mb-4">
                        <div class="scan-attempts">
                            <div class="scan-attempt" id="scan1">
                                <div class="scan-circle"><i class="bi bi-fingerprint"></i></div>
                                <small>Scan 1</small>
                            </div>
                            <div class="scan-attempt" id="scan2">
                                <div class="scan-circle"><i class="bi bi-fingerprint"></i></div>
                                <small>Scan 2</small>
                            </div>
                            <div class="scan-attempt" id="scan3">
                                <div class="scan-circle"><i class="bi bi-fingerprint"></i></div>
                                <small>Scan 3</small>
                            </div>
                            <div class="scan-attempt" id="scan4">
                                <div class="scan-circle"><i class="bi bi-fingerprint"></i></div>
                                <small>Scan 4</small>
                            </div>
                            <div class="scan-attempt" id="scan5">
                                <div class="scan-circle"><i class="bi bi-fingerprint"></i></div>
                                <small>Scan 5</small>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info" id="statusMessage">
                        <i class="bi bi-info-circle"></i> <span id="statusText">Waiting for scanner...</span>
                    </div>

                    <div class="d-flex gap-2 justify-content-between">
                        <button type="button" class="btn btn-secondary" id="btnCancelEnrollment">
                            <i class="bi bi-x-circle"></i> Cancel
                        </button>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-warning" id="btnRetryFinger" style="display: none;">
                                <i class="bi bi-arrow-clockwise"></i> Retry This Finger
                            </button>
                            <button type="button" class="btn btn-primary" id="btnNextFinger" style="display: none;">
                                <i class="bi bi-arrow-right"></i> Next Finger
                            </button>
                        </div>
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
}

.scan-circle.error i {
    color: #ef4444;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.enrollment-header {
    text-align: center;
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 15px;
}
</style>

<script>
// Fingerprint Modal State and Form Validation
let updateState = {
    modal: null,
    studentId: null,
    studentName: '',
    numFingers: 0,
    currentFinger: 1,
    currentScan: 1,
    enrolledFingerprints: [],
    fingerprintsChanged: false,
    sessionId: null,
    registrationId: null,
    monitorHandle: null,
    lastServerFinger: 1,
    enrollmentCompleted: false
};

// Initialize modal when page loads
document.addEventListener('DOMContentLoaded', function() {
    updateState.modal = new bootstrap.Modal(document.getElementById('fingerprintModal'));
    updateModeIndicator('attendance');
    
    // Finger selection buttons
    document.querySelectorAll('.finger-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.finger-btn').forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            updateState.numFingers = parseInt(this.dataset.fingers);
            
            setTimeout(() => startEnrollment(), 500);
        });
    });
    
    // Cancel button
    document.getElementById('btnCancelEnrollment').addEventListener('click', function() {
        setScannerMode('attendance');
        updateModeIndicator('attendance');
        resetModal();
        updateState.modal.hide();
    });

    document.getElementById('fingerprintModal').addEventListener('shown.bs.modal', function() {
        setScannerMode('registration');
        updateModeIndicator('registration');
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
        badge.className = 'badge bg-warning text-dark me-2';
        badge.textContent = 'Mode: Registration (Busy)';
        return;
    }

    badge.className = 'badge bg-success me-2';
    badge.textContent = 'Mode: Attendance';
}

function startEnrollment() {
    document.getElementById('step1SelectFingers').style.display = 'none';
    document.getElementById('step2EnrollmentProgress').style.display = 'block';
    
    updateState.currentFinger = 1;
    updateState.currentScan = 1;
    updateState.enrolledFingerprints = [];
    
    checkScannerOnline(true).then((isOnline) => {
        if (!isOnline) {
            showWaitingMessage('Scanner heartbeat is stale. Starting registration mode anyway...');
        }

        showWaitingMessage('Starting registration mode...');
        return fetch('../api/start_registration.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
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

        if (!data.success) {
            showError('Failed to initialize enrollment: ' + data.message);
            return;
        }

        updateState.registrationId = data.registration_id;
        updateState.lastServerFinger = 1;
        updateState.currentFinger = 1;
        updateState.enrollmentCompleted = false;
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

    showWaitingMessage();

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
                    while (updateState.lastServerFinger < serverFinger && updateState.lastServerFinger <= updateState.numFingers) {
                        markFingerCompleted(updateState.lastServerFinger);
                        updateState.lastServerFinger++;
                    }
                }

                updateState.currentFinger = Math.min(serverFinger, updateState.numFingers);
                updateProgress();
                showWaitingMessage();
                return;
            }

            if (data.mode === 'attendance' && updateState.registrationId) {
                if (updateState.lastServerFinger <= updateState.numFingers) {
                    markFingerCompleted(updateState.lastServerFinger);
                }
                showCompletion();
            }
        } catch (e) {
            // Keep polling on transient failures.
        }
    }, 2000);
}

function markFingerCompleted(fingerNo) {
    document.querySelectorAll('.scan-circle').forEach(circle => {
        circle.classList.remove('scanning', 'error');
        circle.classList.add('success');
        circle.innerHTML = '<i class="bi bi-check"></i>';
    });

    if (!updateState.enrolledFingerprints.find(f => f.finger === fingerNo)) {
        updateState.enrolledFingerprints.push({ finger: fingerNo, scans: 1 });
    }
}

function updateProgress() {
    document.getElementById('currentFingerTitle').textContent = 
        `Enrolling Finger ${updateState.currentFinger} of ${updateState.numFingers}`;
    
    // Reset scan circles
    document.querySelectorAll('.scan-circle').forEach(circle => {
        circle.classList.remove('scanning', 'success');
    });
    
    // Show current scan as scanning
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
                <div class="alert alert-danger mb-0">
                    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                        <div>
                            <i class="bi bi-wifi-off me-2"></i>
                            <strong>Scanner Offline</strong><br>
                            <small>${(data.scanner && data.scanner.message) ? data.scanner.message : 'No recent heartbeat from scanner'}</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="startEnrollment()">
                            <i class="bi bi-arrow-clockwise me-1"></i>Retry Check
                        </button>
                    </div>
                </div>
            `;
        }

        return isOnline;
    } catch (error) {
        if (showStatus) {
            statusDiv.innerHTML = `
                <div class="alert alert-danger mb-0">
                    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                        <div>
                            <i class="bi bi-wifi-off me-2"></i>
                            <strong>Scanner Offline</strong><br>
                            <small>Status check failed. Verify scanner and server connection.</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="startEnrollment()">
                            <i class="bi bi-arrow-clockwise me-1"></i>Retry Check
                        </button>
                    </div>
                </div>
            `;
        }

        return false;
    }
}

function showWaitingMessage() {
    const statusDiv = document.getElementById('statusMessage');
    statusDiv.innerHTML = `
        <div class="d-flex align-items-center">
            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
            <div>
                <strong>Waiting for ESP32 Scanner</strong><br>
                <small>Enroll finger ${updateState.currentFinger} of ${updateState.numFingers}</small><br>
                <small class="text-muted">Place the finger on scanner until device confirms save</small>
            </div>
        </div>
    `;
}

function showError(message) {
    const statusDiv = document.getElementById('statusMessage');
    statusDiv.innerHTML = `
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            ${message}
            <div class="mt-2">
                <button type="button" class="btn btn-sm btn-primary" onclick="startEnrollment()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Retry
                </button>
            </div>
        </div>
    `;
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
        circle.classList.remove('scanning', 'success');
        circle.innerHTML = '<i class="bi bi-fingerprint"></i>';
    });
    
    updateState.numFingers = 0;
    updateState.currentFinger = 1;
    updateState.currentScan = 1;
    updateState.enrolledFingerprints = [];
    updateState.fingerprintsChanged = false;
    updateState.sessionId = null;
    updateState.registrationId = null;
    updateState.monitorHandle = null;
    updateState.lastServerFinger = 1;
    updateState.enrollmentCompleted = false;
}

function validateFormSubmission(e) {
    // Check if fingerprints were changed but not updated
    if (updateState.fingerprintsChanged && !updateState.enrollmentCompleted) {
        e.preventDefault();
        
        // Show warning
        document.getElementById('fingerprintWarning').style.display = 'block';
        document.getElementById('fingerprintStatus').style.display = 'none';
        
        // Scroll to warning
        document.getElementById('fingerprintWarning').scrollIntoView({ 
            behavior: 'smooth', 
            block: 'center' 
        });
        
        // Highlight the fingerprint button
        const fingerprintBtn = document.getElementById('updateFingerprintsBtn');
        fingerprintBtn.classList.add('btn-warning');
        fingerprintBtn.classList.remove('btn-primary');
        
        setTimeout(() => {
            fingerprintBtn.classList.remove('btn-warning');
            fingerprintBtn.classList.add('btn-primary');
        }, 3000);
        
        return false;
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

<?php require '../includes/footer.php'; ?>