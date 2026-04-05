<?php
/**
 * Sidebar Component
 * Clean, modern sidebar with icons and labels
 */
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <i class="bi bi-fingerprint"></i>
            <span>CAMS</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php
        $current_page = basename($_SERVER['PHP_SELF']);
        $role = $_SESSION['role'] ?? 'admin';
        ?>

        <?php if ($role === 'admin'): ?>
            <!-- Admin Navigation -->
            <a href="dashboard.php" class="nav-item <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-line"></i>
                <span class="nav-label">Dashboard</span>
            </a>

            <a href="students.php" class="nav-item <?php echo $current_page === 'students.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-users"></i>
                <span class="nav-label">Students</span>
            </a>

            <a href="register.php" class="nav-item <?php echo $current_page === 'register.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-user-plus"></i>
                <span class="nav-label">Registration</span>
            </a>

            <a href="logs.php" class="nav-item <?php echo $current_page === 'logs.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-clock"></i>
                <span class="nav-label">Attendance Logs</span>
            </a>

            <a href="users.php" class="nav-item <?php echo $current_page === 'users.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-user-tie"></i>
                <span class="nav-label">Users</span>
            </a>

            <a href="settings.php" class="nav-item <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-gear"></i>
                <span class="nav-label">Settings</span>
            </a>

            <a href="notifications.php" class="nav-item <?php echo $current_page === 'notifications.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-bell"></i>
                <span class="nav-label">Notifications</span>
            </a>

        <?php elseif ($role === 'teacher'): ?>
            <!-- Teacher Navigation -->
            <a href="dashboard.php" class="nav-item <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-line"></i>
                <span class="nav-label">Dashboard</span>
            </a>

            <a href="my_class.php" class="nav-item <?php echo $current_page === 'my_class.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-users"></i>
                <span class="nav-label">My Class</span>
            </a>

            <a href="attendance_report.php" class="nav-item <?php echo $current_page === 'attendance_report.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-bar"></i>
                <span class="nav-label">Reports</span>
            </a>

            <a href="notifications.php" class="nav-item <?php echo $current_page === 'notifications.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-bell"></i>
                <span class="nav-label">Notifications</span>
            </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="../auth/logout.php" class="nav-item logout">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span class="nav-label">Logout</span>
        </a>
    </div>
</aside>

<style>
/* Sidebar */
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 260px;
    height: 100vh;
    background: #2c3e50;
    display: flex;
    flex-direction: column;
    z-index: 1000;
    overflow-y: auto;
    box-shadow: 4px 0 12px rgba(0, 0, 0, 0.1);
}

.sidebar-header {
    padding: 24px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-brand {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
}

.sidebar-brand i {
    width: 48px;
    height: 48px;
    background: #3b82f6;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
}

.sidebar-brand span {
    font-size: 1.4rem;
    font-weight: 700;
    color: white;
    letter-spacing: 1px;
}

.sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
    padding: 20px 16px;
}

.nav-item {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 14px;
    padding: 14px 16px;
    border-radius: 10px;
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    transition: all 0.2s ease;
}

.nav-item i {
    font-size: 1.15rem;
    width: 22px;
    text-align: center;
}

.nav-label {
    font-size: 0.95rem;
    font-weight: 500;
}

.nav-item:hover {
    background: rgba(59, 130, 246, 0.15);
    color: #93c5fd;
}

.nav-item.active {
    background: #3b82f6;
    color: white;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.nav-item.logout:hover {
    background: rgba(239, 68, 68, 0.15);
    color: #fca5a5;
}

.sidebar-footer {
    padding: 16px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

/* Scrollbar styling */
.sidebar::-webkit-scrollbar {
    width: 5px;
}

.sidebar::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}

/* Responsive design */
@media (max-width: 1024px) {
    .sidebar {
        width: 240px;
    }
}

@media (max-width: 768px) {
    .sidebar {
        width: 220px;
    }
}

@media (max-width: 640px) {
    .sidebar {
        width: 75px;
    }

    .sidebar-header {
        padding: 16px 12px;
    }

    .sidebar-brand span {
        display: none;
    }

    .sidebar-brand i {
        width: 42px;
        height: 42px;
        font-size: 1.3rem;
    }

    .nav-item {
        justify-content: center;
        padding: 12px;
    }

    .nav-label {
        display: none;
    }

    .nav-item i {
        font-size: 1.3rem;
    }
}
</style>
