<?php
/**
 * Student Management Page
 * CRUD operations for student records
 */

session_start();
require_once '../config/db.php';
require '../includes/header.php';

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
$result = $mysqli->query("
    SELECT 
        s.id, 
        s.student_id, 
        s.first_name, 
        s.last_name, 
        s.email, 
        s.year, 
        s.section, 
        s.status, 
        COALESCE(fp_summary.fingerprint_count, 0) AS fingerprint_count, 
        COALESCE(fp_summary.fingerprint_list, '') AS fingerprint_list 
    FROM students s 
    LEFT JOIN (
        SELECT 
            fp.student_id, 
            COUNT(fp.id) AS fingerprint_count, 
            GROUP_CONCAT(CONCAT('F', fp.finger_index, ':', fp.sensor_id) ORDER BY fp.finger_index SEPARATOR ', ') AS fingerprint_list 
        FROM fingerprints fp 
        GROUP BY fp.student_id
    ) fp_summary ON fp_summary.student_id = s.id 
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
                    <?php foreach ($students as $student): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($student['student_id']); ?></strong></td>
                        <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
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
                            <button class="btn btn-sm btn-primary" title="Update Fingerprints" onclick="openFingerprintModal(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')">
                                <i class="bi bi-fingerprint"></i>
                            </button>
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

<!-- Fingerprint Update Modal -->
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
                        <p class="text-muted mb-0">Place your finger on the scanner <strong>5 times</strong></p>
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

                    <div class="alert alert-secondary mt-2" id="debugMessage" style="display:none;">
                        <strong>Debug Log</strong>
                        <div id="debugText" style="font-family: monospace; font-size: 0.85rem; white-space: pre-wrap;"></div>
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

.scan-circle.success {
    border-color: #10b981;
    background: #ecfdf5;
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Fingerprint Modal State
let updateState = {
    modal: null,
    studentId: null,
    studentName: '',
    scannerOnline: false,
    numFingers: 0,
    currentFinger: 1,
    currentScan: 1,
    enrolledFingerprints: [],
    registrationId: null,
    monitorHandle: null,
    lastServerFinger: 1,
    debugLines: []
};

// Initialize modal when page loads
document.addEventListener('DOMContentLoaded', function() {
    updateState.modal = new bootstrap.Modal(document.getElementById('fingerprintModal'));
    
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
        resetModal();
        updateState.modal.hide();
    });
    
    // Reset modal when closed
    document.getElementById('fingerprintModal').addEventListener('hidden.bs.modal', function() {
        resetModal();
    });
});

function openFingerprintModal(studentId, studentName) {
    updateState.studentId = studentId;
    updateState.studentName = studentName;
    
    document.getElementById('studentName').textContent = studentName;
    resetModal();
    updateState.modal.show();
}

async function startEnrollment() {
    document.getElementById('step1SelectFingers').style.display = 'none';
    document.getElementById('step2EnrollmentProgress').style.display = 'block';
    
    updateState.currentFinger = 1;
    updateState.currentScan = 1;
    updateState.enrolledFingerprints = [];
    updateState.lastServerFinger = 1;
    updateState.registrationId = null;
    appendDebug('Starting enrollment flow');
    
    updateProgress();

    const isOnline = await checkScannerOnline(true);
    if (!isOnline) {
        showError('Scanner offline. Retry when scanner is online.');
        return;
    }

    appendDebug('Scanner online, requesting start_registration');
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
        updateState.lastServerFinger = 1;
        beginEnrollmentMonitor();
    })
    .catch(error => {
        showError('Connection error: ' + error.message);
    });
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

function beginEnrollmentMonitor() {
    if (updateState.monitorHandle) {
        clearInterval(updateState.monitorHandle);
    }

    showWaitingMessage();
    appendDebug('Monitoring get_mode for registration progress');

    updateState.monitorHandle = setInterval(async () => {
        try {
            const response = await fetch('../api/get_mode.php');
            const data = await response.json();

            if (!data.success) {
                appendDebug('get_mode returned unsuccessful response', data);
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
                appendDebug('Registration completed. Mode switched to attendance.');
                showCompletion();
            }
        } catch (error) {
            appendDebug('get_mode error: ' + error.message);
        }
    }, 2000);
}

async function checkScannerOnline(showStatus = false) {
    const statusDiv = document.getElementById('statusMessage');

    try {
        const response = await fetch('../api/scanner_status.php');
        const data = await response.json();
        const isOnline = !!(response.ok && data.success && data.scanner && data.scanner.online);
        updateState.scannerOnline = isOnline;

        if (showStatus && !isOnline) {
            statusDiv.className = 'alert alert-danger';
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

        appendDebug('scanner_status online=' + String(isOnline), data);

        return isOnline;
    } catch (error) {
        updateState.scannerOnline = false;

        if (showStatus) {
            statusDiv.className = 'alert alert-danger';
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

        appendDebug('scanner_status error: ' + error.message);

        return false;
    }
}

function showWaitingMessage() {
    const statusText = document.getElementById('statusText');
    if (statusText) {
        statusText.textContent = `Waiting for scanner save (finger ${updateState.currentFinger} of ${updateState.numFingers})`;
    }
}

function showError(message) {
    const statusDiv = document.getElementById('statusMessage');
    statusDiv.className = 'alert alert-danger';
    statusDiv.innerHTML = `<i class="bi bi-exclamation-triangle"></i> ${message}`;
    appendDebug(message);
}

function markFingerCompleted(fingerNo) {
    if (!updateState.enrolledFingerprints.find(f => f.finger === fingerNo)) {
        updateState.enrolledFingerprints.push({ finger: fingerNo, scans: 1 });
    }

    document.querySelectorAll('.scan-circle').forEach(circle => {
        circle.classList.remove('scanning', 'error');
        circle.classList.add('success');
        circle.innerHTML = '<i class="bi bi-check"></i>';
    });

    appendDebug('Finger ' + String(fingerNo) + ' completed by scanner');
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
    if (updateState.debugLines.length > 12) {
        updateState.debugLines = updateState.debugLines.slice(-12);
    }

    debugText.textContent = updateState.debugLines.join('\n\n');
    debugBox.style.display = 'block';
}

function showCompletion() {
    if (updateState.monitorHandle) {
        clearInterval(updateState.monitorHandle);
        updateState.monitorHandle = null;
    }

    document.getElementById('step2EnrollmentProgress').style.display = 'none';
    document.getElementById('step3Complete').style.display = 'block';
    
    document.getElementById('completionMessage').textContent = 
        `Scans completed for ${updateState.studentName}. Use Edit Student page to persist fingerprint updates.`;
}

function resetModal() {
    if (updateState.monitorHandle) {
        clearInterval(updateState.monitorHandle);
        updateState.monitorHandle = null;
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
    updateState.scannerOnline = false;
    updateState.registrationId = null;
    updateState.lastServerFinger = 1;
    updateState.debugLines = [];

    const debugBox = document.getElementById('debugMessage');
    const debugText = document.getElementById('debugText');
    if (debugBox) {
        debugBox.style.display = 'none';
    }
    if (debugText) {
        debugText.textContent = '';
    }
}
</script>

<?php require '../includes/footer.php'; ?>
