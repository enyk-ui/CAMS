<?php
/**
 * Test Endpoint for Fingerprint Scanning
 * Debug and test the scan.php API endpoint
 *
 * Usage: POST to this file with JSON data
 * Example: {"sensor_id": "12345"}
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../config/db.php';

// Allow both GET (for testing) and POST
$method = $_SERVER['REQUEST_METHOD'];

// For GET requests, show a simple test form
if ($method === 'GET') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>CAMS - Fingerprint Scan Test</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; }
            .container { max-width: 500px; }
            .card { border: none; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
            .card-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px 10px 0 0; }
            .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; }
            .btn-primary:hover { background: linear-gradient(135deg, #764ba2 0%, #667eea 100%); }
            #response { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; }
            .spinner { display: none; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Fingerprint Scan Test</h4>
                </div>
                <div class="card-body">
                    <form id="testForm">
                        <div class="mb-3">
                            <label for="sensorId" class="form-label">Sensor ID (Fingerprint Template ID)</label>
                            <input type="text" class="form-control" id="sensorId" placeholder="Enter sensor ID (e.g., FP001)" required>
                            <small class="text-muted">This should match a registered fingerprint in the database</small>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Test Scan</button>
                        </div>
                    </form>

                    <div class="spinner mt-3 text-center" style="display: none;">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>

                    <div id="response" class="mt-4 p-3" style="display: none;">
                        <h6>Response:</h6>
                        <pre id="responseText"></pre>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            document.getElementById('testForm').addEventListener('submit', async function(e) {
                e.preventDefault();

                const sensorId = document.getElementById('sensorId').value;
                const spinner = document.querySelector('.spinner');
                const responseDiv = document.getElementById('response');
                const responseText = document.getElementById('responseText');

                spinner.style.display = 'block';
                responseDiv.style.display = 'none';

                try {
                    const response = await fetch('scan.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            sensor_id: sensorId
                        })
                    });

                    const data = await response.json();
                    responseText.textContent = JSON.stringify(data, null, 2);
                    responseDiv.style.display = 'block';
                } catch (error) {
                    responseText.textContent = 'Error: ' + error.message;
                    responseDiv.style.display = 'block';
                } finally {
                    spinner.style.display = 'none';
                }
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

// POST request - forward to scan.php logic
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $sensor_id = isset($input['sensor_id']) ? trim($input['sensor_id']) : null;

    if (empty($sensor_id)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing sensor_id parameter'
        ]);
        exit;
    }

    // Include the main scan logic
    include 'scan.php';
}
