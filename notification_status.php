<?php
/**
 * Notification Queue Status Dashboard
 * Monitor email notification queue status
 * Access: http://localhost/CAMS/notification_status.php
 */

require_once 'config/db.php';
require_once 'helpers/NotificationQueueHelper.php';

// Check authentication (add your own auth here if needed)
// session_start();
// if (!isset($_SESSION['admin'])) { header('Location: login.php'); exit; }

$queueHelper = new NotificationQueueHelper($mysqli);

// Get statistics
$stats = $queueHelper->getQueueStats();

// Get failed notifications
$failedNotifications = $queueHelper->getFailedNotifications(20);

// Handle manual retry trigger
$processResult = null;
if (isset($_POST['trigger_process'])) {
    // Include process_notifications logic here or call via HTTP
    $ch = curl_init('http://localhost/CAMS/process_notifications.php?api_key=your-secret-key');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    curl_close($ch);
    $processResult = json_decode($response, true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Queue Status - CAMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            border: none;
        }
        .stat-box {
            text-align: center;
            padding: 20px;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            text-transform: uppercase;
        }
        .stat-pending { color: #ffc107; }
        .stat-sent { color: #28a745; }
        .stat-failed { color: #dc3545; }
        .badge-pending { background-color: #ffc107; }
        .badge-sent { background-color: #28a745; }
        .badge-failed { background-color: #dc3545; }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        .btn-process {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-process:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .alert-custom {
            border-radius: 8px;
            border-left: 4px solid;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0"><i class="bi bi-envelope"></i> Email Notification Queue Status</h3>
            </div>
            <div class="card-body">
                <p class="text-muted">Last updated: <strong><?php echo date('Y-m-d H:i:s'); ?></strong></p>

                <!-- Process Result Alert -->
                <?php if ($processResult): ?>
                    <div class="alert alert-success alert-custom" role="alert">
                        <h6 class="alert-heading">Queue Processor Result</h6>
                        <?php if ($processResult['success']): ?>
                            <p><strong>Message:</strong> <?php echo $processResult['message']; ?></p>
                            <p><strong>Processed:</strong> <?php echo $processResult['processed']; ?> notifications</p>
                            <p><strong>Sent:</strong> <?php echo $processResult['sent']; ?> | <strong>Failed:</strong> <?php echo $processResult['failed']; ?></p>
                        <?php else: ?>
                            <p class="text-danger"><strong>Error:</strong> <?php echo $processResult['error']; ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-box">
                            <div class="stat-number stat-pending"><?php echo $stats['pending'] ?? 0; ?></div>
                            <div class="stat-label">Pending</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <div class="stat-number stat-sent"><?php echo $stats['sent'] ?? 0; ?></div>
                            <div class="stat-label">Sent</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <div class="stat-number stat-failed"><?php echo $stats['failed'] ?? 0; ?></div>
                            <div class="stat-label">Failed</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                            <div class="stat-label">Total</div>
                        </div>
                    </div>
                </div>

                <!-- Manual Process Button -->
                <form method="POST" class="mb-4">
                    <button type="submit" name="trigger_process" class="btn btn-process">
                        <i class="bi bi-arrow-repeat"></i> Process Queue Now
                    </button>
                    <small class="text-muted ms-2">(Manually retry pending notifications)</small>
                </form>

            </div>
        </div>

        <!-- Failed Notifications -->
        <?php if (!empty($failedNotifications)): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-exclamation-circle"></i> Failed Notifications (Requires Manual Review)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Student ID</th>
                                <th>Email Subject</th>
                                <th>Attempts</th>
                                <th>Created</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($failedNotifications as $notif): ?>
                            <tr>
                                <td>#<?php echo $notif['id']; ?></td>
                                <td><?php echo $notif['student_id']; ?></td>
                                <td><?php echo htmlspecialchars($notif['subject']); ?></td>
                                <td><span class="badge bg-warning"><?php echo $notif['attempt_count']; ?></span></td>
                                <td><?php echo date('M d, Y H:i', strtotime($notif['created_at'])); ?></td>
                                <td><span class="badge badge-failed">Failed</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Information -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Information</h5>
            </div>
            <div class="card-body">
                <p><strong>Queue Processing:</strong></p>
                <ul>
                    <li>Pending notifications are retried automatically via cron job (every 5 minutes)</li>
                    <li>Max retries: <strong>3 attempts</strong></li>
                    <li>Failed after max retries marked as <strong>Failed</strong></li>
                    <li>You can manually trigger processing above</li>
                </ul>

                <p class="mt-3"><strong>Common Issues:</strong></p>
                <ul>
                    <li><strong>SMTP Authentication Error:</strong> Check email credentials in config/mail.php</li>
                    <li><strong>Connection Timeout:</strong> Verify firewall allows SMTP port (usually 587)</li>
                    <li><strong>Many Failed Notifications:</strong> Review PHP error logs</li>
                </ul>

                <p class="mt-3"><strong>Setup Cron Job (Linux/Mac):</strong></p>
                <code>*/5 * * * * curl -s http://localhost/CAMS/process_notifications.php?api_key=your-secret-key > /dev/null 2>&1</code>

                <p class="mt-3"><strong>View Logs:</strong></p>
                <code><?php echo ini_get('error_log') ?: '/var/log/php-errors.log'; ?></code>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
