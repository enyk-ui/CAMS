<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../../config/mail.php';

if (!class_exists(PHPMailer::class)) {
    $composerAutoload = __DIR__ . '/../../vendor/autoload.php';
    $manualBase = __DIR__ . '/../lib/PHPMailer/src';

    if (is_file($composerAutoload)) {
        require_once $composerAutoload;
    } elseif (is_file($manualBase . '/PHPMailer.php')) {
        require_once $manualBase . '/Exception.php';
        require_once $manualBase . '/PHPMailer.php';
        require_once $manualBase . '/SMTP.php';
    }
}

class Mailer
{
    private PHPMailer $mailer;
    private ?string $embeddedLogoCid = null;

    public function __construct()
    {
        if (!class_exists(PHPMailer::class)) {
            throw new RuntimeException(
                'PHPMailer not found. Install with Composer (vendor/autoload.php) or place files in app/lib/PHPMailer/src.'
            );
        }

        $this->mailer = new PHPMailer(true);
        $this->configure();
    }

    /**
     * Send attendance notification email.
     *
     * @param array<string,mixed> $payload
     */
    public function sendAttendanceNotification(array $payload): bool
    {
        $email = trim((string)($payload['email'] ?? ''));
        $name = trim((string)($payload['name'] ?? 'Student'));
        $type = strtolower(trim((string)($payload['type'] ?? '')));
        $time = trim((string)($payload['time'] ?? ''));
        $status = trim((string)($payload['status'] ?? 'Present'));

        $summary = is_array($payload['attendance_summary'] ?? null) ? $payload['attendance_summary'] : [];
        $summaryTimeIn = trim((string)($summary['time_in'] ?? 'Not yet'));
        $summaryTimeOut = trim((string)($summary['time_out'] ?? 'Not yet'));
        $summaryHours = trim((string)($summary['total_hours'] ?? '-'));

        $subjectType = $type === 'time_out' ? 'Time Out' : 'Time In';
        $subject = 'Attendance Notification - ' . $subjectType;

        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeType = htmlspecialchars($subjectType, ENT_QUOTES, 'UTF-8');
        $safeStatus = htmlspecialchars($status, ENT_QUOTES, 'UTF-8');
        $safeTime = htmlspecialchars($time, ENT_QUOTES, 'UTF-8');
        $safeSummaryIn = htmlspecialchars($summaryTimeIn, ENT_QUOTES, 'UTF-8');
        $safeSummaryOut = htmlspecialchars($summaryTimeOut, ENT_QUOTES, 'UTF-8');
        $safeSummaryHours = htmlspecialchars($summaryHours, ENT_QUOTES, 'UTF-8');
        $safeFromName = htmlspecialchars((string)MAIL_FROM_NAME, ENT_QUOTES, 'UTF-8');
        $safeHeaderColor = htmlspecialchars((string)(defined('MAIL_HEADER_COLOR') ? MAIL_HEADER_COLOR : '#0f172a'), ENT_QUOTES, 'UTF-8');

                $statusLower = strtolower($status);
                $statusColor = '#2563eb';
                $statusBg = '#dbeafe';
                if ($statusLower === 'late') {
                        $statusColor = '#92400e';
                        $statusBg = '#fef3c7';
                } elseif ($statusLower === 'timeout') {
                        $statusColor = '#991b1b';
                        $statusBg = '#fee2e2';
                }

                $logoHtml = '<div style="width:42px;height:42px;border-radius:10px;background:#1e293b;display:flex;align-items:center;justify-content:center;color:#ffffff;font-size:20px;font-weight:700;">&#128376;</div>';
                $logoCid = $this->attachLogoIfAvailable();
                if ($logoCid !== null) {
                        $logoHtml = '<img src="cid:' . htmlspecialchars($logoCid, ENT_QUOTES, 'UTF-8') . '" alt="Logo" style="display:block;width:52px;height:52px;object-fit:contain;border-radius:8px;background:transparent;">';
                }

                $bodyHtml = <<<HTML
<div style="margin:0;padding:24px;background-color:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
        <tr>
            <td style="background:{$safeHeaderColor};padding:14px 24px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr>
                        <td style="width:62px;vertical-align:middle;">{$logoHtml}</td>
                        <td style="vertical-align:middle;">
                            <div style="font-size:18px;line-height:1.3;font-weight:700;color:#ffffff;">Attendance Notification</div>
                            <div style="margin-top:4px;font-size:13px;color:#cbd5e1;">{$safeFromName}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding:24px;">
                <p style="margin:0 0 14px 0;font-size:15px;line-height:1.5;">Hello <strong>{$safeName}</strong>,</p>
                <p style="margin:0 0 16px 0;font-size:14px;line-height:1.6;color:#334155;">Your attendance has been successfully recorded.</p>

                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;margin:0 0 16px 0;">
                    <tr>
                        <td style="padding:10px 12px;border:1px solid #e5e7eb;background:#f8fafc;font-size:13px;font-weight:600;width:40%;">Type</td>
                        <td style="padding:10px 12px;border:1px solid #e5e7eb;font-size:13px;">{$safeType}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 12px;border:1px solid #e5e7eb;background:#f8fafc;font-size:13px;font-weight:600;">Time</td>
                        <td style="padding:10px 12px;border:1px solid #e5e7eb;font-size:13px;">{$safeTime}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 12px;border:1px solid #e5e7eb;background:#f8fafc;font-size:13px;font-weight:600;">Status</td>
                        <td style="padding:10px 12px;border:1px solid #e5e7eb;font-size:13px;">
                            <span style="display:inline-block;padding:4px 10px;border-radius:999px;background:{$statusBg};color:{$statusColor};font-weight:700;">{$safeStatus}</span>
                        </td>
                    </tr>
                </table>

                <div style="margin:18px 0 8px 0;font-size:14px;font-weight:700;color:#0f172a;">Summary</div>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;">
                    <tr>
                        <td style="padding:10px 12px;border:1px solid #e5e7eb;background:#f8fafc;font-size:13px;font-weight:600;width:40%;">Time In</td>
                        <td style="padding:10px 12px;border:1px solid #e5e7eb;font-size:13px;">{$safeSummaryIn}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 12px;border:1px solid #e5e7eb;background:#f8fafc;font-size:13px;font-weight:600;">Time Out</td>
                        <td style="padding:10px 12px;border:1px solid #e5e7eb;font-size:13px;">{$safeSummaryOut}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 12px;border:1px solid #e5e7eb;background:#f8fafc;font-size:13px;font-weight:600;">Total Hours Rendered</td>
                        <td style="padding:10px 12px;border:1px solid #e5e7eb;font-size:13px;">{$safeSummaryHours}</td>
                    </tr>
                </table>

                <p style="margin:18px 0 0 0;font-size:13px;color:#475569;line-height:1.6;">Thank you.</p>
            </td>
        </tr>
    </table>
</div>
HTML;

        $bodyText = "Hello {$name},\n\n"
            . "Your attendance has been recorded:\n\n"
            . "Type: {$subjectType}\n"
            . "Time: {$time}\n"
            . "Status: {$status}\n\n"
            . "Summary:\n"
            . "Time In: {$summaryTimeIn}\n"
            . "Time Out: {$summaryTimeOut}\n"
            . "Total Hours: {$summaryHours}\n\n"
            . "Thank you.";

        $this->mailer->clearAddresses();
        $this->mailer->clearCCs();
        $this->mailer->clearBCCs();
        $this->mailer->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $this->mailer->addAddress($email, $name);
        $this->mailer->Subject = $subject;
        $this->mailer->isHTML(true);
        $this->mailer->Body = $bodyHtml;
        $this->mailer->AltBody = $bodyText;

        return $this->mailer->send();
    }

