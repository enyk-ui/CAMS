<?php
/**
 * Database Update Script
 * Adds the scan_waiting table for ESP32 coordination
 */

require_once 'config/db.php';

echo "Adding scan_waiting table for ESP32 fingerprint coordination...\n";

$sql = "
CREATE TABLE IF NOT EXISTS scan_waiting (
    id INT AUTO_INCREMENT PRIMARY KEY,
    waiting_id VARCHAR(255) UNIQUE NOT NULL COMMENT 'Unique waiting session identifier',
    session_id VARCHAR(255) NOT NULL COMMENT 'Enrollment session ID',
    finger_index INT NOT NULL COMMENT 'Which finger (1-5)',
    scan_index INT NOT NULL COMMENT 'Which scan attempt (1-5)',
    scan_data TEXT NULL COMMENT 'Fingerprint template data from ESP32',
    quality INT NULL COMMENT 'Scan quality score (0-100)',
    confidence INT NULL COMMENT 'Scan confidence score (0-100)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL COMMENT 'When this waiting record expires',
    INDEX idx_waiting_id (waiting_id),
    INDEX idx_session_id (session_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($mysqli->query($sql)) {
    echo "✅ scan_waiting table created successfully!\n";
} else {
    echo "❌ Error creating table: " . $mysqli->error . "\n";
}

echo "\nDatabase update complete.\n";
echo "\nESP32 Integration Notes:\n";
echo "- ESP32 should GET /api/esp32_status.php to check for waiting scans\n";
echo "- ESP32 should POST to /api/esp32_scan.php with scan data\n";
echo "- Web interface now waits for real scanner data\n";
?>