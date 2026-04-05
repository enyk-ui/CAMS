<?php
/**
 * Notifications Page
 * View and manage email notification queue
 */

session_start();
require_once '../config/db.php';
require '../includes/header.php';

$message = '';
$message_type = '';

// Handle retry failed
if (isset($_POST['retry_all'])) {
    $mysqli->query("
        UPDATE notification_queue
        SET attempt_count = 0, status = 'pending', next_retry_at = NOW()
        WHERE status = 'failed'
    ");
    $message = "Failed notifications queued for retry!";
    $message_type = "success";
}

// Get queue statistics
$stats_result = $mysqli->query("
    SELECT
        status,
        COUNT(*) as count
    FROM notification_queue
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY status
");

$stats = ['pending' => 0, 'sent' => 0, 'failed' => 0];
while ($row = $stats_result->fetch_assoc()) {
    $stats[$row['status']] = $row['count'];
}

// Get pending notifications
$pending = [];
$result = $mysqli->query("
    SELECT id, student_id, email, subject, attempt_count, created_at, next_retry_at
    FROM notification_queue
    WHERE status = 'pending'
    ORDER BY next_retry_at ASC
    LIMIT 50
");

while ($row = $result->fetch_assoc()) {
    $pending[] = $row;
}

// Get failed notifications
$failed = [];
$result = $mysqli->query("
    SELECT id, student_id, email, subject, attempt_count, created_at, last_error
    FROM notification_queue
    WHERE status = 'failed'
    ORDER BY created_at DESC
    LIMIT 20
");

while ($row = $result->fetch_assoc()) {
    $failed[] = $row;
}

// Get sent count
$sent_result = $mysqli->query("
    SELECT COUNT(*) as count
    FROM notification_queue
    WHERE status = 'sent'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$sent_count = $sent_result->fetch_assoc()['count'] ?? 0;
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Statistics -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 style="color: #667eea;"><?php echo $stats['pending']; ?></h3>
                <p class="text-muted mb-0">Pending</p>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 style="color: #28a745;"><?php echo $sent_count; ?></h3>
                <p class="text-muted mb-0">Sent (7 days)</p>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 style="color: #dc3545;"><?php echo $stats['failed']; ?></h3>
                <p class="text-muted mb-0">Failed</p>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <form method="POST" style="margin: 0;">
                    <button type="submit" name="retry_all" class="btn btn-sm btn-primary w-100"
                            onclick="return confirm('Retry all failed notifications?')">
                        <i class="bi bi-arrow-clockwise"></i> Retry Failed
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Pending Notifications -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-hourglass-split"></i> Pending (<?php echo count($pending); ?>)</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Student ID</th>
                        <th>Email</th>
                        <th>Subject</th>
                        <th>Attempts</th>
                        <th>Next Retry</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pending)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3">No pending notifications</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pending as $notif): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($notif['student_id']); ?></strong></td>
                            <td><small><?php echo htmlspecialchars($notif['email']); ?></small></td>
                            <td><?php echo htmlspecialchars(substr($notif['subject'], 0, 40)); ?></td>
                            <td><span class="badge bg-warning"><?php echo $notif['attempt_count']; ?>/3</span></td>
                            <td><small><?php echo date('M d H:i', strtotime($notif['next_retry_at'])); ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Failed Notifications -->
<?php if (!empty($failed)): ?>
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-exclamation-circle"></i> Failed (<?php echo count($failed); ?>)</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Student ID</th>
                        <th>Email</th>
                        <th>Subject</th>
                        <th>Attempts</th>
                        <th>Error</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($failed as $notif): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($notif['student_id']); ?></strong></td>
                        <td><small><?php echo htmlspecialchars($notif['email']); ?></small></td>
                        <td><?php echo htmlspecialchars(substr($notif['subject'], 0, 35)); ?></td>
                        <td><span class="badge bg-danger"><?php echo $notif['attempt_count']; ?>/3</span></td>
                        <td><small class="text-danger" title="<?php echo htmlspecialchars($notif['last_error'] ?? 'N/A'); ?>">
                            <?php echo htmlspecialchars(substr($notif['last_error'] ?? 'Unknown', 0, 20)); ?>...
                        </small></td>
                        <td><small><?php echo date('M d', strtotime($notif['created_at'])); ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

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
</style>

<?php require '../includes/footer.php'; ?>
