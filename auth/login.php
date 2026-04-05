<?php
/**
 * CAMS - Role Selection & Login
 * Users choose Admin or Teacher login
 */

session_start();

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['admin_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: ../admin/dashboard.php');
    } else {
        header('Location: ../teacher/dashboard.php');
    }
    exit;
}

$error = $_GET['error'] ?? $_GET['message'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAMS - Login Selection</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .container-center {
            width: 100%;
            max-width: 500px;
        }

        .logo-section {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2.5rem;
            color: white;
            box-shadow: 0 10px 30px rgba(37, 99, 235, 0.2);
        }

        .logo-section h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 10px;
        }

        .logo-section p {
            font-size: 0.95rem;
            color: #6b7280;
            margin: 0;
        }

        .alert-custom {
            border: none;
            border-radius: 12px;
            margin-bottom: 30px;
            padding: 15px;
            font-size: 0.95rem;
            display: none;
        }

        .alert-custom.show {
            display: block;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .login-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 30px;
        }

        .login-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            padding: 30px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
        }

        .login-card:hover {
            border-color: #2563eb;
            box-shadow: 0 20px 40px rgba(37, 99, 235, 0.1);
            transform: translateY(-5px);
        }

        .card-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .admin-icon {
            color: #2563eb;
        }

        .teacher-icon {
            color: #10b981;
        }

        .login-card h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 8px;
        }

        .login-card p {
            font-size: 0.85rem;
            color: #6b7280;
            margin: 0;
        }

        .divider {
            text-align: center;
            margin: 40px 0 30px;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e5e7eb;
        }

        .divider span {
            background: #f9fafb;
            padding: 0 10px;
            font-size: 0.85rem;
            color: #9ca3af;
            position: relative;
        }

        .demo-info {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            padding: 20px;
            margin-top: 30px;
            font-size: 0.85rem;
            color: #1e40af;
        }

        .demo-info strong {
            display: block;
            margin-bottom: 10px;
            color: #1e40af;
            font-weight: 600;
        }

        .demo-info p {
            margin: 5px 0;
            padding-left: 10px;
            border-left: 3px solid #2563eb;
        }

        @media (max-width: 600px) {
            .login-options {
                grid-template-columns: 1fr;
            }

            .logo-section h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-center">
        <!-- Logo Section -->
        <div class="logo-section">
            <div class="logo-icon">
                <i class="bi bi-fingerprint"></i>
            </div>
            <h1>CAMS</h1>
            <p>Criminology Attendance Monitoring System</p>
        </div>

        <!-- Error Message -->
        <?php if ($error): ?>
            <div class="alert alert-custom alert-danger show" role="alert">
                <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Login Options -->
        <div class="login-options">
            <a href="admin_login.php" class="login-card">
                <div class="card-icon admin-icon">
                    <i class="bi bi-shield-check"></i>
                </div>
                <h3>Admin</h3>
                <p>System Administrator</p>
            </a>

            <a href="teacher_login.php" class="login-card">
                <div class="card-icon teacher-icon">
                    <i class="bi bi-people"></i>
                </div>
                <h3>Teacher</h3>
                <p>Class Instructor</p>
            </a>
        </div>

        <!-- Demo Credentials -->
        <div class="divider">
            <span>Demo Access</span>
        </div>

        <div class="demo-info">
            <strong><i class="bi bi-info-circle"></i> Test Credentials</strong>
            <p><strong>Admin:</strong> admin@cams.edu.ph / admin123</p>
            <p><strong>Teacher:</strong> teacher@cams.edu.ph / teacher123</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
