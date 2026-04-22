-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 13, 2026 at 09:08 AM
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
(1, 41, 5, '2026-03-02', '10:58:22', '12:05:28', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(2, 42, 5, '2026-03-02', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(3, 43, 5, '2026-03-02', '10:55:02', '11:55:30', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(4, 44, 5, '2026-03-02', '11:27:17', '12:05:32', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(5, 45, 5, '2026-03-02', '10:56:51', '11:56:39', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(6, 46, 5, '2026-03-02', '11:04:55', '12:06:48', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(7, 47, 5, '2026-03-02', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(8, 48, 5, '2026-03-02', '10:56:15', '11:56:41', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(9, 49, 5, '2026-03-02', '11:25:20', '11:52:19', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(10, 50, 5, '2026-03-02', '10:57:42', '11:56:38', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(11, 51, 5, '2026-03-02', '10:55:25', '12:09:23', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(12, 52, 5, '2026-03-02', '11:03:38', '12:06:26', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(13, 53, 5, '2026-03-02', '11:00:10', '11:54:19', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(14, 54, 5, '2026-03-02', '11:35:45', '12:09:10', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(15, 55, 5, '2026-03-02', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(16, 56, 5, '2026-03-02', '10:56:53', '11:52:44', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(17, 57, 5, '2026-03-02', '11:01:45', '11:57:21', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(18, 58, 5, '2026-03-02', '11:04:36', '12:00:15', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(19, 59, 5, '2026-03-02', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(20, 61, 5, '2026-03-02', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(21, 63, 5, '2026-03-02', '11:55:05', '11:51:06', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(22, 64, 5, '2026-03-02', '11:02:59', '12:02:52', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(23, 65, 5, '2026-03-02', '11:04:53', '12:00:46', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(24, 66, 5, '2026-03-02', '11:30:34', '11:57:48', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(25, 67, 5, '2026-03-02', '10:56:15', '11:57:10', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(26, 68, 5, '2026-03-02', '10:57:37', '12:06:48', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(27, 60, 5, '2026-03-02', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(28, 69, 5, '2026-03-02', '10:59:13', '11:55:24', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(29, 70, 5, '2026-03-02', '10:58:07', '11:56:34', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(30, 71, 5, '2026-03-02', '11:04:04', '11:52:35', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(31, 72, 5, '2026-03-02', '10:56:34', '11:53:12', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(32, 73, 5, '2026-03-02', '11:19:36', '12:03:15', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(33, 74, 5, '2026-03-02', '10:58:25', '12:08:04', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(34, 75, 5, '2026-03-02', '11:16:31', '11:56:23', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(35, 76, 5, '2026-03-02', '11:20:48', '12:05:28', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(36, 77, 5, '2026-03-02', '11:38:07', '11:52:14', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(37, 78, 5, '2026-03-02', '11:03:09', '11:53:20', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(38, 79, 5, '2026-03-02', '11:42:32', '11:56:23', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(39, 80, 5, '2026-03-02', '11:55:54', '12:07:37', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(40, 81, 5, '2026-03-02', '11:03:14', '12:03:39', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(41, 82, 5, '2026-03-02', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(42, 62, 5, '2026-03-02', '11:03:36', '11:51:46', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(43, 41, 5, '2026-03-03', '12:56:07', '14:53:13', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(44, 42, 5, '2026-03-03', '13:02:01', '15:03:56', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(45, 43, 5, '2026-03-03', '12:56:36', '14:57:27', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(46, 44, 5, '2026-03-03', '13:44:15', '14:52:26', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(47, 45, 5, '2026-03-03', '13:00:50', '15:07:23', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(48, 46, 5, '2026-03-03', '13:01:20', '15:06:58', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(49, 47, 5, '2026-03-03', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(50, 48, 5, '2026-03-03', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(51, 49, 5, '2026-03-03', '13:46:38', '14:57:41', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(52, 50, 5, '2026-03-03', '13:28:02', '14:54:47', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(53, 51, 5, '2026-03-03', '12:57:03', '15:08:35', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(54, 52, 5, '2026-03-03', '13:56:18', '14:56:01', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(55, 53, 5, '2026-03-03', '13:04:04', '14:52:00', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(56, 54, 5, '2026-03-03', '13:35:25', '14:54:05', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(57, 55, 5, '2026-03-03', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(58, 56, 5, '2026-03-03', '12:59:26', '15:05:09', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(59, 57, 5, '2026-03-03', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(60, 58, 5, '2026-03-03', '12:57:22', '15:03:13', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(61, 59, 5, '2026-03-03', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(62, 61, 5, '2026-03-03', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(63, 63, 5, '2026-03-03', '13:31:09', '15:09:31', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(64, 64, 5, '2026-03-03', '13:19:06', '15:01:31', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(65, 65, 5, '2026-03-03', '12:58:33', '14:59:44', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(66, 66, 5, '2026-03-03', '13:33:57', '15:04:32', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(67, 67, 5, '2026-03-03', '13:46:27', '15:04:31', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(68, 68, 5, '2026-03-03', '12:59:51', '15:00:20', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(69, 60, 5, '2026-03-03', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(70, 69, 5, '2026-03-03', '13:01:43', '15:04:00', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(71, 70, 5, '2026-03-03', '13:00:41', '14:53:43', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(72, 71, 5, '2026-03-03', '13:02:43', '15:03:57', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(73, 72, 5, '2026-03-03', '13:02:25', '14:59:17', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(74, 73, 5, '2026-03-03', '13:28:12', '15:03:42', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(75, 74, 5, '2026-03-03', '12:59:32', '15:09:50', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(76, 75, 5, '2026-03-03', '13:36:49', '15:01:03', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(77, 76, 5, '2026-03-03', '13:32:28', '15:01:10', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(78, 77, 5, '2026-03-03', '13:52:23', '15:08:55', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(79, 78, 5, '2026-03-03', '12:58:47', '14:54:39', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(80, 79, 5, '2026-03-03', '13:47:57', '15:04:26', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(81, 80, 5, '2026-03-03', '13:16:47', '14:59:01', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(82, 81, 5, '2026-03-03', '13:02:51', '14:55:34', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(83, 82, 5, '2026-03-03', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(84, 62, 5, '2026-03-03', '13:04:55', '14:55:41', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(85, 41, 5, '2026-03-09', '11:23:31', '12:00:33', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(86, 42, 5, '2026-03-09', '10:55:42', '11:54:56', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(87, 43, 5, '2026-03-09', '10:59:48', '11:53:17', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(88, 44, 5, '2026-03-09', '11:29:28', '12:06:18', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(89, 45, 5, '2026-03-09', '11:50:22', '12:03:53', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(90, 46, 5, '2026-03-09', '11:02:55', '12:07:11', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(91, 47, 5, '2026-03-09', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(92, 48, 5, '2026-03-09', '11:20:11', '11:55:01', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(93, 49, 5, '2026-03-09', '11:45:29', '12:00:40', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(94, 50, 5, '2026-03-09', '10:59:47', '12:08:22', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(95, 51, 5, '2026-03-09', '10:59:14', '12:07:44', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(96, 52, 5, '2026-03-09', '11:02:05', '11:53:20', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(97, 53, 5, '2026-03-09', '11:01:16', '12:04:10', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(98, 54, 5, '2026-03-09', '11:38:09', '11:53:10', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(99, 55, 5, '2026-03-09', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(100, 56, 5, '2026-03-09', '10:55:27', '11:57:17', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(101, 57, 5, '2026-03-09', '11:03:07', '12:06:02', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(102, 58, 5, '2026-03-09', '10:59:33', '11:54:08', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(103, 59, 5, '2026-03-09', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(104, 61, 5, '2026-03-09', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(105, 63, 5, '2026-03-09', '11:35:14', '11:54:10', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(106, 64, 5, '2026-03-09', '10:55:54', '11:51:25', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(107, 65, 5, '2026-03-09', '11:20:05', '11:58:29', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(108, 66, 5, '2026-03-09', '11:38:09', '11:50:51', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(109, 67, 5, '2026-03-09', '11:01:20', '12:06:46', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(110, 68, 5, '2026-03-09', '11:04:35', '11:53:02', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(111, 60, 5, '2026-03-09', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(112, 69, 5, '2026-03-09', '11:01:32', '11:54:21', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(113, 70, 5, '2026-03-09', '10:56:08', '11:55:11', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(114, 71, 5, '2026-03-09', '10:56:31', '12:06:43', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(115, 72, 5, '2026-03-09', '11:03:28', '11:56:36', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(116, 73, 5, '2026-03-09', '11:51:38', '11:59:53', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(117, 74, 5, '2026-03-09', '10:56:44', '11:59:56', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(118, 75, 5, '2026-03-09', '11:53:13', '12:07:27', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(119, 76, 5, '2026-03-09', '11:50:47', '12:09:21', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(120, 77, 5, '2026-03-09', '11:49:16', '11:54:51', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(121, 78, 5, '2026-03-09', '11:03:42', '11:51:52', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(122, 79, 5, '2026-03-09', '11:50:14', '12:01:32', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(123, 80, 5, '2026-03-09', '11:30:25', '11:51:12', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(124, 81, 5, '2026-03-09', '11:48:29', '11:51:53', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(125, 82, 5, '2026-03-09', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(126, 62, 5, '2026-03-09', '11:18:03', '11:59:13', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(127, 41, 5, '2026-03-10', '13:04:58', '15:06:50', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(128, 42, 5, '2026-03-10', '13:56:22', '14:57:45', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(129, 43, 5, '2026-03-10', '13:00:44', '14:59:41', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(130, 44, 5, '2026-03-10', '13:50:16', '14:55:39', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(131, 45, 5, '2026-03-10', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(132, 46, 5, '2026-03-10', '13:02:34', '15:09:54', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(133, 47, 5, '2026-03-10', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(134, 48, 5, '2026-03-10', '13:03:51', '15:09:40', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(135, 49, 5, '2026-03-10', '13:58:53', '14:54:16', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(136, 50, 5, '2026-03-10', '12:55:51', '14:54:09', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(137, 51, 5, '2026-03-10', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(138, 52, 5, '2026-03-10', '13:04:59', '14:59:11', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(139, 53, 5, '2026-03-10', '13:02:25', '15:04:39', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(140, 54, 5, '2026-03-10', '13:44:49', '14:57:13', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(141, 55, 5, '2026-03-10', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(142, 56, 5, '2026-03-10', '12:56:29', '14:59:54', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(143, 57, 5, '2026-03-10', '13:01:13', '14:50:17', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(144, 58, 5, '2026-03-10', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(145, 59, 5, '2026-03-10', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(146, 61, 5, '2026-03-10', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(147, 63, 5, '2026-03-10', '13:33:57', '15:09:22', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(148, 64, 5, '2026-03-10', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(149, 65, 5, '2026-03-10', '13:35:29', '14:57:52', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(150, 66, 5, '2026-03-10', '13:27:20', '15:07:40', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(151, 67, 5, '2026-03-10', '12:59:34', '15:03:00', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(152, 68, 5, '2026-03-10', '13:47:42', '14:55:40', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(153, 60, 5, '2026-03-10', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(154, 69, 5, '2026-03-10', '12:58:17', '14:50:18', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(155, 70, 5, '2026-03-10', '12:56:20', '15:09:05', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(156, 71, 5, '2026-03-10', '13:04:42', '14:55:23', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(157, 72, 5, '2026-03-10', '13:03:02', '15:00:09', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(158, 73, 5, '2026-03-10', '13:36:52', '14:50:58', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(159, 74, 5, '2026-03-10', '13:02:44', '14:52:22', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(160, 75, 5, '2026-03-10', '13:23:49', '14:55:15', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(161, 76, 5, '2026-03-10', '13:39:15', '15:01:58', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(162, 77, 5, '2026-03-10', '13:22:14', '15:01:10', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(163, 78, 5, '2026-03-10', '12:58:38', '14:55:04', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(164, 79, 5, '2026-03-10', '13:25:00', '15:03:58', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(165, 80, 5, '2026-03-10', '13:33:06', '15:00:05', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(166, 81, 5, '2026-03-10', '13:54:02', '14:55:53', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(167, 82, 5, '2026-03-10', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(168, 62, 5, '2026-03-10', '12:55:59', '15:00:59', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(169, 41, 5, '2026-03-16', '11:03:07', '12:07:25', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(170, 42, 5, '2026-03-16', '11:29:18', '11:58:45', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(171, 43, 5, '2026-03-16', '10:55:05', '11:50:45', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(172, 44, 5, '2026-03-16', '11:45:01', '11:55:27', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(173, 45, 5, '2026-03-16', '10:59:12', '11:55:31', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(174, 46, 5, '2026-03-16', '10:55:49', '12:02:01', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(175, 47, 5, '2026-03-16', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(176, 48, 5, '2026-03-16', '11:21:09', '11:59:48', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(177, 49, 5, '2026-03-16', '11:18:29', '12:00:01', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(178, 50, 5, '2026-03-16', '10:56:58', '11:53:30', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(179, 51, 5, '2026-03-16', '11:00:29', '11:53:55', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(180, 52, 5, '2026-03-16', '11:00:05', '11:51:41', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(181, 53, 5, '2026-03-16', '11:00:52', '12:01:24', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(182, 54, 5, '2026-03-16', '11:33:12', '11:58:13', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(183, 55, 5, '2026-03-16', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(184, 56, 5, '2026-03-16', '10:59:19', '12:08:55', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(185, 57, 5, '2026-03-16', '10:57:13', '11:57:47', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(186, 58, 5, '2026-03-16', '11:03:37', '11:50:32', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(187, 59, 5, '2026-03-16', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(188, 61, 5, '2026-03-16', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(189, 63, 5, '2026-03-16', '11:29:21', '12:03:20', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(190, 64, 5, '2026-03-16', '11:01:12', '11:57:11', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(191, 65, 5, '2026-03-16', '10:56:11', '12:08:52', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(192, 66, 5, '2026-03-16', '11:34:53', '12:01:56', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(193, 67, 5, '2026-03-16', '10:55:47', '11:59:53', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(194, 68, 5, '2026-03-16', '11:04:34', '12:06:27', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(195, 60, 5, '2026-03-16', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(196, 69, 5, '2026-03-16', '11:04:36', '12:03:47', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(197, 70, 5, '2026-03-16', '10:58:53', '11:55:30', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(198, 71, 5, '2026-03-16', '10:56:53', '11:59:52', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(199, 72, 5, '2026-03-16', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(200, 73, 5, '2026-03-16', '11:37:54', '11:53:46', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(201, 74, 5, '2026-03-16', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(202, 75, 5, '2026-03-16', '11:16:53', '12:05:54', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(203, 76, 5, '2026-03-16', '11:31:39', '12:04:47', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(204, 77, 5, '2026-03-16', '11:49:25', '12:02:25', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(205, 78, 5, '2026-03-16', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(206, 79, 5, '2026-03-16', '11:43:19', '12:04:57', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(207, 80, 5, '2026-03-16', '11:48:05', '12:00:27', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(208, 81, 5, '2026-03-16', '11:02:28', '12:07:25', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(209, 82, 5, '2026-03-16', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(210, 62, 5, '2026-03-16', '11:00:47', '11:58:24', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(211, 41, 5, '2026-03-17', '13:04:02', '14:57:53', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(212, 42, 5, '2026-03-17', '13:01:57', '14:57:22', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(213, 43, 5, '2026-03-17', '12:57:09', '15:09:14', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(214, 44, 5, '2026-03-17', '13:49:34', '14:51:32', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(215, 45, 5, '2026-03-17', '12:57:31', '14:50:03', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(216, 46, 5, '2026-03-17', '12:59:55', '15:07:54', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(217, 47, 5, '2026-03-17', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(218, 48, 5, '2026-03-17', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(219, 49, 5, '2026-03-17', '13:21:37', '15:05:40', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(220, 50, 5, '2026-03-17', '13:02:33', '14:52:31', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(221, 51, 5, '2026-03-17', '13:49:20', '14:57:20', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(222, 52, 5, '2026-03-17', '12:56:37', '15:00:07', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(223, 53, 5, '2026-03-17', '13:59:44', '15:07:49', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(224, 54, 5, '2026-03-17', '13:27:00', '14:57:52', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(225, 55, 5, '2026-03-17', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(226, 56, 5, '2026-03-17', '12:55:57', '15:08:56', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(227, 57, 5, '2026-03-17', '12:58:52', '15:01:40', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(228, 58, 5, '2026-03-17', '12:55:53', '14:57:01', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(229, 59, 5, '2026-03-17', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(230, 61, 5, '2026-03-17', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(231, 63, 5, '2026-03-17', '13:49:16', '14:53:15', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(232, 64, 5, '2026-03-17', '13:33:59', '14:54:39', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(233, 65, 5, '2026-03-17', '12:59:53', '14:52:46', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(234, 66, 5, '2026-03-17', '13:57:28', '15:06:35', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(235, 67, 5, '2026-03-17', '13:16:04', '15:08:16', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(236, 68, 5, '2026-03-17', '12:58:42', '14:59:33', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(237, 60, 5, '2026-03-17', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(238, 69, 5, '2026-03-17', '12:58:10', '14:50:49', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(239, 70, 5, '2026-03-17', '13:03:38', '14:51:19', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(240, 71, 5, '2026-03-17', '13:02:23', '15:03:52', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(241, 72, 5, '2026-03-17', '13:42:13', '14:56:41', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(242, 73, 5, '2026-03-17', '13:40:24', '14:55:17', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(243, 74, 5, '2026-03-17', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(244, 75, 5, '2026-03-17', '13:50:56', '14:50:44', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(245, 76, 5, '2026-03-17', '13:56:18', '14:51:02', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(246, 77, 5, '2026-03-17', '13:40:46', '14:52:10', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(247, 78, 5, '2026-03-17', '13:50:31', '14:51:56', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(248, 79, 5, '2026-03-17', '13:48:30', '15:05:33', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(249, 80, 5, '2026-03-17', '13:45:14', '15:01:17', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(250, 81, 5, '2026-03-17', '13:01:40', '14:54:44', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(251, 82, 5, '2026-03-17', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(252, 62, 5, '2026-03-17', '12:58:15', '15:02:07', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:17', '2026-04-13 05:58:17'),
(253, 41, 5, '2026-03-23', '11:03:30', '11:55:22', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(254, 42, 5, '2026-03-23', '11:04:09', '12:09:07', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(255, 43, 5, '2026-03-23', '11:00:59', '11:55:12', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(256, 44, 5, '2026-03-23', '11:41:48', '12:07:30', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(257, 45, 5, '2026-03-23', '10:59:54', '11:56:06', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(258, 46, 5, '2026-03-23', '10:56:11', '12:07:10', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(259, 47, 5, '2026-03-23', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(260, 48, 5, '2026-03-23', '11:53:16', '11:51:55', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(261, 49, 5, '2026-03-23', '11:29:36', '12:01:17', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(262, 50, 5, '2026-03-23', '10:59:02', '12:07:26', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(263, 51, 5, '2026-03-23', '10:59:56', '12:07:14', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(264, 52, 5, '2026-03-23', '10:56:18', '11:50:22', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(265, 53, 5, '2026-03-23', '11:03:33', '12:06:37', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(266, 54, 5, '2026-03-23', '11:32:10', '12:01:49', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(267, 55, 5, '2026-03-23', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(268, 56, 5, '2026-03-23', '10:57:43', '12:05:48', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(269, 57, 5, '2026-03-23', '10:55:35', '11:55:37', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(270, 58, 5, '2026-03-23', '11:45:53', '11:51:19', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(271, 59, 5, '2026-03-23', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(272, 61, 5, '2026-03-23', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(273, 63, 5, '2026-03-23', '11:21:16', '11:57:28', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(274, 64, 5, '2026-03-23', '11:00:24', '11:53:25', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(275, 65, 5, '2026-03-23', '11:04:44', '12:00:49', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(276, 66, 5, '2026-03-23', '11:54:35', '12:05:04', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(277, 67, 5, '2026-03-23', '11:04:49', '11:52:28', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(278, 68, 5, '2026-03-23', '10:59:19', '12:06:42', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(279, 60, 5, '2026-03-23', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(280, 69, 5, '2026-03-23', '10:58:28', '11:58:55', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(281, 70, 5, '2026-03-23', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(282, 71, 5, '2026-03-23', '11:03:49', '12:00:24', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(283, 72, 5, '2026-03-23', '11:03:51', '12:08:14', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(284, 73, 5, '2026-03-23', '11:49:21', '11:53:44', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(285, 74, 5, '2026-03-23', '11:01:54', '12:00:09', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(286, 75, 5, '2026-03-23', '11:38:45', '12:09:08', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(287, 76, 5, '2026-03-23', '11:35:21', '12:04:50', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(288, 77, 5, '2026-03-23', '11:34:29', '12:04:58', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(289, 78, 5, '2026-03-23', '11:33:11', '12:05:14', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(290, 79, 5, '2026-03-23', '11:27:04', '11:58:45', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(291, 80, 5, '2026-03-23', '11:17:27', '11:51:58', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(292, 81, 5, '2026-03-23', '10:55:04', '12:04:36', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(293, 82, 5, '2026-03-23', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(294, 62, 5, '2026-03-23', '11:02:12', '12:07:13', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(295, 41, 5, '2026-03-24', '12:58:11', '15:00:42', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(296, 42, 5, '2026-03-24', '13:00:23', '15:08:59', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(297, 43, 5, '2026-03-24', '12:55:38', '14:52:16', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(298, 44, 5, '2026-03-24', '13:59:53', '14:58:54', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(299, 45, 5, '2026-03-24', '12:57:25', '15:05:33', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(300, 46, 5, '2026-03-24', '12:57:49', '15:02:20', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(301, 47, 5, '2026-03-24', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(302, 48, 5, '2026-03-24', '12:55:41', '15:05:14', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(303, 49, 5, '2026-03-24', '13:59:06', '14:53:15', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(304, 50, 5, '2026-03-24', '13:00:10', '14:54:21', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(305, 51, 5, '2026-03-24', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(306, 52, 5, '2026-03-24', '13:03:33', '15:09:47', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(307, 53, 5, '2026-03-24', '13:00:46', '15:03:38', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(308, 54, 5, '2026-03-24', '13:24:48', '14:51:22', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(309, 55, 5, '2026-03-24', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:18', '2026-04-13 05:58:18');
INSERT INTO `attendance` (`id`, `student_id`, `section_id`, `attendance_date`, `time_in_am`, `time_out_am`, `time_in_pm`, `time_out_pm`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(310, 56, 5, '2026-03-24', '12:58:50', '14:59:13', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(311, 57, 5, '2026-03-24', '13:17:24', '14:51:11', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(312, 58, 5, '2026-03-24', '13:03:54', '15:03:04', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(313, 59, 5, '2026-03-24', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(314, 61, 5, '2026-03-24', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(315, 63, 5, '2026-03-24', '13:43:13', '15:04:06', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(316, 64, 5, '2026-03-24', '13:00:09', '15:01:19', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(317, 65, 5, '2026-03-24', '12:59:39', '15:02:25', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(318, 66, 5, '2026-03-24', '13:21:39', '15:02:29', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(319, 67, 5, '2026-03-24', '12:56:43', '15:03:04', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(320, 68, 5, '2026-03-24', '13:04:42', '15:03:13', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(321, 60, 5, '2026-03-24', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(322, 69, 5, '2026-03-24', '13:01:09', '15:06:54', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(323, 70, 5, '2026-03-24', '13:04:25', '14:53:31', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(324, 71, 5, '2026-03-24', '13:03:09', '14:54:01', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(325, 72, 5, '2026-03-24', '13:01:08', '15:02:26', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(326, 73, 5, '2026-03-24', '13:34:02', '14:59:21', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(327, 74, 5, '2026-03-24', '12:56:38', '14:51:41', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(328, 75, 5, '2026-03-24', '13:54:30', '15:07:51', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(329, 76, 5, '2026-03-24', '13:50:39', '14:59:54', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(330, 77, 5, '2026-03-24', '13:59:23', '15:05:46', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(331, 78, 5, '2026-03-24', '13:36:19', '15:02:16', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(332, 79, 5, '2026-03-24', '13:45:48', '15:05:00', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(333, 80, 5, '2026-03-24', '13:48:32', '15:03:08', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(334, 81, 5, '2026-03-24', '13:25:57', '15:08:38', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(335, 82, 5, '2026-03-24', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(336, 62, 5, '2026-03-24', '12:56:38', '14:54:53', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(337, 41, 5, '2026-03-30', '11:03:57', '11:52:05', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(338, 42, 5, '2026-03-30', '11:03:04', '12:08:50', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(339, 43, 5, '2026-03-30', '10:56:02', '11:51:19', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(340, 44, 5, '2026-03-30', '11:50:46', '12:07:21', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(341, 45, 5, '2026-03-30', '10:59:36', '12:05:02', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(342, 46, 5, '2026-03-30', '11:01:03', '11:59:30', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(343, 47, 5, '2026-03-30', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(344, 48, 5, '2026-03-30', '10:56:42', '12:03:11', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(345, 49, 5, '2026-03-30', '11:34:23', '12:04:16', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(346, 50, 5, '2026-03-30', '11:00:56', '12:05:53', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(347, 51, 5, '2026-03-30', '10:55:42', '11:59:57', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(348, 52, 5, '2026-03-30', '11:01:57', '11:58:11', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(349, 53, 5, '2026-03-30', '11:01:20', '11:50:27', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(350, 54, 5, '2026-03-30', '11:59:03', '11:50:35', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(351, 55, 5, '2026-03-30', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(352, 56, 5, '2026-03-30', '10:57:40', '11:57:06', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(353, 57, 5, '2026-03-30', '11:03:18', '12:02:14', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(354, 58, 5, '2026-03-30', '10:59:59', '11:55:37', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(355, 59, 5, '2026-03-30', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(356, 61, 5, '2026-03-30', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(357, 63, 5, '2026-03-30', '11:18:12', '11:57:05', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(358, 64, 5, '2026-03-30', '11:03:19', '12:02:32', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(359, 65, 5, '2026-03-30', '10:58:07', '12:05:42', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(360, 66, 5, '2026-03-30', '11:26:00', '11:57:30', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(361, 67, 5, '2026-03-30', '10:58:38', '12:07:20', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(362, 68, 5, '2026-03-30', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(363, 60, 5, '2026-03-30', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(364, 69, 5, '2026-03-30', '11:22:36', '12:00:09', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(365, 70, 5, '2026-03-30', '11:31:23', '12:02:06', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(366, 71, 5, '2026-03-30', '11:00:30', '11:51:22', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(367, 72, 5, '2026-03-30', '11:19:48', '11:58:54', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(368, 73, 5, '2026-03-30', '11:44:46', '11:56:59', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(369, 74, 5, '2026-03-30', '11:02:05', '12:04:08', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(370, 75, 5, '2026-03-30', '11:23:14', '11:52:27', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(371, 76, 5, '2026-03-30', '11:24:44', '12:07:55', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(372, 77, 5, '2026-03-30', '11:35:54', '12:05:29', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(373, 78, 5, '2026-03-30', '11:00:32', '12:04:01', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(374, 79, 5, '2026-03-30', '11:31:10', '12:06:48', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(375, 80, 5, '2026-03-30', '11:32:07', '11:56:13', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(376, 81, 5, '2026-03-30', '11:00:02', '12:06:18', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(377, 82, 5, '2026-03-30', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(378, 62, 5, '2026-03-30', '11:01:43', '11:53:45', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(379, 41, 5, '2026-03-31', '12:56:49', '15:02:08', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(380, 42, 5, '2026-03-31', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(381, 43, 5, '2026-03-31', '12:59:16', '14:53:20', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(382, 44, 5, '2026-03-31', '13:21:16', '15:03:55', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(383, 45, 5, '2026-03-31', '12:59:44', '15:04:34', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(384, 46, 5, '2026-03-31', '12:57:59', '15:09:42', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(385, 47, 5, '2026-03-31', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(386, 48, 5, '2026-03-31', '12:56:24', '15:01:11', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(387, 49, 5, '2026-03-31', '13:19:51', '14:57:31', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(388, 50, 5, '2026-03-31', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(389, 51, 5, '2026-03-31', '13:00:01', '15:09:40', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(390, 52, 5, '2026-03-31', '12:58:05', '14:52:52', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(391, 53, 5, '2026-03-31', '12:59:38', '15:00:11', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(392, 54, 5, '2026-03-31', '13:53:53', '14:51:01', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(393, 55, 5, '2026-03-31', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(394, 56, 5, '2026-03-31', '13:01:47', '14:51:44', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(395, 57, 5, '2026-03-31', '12:55:41', '15:08:03', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(396, 58, 5, '2026-03-31', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(397, 59, 5, '2026-03-31', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(398, 61, 5, '2026-03-31', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(399, 63, 5, '2026-03-31', '13:32:28', '14:51:59', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(400, 64, 5, '2026-03-31', '12:56:54', '15:03:31', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(401, 65, 5, '2026-03-31', '12:59:17', '14:55:51', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(402, 66, 5, '2026-03-31', '13:18:06', '15:06:37', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(403, 67, 5, '2026-03-31', '12:58:22', '14:59:08', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(404, 68, 5, '2026-03-31', '13:00:38', '14:58:36', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(405, 60, 5, '2026-03-31', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(406, 69, 5, '2026-03-31', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(407, 70, 5, '2026-03-31', '13:03:56', '15:03:32', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(408, 71, 5, '2026-03-31', '12:57:23', '15:05:34', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(409, 72, 5, '2026-03-31', '13:01:31', '15:03:34', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(410, 73, 5, '2026-03-31', '13:31:06', '14:58:14', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(411, 74, 5, '2026-03-31', '12:57:20', '15:06:57', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(412, 75, 5, '2026-03-31', '13:36:39', '14:52:20', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(413, 76, 5, '2026-03-31', '13:34:37', '14:50:09', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(414, 77, 5, '2026-03-31', '13:44:50', '15:07:28', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(415, 78, 5, '2026-03-31', '13:04:17', '14:57:41', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(416, 79, 5, '2026-03-31', '13:21:52', '15:05:23', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(417, 80, 5, '2026-03-31', '13:56:36', '15:07:22', NULL, NULL, 'late', 'Seeded by seed_schedule_attendance.php (L)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(418, 81, 5, '2026-03-31', '12:59:27', '15:01:18', NULL, NULL, 'present', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(419, 82, 5, '2026-03-31', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (A)', '2026-04-13 05:58:18', '2026-04-13 05:58:18'),
(420, 62, 5, '2026-03-31', NULL, NULL, NULL, NULL, 'absent', 'Seeded by seed_schedule_attendance.php (P)', '2026-04-13 05:58:18', '2026-04-13 05:58:18');

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
(12, 62, 1, 'IN', '2026-04-13 02:16:18'),
(13, 60, 1, 'IN', '2026-04-13 02:17:42'),
(14, 42, 1, 'IN', '2026-04-13 02:19:17'),
(15, 41, 1, 'IN', '2026-04-13 02:20:22'),
(16, 77, 1, 'IN', '2026-04-13 02:20:34');

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
(139, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-12 11:46:22', '2026-04-12 11:46:22'),
(140, 1, 'DELETE', NULL, NULL, 0, NULL, 3, 'FAILED', 'clearAll code=1', '2026-04-13 00:36:42', '2026-04-13 01:16:59'),
(141, 1, 'ENROLL', 40, 1, 1, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:18:15', '2026-04-13 01:18:26'),
(142, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:18:26', '2026-04-13 01:18:26'),
(143, 1, 'DELETE', 40, NULL, 1, NULL, 3, 'COMPLETED', NULL, '2026-04-13 01:18:30', '2026-04-13 01:19:22'),
(144, 1, 'ENROLL', 40, 1, NULL, 1, 3, 'FAILED', 'Mode switched to attendance', '2026-04-13 01:18:30', '2026-04-13 01:18:31'),
(145, 1, 'ENROLL', 41, 1, 1, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:25:19', '2026-04-13 01:25:34'),
(146, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:25:34', '2026-04-13 01:25:34'),
(147, 1, 'ENROLL', 42, 1, 3, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:26:45', '2026-04-13 01:26:52'),
(148, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:26:55', '2026-04-13 01:26:55'),
(149, 1, 'ENROLL', 43, 1, 4, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:28:48', '2026-04-13 01:28:58'),
(150, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:28:58', '2026-04-13 01:28:58'),
(151, 1, 'ENROLL', 44, 1, 5, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:30:42', '2026-04-13 01:30:49'),
(152, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:30:49', '2026-04-13 01:30:49'),
(153, 1, 'ENROLL', 45, 1, 6, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:31:26', '2026-04-13 01:31:33'),
(154, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:31:33', '2026-04-13 01:31:33'),
(155, 1, 'ENROLL', 46, 1, 7, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:32:09', '2026-04-13 01:32:21'),
(156, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:32:22', '2026-04-13 01:32:22'),
(157, 1, 'ENROLL', 47, 1, NULL, 3, 3, 'FAILED', 'storeModel code=1', '2026-04-13 01:32:51', '2026-04-13 01:32:59'),
(158, 1, 'ENROLL', 47, 1, 9, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:33:09', '2026-04-13 01:33:29'),
(159, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:33:29', '2026-04-13 01:33:29'),
(160, 1, 'ENROLL', 48, 1, 10, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:34:07', '2026-04-13 01:34:14'),
(161, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:34:14', '2026-04-13 01:34:14'),
(162, 1, 'ENROLL', 49, 1, NULL, 3, 3, 'FAILED', 'storeModel code=1', '2026-04-13 01:35:06', '2026-04-13 01:35:14'),
(163, 1, 'ENROLL', 49, 1, 12, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:35:26', '2026-04-13 01:35:47'),
(164, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:35:48', '2026-04-13 01:35:48'),
(165, 1, 'ENROLL', 50, 1, 13, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:36:18', '2026-04-13 01:36:25'),
(166, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:36:25', '2026-04-13 01:36:25'),
(167, 1, 'ENROLL', 51, 1, 14, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:37:37', '2026-04-13 01:37:58'),
(168, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:37:58', '2026-04-13 01:37:58'),
(169, 1, 'ENROLL', 52, 1, NULL, 3, 3, 'FAILED', 'storeModel code=1', '2026-04-13 01:39:27', '2026-04-13 01:39:54'),
(170, 1, 'ENROLL', 52, 1, 15, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:39:58', '2026-04-13 01:40:08'),
(171, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:40:08', '2026-04-13 01:40:08'),
(172, 1, 'ENROLL', 53, 1, 16, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:40:42', '2026-04-13 01:40:54'),
(173, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:40:55', '2026-04-13 01:40:55'),
(174, 1, 'ENROLL', 54, 1, 17, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:41:25', '2026-04-13 01:41:33'),
(175, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:41:33', '2026-04-13 01:41:33'),
(176, 1, 'ENROLL', 55, 1, 18, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:42:26', '2026-04-13 01:42:37'),
(177, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:42:37', '2026-04-13 01:42:37'),
(178, 1, 'ENROLL', 56, 1, 19, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:43:03', '2026-04-13 01:43:10'),
(179, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:43:10', '2026-04-13 01:43:10'),
(180, 1, 'ENROLL', 57, 1, 20, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:44:00', '2026-04-13 01:44:07'),
(181, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:44:10', '2026-04-13 01:44:10'),
(182, 1, 'ENROLL', 58, 1, 21, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:44:40', '2026-04-13 01:44:47'),
(183, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:44:47', '2026-04-13 01:44:47'),
(184, 1, 'ENROLL', 59, 1, 22, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:46:20', '2026-04-13 01:46:26'),
(185, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:46:26', '2026-04-13 01:46:26'),
(186, 1, 'ENROLL', 60, 1, 23, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:46:50', '2026-04-13 01:46:57'),
(187, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:46:57', '2026-04-13 01:46:57'),
(188, 1, 'ENROLL', 61, 1, 24, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:47:57', '2026-04-13 01:48:30'),
(189, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:48:30', '2026-04-13 01:48:30'),
(190, 1, 'ENROLL', 62, 1, 25, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:49:08', '2026-04-13 01:49:50'),
(191, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:49:50', '2026-04-13 01:49:50'),
(192, 1, 'ENROLL', 63, 1, 26, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:50:35', '2026-04-13 01:50:44'),
(193, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:50:44', '2026-04-13 01:50:44'),
(194, 1, 'ENROLL', 64, 1, 27, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:51:20', '2026-04-13 01:51:28'),
(195, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:51:28', '2026-04-13 01:51:28'),
(196, 1, 'ENROLL', 65, 1, 28, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:52:08', '2026-04-13 01:52:15'),
(197, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:52:15', '2026-04-13 01:52:15'),
(198, 1, 'ENROLL', 66, 1, 29, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:52:53', '2026-04-13 01:53:01'),
(199, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:53:01', '2026-04-13 01:53:01'),
(200, 1, 'ENROLL', 67, 1, 30, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:53:42', '2026-04-13 01:53:51'),
(201, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:53:51', '2026-04-13 01:53:51'),
(202, 1, 'ENROLL', 68, 1, 31, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:54:24', '2026-04-13 01:54:40'),
(203, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:54:40', '2026-04-13 01:54:40'),
(204, 1, 'ENROLL', 69, 1, 32, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:55:21', '2026-04-13 01:55:29'),
(205, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:55:29', '2026-04-13 01:55:29'),
(206, 1, 'ENROLL', 70, 1, 33, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:56:12', '2026-04-13 01:56:28'),
(207, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:56:28', '2026-04-13 01:56:28'),
(208, 1, 'ENROLL', 71, 1, 34, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:58:26', '2026-04-13 01:58:38'),
(209, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:58:38', '2026-04-13 01:58:38'),
(210, 1, 'ENROLL', 72, 1, NULL, 2, 3, 'FAILED', 'getImage#2 code=32', '2026-04-13 01:59:10', '2026-04-13 01:59:15'),
(211, 1, 'ENROLL', 72, 1, 35, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 01:59:19', '2026-04-13 01:59:26'),
(212, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 01:59:26', '2026-04-13 01:59:26'),
(213, 1, 'ENROLL', 73, 1, 36, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 02:00:22', '2026-04-13 02:00:30'),
(214, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 02:00:34', '2026-04-13 02:00:34'),
(215, 1, 'ENROLL', 74, 1, 37, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 02:00:54', '2026-04-13 02:01:04'),
(216, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 02:01:04', '2026-04-13 02:01:04'),
(217, 1, 'ENROLL', 75, 1, 38, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 02:01:30', '2026-04-13 02:02:02'),
(218, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 02:02:02', '2026-04-13 02:02:02'),
(219, 1, 'ENROLL', 76, 1, 39, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 02:02:34', '2026-04-13 02:02:45'),
(220, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 02:02:46', '2026-04-13 02:02:46'),
(221, 1, 'ENROLL', 77, 1, NULL, 3, 3, 'FAILED', 'storeModel code=1', '2026-04-13 02:03:28', '2026-04-13 02:03:38'),
(222, 1, 'ENROLL', 77, 1, 40, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 02:03:40', '2026-04-13 02:03:54'),
(223, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 02:03:54', '2026-04-13 02:03:54'),
(224, 1, 'ENROLL', 78, 1, 41, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 02:04:21', '2026-04-13 02:04:28'),
(225, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 02:04:28', '2026-04-13 02:04:28'),
(226, 1, 'ENROLL', 79, 1, NULL, 2, 3, 'FAILED', 'createModel mismatch', '2026-04-13 02:04:57', '2026-04-13 02:05:13'),
(227, 1, 'ENROLL', 79, 1, NULL, 2, 3, 'FAILED', 'createModel mismatch', '2026-04-13 02:05:17', '2026-04-13 02:05:27'),
(228, 1, 'ENROLL', 79, 1, 42, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 02:05:29', '2026-04-13 02:05:38'),
(229, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 02:05:38', '2026-04-13 02:05:38'),
(230, 1, 'ENROLL', 80, 1, NULL, 3, 3, 'FAILED', 'storeModel code=1', '2026-04-13 02:06:11', '2026-04-13 02:06:20'),
(231, 1, 'ENROLL', 80, 1, 44, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 02:06:24', '2026-04-13 02:06:38'),
(232, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 02:06:38', '2026-04-13 02:06:38'),
(233, 1, 'ENROLL', 81, 1, 46, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 02:07:18', '2026-04-13 02:08:45'),
(234, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 02:08:45', '2026-04-13 02:08:45'),
(235, 1, 'ENROLL', 82, 1, 47, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 02:09:27', '2026-04-13 02:09:42'),
(236, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 02:09:42', '2026-04-13 02:09:42'),
(237, 1, 'ENROLL', 42, 1, NULL, 3, 3, 'FAILED', 'storeModel code=1', '2026-04-13 02:18:29', '2026-04-13 02:18:36'),
(238, 1, 'ENROLL', 42, 1, 49, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 02:18:39', '2026-04-13 02:18:49'),
(239, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 02:18:50', '2026-04-13 02:18:50'),
(240, 1, 'ENROLL', 41, 1, 50, 3, 3, 'COMPLETED', 'total_fingers:1', '2026-04-13 02:20:07', '2026-04-13 02:20:13'),
(241, 1, 'IDLE', NULL, NULL, NULL, NULL, 3, 'PENDING', NULL, '2026-04-13 02:20:13', '2026-04-13 02:20:13'),
(242, 1, 'ENROLL', 62, 1, NULL, NULL, 3, 'FAILED', 'Mode switched to attendance', '2026-04-13 06:59:56', '2026-04-13 06:59:58'),
(243, 1, 'ENROLL', 82, 1, NULL, NULL, 3, 'FAILED', 'Mode switched to attendance', '2026-04-13 07:00:09', '2026-04-13 07:00:26'),
(244, 1, 'ENROLL', 82, 1, NULL, NULL, 3, 'FAILED', 'Mode switched to attendance', '2026-04-13 07:00:28', '2026-04-13 07:08:24');

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
(22, 41, 1, 50, 1, '2026-04-13 01:25:34'),
(23, 42, 1, 49, 1, '2026-04-13 01:26:52'),
(24, 43, 1, 4, 1, '2026-04-13 01:28:58'),
(25, 44, 1, 5, 1, '2026-04-13 01:30:49'),
(26, 45, 1, 6, 1, '2026-04-13 01:31:33'),
(27, 46, 1, 7, 1, '2026-04-13 01:32:21'),
(28, 47, 1, 9, 1, '2026-04-13 01:33:29'),
(29, 48, 1, 10, 1, '2026-04-13 01:34:14'),
(30, 49, 1, 12, 1, '2026-04-13 01:35:47'),
(31, 50, 1, 13, 1, '2026-04-13 01:36:25'),
(32, 51, 1, 14, 1, '2026-04-13 01:37:58'),
(33, 52, 1, 15, 1, '2026-04-13 01:40:08'),
(34, 53, 1, 16, 1, '2026-04-13 01:40:54'),
(35, 54, 1, 17, 1, '2026-04-13 01:41:33'),
(36, 55, 1, 18, 1, '2026-04-13 01:42:37'),
(37, 56, 1, 19, 1, '2026-04-13 01:43:10'),
(38, 57, 1, 20, 1, '2026-04-13 01:44:07'),
(39, 58, 1, 21, 1, '2026-04-13 01:44:47'),
(40, 59, 1, 22, 1, '2026-04-13 01:46:26'),
(41, 60, 1, 23, 1, '2026-04-13 01:46:57'),
(42, 61, 1, 24, 1, '2026-04-13 01:48:30'),
(43, 62, 1, 25, 1, '2026-04-13 01:49:50'),
(44, 63, 1, 26, 1, '2026-04-13 01:50:44'),
(45, 64, 1, 27, 1, '2026-04-13 01:51:28'),
(46, 65, 1, 28, 1, '2026-04-13 01:52:15'),
(47, 66, 1, 29, 1, '2026-04-13 01:53:01'),
(48, 67, 1, 30, 1, '2026-04-13 01:53:51'),
(49, 68, 1, 31, 1, '2026-04-13 01:54:40'),
(50, 69, 1, 32, 1, '2026-04-13 01:55:29'),
(51, 70, 1, 33, 1, '2026-04-13 01:56:28'),
(52, 71, 1, 34, 1, '2026-04-13 01:58:38'),
(53, 72, 1, 35, 1, '2026-04-13 01:59:26'),
(54, 73, 1, 36, 1, '2026-04-13 02:00:30'),
(55, 74, 1, 37, 1, '2026-04-13 02:01:04'),
(56, 75, 1, 38, 1, '2026-04-13 02:02:02'),
(57, 76, 1, 39, 1, '2026-04-13 02:02:45'),
(58, 77, 1, 40, 1, '2026-04-13 02:03:54'),
(59, 78, 1, 41, 1, '2026-04-13 02:04:28'),
(60, 79, 1, 42, 1, '2026-04-13 02:05:38'),
(61, 80, 1, 44, 1, '2026-04-13 02:06:38'),
(62, 81, 1, 46, 1, '2026-04-13 02:08:45'),
(63, 82, 1, 47, 1, '2026-04-13 02:09:42');

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
(3, '2025-2026', '2026-02-27', '2026-03-31', 1, '2026-04-13 00:43:21', '2026-04-13 05:48:11');

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
(8, 'Alpha', '2', '2026-04-12 08:41:21', '2026-04-12 08:43:49'),
(17, 'Beta', '2', '2026-04-13 06:19:03', '2026-04-13 06:19:03'),
(18, 'Charlie', '2', '2026-04-13 06:19:03', '2026-04-13 06:19:03'),
(19, 'Delta', '2', '2026-04-13 06:19:03', '2026-04-13 06:19:03'),
(20, 'Alpha', '3', '2026-04-13 06:19:03', '2026-04-13 06:19:03'),
(21, 'Beta', '3', '2026-04-13 06:19:03', '2026-04-13 06:19:03'),
(22, 'Charlie', '3', '2026-04-13 06:19:03', '2026-04-13 06:19:03'),
(23, 'Delta', '3', '2026-04-13 06:19:03', '2026-04-13 06:19:03'),
(24, 'Alpha', '4', '2026-04-13 06:19:03', '2026-04-13 06:19:03'),
(25, 'Beta', '4', '2026-04-13 06:19:03', '2026-04-13 06:19:03'),
(26, 'Delta', '4', '2026-04-13 06:19:03', '2026-04-13 06:19:03');

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
(41, 'Adrian', '', 'Acusar', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:25:18', '2026-04-13 05:58:17'),
(42, 'Risajane', 'A', 'Aguilar', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:26:45', '2026-04-13 05:58:17'),
(43, 'Dolly', '', 'Alindayu', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:28:48', '2026-04-13 05:58:17'),
(44, 'Edward', '', 'Ballesteros', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:30:41', '2026-04-13 05:58:17'),
(45, 'James Christian', '', 'Batara', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:31:25', '2026-04-13 05:58:17'),
(46, 'Jay-em', '', 'Blando', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:32:08', '2026-04-13 05:58:17'),
(47, 'Teny Boy', '', 'Cabbab', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:32:51', '2026-04-13 05:58:17'),
(48, 'James Bhand', '', 'Calimag', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:34:06', '2026-04-13 05:58:17'),
(49, 'Rheyven', '', 'Calinggangan', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:35:05', '2026-04-13 05:58:17'),
(50, 'Renmar', '', 'Dasalla', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:36:17', '2026-04-13 05:58:17'),
(51, 'Jonathan', '', 'Engarcial', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:37:37', '2026-04-13 05:58:17'),
(52, 'Kaneshane', '', 'Enoy', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:39:26', '2026-04-13 05:58:17'),
(53, 'Cherry', '', 'Esquejo', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:40:41', '2026-04-13 05:58:17'),
(54, 'John Vincent', '', 'Farro', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:41:24', '2026-04-13 05:58:17'),
(55, 'Mark Anthony', '', 'Flotildez', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:42:25', '2026-04-13 05:58:17'),
(56, 'Jaira Mae', '', 'Garcia', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:43:03', '2026-04-13 05:58:17'),
(57, 'John Russel', '', 'Llabore', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:43:59', '2026-04-13 05:58:17'),
(58, 'Jay-ar', '', 'Marinay', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:44:39', '2026-04-13 05:58:17'),
(59, 'Kenneth Bert', '', 'Mauricio', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:46:20', '2026-04-13 05:58:17'),
(60, 'Rogieto', '', 'Quilang', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:46:49', '2026-04-13 05:58:17'),
(61, 'Julius', '', 'Miguel', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:47:56', '2026-04-13 05:58:17'),
(62, 'Maria Angelica', '', 'Villanueva', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:49:07', '2026-04-13 05:58:17'),
(63, 'Sofia Alexis', '', 'Mina', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:50:34', '2026-04-13 05:58:17'),
(64, 'Joanalene', '', 'Molina', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:51:19', '2026-04-13 05:58:17'),
(65, 'Erick John', '', 'Morales', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:52:07', '2026-04-13 05:58:17'),
(66, 'Michelle', '', 'Moriente', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:52:52', '2026-04-13 05:58:17'),
(67, 'John Loyd', '', 'Pascual', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:53:41', '2026-04-13 05:58:17'),
(68, 'Jennilyn', '', 'Picardal', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:54:23', '2026-04-13 05:58:17'),
(69, 'Jervic', '', 'Ramirez', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:55:20', '2026-04-13 05:58:17'),
(70, 'Liza', '', 'Respicio', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:56:11', '2026-04-13 05:58:17'),
(71, 'Veronica', '', 'Rosete', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:58:26', '2026-04-13 05:58:17'),
(72, 'Mark', '', 'Sagadraca', '', 4, 5, 'Charlie', 'active', '2026-04-13 01:59:09', '2026-04-13 05:58:17'),
(73, 'Jaraigne Claire', '', 'Sagucio', '', 4, 5, 'Charlie', 'active', '2026-04-13 02:00:21', '2026-04-13 05:58:17'),
(74, 'Mary', '', 'Salvador', '', 4, 5, 'Charlie', 'active', '2026-04-13 02:00:53', '2026-04-13 05:58:17'),
(75, 'Rochelle', '', 'Salvador', '', 4, 5, 'Charlie', 'active', '2026-04-13 02:01:30', '2026-04-13 05:58:17'),
(76, 'Aaron Javes', '', 'Tamani', '', 4, 5, 'Charlie', 'active', '2026-04-13 02:02:33', '2026-04-13 05:58:17'),
(77, 'Reonil John', '', 'Tanap', '', 4, 5, 'Charlie', 'active', '2026-04-13 02:03:28', '2026-04-13 05:58:17'),
(78, 'Angelica Mae', '', 'Telan', '', 4, 5, 'Charlie', 'active', '2026-04-13 02:04:20', '2026-04-13 05:58:17'),
(79, 'Harley Davidson', '', 'Tomas', '', 4, 5, 'Charlie', 'active', '2026-04-13 02:04:56', '2026-04-13 05:58:17'),
(80, 'Mary-an', '', 'Tomas', '', 4, 5, 'Charlie', 'active', '2026-04-13 02:06:11', '2026-04-13 05:58:17'),
(81, 'Ara May', '', 'Torio', '', 4, 5, 'Charlie', 'active', '2026-04-13 02:07:17', '2026-04-13 05:58:17'),
(82, 'Hans Joshua', '', 'Vigan', '', 4, 5, 'Charlie', 'active', '2026-04-13 02:09:26', '2026-04-13 05:58:17');

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
(13, 7, 5, 1, '11:00:00', '12:00:00', 15, '2026-04-13 02:11:07', '2026-04-13 05:41:20'),
(14, 7, 5, 2, '13:00:00', '15:00:00', 15, '2026-04-13 02:11:43', '2026-04-13 02:11:43');

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
(10, 7, 5, '2026-04-13 06:19:22');

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
(1, 'admin', '$2y$10$woShL7UfMUq6DwHOktsatuna70k4t3aYQ7kGQGXJEoZ.q/G9/ZkJe', 'Administrator', 'admin@cams.edu.ph', 'admin', 'active', '2026-04-08 21:41:37', '2026-04-08 21:41:37', NULL, NULL, NULL),
(2, 'teacher', '$2y$10$q4pNp8NchbZyI5pvtYuMQ.s9g4udDqgQqFmO73Tlzws1wowUN14ZS', 'teacher 1', 'teacher@cams.edu.ph', 'teacher', 'active', '2026-04-08 21:41:37', '2026-04-09 20:47:59', 'Alpha', '2025-2026', 1),
(7, 'SirDonald', '$2y$10$fwjDbP9U6dl1OzRiQTxD4e6Xp5XlJxfPmIILJuQgxoD64NIUZeifK', 'Donald T. Sumad-On', 'donaldsumadon@gmail.com', 'teacher', 'active', '2026-04-13 01:23:59', '2026-04-13 06:19:22', 'Charlie', '2025-2026', 4);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=421;

--
-- AUTO_INCREMENT for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `device_commands`
--
ALTER TABLE `device_commands`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=245;

--
-- AUTO_INCREMENT for table `fingerprints`
--
ALTER TABLE `fingerprints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=902;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `teacher_daily_schedules`
--
ALTER TABLE `teacher_daily_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `teacher_sections`
--
ALTER TABLE `teacher_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
