-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 08, 2026
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cams`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `email`, `password`, `full_name`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin@cams.edu.ph', '$2y$10$MDW.w.TTqjfyNfidavrfxOVV6mTQyJcff.3suXoSrAwDrFxmfzpVK', 'System Administrator', 'active', '2026-04-02 05:18:02', '2026-04-02 05:18:02');

-- --------------------------------------------------------

--
-- Table structure for table `devices`
--

CREATE TABLE `devices` (
  `id` int(11) NOT NULL,
  `device_key` varchar(50) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_seen` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `device_commands`
--

CREATE TABLE `device_commands` (
  `id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `mode` enum('IDLE','ENROLL','DELETE') DEFAULT 'IDLE',
  `user_id` int(11) DEFAULT NULL,
  `finger_index` tinyint(4) DEFAULT NULL,
  `sensor_id` int(11) DEFAULT NULL,
  `status` enum('PENDING','IN_PROGRESS','COMPLETED','FAILED') DEFAULT 'PENDING',
  `error_message` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fingerprints`
--

CREATE TABLE `fingerprints` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `finger_index` tinyint(4) NOT NULL,
  `sensor_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_logs`
--

CREATE TABLE `attendance_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `type` enum('IN','OUT') NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance` (legacy - for migration)
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `time_in_am` time DEFAULT NULL,
  `time_out_am` time DEFAULT NULL,
  `time_in_pm` time DEFAULT NULL,
  `time_out_pm` time DEFAULT NULL,
  `status` enum('present','late','absent','excused') DEFAULT 'absent',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `daily_attendance_summary`
-- (See below for the actual view)
--
CREATE TABLE `daily_attendance_summary` (
`attendance_date` date
,`total_students` bigint(21)
,`present_count` decimal(22,0)
,`late_count` decimal(22,0)
,`absent_count` decimal(22,0)
,`excused_count` decimal(22,0)
);

-- --------------------------------------------------------

--
-- Table structure for table `notification_queue`
--

CREATE TABLE `notification_queue` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `attendance_id` int(11) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `attempt_count` int(11) DEFAULT 0,
  `last_error` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sent_at` timestamp NULL DEFAULT NULL,
  `next_retry_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scanner_heartbeat`
--

CREATE TABLE `scanner_heartbeat` (
  `id` int(11) NOT NULL,
  `device_ip` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `source` varchar(50) NOT NULL DEFAULT 'unknown',
  `last_seen` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `scanner_heartbeat`
--

INSERT INTO `scanner_heartbeat` (`id`, `device_ip`, `user_agent`, `source`, `last_seen`) VALUES
(1, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 10:55:23'),
(2, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 10:55:29'),
(3, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 10:55:36'),
(4, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 10:55:44'),
(5, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 10:55:52'),
(6, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 10:56:06'),
(7, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 10:56:25'),
(8, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 10:58:19'),
(9, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 10:58:28'),
(10, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 10:58:37'),
(11, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 10:58:42'),
(12, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 10:58:49'),
(13, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 10:58:55'),
(14, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 10:59:02'),
(15, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 10:59:10'),
(16, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 10:59:19'),
(17, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 10:59:26'),
(18, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 10:59:35'),
(19, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 10:59:40'),
(20, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 10:59:48'),
(21, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 10:59:57'),
(22, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:00:04'),
(23, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:00:09'),
(24, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:00:16'),
(25, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:00:22'),
(26, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:00:29'),
(27, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:00:34'),
(28, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:00:41'),
(29, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:00:49'),
(30, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:00:59'),
(31, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:01:06'),
(32, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:01:12'),
(33, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:01:18'),
(34, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:01:23'),
(35, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:01:29'),
(36, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:01:37'),
(37, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:01:46'),
(38, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:01:54'),
(39, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:02:01'),
(40, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:02:09'),
(41, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:02:14'),
(42, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:02:20'),
(43, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:02:28'),
(44, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:02:37'),
(45, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:02:44'),
(46, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:02:53'),
(47, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:03:01'),
(48, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:03:07'),
(49, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:03:12'),
(50, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:03:19'),
(51, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:03:28'),
(52, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:03:37'),
(53, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:03:43'),
(54, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:03:51'),
(55, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:04:00'),
(56, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:04:07'),
(57, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:04:14'),
(58, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:04:24'),
(59, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:04:33'),
(60, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:04:41'),
(61, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:04:50'),
(62, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:04:57'),
(63, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:05:02'),
(64, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:05:09'),
(65, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:05:17'),
(66, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:05:26'),
(67, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:05:38'),
(68, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:05:43'),
(69, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:05:50'),
(70, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:05:57'),
(71, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:06:03'),
(72, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:06:10'),
(73, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:06:19'),
(74, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:06:26'),
(75, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:06:33'),
(76, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:06:41'),
(77, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:06:49'),
(78, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:06:56'),
(79, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:07:04'),
(80, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:07:12'),
(81, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:07:19'),
(82, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:07:26'),
(83, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:07:34'),
(84, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:07:39'),
(85, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:07:47'),
(86, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:07:53'),
(87, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:08:01'),
(88, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:08:06'),
(89, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:08:14'),
(90, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:08:19'),
(91, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:08:28'),
(92, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:08:33'),
(93, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:08:41'),
(94, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:08:46'),
(95, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:08:54'),
(96, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:09:00'),
(97, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:09:07'),
(98, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:09:15'),
(99, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:09:23'),
(100, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:09:31'),
(101, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:09:36'),
(102, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:09:42'),
(103, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:09:49'),
(104, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:09:56'),
(105, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:10:01'),
(106, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:10:09'),
(107, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:10:14'),
(108, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:10:22'),
(109, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:10:31'),
(110, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:10:40'),
(111, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:10:48'),
(112, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:10:53'),
(113, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:11:02'),
(114, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:11:07'),
(115, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:11:14'),
(116, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:11:19'),
(117, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:11:28'),
(118, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:11:33'),
(119, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:11:40'),
(120, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:11:48'),
(121, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:11:56'),
(122, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:12:03'),
(123, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:12:10'),
(124, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:12:18'),
(125, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:12:23'),
(126, '10.18.239.169', '', 'api/scanner_status.php?heartbeat', '2026-04-05 11:12:30');

-- --------------------------------------------------------

--
-- Table structure for table `scan_waiting`
--

CREATE TABLE `scan_waiting` (
  `id` int(11) NOT NULL,
  `waiting_id` varchar(255) NOT NULL COMMENT 'Unique waiting session identifier',
  `session_id` varchar(255) NOT NULL COMMENT 'Enrollment session ID',
  `finger_index` int(11) NOT NULL COMMENT 'Which finger (1-5)',
  `scan_index` int(11) NOT NULL COMMENT 'Which scan attempt (1-5)',
  `scan_data` text DEFAULT NULL COMMENT 'Fingerprint template data from ESP32',
  `quality` int(11) DEFAULT NULL COMMENT 'Scan quality score (0-100)',
  `confidence` int(11) DEFAULT NULL COMMENT 'Scan confidence score (0-100)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'When this waiting record expires'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES
(1, 'late_threshold_minutes', '15', 'Minutes past AM start time to mark as late', '2026-04-02 05:18:02'),
(2, 'absent_threshold_hours', '2', 'Hours after AM start to mark as absent if no scan', '2026-04-02 05:18:02'),
(3, 'am_start_time', '08:00:00', 'AM session start time', '2026-04-02 05:18:02'),
(4, 'am_end_time', '12:00:00', 'AM session end time', '2026-04-02 05:18:02'),
(5, 'pm_start_time', '13:00:00', 'PM session start time', '2026-04-02 05:18:02'),
(6, 'pm_end_time', '17:00:00', 'PM session end time', '2026-04-02 05:18:02'),
(7, 'system_name', 'CAMS - Criminology Attendance System', 'System name for display', '2026-04-02 05:18:02'),
(8, 'email_from', 'noreply@cams.local', 'Email sender address', '2026-04-02 05:18:02'),
(9, 'notification_enabled', 'true', 'Enable/disable email notifications', '2026-04-02 05:18:02'),
(10, 'current_mode', 'registration', 'Current scanner mode', '2026-04-05 10:55:42');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_initial` varchar(10) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive','graduated') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id`, `first_name`, `middle_initial`, `last_name`, `email`, `year`, `section`, `status`, `created_at`, `updated_at`) VALUES
(1, '2024-001', 'Juan', NULL, 'Dela Cruz', 'juan@student.edu.ph', 1, 'A', 'active', '2026-04-02 05:18:02', '2026-04-02 05:18:02'),
(2, 'adas', 'oefoO', 'O', 'OOKD', 'dada@ffs.vom', 1, 'WQDW', 'active', '2026-04-05 10:17:25', '2026-04-05 10:17:25'),
(3, 'DWQ', 'W', 'W', 'Q', 'W@GM.D', 1, 'W', 'active', '2026-04-05 10:19:35', '2026-04-05 10:19:35');

-- --------------------------------------------------------

--
-- Stand-in structure for view `student_attendance_history`
-- (See below for the actual view)
--
CREATE TABLE `student_attendance_history` (
`id` int(11)
,`student_id` varchar(50)
,`first_name` varchar(100)
,`last_name` varchar(100)
,`email` varchar(100)
,`attendance_date` date
,`time_in_am` time
,`time_out_am` time
,`time_in_pm` time
,`time_out_pm` time
,`status` enum('present','late','absent','excused')
,`notes` text
);

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `section` varchar(50) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`id`, `email`, `password`, `full_name`, `section`, `status`, `created_at`, `updated_at`) VALUES
(1, 'teacher@cams.edu.ph', '$2y$10$8VOY7DU7vA16qU76MMh3qe19POIS7R7yhLHDPE4RiVyOX397FHcmC', 'Sample Instructor', 'A', 'active', '2026-04-02 05:18:02', '2026-04-02 05:18:02');

