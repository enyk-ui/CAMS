<?php
/**
 * CAMS - Login Page
 * Authentication system with session handling
 */

session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email and password are required';
    } else {
        // Check admin credentials (you can add custom admin table or use hardcoded for now)
        // For production, create an 'admins' table with hashed passwords

        // Simple hardcoded admin for testing (CHANGE THESE!)
        $valid_email = 'admin@cams.edu.ph';
        $valid_password = 'admin123';  // In production, use properly hashed password

        if ($email === $valid_email && $password === $valid_password) {
            $_SESSION['admin_id'] = 1;
            $_SESSION['admin_email'] = $email;
            $_SESSION['login_time'] = time();

            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CAMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            max-width: 400px;
            width: 100%;
            padding: 20px;
        }

        .login-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }

        .login-header h2 {
            margin: 0;
            font-weight: bold;
        }

        .login-header p {
            margin: 5px 0 0 0;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .login-body {
            padding: 30px;
        }

        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 1px solid #dee2e6;
            margin-bottom: 15px;
            font-size: 0.95rem;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 10px;
            width: 100%;
            transition: all 0.3s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 20px;
        }

        .demo-info {
            background: #f0f4ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 0.9rem;
        }

        .demo-info strong {
            display: block;
            margin-bottom: 8px;
            color: #667eea;
        }

        .demo-info p {
            margin: 0;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card login-card">
            <div class="login-header">
                <h2><i class="bi bi-fingerprint"></i> CAMS</h2>
                <p>Criminology Attendance Monitoring System</p>
            </div>

            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email"
                               placeholder="admin@cams.edu.ph" required autofocus
                               value="<?php echo htmlspecialchars($email ?? ''); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Enter password" required>
                    </div>

                    <button type="submit" class="btn btn-login">
                        <i class="bi bi-box-arrow-right"></i> Login
                    </button>
                </form>

                <!-- Demo Info -->
                <div class="demo-info">
                    <strong><i class="bi bi-info-circle"></i> Demo Credentials:</strong>
                    <p><strong>Email:</strong> admin@cams.edu.ph</p>
                    <p><strong>Password:</strong> admin123</p>
                    <p style="font-size: 0.85rem; margin-top: 8px; color: #888;">
                        ⚠️ Change these in production!
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
/*
 * � 2026 TambyTech.
 * This source code is proprietary and confidential.
 * Any unauthorized use, copying, modification, distribution, or disclosure is strictly prohibited.
 * All rights reserved.
 */
?>
