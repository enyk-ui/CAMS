<?php
/**
 * Settings Page
 * Configure system parameters
 */

session_start();
require_once 'config/db.php';
require 'includes/header.php';

$message = '';
$message_type = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings_to_update = [
        'late_threshold_minutes' => $_POST['late_threshold'] ?? '',
        'absent_threshold_hours' => $_POST['absent_threshold'] ?? '',
        'am_start_time' => $_POST['am_start'] ?? '',
        'am_end_time' => $_POST['am_end'] ?? '',
        'pm_start_time' => $_POST['pm_start'] ?? '',
        'pm_end_time' => $_POST['pm_end'] ?? '',
        'notification_enabled' => isset($_POST['notifications']) ? 'true' : 'false'
    ];

    foreach ($settings_to_update as $key => $value) {
        if ($value !== '') {
            $mysqli->query("
                INSERT INTO settings (setting_key, setting_value)
                VALUES ('$key', '$value')
                ON DUPLICATE KEY UPDATE setting_value = '$value'
            ");
        }
    }

    $message = "Settings updated successfully!";
    $message_type = "success";
}

// Get current settings
$settings = [];
$result = $mysqli->query("SELECT setting_key, setting_value FROM settings");

while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Set defaults
$late_threshold = $settings['late_threshold_minutes'] ?? '15';
$absent_threshold = $settings['absent_threshold_hours'] ?? '2';
$am_start = $settings['am_start_time'] ?? '08:00:00';
$am_end = $settings['am_end_time'] ?? '12:00:00';
$pm_start = $settings['pm_start_time'] ?? '13:00:00';
$pm_end = $settings['pm_end_time'] ?? '17:00:00';
$notifications_enabled = ($settings['notification_enabled'] ?? 'true') === 'true';
?>

<h2 class="page-title"><i class="bi bi-gear"></i> System Settings</h2>
<p class="page-subtitle">Configure attendance thresholds and schedules</p>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST" class="row">
    <!-- Attendance Thresholds -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock"></i> Attendance Thresholds</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="late_threshold" class="form-label">Late Threshold (Minutes)</label>
                    <p class="form-text text-muted small">Minutes after AM start time to mark as late</p>
                    <input type="number" class="form-control" id="late_threshold" name="late_threshold"
                           value="<?php echo $late_threshold; ?>" min="0" max="60">
                </div>

                <div class="mb-3">
                    <label for="absent_threshold" class="form-label">Absent Threshold (Hours)</label>
                    <p class="form-text text-muted small">Hours after AM start to mark as absent if no scan</p>
                    <input type="number" class="form-control" id="absent_threshold" name="absent_threshold"
                           value="<?php echo $absent_threshold; ?>" min="0" max="12">
                </div>
            </div>
        </div>
    </div>

    <!-- Session Schedule -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-calendar-event"></i> Session Schedule</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-6">
                        <label for="am_start" class="form-label">AM Start Time</label>
                        <input type="time" class="form-control" id="am_start" name="am_start"
                               value="<?php echo substr($am_start, 0, 5); ?>">
                    </div>
                    <div class="col-6">
                        <label for="am_end" class="form-label">AM End Time</label>
                        <input type="time" class="form-control" id="am_end" name="am_end"
                               value="<?php echo substr($am_end, 0, 5); ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-6">
                        <label for="pm_start" class="form-label">PM Start Time</label>
                        <input type="time" class="form-control" id="pm_start" name="pm_start"
                               value="<?php echo substr($pm_start, 0, 5); ?>">
                    </div>
                    <div class="col-6">
                        <label for="pm_end" class="form-label">PM End Time</label>
                        <input type="time" class="form-control" id="pm_end" name="pm_end"
                               value="<?php echo substr($pm_end, 0, 5); ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications -->
    <div class="col-lg-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-envelope"></i> Email Notifications</h5>
            </div>
            <div class="card-body">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="notifications" name="notifications"
                           <?php echo $notifications_enabled ? 'checked' : ''; ?> style="cursor: pointer;">
                    <label class="form-check-label" for="notifications" style="cursor: pointer;">
                        <strong>Enable email notifications</strong>
                        <p class="text-muted small mb-0">Send attendance confirmation emails to students after each scan</p>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- Save Button -->
    <div class="col-lg-12">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-check-circle"></i> Save Settings
        </button>
    </div>
</form>

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

    .form-control, .form-select {
        border-radius: 8px;
        border: 1px solid #dee2e6;
    }

    .form-check-input {
        width: 2rem;
        height: 2rem;
    }

    .form-check-label {
        margin-left: 10px;
    }

    .btn-lg {
        padding: 12px 30px;
    }
</style>

<?php require 'includes/footer.php'; ?>