-- --------------------------------------------------------

--
-- Structure for view `daily_attendance_summary`
--
DROP TABLE IF EXISTS `daily_attendance_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `daily_attendance_summary`  AS SELECT `a`.`attendance_date` AS `attendance_date`, count(0) AS `total_students`, sum(case when `a`.`status` = 'present' then 1 else 0 end) AS `present_count`, sum(case when `a`.`status` = 'late' then 1 else 0 end) AS `late_count`, sum(case when `a`.`status` = 'absent' then 1 else 0 end) AS `absent_count`, sum(case when `a`.`status` = 'excused' then 1 else 0 end) AS `excused_count` FROM `attendance` AS `a` GROUP BY `a`.`attendance_date` ;

-- --------------------------------------------------------

--
-- Structure for view `student_attendance_history`
--
DROP TABLE IF EXISTS `student_attendance_history`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `student_attendance_history`  AS SELECT `s`.`id` AS `id`, `s`.`student_id` AS `student_id`, `s`.`first_name` AS `first_name`, `s`.`last_name` AS `last_name`, `s`.`email` AS `email`, `a`.`attendance_date` AS `attendance_date`, `a`.`time_in_am` AS `time_in_am`, `a`.`time_out_am` AS `time_out_am`, `a`.`time_in_pm` AS `time_in_pm`, `a`.`time_out_pm` AS `time_out_pm`, `a`.`status` AS `status`, `a`.`notes` AS `notes` FROM (`students` `s` left join `attendance` `a` on(`s`.`id` = `a`.`student_id`)) WHERE `s`.`status` = 'active' ORDER BY `s`.`student_id` ASC, `a`.`attendance_date` DESC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attendance_per_day` (`student_id`,`attendance_date`),
  ADD KEY `idx_attendance_date` (`attendance_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `fingerprints`
--
ALTER TABLE `fingerprints`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_finger_per_student` (`student_id`,`finger_index`),
  ADD UNIQUE KEY `sensor_id` (`sensor_id`),
  ADD KEY `idx_sensor_id` (`sensor_id`);

--
-- Indexes for table `fingerprint_registrations`
--
ALTER TABLE `fingerprint_registrations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status_updated` (`status`,`updated_at`),
  ADD KEY `idx_student_status` (`student_id`,`status`);

--
-- Indexes for table `notification_queue`
--
ALTER TABLE `notification_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `attendance_id` (`attendance_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_next_retry` (`status`,`next_retry_at`);

--
-- Indexes for table `scanner_heartbeat`
--
ALTER TABLE `scanner_heartbeat`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_last_seen` (`last_seen`),
  ADD KEY `idx_device_ip` (`device_ip`);

--
-- Indexes for table `scan_waiting`
--
ALTER TABLE `scan_waiting`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `waiting_id` (`waiting_id`),
  ADD KEY `idx_waiting_id` (`waiting_id`),
  ADD KEY `idx_session_id` (`session_id`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_setting_key` (`setting_key`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_email` (`email`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_section` (`section`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fingerprints`
--
ALTER TABLE `fingerprints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fingerprint_registrations`
--
ALTER TABLE `fingerprint_registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notification_queue`
--
ALTER TABLE `notification_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scanner_heartbeat`
--
ALTER TABLE `scanner_heartbeat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=127;

--
-- AUTO_INCREMENT for table `scan_waiting`
--
ALTER TABLE `scan_waiting`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fingerprints`
--
ALTER TABLE `fingerprints`
  ADD CONSTRAINT `fingerprints_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_queue`
--
ALTER TABLE `notification_queue`
  ADD CONSTRAINT `notification_queue_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notification_queue_ibfk_2` FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
