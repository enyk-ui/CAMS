-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 13, 2026 at 02:14 AM
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
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
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

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `student_id`, `section_id`, `attendance_date`, `time_in_am`, `time_out_am`, `time_in_pm`, `time_out_pm`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 34, 2, '2026-04-12', NULL, NULL, '20:19:19', NULL, 'late', NULL, '2026-04-12 12:19:19', '2026-04-12 12:19:19'),
(2, 34, 2, '2026-04-13', '08:02:24', NULL, NULL, NULL, 'present', NULL, '2026-04-13 00:02:24', '2026-04-13 00:02:24');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_logs`
--

CREATE TABLE `attendance_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `student_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `type` enum('IN','OUT') NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance_logs`
--

INSERT INTO `attendance_logs` (`id`, `student_id`, `device_id`, `type`, `timestamp`) VALUES
(1, 34, 1, 'IN', '2026-04-12 12:19:19'),
(2, 34, 1, 'IN', '2026-04-13 00:02:24');

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

--
-- Dumping data for table `devices`
--

INSERT INTO `devices` (`id`, `device_key`, `name`, `location`, `is_active`, `last_seen`, `created_at`) VALUES
(1, 'CAMS_ESP8266', 'Default Scanner', NULL, 1, NULL, '2026-04-09 04:33:10');

-- --------------------------------------------------------

--
-- Table structure for table `device_commands`
--

CREATE TABLE `device_commands` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `device_id` int(11) NOT NULL,
  `mode` enum('IDLE','ENROLL','DELETE') DEFAULT 'IDLE',
  `student_id` int(11) DEFAULT NULL,
  `finger_index` tinyint(4) DEFAULT NULL,
  `sensor_id` int(11) DEFAULT NULL,
  `scan_step` tinyint(3) UNSIGNED DEFAULT NULL,
  `total_scan_steps` tinyint(3) UNSIGNED DEFAULT 3,
  `status` enum('PENDING','IN_PROGRESS','COMPLETED','FAILED') DEFAULT 'PENDING',
  `error_message` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `device_commands`
--

