<?php
/**
 * Header & Authentication Template
 * Include this in all authenticated pages
 * Handles session checking, role-based navigation, and blue/white theme
 */

// Start session only if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/helpers/RoleHelper.php';

// Check session and redirect to login if not authenticated
if (!RoleHelper::isAuthenticated()) {
    header('Location: ../index.php?error=Session expired');
    exit;
}

// Session timeout check (30 minutes)
$timeout = 30 * 60;
if (time() - $_SESSION['login_time'] > $timeout) {
    session_destroy();
    header('Location: ../index.php?error=Session expired');
    exit;
}

// Update last activity
$_SESSION['login_time'] = time();

// Get user info
$user_role = RoleHelper::getRole();
$user_email = RoleHelper::getUserEmail();
$user_section = RoleHelper::getTeacherSection();

// Backfill missing teacher section for older sessions so teacher pages do not
// raise notices when they read the session directly.
if ($user_role === 'teacher' && (!isset($_SESSION['teacher_section']) || trim((string)$_SESSION['teacher_section']) === '')) {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $teacherId = RoleHelper::getUserId();
        if ($teacherId !== null) {
            $stmt = $mysqli->prepare('SELECT section FROM users WHERE id = ? AND role = "teacher" LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $teacherId);
                $stmt->execute();
                $teacherRow = $stmt->get_result()->fetch_assoc();
                $resolvedSection = trim((string)($teacherRow['section'] ?? ''));
                if ($resolvedSection !== '') {
                    $_SESSION['teacher_section'] = $resolvedSection;
                    $user_section = $resolvedSection;
                }
            }
        }
    }

    if (!isset($_SESSION['teacher_section'])) {
        $_SESSION['teacher_section'] = '';
    }
}

// Dynamic page title based on current file
function getPageTitle() {
    $current_file = basename($_SERVER['PHP_SELF'], '.php');
    
    $page_titles = [
        'dashboard' => 'Dashboard',
        'students' => 'Students',
        'register' => 'Student Registration',
        'logs' => 'Attendance Logs',
        'settings' => 'Settings',
        'users' => 'Teachers',
        'my_class' => 'My Class',
        'attendance_report' => 'Attendance Reports',
        'profile' => 'Profile',
        'my_account' => 'My Account'
    ];
    
    return $page_titles[$current_file] ?? 'CAMS';
}

