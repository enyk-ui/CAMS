-- Add system_settings table for tracking scanner mode
-- Run this SQL in your phpMyAdmin or MySQL client

CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL UNIQUE,
  `value` text NOT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default mode (attendance)
INSERT INTO `system_settings` (`setting_key`, `value`, `updated_at`)
VALUES ('current_mode', 'attendance', NOW())
ON DUPLICATE KEY UPDATE value = 'attendance';

-- You can add more settings here as needed
-- Example: INSERT INTO `system_settings` (`setting_key`, `value`) VALUES ('scanner_timeout', '30');
