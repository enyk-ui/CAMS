<?php
/**
 * Teacher Notifications
 * Email notifications for teacher's class
 */

require_once '../config/db.php';
require '../includes/header.php';

if ($_SESSION['role'] !== 'teacher') {
    header('Location: ../index.php?error=Unauthorized');
    exit;
}

$section = $_SESSION['teacher_section'];

// Get notifications for students in this section
$notifications = [];
$result = $mysqli->query("
    SELECT
        nq.id,
        nq.email,
        nq.subject,
        nq.message,
        nq.status,
        nq.attempt_count,
        nq.created_at,
        nq.sent_at,
        s.first_name,
        s.last_name
    FROM notification_queue nq
    LEFT JOIN students s ON nq.student_id = s.id
    WHERE s.section = '$section'
    ORDER BY nq.created_at DESC
    LIMIT 100
");

while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

// Get statistics
$stats = $mysqli->query("
    SELECT
        nq.status,
        COUNT(*) as count
    FROM notification_queue nq
    LEFT JOIN students s ON nq.student_id = s.id
    WHERE s.section = '$section'
    GROUP BY nq.status
")->fetch_all(MYSQLI_ASSOC);

$stats_arr = [];
foreach ($stats as $stat) {
    $stats_arr[$stat['status']] = $stat['count'];
}
?>

<div class="container-fluid">
    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-muted tiny">Pending</h5>
                    <h3 style="color: #f59e0b;"><?php echo $stats_arr['pending'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-muted tiny">Sent</h5>
                    <h3 style="color: #10b981;"><?php echo $stats_arr['sent'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-muted tiny">Failed</h5>
                    <h3 style="color: #ef4444;"><?php echo $stats_arr['failed'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-muted tiny">Total</h5>
                    <h3 style="color: #2563eb;"><?php echo count($notifications); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Notifications</h5>
                </div>
                <div class="card-body">
                    <?php if (count($notifications) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Recipient</th>
                                        <th>Email</th>
                                        <th>Subject</th>
                                        <th>Status</th>
                                        <th>Attempts</th>
                                        <th>Sent Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($notifications as $notif): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($notif['first_name'] . ' ' . ($notif['last_name'] ?? '')); ?></td>
                                            <td><?php echo htmlspecialchars($notif['email']); ?></td>
                                            <td><?php echo htmlspecialchars(substr($notif['subject'], 0, 50)); ?></td>
                                            <td>
                                                <span class="badge <?php
                                                    if ($notif['status'] === 'sent') echo 'badge-success';
                                                    elseif ($notif['status'] === 'pending') echo 'badge-warning';
                                                    else echo 'badge-danger';
                                                ?>">
                                                    <?php echo ucfirst($notif['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $notif['attempt_count']; ?>/3</td>
                                            <td><?php echo $notif['sent_at'] ? date('M d H:i', strtotime($notif['sent_at'])) : dt('M d H:i', strtotime($notif['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> No notifications found for this section
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