// Dynamic page subtitle
function getPageSubtitle() {
    $current_file = basename($_SERVER['PHP_SELF'], '.php');
    
    $page_subtitles = [
        'dashboard' => 'System overview and attendance statistics',
        'students' => 'Manage student records and information',
        'register' => 'Add new students and enroll fingerprints',
        'logs' => 'View detailed attendance records',
        'settings' => 'System configuration and preferences',
        'users' => 'Manage teacher accounts',
        'my_class' => 'Your assigned students and attendance',
        'attendance_report' => 'Generate and view attendance reports',
        'my_account' => 'Manage your teacher account'
    ];
    
    return $page_subtitles[$current_file] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAMS - Criminology Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
    <style>
    :root {
        --primary-dark: #000000;
        --primary-blue: #ff0000;
        --primary-light: #ffffff;
        --sidebar-gray: #000000;
        --gray-dark: #000000;
        --gray-medium: rgba(0, 0, 0, 0.7);
        --gray-light: #ffffff;
        --gray-lighter: #ffffff;
        --white: #ffffff;
        --success: #ff0000;
        --danger: #ff0000;
        --border: #000000;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    html,
    body {
        height: 100%;
        width: 100%;
    }

    body {
        background: #ffffff;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        margin: 0;
        padding: 0;
        color: #000000;
        overflow-x: hidden;
    }

    .page-wrapper {
        display: flex;
        min-height: 100vh;
    }

    /* Main Content & Header */
    .page-content {
        margin-left: 260px;
        flex: 1;
        display: flex;
        flex-direction: column;
        min-height: 100vh;
        background: #ffffff;
    }

    /* Top Header Bar */
    .topbar {
        background: var(--white);
        border-bottom: 2px solid var(--border);
        padding: 12px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        height: 70px;
        position: sticky;
        top: 0;
        z-index: 999;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }

    .topbar-left {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .page-title {
        font-size: 1.4rem;
        font-weight: 600;
        color: var(--gray-dark);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .page-title i {
        color: var(--primary-blue);
        font-size: 1.2rem;
    }

    .page-subtitle {
        color: var(--gray-medium);
        font-size: 0.9rem;
        margin-top: 4px;
        margin-bottom: 0;
        font-weight: 400;
    }

    .topbar-right {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: #000000;
        border: 1px solid #ff0000;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--white);
        font-weight: 600;
        font-size: 0.85rem;
    }

    .user-details {
        display: flex;
        flex-direction: column;
    }

    .user-email {
        font-size: 0.8rem;
        color: var(--gray-dark);
        font-weight: 500;
    }

    .user-role {
        font-size: 0.7rem;
        color: var(--gray-medium);
        text-transform: capitalize;
    }

    .user-section {
        font-size: 0.7rem;
        color: var(--primary-blue);
        font-weight: 600;
    }

    /* Main Content Area */
    .main-content {
        flex: 1;
        padding: 30px;
        overflow-y: auto;
        overflow-x: hidden;
        max-width: 100%;
        background: #ffffff;
    }

    /* Theme overrides for the red/black/white admin look */
    .main-content .card,
    .card {
        background: #ffffff;
        border: 1px solid #000000;
        border-radius: 14px;
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.08);
        overflow: hidden;
    }

    .main-content .card:hover,
    .card:hover {
        box-shadow: 0 14px 32px rgba(0, 0, 0, 0.12);
    }

    .main-content .card-header,
    .card-header {
        background: #000000;
        color: #ffffff;
        border-bottom: 2px solid #ff0000;
        padding: 16px 20px;
    }

    .main-content .card-body,
    .card-body {
        padding: 20px;
        color: #000000;
    }

    .main-content .card-stat {
        background: #ffffff;
        border: 1px solid #000000;
        border-radius: 14px;
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.08);
    }

    .main-content .card-stat:hover {
        transform: translateY(-4px);
        box-shadow: 0 14px 32px rgba(0, 0, 0, 0.12);
    }

    .main-content .stat-label {
        color: #000000;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-size: 0.78rem;
        font-weight: 700;
    }

    .main-content .stat-number {
        color: #000000;
        font-size: 2rem;
        font-weight: 800;
    }

    .main-content .stat-icon {
        color: #ff0000 !important;
        opacity: 0.18;
    }

    .main-content .card-header h5,
    .main-content .card-header .mb-0 {
        color: #ffffff;
    }

    .main-content .btn,
    .btn {
        border-radius: 10px;
        font-weight: 600;
        padding: 10px 16px;
        box-shadow: none;
    }

    .main-content .btn-primary,
    .btn-primary {
        background: #ff0000;
        border-color: #ff0000;
        color: #ffffff;
    }

    .main-content .btn-primary:hover,
    .btn-primary:hover {
        background: #000000;
        border-color: #000000;
        color: #ffffff;
    }

    .main-content .btn-success,
    .btn-success,
    .main-content .btn-outline-success,
    .btn-outline-success {
        background: #ffffff;
        border-color: #000000;
        color: #000000;
    }

    .main-content .btn-success:hover,
    .btn-success:hover,
    .main-content .btn-outline-success:hover,
    .btn-outline-success:hover {
        background: #ff0000;
        border-color: #ff0000;
        color: #ffffff;
    }

    .main-content .btn-danger,
    .btn-danger,
    .main-content .btn-outline-danger,
    .btn-outline-danger {
        background: #ff0000;
        border-color: #ff0000;
        color: #ffffff;
    }

    .main-content .btn-danger:hover,
    .btn-danger:hover,
    .main-content .btn-outline-danger:hover,
    .btn-outline-danger:hover {
        background: #000000;
        border-color: #000000;
        color: #ffffff;
    }

    .main-content .btn-outline-primary,
    .btn-outline-primary {
        background: #ffffff;
        border-color: #000000;
        color: #000000;
    }

    .main-content .btn-outline-primary:hover,
    .btn-outline-primary:hover {
        background: #ff0000;
        border-color: #ff0000;
        color: #ffffff;
    }

    .main-content .form-control,
    .main-content .form-select,
    .form-control,
    .form-select {
        background: #ffffff;
        border: 1px solid #000000;
        border-radius: 10px;
        color: #000000;
        padding: 10px 14px;
    }

    .main-content .form-control:focus,
    .main-content .form-select:focus,
    .form-control:focus,
    .form-select:focus {
        border-color: #ff0000;
        box-shadow: 0 0 0 3px rgba(255, 0, 0, 0.12);
    }

    .main-content .form-label,
    .form-label {
        font-weight: 700;
        color: #000000;
    }

    .main-content .table,
    .table {
        margin: 0;
        color: #000000;
    }

    .main-content .table thead th,
    .table thead th {
        background: #000000;
        color: #ffffff;
        border-bottom: 2px solid #ff0000;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        padding: 14px 16px;
    }

    .main-content .table tbody tr,
    .table tbody tr {
        border-bottom: 1px solid #000000;
    }

    .main-content .table tbody tr:hover,
    .table tbody tr:hover {
        background: rgba(255, 0, 0, 0.06);
    }

    .main-content .table tbody td,
    .table tbody td {
        padding: 14px 16px;
        vertical-align: middle;
    }

    .main-content .table-light,
    .table-light {
        background: #000000 !important;
        color: #ffffff !important;
    }

    .main-content .badge,
    .badge {
        border-radius: 999px;
        padding: 6px 12px;
        font-weight: 700;
        letter-spacing: 0.02em;
    }

    .main-content .bg-success,
    .bg-success {
        background: #000000 !important;
        color: #ffffff !important;
    }

    .main-content .bg-warning,
    .bg-warning {
        background: #ff0000 !important;
        color: #ffffff !important;
    }

    .main-content .bg-danger,
    .bg-danger {
        background: #ffffff !important;
        color: #000000 !important;
        border: 1px solid #000000;
    }

    .main-content .bg-info,
    .bg-info {
        background: #ffffff !important;
        color: #000000 !important;
        border: 1px solid #ff0000;
    }

    .main-content .bg-secondary,
    .bg-secondary {
        background: #000000 !important;
        color: #ffffff !important;
    }

    .main-content .alert,
    .alert {
        border: 1px solid #000000;
        border-left: 6px solid #ff0000;
        border-radius: 12px;
        background: #ffffff;
        color: #000000;
    }

    .main-content .alert-success,
    .alert-success,
    .main-content .alert-info,
    .alert-info,
    .main-content .alert-danger,
    .alert-danger {
        background: #ffffff;
        color: #000000;
    }

    .main-content .page-title,
    .page-title {
        color: #000000;
    }

    .main-content .page-subtitle,
    .page-subtitle {
        color: rgba(0, 0, 0, 0.75);
    }

    /* Cards & Components */
    .card {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
    }

    .card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
    }

    .card-header {
        background: var(--gray-lighter);
        border-bottom: 1px solid var(--border);
        border-radius: 12px 12px 0 0;
        padding: 16px 20px;
        font-weight: 600;
        color: var(--gray-dark);
    }

    .card-body {
        padding: 20px;
    }

    /* Buttons */
    .btn-primary {
        background: var(--primary-blue);
        border: none;
        color: var(--white);
        border-radius: 8px;
        padding: 10px 18px;
        font-weight: 500;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(37, 99, 235, 0.3);
        color: var(--white);
    }

    .btn-success {
        background: var(--success);
        border: none;
        color: var(--white);
        border-radius: 8px;
        padding: 10px 18px;
        font-weight: 500;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .btn-success:hover {
        background: #059669;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(16, 185, 129, 0.3);
        color: var(--white);
    }

    .btn-danger {
        background: var(--danger);
        border: none;
        color: var(--white);
        border-radius: 8px;
        padding: 10px 18px;
        font-weight: 500;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .btn-danger:hover {
        background: #dc2626;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(239, 68, 68, 0.3);
        color: var(--white);
    }

    /* Forms */
    .form-control {
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 0.9rem;
        background: var(--white);
        transition: all 0.3s ease;
    }

    .form-control:focus {
        background: var(--white);
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .form-label {
        font-weight: 600;
        color: var(--gray-dark);
        margin-bottom: 8px;
        font-size: 0.9rem;
    }

    /* Alerts */
    .alert {
        border: none;
        border-radius: 8px;
        border-left: 4px solid;
        padding: 14px 16px;
        font-size: 0.9rem;
    }

    .alert-success {
        background: #f0fdf4;
        border-color: var(--success);
        color: #166534;
    }

    .alert-danger {
        background: #fee2e2;
        border-color: var(--danger);
        color: #991b1b;
    }

    .alert-info {
        background: #eff6ff;
        border-color: var(--primary-blue);
        color: #1e40af;
    }

    /* Tables */
    .table {
        margin: 0;
        font-size: 0.9rem;
    }

    .table thead th {
        background: var(--gray-lighter);
        border-top: none;
        border-bottom: 2px solid var(--border);
        color: var(--gray-dark);
        font-weight: 600;
        padding: 14px 16px;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .table tbody tr {
        border-bottom: 1px solid var(--border);
        transition: background 0.2s;
    }

    .table tbody tr:hover {
        background: var(--gray-lighter);
    }

    .table tbody td {
        padding: 14px 16px;
        vertical-align: middle;
    }

    /* Badges */
    .badge {
        border-radius: 6px;
        padding: 6px 12px;
        font-weight: 500;
        font-size: 0.75rem;
    }

    .badge-success {
        background: #d1fae5;
        color: #065f46;
    }

    .badge-warning {
        background: #fef3c7;
        color: #92400e;
    }

    .badge-danger {
        background: #fee2e2;
        color: #991b1b;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .page-content {
            margin-left: 240px;
        }
    }

    @media (max-width: 768px) {
        .page-content {
            margin-left: 220px;
        }

        .main-content {
            padding: 20px;
        }

        .topbar {
            padding: 12px 20px;
            height: 65px;
        }

        .page-title {
            font-size: 1.2rem;
        }

        .page-title i {
            font-size: 1.1rem;
        }

        .page-subtitle {
            font-size: 0.85rem;
        }

        .user-details {
            display: none;
        }

        .topbar-right {
            gap: 10px;
        }
    }

    @media (max-width: 640px) {
        .page-content {
            margin-left: 75px;
        }

        .main-content {
            padding: 16px;
        }

        .topbar {
            padding: 10px 16px;
            height: 60px;
        }

        .page-title {
            font-size: 1.1rem;
        }

        .page-title i {
            font-size: 1rem;
        }

        .page-subtitle {
            display: none;
        }
    }

    /* Print Styles */
    @media print {

        .topbar,
        .collapsed-sidebar {
            display: none;
        }

        .page-content {
            margin-left: 0;
        }

        .main-content {
            padding: 0;
        }
    }
    </style>
</head>

<body>
    <div class="page-wrapper">
        <!-- Include Collapsed Sidebar -->
        <?php include dirname(__FILE__) . '/collapsed_sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="page-content">
            <!-- Top Header Bar -->
            <div class="topbar">
                <div class="topbar-left">
                    <h2 class="page-title">
                        <i class="bi bi-<?php 
                            $current_file = basename($_SERVER['PHP_SELF'], '.php');
                            $icons = [
                                'dashboard' => 'speedometer2',
                                'students' => 'people',
                                'register' => 'person-plus',
                                'logs' => 'clock-history',
                                'settings' => 'gear',
                                'users' => 'person-gear',
                                'my_class' => 'mortarboard',
                                'attendance_report' => 'graph-up'
                            ];
                            echo $icons[$current_file] ?? 'house';
                        ?>"></i>
                        <?php echo getPageTitle(); ?>
                    </h2>
                    <?php if (getPageSubtitle()): ?>
                    <div class="page-subtitle"><?php echo getPageSubtitle(); ?></div>
                    <?php endif; ?>
                </div>

                <div class="topbar-right">
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user_email, 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <div class="user-email"><?php echo htmlspecialchars($user_email); ?></div>
                            <div class="user-role"><?php echo htmlspecialchars($user_role); ?></div>
                            <?php if ($user_section): ?>
                            <div class="user-section">Section <?php echo htmlspecialchars($user_section); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Page Content Container -->
            <div class="main-content">

<?php
/*
 * © 2026 TambyTech.
 * This source code is proprietary and confidential.
 * Any unauthorized use, copying, modification, distribution, or disclosure is strictly prohibited.
 * All rights reserved.
 */
?>
