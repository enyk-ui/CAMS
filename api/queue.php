<?php
/**
 * Process Notification Queue - Retry Failed Emails
 * Run this via cron job or manually: curl http://localhost/CAMS/process_notifications.php
 *
 * Cron example (every 5 minutes):
 * */5 * * * * curl -s http://localhost/CAMS/process_notifications.php > /dev/null 2>&1
 *
 * Or using PHP's built-in server check interval (set in cron to check every minute)
 */

// Only allow API access or cron requests
if (php_sapi_name() !== 'cli') {
    // Via HTTP - check if authorized
    $apiKey = $_GET['api_key'] ?? $_POST['api_key'] ?? '';
    if (empty($apiKey) || $apiKey !== 'your-secret-api-key') {
        header('HTTP/1.1 401 Unauthorized');
        die(json_encode(['error' => 'Unauthorized']));
    }
}

require_once '../config/db.php';
require_once '../helpers/EmailHelper.php';
require_once '../helpers/NotificationQueueHelper.php';
require_once 'config/mail.php';

header('Content-Type: application/json');

try {
    // Initialize helpers
    $emailHelper = new EmailHelper();
    $queueHelper = new NotificationQueueHelper($mysqli);

    // Get stats before processing
    $statsBefore = $queueHelper->getQueueStats();

    // Get pending notifications
    $pendingNotifications = $queueHelper->getPendingNotifications();

    if (empty($pendingNotifications)) {
        echo json_encode([
            'success' => true,
            'message' => 'No pending notifications',
            'stats' => $statsBefore
        ]);
        exit;
    }

    $processed = 0;
    $sent = 0;
    $failed = 0;
    $errors = [];

    // Process each pending notification
    foreach ($pendingNotifications as $notification) {
        $notificationId = $notification['id'];
        $studentId = $notification['student_id'];
        $email = $notification['email'];
        $subject = $notification['subject'];
        $message = $notification['message'];
        $attemptCount = $notification['attempt_count'];

        // Send email
        $success = $emailHelper->sendCustomEmail(
            $email,
            $studentId,
            $subject,
            $message
        );

        // Update queue status
        $queueHelper->updateNotificationStatus($notificationId, $success);

        if ($success) {
            $sent++;
            error_log("Notification $notificationId sent successfully (attempt " . ($attemptCount + 1) . ")");
        } else {
            $failed++;
            $errors[] = [
                'notification_id' => $notificationId,
                'student_id' => $studentId,
                'error' => $emailHelper->getLastError(),
                'attempt' => $attemptCount + 1
            ];
            error_log("Notification $notificationId failed (attempt " . ($attemptCount + 1) . "): " . $emailHelper->getLastError());
        }

        $processed++;
    }

    // Cleanup old notifications
    $cleaned = $queueHelper->cleanupOldNotifications();

    // Get stats after processing
    $statsAfter = $queueHelper->getQueueStats();

    echo json_encode([
        'success' => true,
        'message' => "Processed $processed notifications",
        'processed' => $processed,
        'sent' => $sent,
        'failed' => $failed,
        'cleaned' => $cleaned,
        'stats_before' => $statsBefore,
        'stats_after' => $statsAfter,
        'errors' => $errors
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    error_log("Process notifications exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
