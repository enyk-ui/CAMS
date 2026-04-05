<?php
/**
 * CAMS - Admin Login
 * Administrator authentication
 */

session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_id'])) {
    header('Location: ../admin/dashboard.php');
    exit;
}

require_once '../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation
    if (empty($email) || empty($password)) {
        $error = 'Email and password are required';
    } else {
        // Demo hardcoded admin (change in production to use database with hashed passwords)
        $valid_email = 'admin@cams.edu.ph';
        $valid_password = 'admin123';

        if ($email === $valid_email && $password === $valid_password) {
            $_SESSION['admin_id'] = 1;
            $_SESSION['admin_email'] = $email;
            $_SESSION['role'] = 'admin';
            $_SESSION['login_time'] = time();

            header('Location: ../admin/dashboard.php');
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
    <title>CAMS - Admin Login</title>
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
            max-width: 420px;
        }

        .back-link {
            margin-bottom: 20px;
        }

        .back-link a {
            color: #6b7280;
            text-decoration: none;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: color 0.3s;
        }

        .back-link a:hover {
            color: #2563eb;
        }

        .logo-section {
            text-align: center;
            margin-bottom: 35px;
        }

        .logo-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2rem;
            color: white;
        }

        .logo-section h2 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 5px;
        }

        .logo-section p {
            font-size: 0.85rem;
            color: #6b7280;
            margin: 0;
        }

        .login-card {
            background: white;
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            margin-top: 30px;
        }

        .card-header-custom {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            padding: 25px;
            text-align: center;
        }

        .card-header-custom h3 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
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
            font-size: 0.9rem;
        }

        .form-control {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 0.95rem;
            transition: all 0.3s;
            background: #f9fafb;
        }

        .form-control:focus {
            background: white;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
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
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.3);
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
            font-size: 0.9rem;
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
            font-size: 0.8rem;
            color: #1e40af;
        }

        .demo-info strong {
            display: block;
            margin-bottom: 8px;
            color: #1e40af;
            font-weight: 600;
        }

        .demo-info p {
            margin: 4px 0;
        }
    </style>
</head>
<body>
    <div class="container-center">
        <!-- Back Link -->
        <div class="back-link">
            <a href="login.php">
                <i class="bi bi-chevron-left"></i> Back
            </a>
        </div>

        <!-- Logo Section -->
        <div class="logo-section">
            <div class="logo-icon">
                <i class="bi bi-shield-check"></i>
            </div>
            <h2>Admin Login</h2>
            <p>System Administrator Access</p>
        </div>

        <!-- Login Card -->
        <div class="login-card">
            <div class="card-header-custom">
                <h3>
                    <i class="bi bi-lock"></i> Secure Access
                </h3>
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
                            placeholder="admin@cams.edu.ph"
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
                    <strong><i class="bi bi-info-circle"></i> Demo Credentials</strong>
                    <p>Email: admin@cams.edu.ph</p>
                    <p>Password: admin123</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
