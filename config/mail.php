<?php
/**
 * Mail configuration for CAMS.
 *
 * Reads values from environment first, then falls back to defaults.
 */

if (!function_exists('mail_env')) {
    function mail_env(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }

        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string)$_ENV[$key];
        }

        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return (string)$_SERVER[$key];
        }

        return $default;
    }
}

// Mail Configuration
define('MAIL_FROM_EMAIL', (string)mail_env('MAIL_FROM_EMAIL', 'cams@criminology.edu.ph'));
define('MAIL_FROM_NAME', (string)mail_env('MAIL_FROM_NAME', 'CAMS - Criminology Attendance System'));

// SMTP Server (Gmail example - change for your provider)
define('SMTP_HOST', (string)mail_env('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', (int)mail_env('SMTP_PORT', '587'));
define('SMTP_USERNAME', (string)mail_env('SMTP_USERNAME', ''));
define('SMTP_PASSWORD', (string)mail_env('SMTP_PASSWORD', ''));
define('SMTP_ENCRYPTION', (string)mail_env('SMTP_ENCRYPTION', 'tls')); // 'tls' or 'ssl'
define('MAIL_HEADER_COLOR', (string)mail_env('MAIL_HEADER_COLOR', '#0f172a'));

// Email Templates
define('EMAIL_TEMPLATE_PRESENT', 'attendance_present');
define('EMAIL_TEMPLATE_LATE', 'attendance_late');
define('EMAIL_TEMPLATE_ABSENT', 'attendance_absent');

// Retry Configuration
define('NOTIFICATION_MAX_RETRIES', 3);       // Max retry attempts
define('NOTIFICATION_RETRY_INTERVAL', 300);  // Retry after 5 minutes
define('NOTIFICATION_QUEUE_LIMIT', 50);      // Process max 50 per batch

// Email Features
define('SEND_EMAIL_ON_ATTENDANCE', true);    // Send email for every scan
define('SEND_EMAIL_ON_LATE', true);          // Extra email for late arrivals
define('INCLUDE_QR_CODE_IN_EMAIL', false);   // Include QR code (requires qrcode library)

// Email Content
$EMAIL_TEMPLATES = [
    'attendance_present' => [
        'subject' => 'Attendance Recorded - Present',
        'body' => 'Dear {STUDENT_NAME},<br><br>
Your attendance has been recorded as <strong>PRESENT</strong>.<br>
Time: {ATTENDANCE_TIME}<br>
Date: {ATTENDANCE_DATE}<br><br>
Thank you!<br>
Criminology Attendance Monitoring System'
    ],

    'attendance_late' => [
        'subject' => 'Attention: Late Arrival Recorded',
        'body' => 'Dear {STUDENT_NAME},<br><br>
Your attendance has been recorded as <strong>LATE</strong>.<br>
Time: {ATTENDANCE_TIME}<br>
Date: {ATTENDANCE_DATE}<br><br>
Please be more punctual for future sessions.<br>
Criminology Attendance Monitoring System'
    ],

    'attendance_absent' => [
        'subject' => 'Absence Notice',
        'body' => 'Dear {STUDENT_NAME},<br><br>
You have been recorded as <strong>ABSENT</strong> for today\'s session.<br>
Date: {ATTENDANCE_DATE}<br><br>
Please make up for this absence if applicable.<br>
Criminology Attendance Monitoring System'
    ]
];

/*
 * © 2026 TambyTech.
 * This source code is proprietary and confidential.
 * Any unauthorized use, copying, modification, distribution, or disclosure is strictly prohibited.
 * All rights reserved.
 */