<?php
/**
 * Notification Queue Helper
 * Manages failed email notifications for retry
 */

require_once __DIR__ . '/../config/mail.php';

class NotificationQueueHelper {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    /**
     * Add message to notification queue
     *
     * @param int $studentId
     * @param string $message (Email body)
     * @param string $subject
     * @param string $email
     * @return bool
     */
    public function enqueueNotification($studentId, $message, $subject, $email) {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO notification_queue (student_id, message, subject, email, status, attempt_count, created_at, next_retry_at)
                 VALUES (?, ?, ?, ?, 'pending', 0, NOW(), DATE_ADD(NOW(), INTERVAL ? MINUTE))"
            );

            $retryInterval = NOTIFICATION_RETRY_INTERVAL / 60;  // Convert to minutes
            $stmt->bind_param(
                "isssd",
                $studentId,
                $message,
                $subject,
                $email,
                $retryInterval
            );

            $result = $stmt->execute();
            $stmt->close();

            if ($result) {
                error_log("Notification queued for student $studentId");
                return true;
            }

            error_log("Failed to queue notification: " . $this->db->error);
            return false;

        } catch (Exception $e) {
            error_log("Notification queue exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get pending notifications for retry
     *
     * @return array of notifications
     */
    public function getPendingNotifications() {
        try {
            $query = "SELECT id, student_id, message, subject, email, attempt_count
                      FROM notification_queue
                      WHERE status = 'pending'
                      AND attempt_count < " . NOTIFICATION_MAX_RETRIES . "
                      AND next_retry_at <= NOW()
                      ORDER BY created_at ASC
                      LIMIT " . NOTIFICATION_QUEUE_LIMIT;

            $result = $this->db->query($query);

            if (!$result) {
                error_log("Query error: " . $this->db->error);
                return [];
            }

            $notifications = [];
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }

            $result->free();
            return $notifications;

        } catch (Exception $e) {
            error_log("Get pending notifications exception: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update notification status after send attempt
     *
     * @param int $notificationId
     * @param bool $success
     * @return bool
     */
    public function updateNotificationStatus($notificationId, $success) {
        try {
            if ($success) {
                // Mark as sent
                $stmt = $this->db->prepare(
                    "UPDATE notification_queue
                     SET status = 'sent', sent_at = NOW()
                     WHERE id = ?"
                );
            } else {
                // Mark as failed and schedule retry
                $retryInterval = NOTIFICATION_RETRY_INTERVAL / 60;
                $stmt = $this->db->prepare(
                    "UPDATE notification_queue
                     SET attempt_count = attempt_count + 1,
                         next_retry_at = DATE_ADD(NOW(), INTERVAL ? MINUTE),
                         status = CASE
                            WHEN attempt_count >= ? THEN 'failed'
                            ELSE 'pending'
                         END
                     WHERE id = ?"
                );
                $stmt->bind_param("ddi", $retryInterval, NOTIFICATION_MAX_RETRIES, $notificationId);
            }

            if (!$success) {
                $stmt->execute();
            } else {
                $stmt->bind_param("i", $notificationId);
                $stmt->execute();
            }

            $stmt->close();
            return true;

        } catch (Exception $e) {
            error_log("Update notification exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get queue statistics
     *
     * @return array with counts
     */
    public function getQueueStats() {
        try {
            $query = "SELECT
                        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                        COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent,
                        COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
                        COUNT(*) as total
                      FROM notification_queue
                      WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";

            $result = $this->db->query($query);

            if (!$result) {
                error_log("Query error: " . $this->db->error);
                return [];
            }

            $stats = $result->fetch_assoc();
            $result->free();

            return $stats;

        } catch (Exception $e) {
            error_log("Queue stats exception: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Clear old sent notifications (older than 30 days)
     *
     * @return int number of rows deleted
     */
    public function cleanupOldNotifications() {
        try {
            $stmt = $this->db->prepare(
                "DELETE FROM notification_queue
                 WHERE status = 'sent'
                 AND sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );

            $result = $stmt->execute();
            $affectedRows = $this->db->affected_rows;
            $stmt->close();

            error_log("Cleanup: Deleted $affectedRows old notifications");
            return $affectedRows;

        } catch (Exception $e) {
            error_log("Cleanup exception: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get failed notifications for manual review
     *
     * @return array
     */
    public function getFailedNotifications($limit = 50) {
        try {
            $stmt = $this->db->prepare(
                "SELECT id, student_id, subject, email, attempt_count, created_at
                 FROM notification_queue
                 WHERE status = 'failed'
                 ORDER BY created_at DESC
                 LIMIT ?"
            );

            $stmt->bind_param("i", $limit);
            $stmt->execute();
            $result = $stmt->get_result();

            $notifications = [];
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }

            $stmt->close();
            return $notifications;

        } catch (Exception $e) {
            error_log("Get failed notifications exception: " . $e->getMessage());
            return [];
        }
    }
}

/*
 * © 2026 TambyTech.
 * This source code is proprietary and confidential.
 * Any unauthorized use, copying, modification, distribution, or disclosure is strictly prohibited.
 * All rights reserved.
 */