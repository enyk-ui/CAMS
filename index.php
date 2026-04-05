<?php
/**
 * CAMS - Home/Login Page
 * Unified login with system information
 */

session_start();

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['admin_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: teacher/dashboard.php');
    }
    exit;
}

require_once 'config/db.php';

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation
    if (empty($email) || empty($password)) {
        $error = 'Email and password are required';
    } else {
        // Check admin table
        $admin_email = $mysqli->real_escape_string($email);
        $admin_result = $mysqli->query("SELECT id, email, password, full_name FROM admins WHERE email = '$admin_email' AND status = 'active'");

        if ($admin_result && $admin_result->num_rows > 0) {
            $admin = $admin_result->fetch_assoc();
            // Verify password
            if (password_verify($password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_name'] = $admin['full_name'];
                $_SESSION['role'] = 'admin';
                $_SESSION['login_time'] = time();
                header('Location: admin/dashboard.php');
                exit;
            } else {
                $error = 'Invalid email or password';
            }
        } else {
            // Check teacher table
            $teacher_result = $mysqli->query("SELECT id, email, password, full_name, section FROM teachers WHERE email = '$admin_email' AND status = 'active'");

            if ($teacher_result && $teacher_result->num_rows > 0) {
                $teacher = $teacher_result->fetch_assoc();
                // Verify password
                if (password_verify($password, $teacher['password'])) {
                    $_SESSION['admin_id'] = $teacher['id'];
                    $_SESSION['admin_email'] = $teacher['email'];
                    $_SESSION['admin_name'] = $teacher['full_name'];
                    $_SESSION['role'] = 'teacher';
                    $_SESSION['teacher_section'] = $teacher['section'];
                    $_SESSION['login_time'] = time();
                    header('Location: teacher/dashboard.php');
                    exit;
                } else {
                    $error = 'Invalid email or password';
                }
            } else {
                $error = 'Invalid email or password';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAMS - Criminology Attendance Monitoring System</title>
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

        .container-wrapper {
            width: 100%;
            max-width: 1200px;
        }

        .main-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: center;
        }

        /* Information Section */
        .info-section {
            color: #111827;
        }

        .logo-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            animation: slideInLeft 0.6s ease-out;
        }

        .logo-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 2rem;
            color: white;
        }

        .logo-text h1 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            color: #111827;
        }

        .logo-text p {
            font-size: 0.95rem;
            margin: 5px 0 0 0;
            color: #6b7280;
        }

        .system-description {
            margin-bottom: 40px;
            animation: slideInLeft 0.8s ease-out;
        }

        .system-description h2 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            font-weight: 600;
            color: #111827;
        }

        .system-description p {
            font-size: 1rem;
            line-height: 1.6;
            color: #6b7280;
            margin-bottom: 0;
        }

        .features-list {
            animation: slideInLeft 1s ease-out;
        }

        .features-list h3 {
            font-size: 1.1rem;
            margin-bottom: 20px;
            font-weight: 600;
            color: #111827;
        }

        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            transition: transform 0.3s;
        }

        .feature-item:hover {
            transform: translateX(5px);
        }

        .feature-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.2rem;
            flex-shrink: 0;
            color: white;
        }

        .feature-text {
            font-size: 0.95rem;
            color: #6b7280;
        }

        .feature-text strong {
            color: #111827;
        }

        /* Login Form Section */
        .login-section {
            animation: slideInRight 0.6s ease-out;
        }

        .login-card {
            background: white;
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .card-header-custom {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            padding: 30px 25px;
            text-align: center;
        }

        .card-header-custom h3 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .card-header-custom p {
            margin: 8px 0 0 0;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .card-body-custom {
            padding: 35px 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 600;
            color: #111827;
            margin-bottom: 8px;
            display: block;
            font-size: 0.95rem;
        }

        .form-control {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 0.95rem;
            transition: all 0.3s;
            background: #f9fafb;
        }

        .form-control:focus {
            background: white;
            border-color: #2563eb;
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.15);
        }

        .form-control::placeholder {
            color: #9ca3af;
        }

        .btn-login {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            border: none;
            color: white;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            width: 100%;
            margin-top: 10px;
            transition: all 0.3s;
            cursor: pointer;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.4);
            color: white;
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert-custom {
            border: none;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid;
            padding: 15px;
            font-size: 0.95rem;
        }

        .alert-danger {
            background: #fee2e2;
            border-color: #ef4444;
            color: #991b1b;
        }

        .demo-info {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.85rem;
            color: #1e40af;
        }

        .demo-info strong {
            display: block;
            margin-bottom: 8px;
            color: #1e40af;
        }

        .demo-info p {
            margin: 5px 0;
        }

        /* Animations */
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-container {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .info-section {
                text-align: center;
            }

            .logo-header {
                justify-content: center;
                animation: none;
            }

            .system-description,
            .features-list {
                animation: none;
            }

            .feature-item {
                justify-content: center;
                text-align: center;
            }

            .login-section {
                animation: none;
            }

            .logo-text h1 {
                font-size: 1.5rem;
            }

            .system-description h2 {
                font-size: 1.3rem;
            }

            .features-list h3 {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-wrapper">
        <div class="main-container">
            <!-- Left Side: System Information -->
            <div class="info-section">
                <div class="logo-header">
                    <div class="logo-icon">
                        <i class="bi bi-fingerprint"></i>
                    </div>
                    <div class="logo-text">
                        <h1>CAMS</h1>
                        <p>Attendance System</p>
                    </div>
                </div>

                <div class="system-description">
                    <h2>Criminology Attendance Monitoring System</h2>
                    <p>
                        A cutting-edge fingerprint-based attendance solution designed for criminology programs.
                        Combines biometric security with modern web technology for accurate, real-time attendance tracking.
                    </p>
                </div>

                <div class="features-list">
                    <h3><i class="bi bi-check-circle"></i> Key Features</h3>

                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="bi bi-hand-index"></i>
                        </div>
                        <div class="feature-text">
                            <strong>Fingerprint Recognition</strong><br>
                            <small>Secure biometric scanning via ESP8266 device</small>
                        </div>
                    </div>

                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="bi bi-speedometer2"></i>
                        </div>
                        <div class="feature-text">
                            <strong>Real-time Monitoring</strong><br>
                            <small>Live dashboard with attendance statistics</small>
                        </div>
                    </div>

                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="bi bi-envelope"></i>
                        </div>
                        <div class="feature-text">
                            <strong>Email Notifications</strong><br>
                            <small>Automatic confirmation emails with retry mechanism</small>
                        </div>
                    </div>

                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="bi bi-cloud-check"></i>
                        </div>
                        <div class="feature-text">
                            <strong>Multi-Platform</strong><br>
                            <small>Web dashboard + ESP8266 hardware integration</small>
                        </div>
                    </div>

                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="bi bi-graph-up"></i>
                        </div>
                        <div class="feature-text">
                            <strong>Detailed Reports</strong><br>
                            <small>Export attendance data and analytics</small>
                        </div>
                    </div>

                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="bi bi-gear"></i>
                        </div>
                        <div class="feature-text">
                            <strong>Fully Configurable</strong><br>
                            <small>Customize thresholds, schedules, and settings</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Login Form -->
            <div class="login-section">
                <div class="login-card">
                    <div class="card-header-custom">
                        <h3><i class="bi bi-box-arrow-right"></i> Login</h3>
                        <p>Access the attendance system</p>
                    </div>

                    <div class="card-body-custom">
                        <?php if ($error): ?>
                            <div class="alert alert-custom alert-danger" role="alert">
                                <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="form-group">
                                <label for="email" class="form-label">
                                    <i class="bi bi-envelope"></i> Email Address
                                </label>
                                <input
                                    type="email"
                                    class="form-control"
                                    id="email"
                                    name="email"
                                    placeholder="Enter your email"
                                    required
                                    autofocus
                                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="password" class="form-label">
                                    <i class="bi bi-lock"></i> Password
                                </label>
                                <input
                                    type="password"
                                    class="form-control"
                                    id="password"
                                    name="password"
                                    placeholder="Enter your password"
                                    required
                                >
                            </div>

                            <button type="submit" class="btn btn-login">
                                <i class="bi bi-box-arrow-right"></i> Login Now
                            </button>
                        </form>

                        <div class="demo-info">
                            <strong><i class="bi bi-info-circle"></i> Demo Accounts</strong>
                            <p><strong>Admin:</strong> admin@cams.edu.ph / admin123</p>
                            <p><strong>Teacher:</strong> teacher@cams.edu.ph / teacher123</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
