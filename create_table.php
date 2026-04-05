<?php
/**
 * Quick Database Table Creation Script
 */

header('Content-Type: text/plain; charset=utf-8');

require_once 'config/db.php';

echo "CAMS Database Table Creator\n";
echo "==========================\n\n";

// Check current tables
echo "Checking existing tables...\n";
$result = $mysqli->query("SHOW TABLES LIKE 'scan_waiting'");
if ($result->num_rows > 0) {
    echo "✅ scan_waiting table already exists!\n\n";
} else {
    echo "❌ scan_waiting table is missing. Creating it now...\n\n";
    
    // Create the scan_waiting table
    $sql = "
    CREATE TABLE scan_waiting (
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
        echo "✅ scan_waiting table created successfully!\n\n";
    } else {
        echo "❌ Error creating table: " . $mysqli->error . "\n\n";
        exit(1);
    }
}

// List all tables to verify
echo "Current database tables:\n";
$result = $mysqli->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    echo "  - " . $row[0] . "\n";
}

echo "\n✅ Database setup complete!\n";
echo "\nYou can now use the fingerprint enrollment system.\n";
?>