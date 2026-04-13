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
$indexLogoRelativePath = 'asset/logo/logo.png';
$indexLogoAbsolutePath = __DIR__ . '/asset/logo/logo.png';
$indexHasLogo = is_file($indexLogoAbsolutePath);

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginIdentifier = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation
    if (empty($loginIdentifier) || empty($password)) {
        $error = 'Username/email and password are required';
    } else {
        // Check users table (supports both admin and teacher roles)
        $stmt = $mysqli->prepare("SELECT id, username, password, full_name, email, role FROM users WHERE (username = ? OR email = ?) AND status = 'active'");
        $stmt->bind_param('ss', $loginIdentifier, $loginIdentifier);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            // Verify password
            if (password_verify($password, $user['password'])) {
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_email'] = $user['email'] ?? $user['username'];
                $_SESSION['admin_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();
                
                if ($user['role'] === 'admin') {
                    header('Location: admin/dashboard.php');
                } else {
                    header('Location: teacher/dashboard.php');
                }
                exit;
            } else {
                $error = 'Invalid username or password';
            }
        } else {
            $error = 'Invalid username or password';
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background:
                radial-gradient(circle at top left, rgba(239, 68, 68, 0.22), transparent 28%),
                radial-gradient(circle at bottom right, rgba(248, 113, 113, 0.18), transparent 24%),
                linear-gradient(135deg, #0b0b0d 0%, #151518 45%, #f8f8f8 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'Montserrat', sans-serif;
            color: #111111;
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

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.14);
            color: #f8f8f8;
            font-size: 0.82rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 24px;
        }

        /* Information Section */
        .info-section {
            color: #f8f8f8;
        }

        .logo-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            animation: slideInLeft 0.6s ease-out;
        }

        .logo-icon {
            width: 74px;
            height: 74px;
            background: linear-gradient(135deg, #ef4444 0%, #7f1d1d 100%);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 2.2rem;
            color: white;
        }

        .logo-icon img {
            width: 68px;
            height: 68px;
            object-fit: contain;
            border-radius: 10px;
            background: transparent;
            padding: 0;
        }

        .logo-text h1 {
            font-size: 2rem;
            font-weight: 800;
            margin: 0;
            color: #ffffff;
            letter-spacing: 0.02em;
        }

        .logo-text p {
            font-size: 0.95rem;
            margin: 5px 0 0 0;
            color: rgba(255, 255, 255, 0.75);
        }

        .system-description {
            margin-bottom: 40px;
            animation: slideInLeft 0.8s ease-out;
        }

        .system-description h2 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            font-weight: 800;
            color: #ffffff;
        }

        .system-description p {
            font-size: 1rem;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.72);
            margin-bottom: 0;
        }

        .features-list {
            animation: slideInLeft 1s ease-out;
        }

        .features-list h3 {
            font-size: 1.1rem;
            margin-bottom: 20px;
            font-weight: 700;
            color: #ffffff;
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
            background: linear-gradient(135deg, #ef4444 0%, #7f1d1d 100%);
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
            color: rgba(255, 255, 255, 0.72);
        }

        .feature-text strong {
            color: #ffffff;
        }

        /* Login Form Section */
        .login-section {
            animation: slideInRight 0.6s ease-out;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(255, 255, 255, 0.65);
            border-radius: 24px;
            box-shadow: 0 30px 70px rgba(0, 0, 0, 0.35);
            overflow: hidden;
            backdrop-filter: blur(16px);
        }

        .card-header-custom {
            background:
                linear-gradient(135deg, rgba(17, 17, 17, 0.92) 0%, rgba(51, 51, 51, 0.92) 55%, rgba(239, 68, 68, 0.94) 100%);
            color: white;
            padding: 34px 28px;
            text-align: center;
        }

        .card-header-custom h3 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 800;
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
            padding: 34px 28px 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 700;
            color: #111111;
            margin-bottom: 8px;
            display: block;
            font-size: 0.95rem;
        }

        .form-control {
            border: 1px solid #d1d5db;
            border-radius: 12px;
            padding: 12px 15px;
            font-size: 0.95rem;
            transition: all 0.3s;
            background: #ffffff;
            color: #111111;
        }

        .form-control:focus {
            background: white;
            border-color: #ef4444;
            box-shadow: 0 0 0 0.2rem rgba(239, 68, 68, 0.16);
        }

        .form-control::placeholder {
            color: #9ca3af;
        }

        .btn-login {
            background: linear-gradient(135deg, #111111 0%, #ef4444 100%);
            border: none;
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 0.95rem;
            width: 100%;
            margin-top: 10px;
            transition: all 0.3s;
            cursor: pointer;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 28px rgba(239, 68, 68, 0.34);
            color: white;
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert-custom {
            border: none;
            border-radius: 12px;
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
            background: linear-gradient(135deg, rgba(17, 17, 17, 0.04), rgba(239, 68, 68, 0.08));
            border: 1px solid rgba(17, 17, 17, 0.08);
            border-radius: 12px;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.85rem;
            color: #111111;
        }

        .demo-info strong {
            display: block;
            margin-bottom: 8px;
            color: #ef4444;
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
                <div class="hero-badge">
                    <i class="bi bi-shield-lock"></i>
                    Secure access for admins and teachers
                </div>

                <div class="logo-header">
                    <div class="logo-icon">
                        <?php if ($indexHasLogo): ?>
                            <img src="<?php echo htmlspecialchars($indexLogoRelativePath); ?>" alt="CAMS Logo">
                        <?php else: ?>
                            <i class="fa-solid fa-fingerprint"></i>
                        <?php endif; ?>
                    </div>
                    <div class="logo-text">
                        <h1>CAMS</h1>
                        <p>Attendance System</p>
                    </div>
                </div>

                <div class="system-description">
                    <h2>Criminology Attendance Monitoring System</h2>
                    <p>
                        A fingerprint-based attendance platform built for criminology programs.
                        It pairs biometric tracking with a sharp red, black, and white interface for fast daily access.
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
                                <label for="login" class="form-label">
                                    <i class="bi bi-person-badge"></i> Username or Email
                                </label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="login"
                                    name="login"
                                    placeholder="Enter your username or email"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>"
                                >
                                <small class="text-muted d-block mt-2">Use either your username or your registered email address.</small>
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

<?php
/*
 * © 2026 TambyTech.
 * This source code is proprietary and confidential.
 * Any unauthorized use, copying, modification, distribution, or disclosure is strictly prohibited.
 * All rights reserved.
 */
?>