    private function configure(): void
    {
        $host = SMTP_HOST;
        $port = SMTP_PORT;
        $username = SMTP_USERNAME;
        $password = SMTP_PASSWORD;
        $encryption = strtolower(trim(SMTP_ENCRYPTION));

        if ($host === '') {
            throw new RuntimeException('SMTP_HOST is required.');
        }

        $this->mailer->isSMTP();
        $this->mailer->Host = $host;
        $this->mailer->Port = $port > 0 ? $port : 587;
        $this->mailer->SMTPAuth = ($username !== '' || $password !== '');
        $this->mailer->Username = $username;
        $this->mailer->Password = $password;

        if ($encryption === 'ssl') {
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $this->mailer->SMTPSecure = '';
            $this->mailer->SMTPAutoTLS = false;
        }

        $this->mailer->CharSet = 'UTF-8';
        $this->mailer->Timeout = 15;
    }

    private function attachLogoIfAvailable(): ?string
    {
        $logoPath = __DIR__ . '/../../asset/logo/logo.png';
        if (!is_file($logoPath)) {
            return null;
        }

        if ($this->embeddedLogoCid !== null) {
            return $this->embeddedLogoCid;
        }

        $cid = 'cams-logo-' . md5($logoPath);
        $this->mailer->addEmbeddedImage($logoPath, $cid, 'logo.png', 'base64', 'image/png');
        $this->embeddedLogoCid = $cid;

        return $cid;
    }
}

/*
 * © 2026 TambyTech.
 * This source code is proprietary and confidential.
 * Any unauthorized use, copying, modification, distribution, or disclosure is strictly prohibited.
 * All rights reserved.
 */