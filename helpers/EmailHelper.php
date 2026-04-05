<?php


require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class EmailHelper {
    private $mailer;
    private $lastError = '';

    public function __construct() {
        try {
            $this->mailer = new PHPMailer(true);
            $this->configureMail();
        } catch (Exception $e) {
            $this->lastError = "PHPMailer initialization error: " . $e->getMessage();
            error_log($this->lastError);
        }
    }

    /**
     * Configure PHPMailer based on settings
     */
    private function configureMail() {
        if (defined('USE_SENDMAIL') && USE_SENDMAIL) {
            // Use sendmail
            $this->mailer->isSendmail();
        } elseif (defined('USE_PHP_MAIL') && USE_PHP_MAIL) {
            // Use PHP's mail() function
            $this->mailer->isMail();
        } else {
            // Use SMTP (default)
            $this->mailer->isSMTP();
            $this->mailer->Host = SMTP_HOST;
            $this->mailer->Port = SMTP_PORT;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = SMTP_USERNAME;
            $this->mailer->Password = SMTP_PASSWORD;
            $this->mailer->SMTPSecure = SMTP_ENCRYPTION;

            // Debug level (0 = no debug, 1-4 = increasing verbosity)
            // $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
        }

        // Set charset
        $this->mailer->CharSet = 'UTF-8';

        // Timeout
        $this->mailer->Timeout = 10;
    }

    /**
     * Send attendance email
     *
     * @param string $studentEmail
     * @param string $studentName
     * @param string $status (present, late, absent)
     * @param string $attendanceTime
     * @param string $attendanceDate
     * @return bool
     */
    public function sendAttendanceEmail($studentEmail, $studentName, $status, $attendanceTime, $attendanceDate) {
        try {
            // Clear previous recipients
            $this->mailer->clearAddresses();
            $this->mailer->clearCCs();
            $this->mailer->clearBCCs();

            // Set From
            $this->mailer->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);

            // Set To
            $this->mailer->addAddress($studentEmail, $studentName);

            // Get template
            $templateKey = 'attendance_' . strtolower($status);
            if (!isset($GLOBALS['EMAIL_TEMPLATES'][$templateKey])) {
                $this->lastError = "Template not found: $templateKey";
                return false;
            }

            $template = $GLOBALS['EMAIL_TEMPLATES'][$templateKey];

            // Replace placeholders
            $subject = str_replace(
                ['{STUDENT_NAME}', '{ATTENDANCE_TIME}', '{ATTENDANCE_DATE}'],
                [$studentName, $attendanceTime, $attendanceDate],
                $template['subject']
            );

            $body = str_replace(
                ['{STUDENT_NAME}', '{ATTENDANCE_TIME}', '{ATTENDANCE_DATE}'],
                [$studentName, $attendanceTime, $attendanceDate],
                $template['body']
            );

            // Set Subject
            $this->mailer->Subject = $subject;

            // Set Body (HTML)
            $this->mailer->isHTML(true);
            $this->mailer->Body = $body;

            // Alternative plain text version
            $this->mailer->AltBody = strip_tags($body);

            // Send
            $sent = $this->mailer->send();

            if ($sent) {
                error_log("Email sent to $studentEmail - Status: $status");
                return true;
            }

            $this->lastError = "Failed to send email: " . $this->mailer->ErrorInfo;
            error_log($this->lastError);
            return false;

        } catch (Exception $e) {
            $this->lastError = "Email exception: " . $e->getMessage();
            error_log($this->lastError);
            return false;
        }
    }

    /**
     * Send custom email
     *
     * @param string $toEmail
     * @param string $toName
     * @param string $subject
     * @param string $body (HTML)
     * @return bool
     */
    public function sendCustomEmail($toEmail, $toName, $subject, $body) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            $this->mailer->addAddress($toEmail, $toName);
            $this->mailer->Subject = $subject;
            $this->mailer->isHTML(true);
            $this->mailer->Body = $body;
            $this->mailer->AltBody = strip_tags($body);

            return $this->mailer->send();

        } catch (Exception $e) {
            $this->lastError = "Custom email exception: " . $e->getMessage();
            error_log($this->lastError);
            return false;
        }
    }

    /**
     * Get last error message
     */
    public function getLastError() {
        return $this->lastError;
    }

    /**
     * Test email configuration
     */
    public function testConfiguration() {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            $this->mailer->addAddress(MAIL_FROM_EMAIL);
            $this->mailer->Subject = "CAMS Email System Test";
            $this->mailer->isHTML(true);
            $this->mailer->Body = "This is a test email from CAMS system.";

            return $this->mailer->send();

        } catch (Exception $e) {
            $this->lastError = "Test failed: " . $e->getMessage();
            return false;
        }
    }
}