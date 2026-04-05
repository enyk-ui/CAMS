-- =====================================================
-- Multi-Scan Fingerprint Registration System
-- =====================================================
-- This tracks the 5-scan registration process per finger
-- Supports registering multiple fingers per student

-- Table to track active fingerprint registrations
CREATE TABLE IF NOT EXISTS `fingerprint_registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(50) NOT NULL,
  `finger_number` int(11) NOT NULL DEFAULT 1,  -- Which finger (1-10)
  `scan_number` int(11) NOT NULL DEFAULT 0,    -- Current scan (0-4 for 5 total)
  `total_fingers` int(11) NOT NULL DEFAULT 1,  -- How many fingers to register
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table to store individual fingerprint scans
CREATE TABLE IF NOT EXISTS `fingerprint_scans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `finger_number` int(11) NOT NULL,
  `scan_number` int(11) NOT NULL,  -- 0-4 (5 scans total)
  `scan_data` text,                -- Fingerprint template data
  `quality` int(11) DEFAULT NULL,  -- Scan quality score
  `scanned_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `registration_id` (`registration_id`),
  KEY `student_id` (`student_id`),
  FOREIGN KEY (`registration_id`) REFERENCES `fingerprint_registrations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Example: Start a new registration
-- INSERT INTO fingerprint_registrations (student_id, finger_number, scan_number, total_fingers, status)
-- VALUES ('STU001', 1, 0, 2, 'active');

-- Example: Mark registration complete
-- UPDATE fingerprint_registrations SET status = 'completed' WHERE id = 1;

-- Example: Get active registration
-- SELECT * FROM fingerprint_registrations WHERE status = 'active' ORDER BY updated_at DESC LIMIT 1;
