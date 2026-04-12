<?php
/**
 * Public Student Registration
 * Form for students to register fingerprints
 */

session_start();

$error = '';
$success = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAMS - Student Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
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

        .header-link {
            margin-bottom: 20px;
            text-align: center;
        }

        .header-link a {
            color: #2563eb;
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: color 0.3s;
        }

        .header-link a:hover {
            color: #1e40af;
        }

        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .card-header {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            border-radius: 16px 16px 0 0;
            padding: 25px;
            text-align: center;
        }

        .card-header h3 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .card-body {
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
            background: #f9fafb;
            transition: all 0.3s;
        }

        .form-control:focus {
            background: white;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn-submit {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            border: none;
            color: white;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            width: 100%;
            transition: all 0.3s;
            cursor: pointer;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.3);
            color: white;
        }

        .alert {
            border: none;
            border-radius: 10px;
            margin-bottom: 20px;
            padding: 15px;
            border-left: 4px solid;
        }

        .alert-danger {
            background: #fee2e2;
            border-color: #ef4444;
            color: #991b1b;
        }

        .alert-success {
            background: #f0fdf4;
            border-color: #10b981;
            color: #166534;
        }

        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.85rem;
            color: #1e40af;
        }

        .info-box strong {
            display: block;
            margin-bottom: 8px;
            color: #1e40af;
            font-weight: 600;
        }

        .info-box p {
            margin: 4px 0;
        }
    </style>
</head>
<body>
    <div class="container-center">
        <div class="header-link">
            <a href="../">
                <i class="bi bi-chevron-left"></i> Back to Login
            </a>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>
                    <i class="bi bi-person-plus"></i> Student Registration
                </h3>
            </div>

            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label for="first_name" class="form-label">
                            <i class="bi bi-person"></i> First Name
                        </label>
                        <input
                            type="text"
                            class="form-control"
                            id="first_name"
                            name="first_name"
                            placeholder="Your first name"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="last_name" class="form-label">
                            <i class="bi bi-person"></i> Last Name
                        </label>
                        <input
                            type="text"
                            class="form-control"
                            id="last_name"
                            name="last_name"
                            placeholder="Your last name"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="section" class="form-label">
                            <i class="bi bi-building"></i> Section
                        </label>
                        <select class="form-control" id="section" name="section" required>
                            <option value="">Select your section...</option>
                            <option value="A">Section A</option>
                            <option value="B">Section B</option>
                            <option value="C">Section C</option>
                            <option value="D">Section D</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="bi bi-person-check"></i> Continue to Fingerprint Registration
                    </button>
                </form>

                <div class="info-box">
                    <strong><i class="bi bi-info-circle"></i> Next Steps</strong>
                    <p>After registration, you'll be guided to scan your fingerprints for the attendance system.</p>
                    <p>Please ensure you're at the fingerprint scanner device before submitting this form.</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
