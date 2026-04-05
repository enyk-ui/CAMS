<?php
/**
 * PHPMailer Configuration
 * Email settings for CAMS system
 */

// Mail Configuration
define('MAIL_FROM_EMAIL', 'cams@criminology.edu.ph');
define('MAIL_FROM_NAME', 'CAMS - Criminology Attendance System');

// SMTP Server (Gmail example - change for your provider)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');  // Gmail: Use App Password, not regular password
define('SMTP_ENCRYPTION', 'tls');              // 'tls' or 'ssl'

// Alternative: Use sendmail (localhost mail server)
// Uncomment below and comment out SMTP settings above
// define('USE_SENDMAIL', true);

// Fallback: Use PHP's mail() function
// define('USE_PHP_MAIL', true);

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