INSERT INTO `device_commands` (`id`, `device_id`, `mode`, `student_id`, `finger_index`, `sensor_id`, `scan_step`, `total_scan_steps`, `status`, `error_message`, `created_at`, `updated_at`) VALUES
(1, 1, 'ENROLL', 6, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 05:02:06', '2026-04-09 05:42:57'),
(2, 1, 'ENROLL', 7, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 05:16:44', '2026-04-09 05:42:57'),
(3, 1, 'ENROLL', 8, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 05:19:33', '2026-04-09 05:42:57'),
(4, 1, 'ENROLL', 9, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 05:21:29', '2026-04-09 05:42:57'),
(5, 1, 'ENROLL', 10, 1, NULL, NULL, 3, 'FAILED', 'Registration cancelled by admin', '2026-04-09 05:22:00', '2026-04-09 05:35:38'),
(6, 1, 'ENROLL', 11, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 05:35:56', '2026-04-09 05:42:57'),
(7, 1, 'ENROLL', 1, 1, NULL, NULL, 3, 'FAILED', 'Scanner enrollment failed (image/model/store)', '2026-04-09 05:42:57', '2026-04-09 05:43:20'),
(8, 1, 'ENROLL', 12, 1, 13, NULL, 3, 'COMPLETED', 'total_fingers:2', '2026-04-09 05:50:04', '2026-04-09 05:50:29'),
(9, 1, 'ENROLL', 12, 2, NULL, NULL, 3, 'FAILED', 'Scanner enrollment failed (image/model/store)', '2026-04-09 05:50:29', '2026-04-09 05:50:38'),
(10, 1, 'ENROLL', 13, 1, NULL, 1, 3, 'FAILED', 'Scanner enrollment failed (image/model/store)', '2026-04-09 06:03:08', '2026-04-09 06:03:09'),
(11, 1, 'ENROLL', 13, 1, NULL, 1, 3, 'FAILED', 'Scanner enrollment failed (image/model/store)', '2026-04-09 06:03:15', '2026-04-09 06:03:31'),
(12, 1, 'ENROLL', 14, 1, NULL, 1, 3, 'FAILED', 'Scanner enrollment failed (image/model/store)', '2026-04-09 06:10:06', '2026-04-09 06:10:22'),
(13, 1, 'ENROLL', 14, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 06:10:26', '2026-04-09 06:10:27'),
(14, 1, 'ENROLL', 14, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 06:10:27', '2026-04-09 06:10:27'),
(15, 1, 'ENROLL', 14, 1, NULL, 1, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 06:10:27', '2026-04-09 06:10:33'),
(16, 1, 'ENROLL', 14, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 06:10:33', '2026-04-09 06:10:33'),
(17, 1, 'ENROLL', 14, 1, NULL, NULL, 3, 'FAILED', 'Scanner enrollment failed (image/model/store)', '2026-04-09 06:10:33', '2026-04-09 06:10:43'),
(18, 1, 'ENROLL', 15, 1, 19, 3, 3, 'COMPLETED', 'total_fingers:2', '2026-04-09 06:14:41', '2026-04-09 06:14:56'),
(19, 1, 'ENROLL', 15, 2, 20, 3, 3, 'COMPLETED', 'total_fingers:2', '2026-04-09 06:14:56', '2026-04-09 06:15:06'),
(20, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-09 06:15:07', '2026-04-09 06:15:07'),
(21, 1, 'ENROLL', 16, 1, NULL, 1, 3, 'FAILED', 'getImage#1 timeout', '2026-04-09 06:23:41', '2026-04-09 06:23:58'),
(22, 1, 'ENROLL', 16, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 06:25:23', '2026-04-09 06:25:25'),
(23, 1, 'ENROLL', 16, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 06:25:25', '2026-04-09 06:25:28'),
(24, 1, 'ENROLL', 16, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 06:25:28', '2026-04-09 06:25:29'),
(25, 1, 'ENROLL', 16, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 06:25:29', '2026-04-09 06:25:31'),
(26, 1, 'ENROLL', 16, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 06:25:31', '2026-04-09 06:25:32'),
(27, 1, 'ENROLL', 16, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 06:25:32', '2026-04-09 06:25:33'),
(28, 1, 'ENROLL', 16, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 06:25:33', '2026-04-09 06:25:34'),
(29, 1, 'ENROLL', 16, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 06:25:34', '2026-04-09 06:25:35'),
(30, 1, 'ENROLL', 16, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 06:25:35', '2026-04-09 06:25:35'),
(31, 1, 'ENROLL', 16, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 06:25:35', '2026-04-09 06:25:35'),
(32, 1, 'ENROLL', 16, 1, NULL, NULL, 3, 'FAILED', 'Mode switched to attendance', '2026-04-09 06:25:35', '2026-04-09 06:25:39'),
(33, 1, 'ENROLL', 17, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 06:25:47', '2026-04-09 06:25:48'),
(34, 1, 'ENROLL', 17, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 06:25:49', '2026-04-09 06:25:49'),
(35, 1, 'ENROLL', 17, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 06:25:49', '2026-04-09 06:25:50'),
(36, 1, 'ENROLL', 17, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 06:25:50', '2026-04-09 06:25:52'),
(37, 1, 'ENROLL', 17, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 06:25:52', '2026-04-09 06:25:58'),
(38, 1, 'ENROLL', 17, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 06:25:53', '2026-04-09 06:25:58'),
(39, 1, 'ENROLL', 17, 1, NULL, NULL, 3, 'FAILED', 'getImage#1 timeout', '2026-04-09 06:25:59', '2026-04-09 06:26:06'),
(40, 1, 'ENROLL', 18, 1, NULL, 1, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 06:31:22', '2026-04-09 06:31:24'),
(41, 1, 'ENROLL', 18, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 06:31:24', '2026-04-09 06:31:25'),
(42, 1, 'ENROLL', 18, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-09 06:31:25', '2026-04-09 06:31:26'),
(43, 1, 'ENROLL', 18, 1, NULL, NULL, 3, 'FAILED', 'getImage#1 timeout', '2026-04-09 06:31:26', '2026-04-09 06:31:38'),
(44, 1, 'ENROLL', 18, 1, NULL, 1, 3, 'FAILED', 'getImage#1 timeout', '2026-04-09 06:32:08', '2026-04-09 06:32:28'),
(45, 1, 'ENROLL', 19, 1, NULL, 1, 3, 'FAILED', 'getImage#1 timeout', '2026-04-09 06:35:43', '2026-04-09 06:36:05'),
(46, 1, 'ENROLL', 20, 1, NULL, 2, 3, 'FAILED', 'getImage#2 code=1', '2026-04-09 06:39:22', '2026-04-09 06:39:31'),
(47, 1, 'ENROLL', 20, 1, NULL, NULL, 3, 'FAILED', 'Retry enrollment requested', '2026-04-09 06:44:40', '2026-04-09 06:44:40'),
(48, 1, 'ENROLL', 20, 1, NULL, NULL, 3, 'FAILED', 'Retry enrollment requested', '2026-04-09 06:44:40', '2026-04-09 06:44:42'),
(49, 1, 'ENROLL', 20, 1, NULL, NULL, 3, 'FAILED', 'Mode switched to attendance', '2026-04-09 06:44:42', '2026-04-09 06:44:46'),
(50, 1, 'ENROLL', 21, 1, NULL, NULL, 3, 'FAILED', 'Mode switched to attendance', '2026-04-09 21:24:20', '2026-04-09 21:29:24'),
(51, 1, 'ENROLL', 22, 1, NULL, NULL, 3, 'FAILED', 'Registration cancelled by admin', '2026-04-09 21:29:30', '2026-04-09 21:29:38'),
(52, 1, 'ENROLL', 23, 1, NULL, 1, 3, 'FAILED', 'getImage#1 code=32', '2026-04-09 21:30:00', '2026-04-09 21:34:31'),
(53, 1, 'ENROLL', 23, 1, 21, 3, 3, 'COMPLETED', 'total_fingers:2', '2026-04-09 21:41:19', '2026-04-09 21:41:38'),
(54, 1, 'ENROLL', 23, 2, 22, 3, 3, 'COMPLETED', 'total_fingers:2', '2026-04-09 21:41:38', '2026-04-09 21:41:48'),
(55, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-09 21:41:48', '2026-04-09 21:41:48'),
(56, 1, 'ENROLL', 24, 1, 1, 3, 3, 'COMPLETED', 'total_fingers:3', '2026-04-09 22:35:10', '2026-04-09 22:35:23'),
(57, 1, 'ENROLL', 24, 2, NULL, 1, 3, 'FAILED', 'duplicate finger detected sensor_id=1', '2026-04-09 22:35:23', '2026-04-09 22:35:24'),
(58, 1, 'DELETE', 24, NULL, 1, NULL, 3, 'COMPLETED', NULL, '2026-04-09 22:36:01', '2026-04-09 22:51:49'),
(59, 1, 'ENROLL', 24, 1, NULL, 1, 3, 'FAILED', 'getImage#1 timeout', '2026-04-09 22:36:01', '2026-04-09 22:52:15'),
(60, 1, 'ENROLL', 24, 1, NULL, NULL, 3, 'FAILED', 'Mode switched to attendance', '2026-04-09 23:36:14', '2026-04-09 23:36:17'),
(61, 1, 'ENROLL', 25, 1, 1, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-10 12:00:23', '2026-04-10 12:00:36'),
(62, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-10 12:00:37', '2026-04-10 12:00:37'),
(63, 1, 'ENROLL', 26, 1, NULL, NULL, 3, 'FAILED', 'Mode switched to attendance', '2026-04-11 02:24:09', '2026-04-11 02:24:14'),
(64, 1, 'ENROLL', 27, 1, 23, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-12 09:47:49', '2026-04-12 09:47:59'),
(65, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-12 09:47:59', '2026-04-12 09:47:59'),
(66, 1, 'ENROLL', 28, 1, 24, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-12 09:48:16', '2026-04-12 09:48:41'),
(67, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-12 09:48:42', '2026-04-12 09:48:42'),
(68, 1, 'DELETE', 28, NULL, 24, NULL, 3, 'COMPLETED', NULL, '2026-04-12 09:48:45', '2026-04-12 09:49:12'),
(69, 1, 'ENROLL', 28, 1, NULL, 3, 3, 'FAILED', 'createModel code=10', '2026-04-12 09:48:45', '2026-04-12 09:49:11'),
(70, 1, 'ENROLL', 29, 1, NULL, 1, 3, 'FAILED', 'getImage#1 timeout', '2026-04-12 09:59:01', '2026-04-12 10:01:09'),
(71, 1, 'ENROLL', 29, 1, NULL, 1, 3, 'FAILED', 'Retry enrollment requested', '2026-04-12 10:01:17', '2026-04-12 10:01:28'),
(72, 1, 'ENROLL', 29, 1, NULL, 1, 3, 'FAILED', 'getImage#1 code=32', '2026-04-12 10:01:28', '2026-04-12 10:01:37'),
(73, 1, 'ENROLL', 30, 1, NULL, 1, 3, 'FAILED', 'getImage#1 timeout', '2026-04-12 10:01:45', '2026-04-12 10:02:19'),
(74, 1, 'ENROLL', 31, 1, NULL, 1, 3, 'FAILED', 'Registration cancelled by admin', '2026-04-12 10:06:11', '2026-04-12 10:06:17'),
(75, 1, 'ENROLL', 32, 1, 24, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-12 10:06:31', '2026-04-12 10:07:41'),
(76, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-12 10:07:41', '2026-04-12 10:07:41'),
(77, 1, 'ENROLL', 33, 1, 25, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-12 10:08:44', '2026-04-12 10:08:56'),
(78, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-12 10:08:56', '2026-04-12 10:08:56'),
(79, 1, 'ENROLL', 34, 1, 26, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-12 10:09:10', '2026-04-12 10:10:10'),
(80, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-12 10:10:11', '2026-04-12 10:10:11'),
(81, 1, 'ENROLL', 35, 1, NULL, 1, 3, 'FAILED', 'Registration cancelled by admin', '2026-04-12 10:10:49', '2026-04-12 10:11:03'),
(82, 1, 'ENROLL', 36, 1, NULL, 1, 3, 'FAILED', 'getImage#1 timeout', '2026-04-12 10:11:10', '2026-04-12 10:11:58'),
(83, 1, 'ENROLL', 37, 1, NULL, 1, 3, 'FAILED', 'Mode switched to attendance', '2026-04-12 10:14:24', '2026-04-12 10:14:56'),
(84, 1, 'ENROLL', 34, 1, NULL, 1, 3, 'FAILED', 'getImage#1 timeout', '2026-04-12 10:20:31', '2026-04-12 10:21:35'),
(85, 1, 'ENROLL', 34, 1, NULL, 3, 3, 'FAILED', 'createModel code=10', '2026-04-12 10:21:40', '2026-04-12 10:22:20'),
(86, 1, 'ENROLL', 34, 1, NULL, 1, 3, 'FAILED', 'getImage#1 timeout', '2026-04-12 10:22:25', '2026-04-12 10:22:52'),
(87, 1, 'ENROLL', 38, 1, NULL, 1, 3, 'FAILED', 'getImage#1 code=32', '2026-04-12 10:28:47', '2026-04-12 10:29:00'),
(88, 1, 'ENROLL', 38, 1, NULL, 3, 3, 'FAILED', 'createModel code=10', '2026-04-12 10:29:08', '2026-04-12 10:29:16'),
(89, 1, 'ENROLL', 38, 1, 30, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-12 10:29:47', '2026-04-12 10:29:58'),
(90, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-12 10:29:58', '2026-04-12 10:29:58'),
(91, 1, 'ENROLL', 39, 1, NULL, 3, 3, 'FAILED', 'createModel code=10', '2026-04-12 10:38:38', '2026-04-12 10:38:54'),
(92, 1, 'ENROLL', 39, 1, NULL, 1, 3, 'FAILED', 'Retry enrollment requested', '2026-04-12 10:39:00', '2026-04-12 10:39:13'),
(93, 1, 'ENROLL', 39, 1, NULL, NULL, 3, 'FAILED', 'Retry enrollment requested', '2026-04-12 10:39:13', '2026-04-12 10:39:16'),
(94, 1, 'ENROLL', 39, 1, 31, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-12 10:39:16', '2026-04-12 10:39:23'),
(95, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-12 10:39:23', '2026-04-12 10:39:23'),
(96, 1, 'DELETE', 15, NULL, 20, NULL, 3, 'COMPLETED', NULL, '2026-04-12 10:45:08', '2026-04-12 10:45:08'),
(97, 1, 'DELETE', 23, NULL, 22, NULL, 3, 'COMPLETED', NULL, '2026-04-12 10:45:08', '2026-04-12 10:45:10'),
(98, 1, 'DELETE', 25, NULL, 1, NULL, 3, 'COMPLETED', NULL, '2026-04-12 10:45:08', '2026-04-12 10:45:11'),
(99, 1, 'DELETE', 27, NULL, 23, NULL, 3, 'COMPLETED', NULL, '2026-04-12 10:45:08', '2026-04-12 10:45:13'),
(100, 1, 'DELETE', 32, NULL, 24, NULL, 3, 'COMPLETED', NULL, '2026-04-12 10:45:08', '2026-04-12 10:45:14'),
(101, 1, 'DELETE', 33, NULL, 25, NULL, 3, 'COMPLETED', NULL, '2026-04-12 10:45:08', '2026-04-12 10:45:16'),
(102, 1, 'DELETE', 34, NULL, 26, NULL, 3, 'COMPLETED', NULL, '2026-04-12 10:45:08', '2026-04-12 10:45:17'),
(103, 1, 'DELETE', 38, NULL, 30, NULL, 3, 'COMPLETED', NULL, '2026-04-12 10:45:08', '2026-04-12 10:45:19'),
(104, 1, 'DELETE', 39, NULL, 31, NULL, 3, 'COMPLETED', NULL, '2026-04-12 10:45:08', '2026-04-12 10:45:21'),
(105, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', 'Sensor reset complete', '2026-04-12 10:45:08', '2026-04-12 10:45:08'),
(106, 1, 'ENROLL', 39, 1, NULL, 1, 3, 'FAILED', 'getImage#1 timeout', '2026-04-12 10:46:36', '2026-04-12 10:47:29'),
(107, 1, 'DELETE', NULL, NULL, 0, NULL, 3, 'COMPLETED', NULL, '2026-04-12 11:13:59', '2026-04-12 11:15:50'),
(108, 1, 'DELETE', NULL, NULL, 0, NULL, 3, 'COMPLETED', NULL, '2026-04-12 11:17:09', '2026-04-12 11:17:13'),
(109, 1, 'ENROLL', 39, 1, 1, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-12 11:17:38', '2026-04-12 11:17:56'),
(110, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-12 11:17:56', '2026-04-12 11:17:56'),
(111, 1, 'ENROLL', 38, 1, 2, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-12 11:18:06', '2026-04-12 11:18:15'),
(112, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-12 11:18:15', '2026-04-12 11:18:15'),
(113, 1, 'ENROLL', 38, 1, NULL, 1, 3, 'FAILED', 'Mode switched to attendance', '2026-04-12 11:19:22', '2026-04-12 11:19:28'),
(114, 1, 'ENROLL', 38, 1, NULL, 2, 3, 'FAILED', 'createModel mismatch', '2026-04-12 11:22:20', '2026-04-12 11:23:10'),
(115, 1, 'ENROLL', 38, 1, NULL, 2, 3, 'FAILED', 'createModel mismatch', '2026-04-12 11:23:14', '2026-04-12 11:23:30'),
(116, 1, 'ENROLL', 38, 1, NULL, 2, 3, 'FAILED', 'image2Tz#2 code=1', '2026-04-12 11:23:36', '2026-04-12 11:24:04'),
(117, 1, 'ENROLL', 38, 1, NULL, 2, 3, 'FAILED', 'createModel mismatch', '2026-04-12 11:24:16', '2026-04-12 11:24:29'),
(118, 1, 'ENROLL', 38, 1, NULL, 1, 3, 'FAILED', 'Mode switched to attendance', '2026-04-12 11:24:33', '2026-04-12 11:24:36'),
(119, 1, 'ENROLL', 38, 1, NULL, 2, 3, 'FAILED', 'createModel mismatch', '2026-04-12 11:28:41', '2026-04-12 11:29:24'),
(120, 1, 'ENROLL', 38, 1, NULL, 2, 3, 'FAILED', 'createModel mismatch', '2026-04-12 11:29:38', '2026-04-12 11:29:54'),
(121, 1, 'ENROLL', 38, 1, NULL, 2, 3, 'FAILED', 'createModel mismatch', '2026-04-12 11:30:06', '2026-04-12 11:30:24'),
(122, 1, 'ENROLL', 38, 1, NULL, 2, 3, 'FAILED', 'getImage#2 code=32', '2026-04-12 11:30:26', '2026-04-12 11:30:36'),
(123, 1, 'ENROLL', 38, 1, NULL, 2, 3, 'FAILED', 'getImage#2 timeout', '2026-04-12 11:32:09', '2026-04-12 11:32:45'),
(124, 1, 'ENROLL', 38, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-12 11:33:03', '2026-04-12 11:33:07'),
(125, 1, 'ENROLL', 38, 1, NULL, NULL, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-12 11:33:08', '2026-04-12 11:33:08'),
(126, 1, 'ENROLL', 38, 1, NULL, 1, 3, 'FAILED', 'getImage#1 timeout', '2026-04-12 11:33:09', '2026-04-12 11:33:38'),
(127, 1, 'ENROLL', 38, 1, NULL, 1, 3, 'FAILED', 'Superseded by new enrollment session', '2026-04-12 11:35:34', '2026-04-12 11:35:47'),
(128, 1, 'ENROLL', 38, 1, NULL, 2, 3, 'FAILED', 'createModel mismatch', '2026-04-12 11:35:48', '2026-04-12 11:37:26'),
(129, 1, 'ENROLL', 38, 1, NULL, 1, 3, 'FAILED', 'Mode switched to attendance', '2026-04-12 11:37:33', '2026-04-12 11:37:51'),
(130, 1, 'ENROLL', 38, 1, NULL, 2, 3, 'FAILED', 'getImage#2 timeout', '2026-04-12 11:37:54', '2026-04-12 11:38:32'),
(131, 1, 'DELETE', NULL, NULL, 0, NULL, 3, 'COMPLETED', NULL, '2026-04-12 11:38:57', '2026-04-12 11:39:02'),
(132, 1, 'DELETE', NULL, NULL, 0, NULL, 3, 'FAILED', 'clearAll code=1', '2026-04-12 11:44:53', '2026-04-12 11:44:55'),
(133, 1, 'ENROLL', 39, 1, 1, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-12 11:45:07', '2026-04-12 11:45:17'),
(134, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-12 11:45:17', '2026-04-12 11:45:17'),
(135, 1, 'ENROLL', 38, 1, NULL, 2, 3, 'FAILED', 'createModel mismatch', '2026-04-12 11:45:25', '2026-04-12 11:45:39'),
(136, 1, 'ENROLL', 38, 1, 2, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-12 11:45:48', '2026-04-12 11:46:05'),
(137, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-12 11:46:05', '2026-04-12 11:46:05'),
(138, 1, 'ENROLL', 34, 1, 3, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-12 11:46:13', '2026-04-12 11:46:22'),
(139, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-12 11:46:22', '2026-04-12 11:46:22');

-- --------------------------------------------------------

--
-- Table structure for table `fingerprints`
--

CREATE TABLE `fingerprints` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `finger_index` tinyint(4) NOT NULL,
  `sensor_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fingerprints`
--

INSERT INTO `fingerprints` (`id`, `student_id`, `finger_index`, `sensor_id`, `device_id`, `created_at`) VALUES
(18, 39, 1, 1, 1, '2026-04-12 11:45:17'),
(19, 38, 1, 2, 1, '2026-04-12 11:46:05'),
(20, 34, 1, 3, 1, '2026-04-12 11:46:22');

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
-- Table structure for table `school_years`
--

CREATE TABLE `school_years` (
  `id` int(11) NOT NULL,
  `label` varchar(20) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `school_years`
--

INSERT INTO `school_years` (`id`, `label`, `start_date`, `end_date`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '2025-2026', '2025-06-01', '2026-05-31', 1, '2026-04-09 04:33:17', '2026-04-09 04:33:17');

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `year_grade` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `name`, `year_grade`, `created_at`, `updated_at`) VALUES
(1, 'Alpha', '1', '2026-04-12 07:38:09', '2026-04-12 08:43:48'),
(2, 'Beta', '1', '2026-04-12 07:38:09', '2026-04-12 08:43:48'),
(3, 'Charlie', '1', '2026-04-12 07:38:09', '2026-04-12 07:38:09'),
(4, 'Delta', '1', '2026-04-12 07:38:09', '2026-04-12 07:38:09'),
(5, 'Charlie', '4', '2026-04-12 08:41:21', '2026-04-12 08:43:48'),
(8, 'Alpha', '2', '2026-04-12 08:41:21', '2026-04-12 08:43:49');

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
(3, 'am_start_time', '08:00', 'AM session start time', '2026-04-09 11:57:41'),
(4, 'am_end_time', '12:00', 'AM session end time', '2026-04-09 11:57:41'),
(5, 'pm_start_time', '13:00', 'PM session start time', '2026-04-09 11:57:41'),
(6, 'pm_end_time', '17:00', 'PM session end time', '2026-04-09 11:57:41'),
(7, 'system_name', 'CAMS - Criminology Attendance System', 'System name for display', '2026-04-02 05:18:02'),
(8, 'email_from', 'noreply@cams.local', 'Email sender address', '2026-04-02 05:18:02'),
(9, 'notification_enabled', 'true', 'Enable/disable email notifications', '2026-04-02 05:18:02'),
(10, 'current_mode', 'registration', 'Current scanner mode', '2026-04-05 10:55:42'),
(13, 'school_year', '2025-2026', NULL, '2026-04-09 04:33:17');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_initial` varchar(10) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `extension` varchar(20) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive','graduated') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `first_name`, `middle_initial`, `last_name`, `extension`, `year`, `section_id`, `section`, `status`, `created_at`, `updated_at`) VALUES
(25, 'Fsg', 'G', 'Fgdgr', 'Jr', 4, 5, 'Charlie', 'active', '2026-04-10 12:00:17', '2026-04-12 08:42:16'),
(27, 'Alfred', 'C', 'Marcelino', 'Jr', 1, 2, 'Beta', 'active', '2026-04-12 09:47:38', '2026-04-12 09:48:02'),
(32, 'Alfred', 'C', 'Marcelino', 'Sr', 1, 1, 'Alpha', 'active', '2026-04-12 10:06:30', '2026-04-12 10:07:44'),
(33, 'Alfred', 'C', 'Marcelino', 'Jr', 1, 2, 'Beta', 'active', '2026-04-12 10:08:43', '2026-04-12 10:09:01'),
(34, 'Alfred', 'C', 'Marcelino', 'Jr', 1, 2, 'Beta', 'active', '2026-04-12 10:09:09', '2026-04-12 11:46:27'),
(38, 'Alfred1', 'C', 'Marcelino', 'Jr', 1, 1, 'Beta', 'active', '2026-04-12 10:28:46', '2026-04-12 11:51:25'),
(39, 'Alfred', 'C', 'Marcelino', 'Ii', 1, 1, 'Alpha', 'active', '2026-04-12 10:38:38', '2026-04-12 11:45:20');

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
-- Table structure for table `teacher_daily_schedules`
--

CREATE TABLE `teacher_daily_schedules` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `day_of_week` tinyint(3) UNSIGNED NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `late_threshold_minutes` tinyint(3) UNSIGNED NOT NULL DEFAULT 15,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `teacher_daily_schedules`
--

INSERT INTO `teacher_daily_schedules` (`id`, `teacher_id`, `section_id`, `day_of_week`, `start_time`, `end_time`, `late_threshold_minutes`, `created_at`, `updated_at`) VALUES
(11, 2, 1, 2, '08:00:00', '17:00:00', 15, '2026-04-11 05:36:19', '2026-04-12 11:51:14'),
(12, 3, 2, 7, '20:00:00', '10:00:00', 15, '2026-04-12 12:11:52', '2026-04-12 12:11:52');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_sections`
--

CREATE TABLE `teacher_sections` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `teacher_sections`
--

INSERT INTO `teacher_sections` (`id`, `teacher_id`, `section_id`, `created_at`) VALUES
(1, 2, 1, '2026-04-12 08:42:17'),
(2, 3, 2, '2026-04-12 08:42:17'),
(3, 5, 8, '2026-04-12 08:42:17'),
(4, 6, 5, '2026-04-12 08:42:17');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(120) DEFAULT NULL,
  `role` enum('admin','teacher') NOT NULL DEFAULT 'teacher',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `section` varchar(50) DEFAULT NULL,
  `school_year_label` varchar(20) DEFAULT NULL,
  `year_level` tinyint(3) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `role`, `status`, `created_at`, `updated_at`, `section`, `school_year_label`, `year_level`) VALUES
(1, 'admin', '$2y$10$woShL7UfMUq6DwHOktsatuna70k4t3aYQ7kGQGXJEoZ.q/G9/ZkJe', 'Administrator', 'admin@cams.edu.ph', 'admin', 'active', '2026-04-09 05:41:37', '2026-04-09 05:41:37', NULL, NULL, NULL),
(2, 'teacher', '$2y$10$q4pNp8NchbZyI5pvtYuMQ.s9g4udDqgQqFmO73Tlzws1wowUN14ZS', 'teacher 1', 'teacher@cams.edu.ph', 'teacher', 'active', '2026-04-09 05:41:37', '2026-04-10 04:47:59', 'Alpha', '2025-2026', 1),
(3, 'Teacher2', '$2y$10$3iBEda/CQYtKzvKUPlgRl.7cPYCtfcOoIAPz975i0FPVtIwnr.l0q', 'Demo Teacher', 'alfredmarcelinoii45@gmail.com', 'teacher', 'active', '2026-04-09 21:17:21', '2026-04-09 21:17:21', 'Beta', '2025-2026', 1),
(5, 'teacher3', '$2y$10$pDrLw9OUzj3FLzcR6NS3hunLiPwYw8j0nNjipF1kIWFloPUB8xYNi', 'Demo Teacher', 'alfredmarcelino455@gmail.com', 'teacher', 'active', '2026-04-09 21:22:07', '2026-04-09 21:22:07', 'Alpha', '2025-2026', 2),
(6, 'ete', '$2y$10$ONT9s2c2SCGc3bdO.aExjufqAOpQsszCWnVilc3BvnfTKFeQcPJ8S', 'Demo Teacher', 'ada@gsz.bo', 'teacher', 'active', '2026-04-10 11:59:26', '2026-04-10 11:59:26', 'Charlie', '2025-2026', 4);

-- --------------------------------------------------------

--
-- Structure for view `daily_attendance_summary`
--
DROP TABLE IF EXISTS `daily_attendance_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `daily_attendance_summary`  AS SELECT `a`.`attendance_date` AS `attendance_date`, count(0) AS `total_students`, sum(case when `a`.`status` = 'present' then 1 else 0 end) AS `present_count`, sum(case when `a`.`status` = 'late' then 1 else 0 end) AS `late_count`, sum(case when `a`.`status` = 'absent' then 1 else 0 end) AS `absent_count`, sum(case when `a`.`status` = 'excused' then 1 else 0 end) AS `excused_count` FROM `attendance` AS `a` GROUP BY `a`.`attendance_date` ;

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
-- Indexes for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `device_commands`
--
ALTER TABLE `device_commands`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `fingerprints`
--
ALTER TABLE `fingerprints`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_fingerprints_user_finger` (`student_id`,`finger_index`),
  ADD UNIQUE KEY `uniq_fingerprints_sensor` (`sensor_id`),
  ADD UNIQUE KEY `uniq_student_finger` (`student_id`,`finger_index`),
  ADD UNIQUE KEY `uniq_student_one` (`student_id`);

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
-- Indexes for table `school_years`
--
ALTER TABLE `school_years`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_school_year_label` (`label`),
  ADD KEY `idx_school_year_active` (`is_active`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_section_name_year` (`name`,`year_grade`);

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
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_section` (`section`);

--
-- Indexes for table `teacher_daily_schedules`
--
ALTER TABLE `teacher_daily_schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_teacher_section_day` (`teacher_id`,`section_id`,`day_of_week`),
  ADD KEY `idx_teacher_day` (`teacher_id`,`day_of_week`);

--
-- Indexes for table `teacher_sections`
--
ALTER TABLE `teacher_sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_teacher_section` (`teacher_id`,`section_id`),
  ADD UNIQUE KEY `uniq_section_teacher` (`section_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `device_commands`
--
ALTER TABLE `device_commands`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=140;

--
-- AUTO_INCREMENT for table `fingerprints`
--
ALTER TABLE `fingerprints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

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
-- AUTO_INCREMENT for table `school_years`
--
ALTER TABLE `school_years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=650;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `teacher_daily_schedules`
--
ALTER TABLE `teacher_daily_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `teacher_sections`
--
ALTER TABLE `teacher_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

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
