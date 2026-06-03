-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:33078
-- Generation Time: Jun 03, 2026 at 05:20 PM
-- Server version: 11.8.2-MariaDB-ubu2404
-- PHP Version: 8.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `jeepnigo`
--

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `boarding_events`
--

CREATE TABLE `boarding_events` (
  `id` int(11) NOT NULL,
  `passenger_id` int(11) NOT NULL,
  `route` varchar(100) NOT NULL,
  `boarded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `boarding_events`
--

INSERT INTO `boarding_events` (`id`, `passenger_id`, `route`, `boarded_at`) VALUES
(1, 36, 'Route 1', '2025-07-03 14:07:14'),
(2, 36, 'Route 2', '2025-07-03 14:07:17'),
(3, 36, 'Route 1', '2025-07-08 15:03:47'),
(4, 36, 'Route 2', '2025-07-08 15:06:56'),
(5, 36, 'Route 1', '2025-07-09 06:04:03'),
(6, 36, 'Route 2', '2025-07-09 06:21:08'),
(7, 36, 'Route 1', '2025-07-12 08:36:00'),
(8, 36, 'toril', '2025-07-12 08:52:40'),
(9, 36, 'Route 2', '2025-07-12 09:33:03'),
(10, 36, 'toril', '2025-07-17 01:33:57'),
(11, 36, 'toril', '2025-07-21 13:06:30'),
(12, 36, 'Route 2', '2025-07-21 23:33:54'),
(13, 36, 'toril', '2025-07-21 23:39:11'),
(14, 36, 'toril', '2025-07-22 03:14:44'),
(15, 36, 'toril', '2025-09-23 14:48:20'),
(16, 36, 'Route 1', '2025-09-24 09:33:51');

-- --------------------------------------------------------

--
-- Table structure for table `boundary_payments`
--

CREATE TABLE `boundary_payments` (
  `id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `operator_id` int(11) NOT NULL,
  `jeepney_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `paid_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'Pending',
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `boundary_payments`
--

INSERT INTO `boundary_payments` (`id`, `driver_id`, `operator_id`, `jeepney_id`, `amount`, `payment_method`, `paid_at`, `status`, `reference_number`, `notes`) VALUES
(1, 28, 34, 9, 500.00, 'GCash', '2025-07-03 14:20:54', 'Pending', NULL, NULL),
(2, 28, 34, 9, 500.00, 'Cash', '2025-07-03 14:25:56', 'Pending', NULL, NULL),
(3, 28, 34, 9, 500.00, 'Cash', '2025-07-12 10:44:43', 'Pending', NULL, NULL),
(4, 28, 34, 9, 500.00, 'Cash', '2025-07-12 10:47:13', 'Pending', NULL, NULL),
(5, 28, 34, 9, 500.00, 'GCash', '2025-07-12 10:51:01', 'Pending', NULL, NULL),
(6, 28, 34, 9, 500.00, 'Cash', '2025-07-12 11:10:15', 'Pending', NULL, NULL),
(7, 28, 34, 9, 500.00, 'Cash', '2025-07-12 11:13:58', 'Pending', NULL, NULL),
(8, 28, 34, 9, 500.00, 'Cash', '2025-07-12 11:17:59', 'Pending', NULL, NULL),
(9, 28, 34, 9, 500.00, 'Cash', '2025-07-12 11:34:54', 'Pending', NULL, NULL),
(10, 28, 34, 9, 501.00, 'Cash', '2025-07-12 11:37:07', 'Pending', NULL, NULL),
(11, 28, 34, 9, 500.00, 'Cash', '2025-07-12 11:43:02', 'Pending', NULL, NULL),
(12, 28, 34, 9, 500.00, 'Cash', '2025-07-13 13:20:21', 'Pending', NULL, NULL),
(13, 28, 34, 9, 500.00, 'Cash', '2025-07-13 13:31:50', 'Pending', 'BND-20250713-0028', ''),
(14, 28, 34, 9, 500.00, 'Cash', '2025-07-13 13:34:42', 'Pending', 'BND-20250713-0028', ''),
(15, 28, 34, 9, 500.00, 'Cash', '2025-07-13 13:40:05', 'Pending', 'BND-20250713-0028', ''),
(16, 28, 34, 9, 500.00, 'GCash', '2025-07-13 13:40:36', 'Pending', 'BND-20250713-0028', ''),
(17, 28, 34, 9, 500.00, 'GCash', '2025-07-13 13:57:31', 'Collected', 'BND-20250713-0028', ''),
(18, 28, 34, 9, 500.00, 'Cash', '2025-07-13 13:59:35', 'Collected', 'BND-20250713-0028', ''),
(19, 28, 34, 9, 500.00, 'GCash', '2025-07-13 14:02:43', 'Collected', 'BND-20250713-0028', ''),
(20, 28, 34, 9, 500.00, 'Cash', '2025-07-13 14:04:59', 'Collected', 'BND-20250713-0028', ''),
(21, 28, 34, 9, 500.00, 'GCash', '2025-07-13 14:10:06', 'Collected', 'BND-20250713-0028', ''),
(22, 28, 34, 9, 500.00, 'Cash', '2025-07-13 14:16:55', 'Collected', 'BND-20250713-0028', ''),
(23, 28, 34, 9, 500.00, 'Cash', '2025-07-13 14:20:36', 'Collected', 'BND-20250713-0028', ''),
(24, 28, 17, 9, 500.00, 'Cash', '2025-07-17 01:35:19', 'Pending', 'BND-20250717-0028', ''),
(25, 28, 17, 9, 510.00, 'Cash', '2025-07-21 13:20:20', 'Pending', 'BND-20250721-0028', ''),
(26, 28, 17, 9, 500.00, 'Bank', '2025-07-21 13:24:27', 'Pending', 'BND-20250721-0028', ''),
(27, 28, 17, 9, 520.00, 'Bank', '2025-07-21 13:27:59', 'Pending', 'BND-20250721-0028', ''),
(28, 28, 17, 9, 523.00, 'Bank', '2025-07-21 13:29:53', 'Pending', 'BND-20250721-0028-152953', ''),
(29, 28, 17, 9, 500.00, 'Bank', '2025-07-21 13:36:08', 'Pending', 'BND-20250721-0028-153608', ''),
(30, 28, 17, 9, 500.00, 'Bank', '2025-07-21 13:41:46', 'Pending', 'BND-20250721-0028-154146', ''),
(31, 28, 17, 9, 500.00, 'Bank', '2025-07-21 13:47:17', 'Pending', 'BND-20250721-0028-154717', ''),
(32, 28, 17, 9, 500.00, 'Bank', '2025-07-21 14:17:32', 'Pending', 'BND-20250721-0028-161732', ''),
(33, 28, 17, 9, 500.00, 'Bank', '2025-07-21 14:33:13', 'Pending', 'BND-20250721-0028-163313', ''),
(34, 28, 17, 9, 500.00, 'GCash', '2025-07-21 14:38:10', 'Pending', 'BND-20250721-0028-163810', ''),
(35, 28, 17, 9, 500.00, 'GCash', '2025-07-21 14:45:47', 'Pending', 'BND-20250721-0028-164547', ''),
(36, 28, 17, 9, 500.00, 'GCash', '2025-07-21 14:48:19', 'Pending', 'BND-20250721-0028-164819', ''),
(37, 28, 17, 9, 500.00, 'Bank', '2025-07-21 15:02:02', 'Pending', 'BND-20250721-0028-170202', ''),
(38, 28, 17, 9, 500.00, 'Bank', '2025-07-21 15:09:41', 'Pending', 'BND-20250721-0028-170941', ''),
(39, 28, 17, 9, 500.00, 'Bank', '2025-07-21 15:19:20', 'Pending', 'BND-20250721-0028-171920', ''),
(40, 28, 17, 9, 500.00, 'Bank', '2025-07-21 15:23:23', 'Pending', 'BND-20250721-0028-172323', ''),
(41, 28, 17, 9, 500.00, 'Bank', '2025-07-21 15:27:28', 'Pending', 'BND-20250721-0028-172728', ''),
(42, 28, 17, 9, 500.00, 'GCash', '2025-07-21 15:29:43', 'Pending', 'BND-20250721-0028-172943', ''),
(43, 28, 17, 9, 500.00, 'Bank', '2025-07-21 15:32:37', 'Pending', 'BND-20250721-0028-173237', ''),
(44, 28, 17, 9, 500.00, 'Bank', '2025-07-21 15:35:32', 'Pending', 'BND-20250721-0028-173532', ''),
(45, 28, 17, 9, 500.00, 'Bank', '2025-07-21 15:40:02', 'Pending', 'BND-20250721-0028-174002', ''),
(46, 28, 17, 9, 500.00, 'Bank', '2025-07-21 15:47:08', 'Pending', 'BND-20250721-0028-174708', ''),
(47, 28, 17, 9, 500.00, 'Bank', '2025-07-21 15:49:24', 'Pending', 'BND-20250721-0028-174924', ''),
(48, 28, 17, 9, 500.00, 'Bank', '2025-07-21 23:07:31', 'Pending', 'BND-20250722-0028-010731', ''),
(49, 28, 17, 9, 500.00, 'Bank', '2025-07-21 23:10:01', 'Pending', 'BND-20250722-0028-011001', ''),
(50, 28, 17, 9, 500.00, 'Bank', '2025-07-21 23:14:14', 'Pending', 'BND-20250722-0028-011414', ''),
(51, 28, 17, 9, 500.00, 'PayMaya', '2025-07-21 23:26:59', 'Pending', 'BND-20250722-0028-012659', ''),
(52, 28, 17, 9, 500.00, 'Bank', '2025-07-21 23:32:07', 'Pending', 'BND-20250722-0028-013207', ''),
(53, 28, 17, 9, 500.00, 'Bank', '2025-07-21 23:49:50', 'Pending', 'BND-20250722-0028-014950', ''),
(54, 28, 17, 9, 500.00, 'Bank', '2025-07-21 23:53:57', 'Pending', 'BND-20250722-0028-015357', ''),
(55, 28, 17, 9, 500.00, 'PayMaya', '2025-07-21 23:59:12', 'Pending', 'BND-20250722-0028-015912', ''),
(56, 28, 17, 9, 500.00, 'Bank', '2025-07-22 00:05:25', 'Pending', 'BND-20250722-0028-020525', ''),
(57, 28, 17, 9, 500.00, 'PayMaya', '2025-07-22 00:14:32', 'Pending', 'BND-20250722-0028-021432', ''),
(58, 28, 17, 9, 500.00, 'PayMaya', '2025-07-22 00:21:12', 'Pending', 'BND-20250722-0028-022112', ''),
(59, 28, 17, 9, 500.00, 'PayMaya', '2025-07-22 00:29:18', 'Pending', 'BND-20250722-0028-022918', ''),
(60, 28, 17, 9, 500.00, 'PayMaya', '2025-07-22 00:34:21', 'Pending', 'BND-20250722-0028-023421', ''),
(61, 28, 17, 9, 500.00, 'PayMaya', '2025-07-22 00:40:40', 'Pending', 'BND-20250722-0028-024040', ''),
(62, 28, 17, 9, 500.00, 'PayMaya', '2025-07-22 00:43:13', 'Pending', 'BND-20250722-0028-024313', ''),
(63, 28, 17, 9, 500.00, 'PayMaya', '2025-07-22 00:45:22', 'Pending', 'BND-20250722-0028-024522', ''),
(64, 28, 17, 9, 500.00, 'PayMaya', '2025-07-22 00:57:35', 'Pending', 'BND-20250722-0028-025735', ''),
(65, 28, 17, 9, 500.00, 'PayMaya', '2025-07-22 01:02:27', 'Pending', 'BND-20250722-0028-030227', ''),
(66, 28, 17, 9, 500.00, 'PayMaya', '2025-07-22 01:08:39', 'Pending', 'BND-20250722-0028-030839', ''),
(67, 28, 17, 9, 500.00, 'PayMaya', '2025-07-22 01:12:30', 'Pending', 'BND-20250722-0028-031230', ''),
(68, 28, 17, 9, 500.00, 'Cash', '2025-07-22 03:16:16', 'Pending', 'BND-20250722-0028-051616', ''),
(69, 28, 17, 9, 500.00, 'GCash', '2025-10-19 05:04:29', 'Pending', 'BND-20251019-0028-010428', '');

-- --------------------------------------------------------

--
-- Table structure for table `cooperative_applications`
--

CREATE TABLE `cooperative_applications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `cooperative_name` varchar(255) NOT NULL,
  `registration_number` varchar(255) NOT NULL,
  `certificate` varchar(255) DEFAULT NULL,
  `contact_info` varchar(255) NOT NULL,
  `additional_details` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `details` text DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cooperative_fund_payments`
--

CREATE TABLE `cooperative_fund_payments` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `paid_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cooperative_fund_payments`
--

INSERT INTO `cooperative_fund_payments` (`id`, `member_id`, `amount`, `payment_method`, `paid_at`, `status`) VALUES
(1, 34, 500.00, 'cash', '2025-07-22 03:03:18', 'Confirmed'),
(2, 34, 500.00, 'cash', '2025-07-22 03:17:38', 'Confirmed');

-- --------------------------------------------------------

--
-- Table structure for table `fare_payments`
--

CREATE TABLE `fare_payments` (
  `id` int(11) NOT NULL,
  `passenger_id` int(11) NOT NULL,
  `route` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `receipt_number` varchar(50) NOT NULL,
  `paid_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'Paid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fare_payments`
--

INSERT INTO `fare_payments` (`id`, `passenger_id`, `route`, `amount`, `payment_method`, `receipt_number`, `paid_at`, `status`) VALUES
(18, 36, 'Toril Market → San Pedro', 12.00, 'GCash', 'FARE-64030', '2025-10-16 15:05:12', 'Collected'),
(21, 46, 'Toril Market â†’ Matina Crossing', 12.00, 'GCash', 'FARE-26793', '2025-10-31 15:38:05', 'Collected'),
(23, 50, 'Toril Market â†’ San Pedro', 12.00, 'Cash', 'FARE-92879', '2025-11-08 16:40:57', 'Collected'),
(24, 50, 'Toril Market â†’ San Pedro', 12.00, 'Cash', 'FARE-71970', '2025-11-05 16:41:00', 'Collected'),
(25, 52, 'Toril Market â†’ San Pedro', 9.50, 'Cash', 'FARE-38511', '2025-11-04 15:58:49', 'Collected'),
(26, 52, 'Toril Market â†’ Toril Market', 9.50, 'Cash', 'FARE-24094', '2025-11-04 16:58:04', 'Collected'),
(27, 50, 'Toril Market â†’ San Pedro', 12.00, 'Cash', 'FARE-86559', '2025-11-05 15:59:18', 'Collected'),
(28, 50, 'Toril Market â†’ San Pedro', 12.00, 'Cash', 'FARE-25620', '2025-10-29 14:59:10', 'Collected'),
(29, 49, 'Toril Market â†’ Toril Market', 9.50, 'Cash', 'FARE-21859', '2025-11-04 16:24:05', 'Collected'),
(30, 54, 'Toril Market â†’ Toril Market', 12.00, 'Cash', 'FARE-12356', '2025-11-06 15:42:22', 'Collected'),
(31, 45, 'Toril Market â†’ San Pedro', 12.00, 'GCash', 'FARE-57974', '2025-11-06 15:48:22', 'Collected'),
(32, 55, 'Toril Market â†’ Toril Market', 12.00, 'Cash', 'FARE-48809', '2025-11-06 15:48:25', 'Collected'),
(33, 56, 'Toril Market â†’ San Pedro', 9.50, 'Cash', 'FARE-27617', '2025-11-06 15:53:37', 'Collected'),
(34, 55, 'Toril Market â†’ San Pedro', 12.00, 'GCash', 'FARE-90060', '2025-11-06 15:05:33', 'Collected'),
(35, 51, 'Bangkal Crossing â†’ Toril Market', 9.50, 'Cash', 'FARE-42305', '2025-11-01 15:10:38', 'Collected'),
(36, 47, 'Toril Market â†’ Matina Crossing', 12.00, 'Cash', 'FARE-83151', '2025-11-08 17:27:21', 'Collected'),
(37, 40, 'Toril Market â†’ San Pedro', 12.00, 'GCash', 'FARE-83279', '2025-11-09 02:29:35', 'Collected');

-- --------------------------------------------------------

--
-- Table structure for table `jeepneys`
--

CREATE TABLE `jeepneys` (
  `id` int(11) NOT NULL,
  `plate_number` varchar(20) NOT NULL,
  `body_number` varchar(20) NOT NULL,
  `route` varchar(100) NOT NULL,
  `status` enum('Available','Assigned','Maintenance') DEFAULT 'Available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jeepney_assignments`
--

CREATE TABLE `jeepney_assignments` (
  `id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `operator_id` int(11) NOT NULL DEFAULT 0,
  `plate_number` varchar(20) NOT NULL,
  `body_number` varchar(20) NOT NULL,
  `route` varchar(100) NOT NULL,
  `notes` text DEFAULT NULL,
  `assigned_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Active','Inactive') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jeepney_assignments`
--

INSERT INTO `jeepney_assignments` (`id`, `driver_id`, `operator_id`, `plate_number`, `body_number`, `route`, `notes`, `assigned_date`, `status`) VALUES
(1, 28, 17, 'SASASASASA', '565656565', 'Route 1', 'gtgtgtgt', '2025-05-17 07:31:23', 'Inactive'),
(2, 28, 17, 'SEK 195', 'hkjdhfkdsj', 'Route 1', 'sssss', '2025-05-17 07:50:17', 'Inactive'),
(3, 28, 17, 'MAR 123`', 'hhhh', 'Route 3', 'vcvcvcvc', '2025-05-17 07:58:12', 'Inactive'),
(4, 28, 17, 'Y555', '24525', 'Route 1', '', '2025-05-20 13:35:18', 'Inactive'),
(5, 28, 17, 'Y555', '24525', 'Route 1', 'vvvvvvvvv', '2025-05-21 15:24:40', 'Inactive'),
(8, 28, 17, 'SEK 144', '3333', 'toril', '', '2025-05-24 07:52:07', 'Inactive'),
(9, 28, 17, 'SEK 200', '444', 'Toril Market - San Pedro', '', '2025-05-24 08:49:00', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `jeepney_locations`
--

CREATE TABLE `jeepney_locations` (
  `id` int(11) NOT NULL,
  `jeepney_id` int(11) NOT NULL,
  `route` varchar(100) DEFAULT NULL,
  `lat` double NOT NULL,
  `lng` double NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `membership_payments`
--

CREATE TABLE `membership_payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` enum('cash','gcash') NOT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `receipt_number` varchar(20) DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `payment_date` datetime NOT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `firstName` varchar(100) DEFAULT NULL,
  `lastName` varchar(100) DEFAULT NULL,
  `userType` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `membership_payments`
--

INSERT INTO `membership_payments` (`id`, `user_id`, `amount`, `method`, `reference_number`, `receipt_number`, `confirmed_at`, `payment_date`, `status`, `firstName`, `lastName`, `userType`) VALUES
(33, 28, 1000.00, 'cash', NULL, 'TEBZ-20250524-0028', '2025-05-24 08:47:18', '2025-05-24 16:46:20', 'Confirmed', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `membership_requirements`
--

CREATE TABLE `membership_requirements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `membership_requirements` text NOT NULL,
  `general_requirements` text NOT NULL,
  `contact_info` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','archived') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `membership_requirements`
--

INSERT INTO `membership_requirements` (`id`, `title`, `membership_requirements`, `general_requirements`, `contact_info`, `created_at`, `status`) VALUES
(62, 'FVFV', 'VFVF', 'VFVFVF', 'VFVF', '2025-02-14 18:03:32', 'active'),
(63, 'MEMBERSHIP REQUIREMENTS', 'Valid Professional Driver&#039;s License\r\nCooperative Education and Transport Operations Seminar (CETOS) Certification\r\nProvisional Authorization\r\nPUV ID\r\n', 'Membership Fee (PHP 1,000)\r\nAttendance from Cooperative Orientation', 'Phone: 09621561188 Email: attc@gmail.com', '2025-02-15 09:03:16', 'active'),
(64, 'driver', 'certificate', 'Biodata', '093103910391', '2025-03-23 07:06:03', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `orientation_attendees`
--

CREATE TABLE `orientation_attendees` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` varchar(50) DEFAULT NULL,
  `orientation_id` int(11) NOT NULL,
  `attendance_mode` varchar(20) DEFAULT NULL,
  `attended_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `attended_mode` enum('online','in-person') DEFAULT NULL,
  `is_completed` tinyint(1) DEFAULT 0,
  `mode` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orientation_attendees`
--

INSERT INTO `orientation_attendees` (`id`, `user_id`, `user_type`, `orientation_id`, `attendance_mode`, `attended_at`, `attended_mode`, `is_completed`, `mode`) VALUES
(33, 28, NULL, 64, NULL, '2025-06-21 13:26:23', 'in-person', 1, NULL),
(34, 17, NULL, 64, NULL, '2025-07-03 13:35:21', 'online', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `orientation_requests`
--

CREATE TABLE `orientation_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('Driver','Operator') NOT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Pending','Scheduled') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orientation_requests`
--

INSERT INTO `orientation_requests` (`id`, `user_id`, `role`, `requested_at`, `status`) VALUES
(1, 19, 'Driver', '2025-03-22 08:58:36', ''),
(2, 20, 'Driver', '2025-03-23 07:09:36', ''),
(3, 18, 'Driver', '2025-03-23 07:14:18', ''),
(4, 24, 'Driver', '2025-03-28 13:40:41', ''),
(5, 18, 'Driver', '2025-03-28 14:22:27', ''),
(6, 18, 'Driver', '2025-03-28 14:27:47', ''),
(7, 28, 'Driver', '2025-03-29 00:30:14', ''),
(8, 32, 'Driver', '2025-03-29 09:37:23', ''),
(9, 33, 'Driver', '2025-04-04 13:26:23', ''),
(10, 19, 'Driver', '2025-04-11 16:11:08', ''),
(11, 32, 'Driver', '2025-04-17 08:27:40', ''),
(12, 34, 'Driver', '2025-04-17 09:45:48', ''),
(13, 34, 'Driver', '2025-04-17 10:23:05', ''),
(14, 28, 'Driver', '2025-04-26 16:30:09', ''),
(15, 28, 'Driver', '2025-05-01 06:01:57', ''),
(16, 28, 'Driver', '2025-05-09 07:07:06', ''),
(18, 28, 'Driver', '2025-06-21 13:17:56', 'Pending'),
(19, 39, 'Driver', '2025-07-03 13:21:50', 'Pending'),
(20, 34, 'Driver', '2025-07-03 13:22:34', 'Pending'),
(21, 17, 'Driver', '2025-07-03 13:25:58', 'Pending'),
(22, 40, 'Driver', '2025-08-06 02:23:02', 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `orientation_schedule`
--

CREATE TABLE `orientation_schedule` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `target_role` varchar(50) NOT NULL,
  `mode` enum('online','in-person') NOT NULL,
  `orientation_date` date NOT NULL,
  `orientation_time` time NOT NULL,
  `venue` varchar(255) NOT NULL,
  `link` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orientation_schedule`
--

INSERT INTO `orientation_schedule` (`id`, `title`, `target_role`, `mode`, `orientation_date`, `orientation_time`, `venue`, `link`, `location`, `notes`, `created_at`) VALUES
(64, 'meeting', 'driver,operator', 'online', '2025-07-04', '21:25:00', 'toril', 'https://workspace.google.com/products/meet/', NULL, NULL, '2025-06-21 13:25:08');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `passenger_id` int(11) NOT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `route` varchar(100) DEFAULT NULL,
  `origin_landmark` varchar(255) DEFAULT NULL,
  `dest_landmark` varchar(255) DEFAULT NULL,
  `distance_km` int(11) DEFAULT NULL,
  `fare_regular` decimal(10,2) DEFAULT NULL,
  `fare_discounted` decimal(10,2) DEFAULT NULL,
  `payment_method` varchar(30) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `status` enum('requested','here','eta_sent','boarded','paid','cancelled') NOT NULL DEFAULT 'requested',
  `eta_time` datetime DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `here_at` datetime DEFAULT NULL,
  `boarded_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `passenger_id`, `driver_id`, `route`, `origin_landmark`, `dest_landmark`, `distance_km`, `fare_regular`, `fare_discounted`, `payment_method`, `paid_at`, `status`, `eta_time`, `requested_at`, `here_at`, `boarded_at`) VALUES
(2, 36, 28, '', 'Toril Market', 'San Pedro', 4, 12.00, 9.50, NULL, NULL, 'boarded', '2025-09-25 14:12:44', '2025-10-16 06:03:19', '2025-10-15 14:03:24', '2025-10-15 14:04:26'),
(6, 36, 28, '', 'Toril Market', 'Matina Crossing', 3, 12.00, 9.50, NULL, NULL, 'boarded', '2025-09-25 16:42:41', '2025-10-16 08:31:48', '2025-10-16 07:32:02', '2025-10-16 07:33:11'),
(10, 46, 28, '', 'Toril Market', 'Matina Crossing', 3, 12.00, 9.50, NULL, NULL, 'boarded', '2025-11-08 06:45:35', '2025-11-01 14:33:00', '2025-11-01 07:34:57', '2025-11-01 07:38:00'),
(13, 49, 28, '', 'Toril Market', 'San Pedro', 1, 12.00, 9.50, NULL, NULL, 'boarded', NULL, '2025-11-01 14:31:38', '2025-11-01 07:31:45', '2025-11-01 07:37:00'),
(14, 50, 28, '', 'Toril Market', 'San Pedro', 4, 12.00, 9.50, NULL, NULL, 'boarded', NULL, '2025-11-01 14:46:54', '2025-11-01 07:47:58', '2025-11-01 07:53:00'),
(15, 51, 28, '', 'Toril Market', 'San Pedro', 1, 12.00, 9.50, NULL, NULL, 'boarded', NULL, '2025-11-02 15:01:00', '2025-11-02 07:02:14', '2025-11-02 07:10:00'),
(17, 52, 28, '', 'Toril Market', 'Toril Market', 1, 12.00, 9.50, NULL, NULL, 'boarded', NULL, '2025-11-05 23:56:00', '2025-11-05 07:56:11', '2025-11-05 07:58:36'),
(18, 50, 28, '', 'Toril Market', 'Ecoland Terminal', 2, 12.00, 9.50, NULL, NULL, 'boarded', NULL, '2025-11-05 23:57:56', '2025-11-05 07:57:59', '2025-11-05 07:59:46'),
(20, 49, 28, '', 'Toril Market', 'Ecoland Terminal', 2, 12.00, 9.50, NULL, NULL, 'boarded', NULL, '2025-11-06 00:19:31', '2025-11-05 08:19:35', '2025-11-05 08:24:12'),
(21, 53, 28, '', 'Toril Market', 'San Pedro', 1, 12.00, 9.50, NULL, NULL, 'boarded', NULL, '2025-11-06 00:23:58', '2025-11-05 18:24:00', '2025-11-05 18:26:03'),
(23, 54, 28, '', 'Toril Market', 'Toril Market', 1, 12.00, 9.50, NULL, NULL, 'boarded', NULL, '2025-11-08 00:38:27', '2025-11-07 08:38:41', '2025-11-07 08:41:25'),
(24, 45, 28, '', 'Toril Market', 'San Pedro', 4, 12.00, 9.50, NULL, NULL, 'boarded', '2025-11-07 08:49:53', '2025-11-08 00:42:33', '2025-11-07 07:43:09', '2025-11-07 07:47:43'),
(28, 56, 28, '', 'Toril Market', 'San Pedro', 4, 12.00, 9.50, NULL, NULL, 'boarded', NULL, '2025-11-08 00:47:20', '2025-11-07 08:47:25', '2025-11-07 08:53:24'),
(29, 55, 28, '', 'Toril Market', 'Ecoland Terminal', 2, 12.00, 9.50, NULL, NULL, 'boarded', '2025-11-07 09:03:03', '2025-11-08 00:58:44', '2025-11-07 08:58:46', '2025-11-07 09:05:21'),
(30, 51, 28, '', 'Toril Market', 'San Pedro', 1, 12.00, 9.50, NULL, NULL, 'boarded', '2025-11-07 09:22:04', '2025-11-07 17:25:00', '2025-11-07 09:26:00', '2025-11-07 09:33:00'),
(31, 58, 28, '', 'Toril Market', 'San Pedro', 1, 12.00, 9.50, NULL, NULL, 'boarded', NULL, '2025-11-07 17:24:55', '2025-11-07 09:25:55', '2025-11-07 09:29:55'),
(32, 59, 28, '', 'Toril Market', 'Toril Market', 1, 12.00, 9.50, NULL, NULL, 'boarded', NULL, '2025-11-08 01:29:06', '2025-11-07 09:29:45', '2025-11-07 09:33:39'),
(33, 59, 28, '', 'Toril Market', 'San Pedro', 1, 12.00, 9.50, NULL, NULL, 'boarded', NULL, '2025-11-08 01:31:20', '2025-11-07 07:31:30', '2025-11-07 07:38:08'),
(34, 60, 28, '', 'Toril Market', 'San Pedro', 1, 12.00, 9.50, NULL, NULL, 'boarded', NULL, '2025-11-10 01:34:47', '2025-11-09 07:35:31', '2025-11-07 07:38:17'),
(35, 47, 28, '', 'Toril Market', 'Matina Crossing', 3, 12.00, 9.50, NULL, NULL, 'boarded', '2025-11-09 10:27:47', '2025-11-09 02:18:51', '2025-11-09 07:20:11', '2025-11-09 07:26:22'),
(36, 40, 28, '', 'Toril Market', 'Bangkal Crossing', 1, 12.00, 9.50, NULL, NULL, 'boarded', NULL, '2025-11-09 05:27:17', '2025-11-09 13:27:25', '2025-11-09 13:29:08'),
(37, 62, 28, '', 'Toril Market', 'San Pedro', 4, 12.00, 9.50, NULL, NULL, 'boarded', NULL, '2025-11-09 07:37:38', '2025-11-09 15:37:44', '2025-11-09 15:54:10'),
(38, 63, 28, '', 'Toril Market', 'Matina Crossing', 3, 12.00, 9.50, NULL, NULL, 'boarded', NULL, '2025-11-09 07:41:21', '2025-11-09 15:30:23', '2025-11-09 15:56:08'),
(39, 65, 28, '', 'Toril Market', 'Ecoland Terminal', 2, 12.00, 9.50, NULL, NULL, 'boarded', NULL, '2025-11-09 11:31:15', NULL, '2025-11-09 20:27:08'),
(40, 66, 28, '', 'Bangkal Crossing', 'Toril Market', 1, 12.00, 9.50, NULL, NULL, 'boarded', NULL, '2025-11-11 00:17:21', '2025-11-11 08:17:29', '2025-11-12 23:12:43'),
(41, 66, 28, '', 'Bangkal Crossing', 'Toril Market', 1, 12.00, 9.50, NULL, NULL, 'boarded', NULL, '2025-11-11 00:17:39', '2025-11-11 08:17:42', '2025-11-12 23:12:47'),
(42, 36, 28, '', 'Toril Market', 'Matina Crossing', 3, 12.00, 9.50, NULL, NULL, 'boarded', NULL, '2025-12-08 12:43:16', '2025-12-08 20:44:54', '2025-12-08 20:46:23'),
(43, 67, 28, '', 'Toril Market', 'Toril Market', 1, 12.00, 9.50, NULL, NULL, 'boarded', NULL, '2026-01-29 11:01:33', '2026-01-29 19:01:35', '2026-02-20 14:57:13'),
(44, 70, NULL, '', 'Toril Market', 'Toril Market', 1, 12.00, 9.50, NULL, NULL, 'here', NULL, '2026-03-11 11:30:08', '2026-03-11 19:30:13', NULL),
(45, 74, NULL, '', 'Toril Market', 'Toril Market', 1, 12.00, 9.50, NULL, NULL, 'here', NULL, '2026-04-06 06:16:51', '2026-04-06 14:16:54', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `submitted_requirements`
--

CREATE TABLE `submitted_requirements` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` varchar(50) NOT NULL,
  `driver_license` varchar(255) DEFAULT NULL,
  `cetos_certification` varchar(255) DEFAULT NULL,
  `provisional_authorization` varchar(255) DEFAULT NULL,
  `puv_id` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Verified','Rejected') DEFAULT 'Pending',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `submitted_requirements`
--

INSERT INTO `submitted_requirements` (`id`, `user_id`, `role`, `driver_license`, `cetos_certification`, `provisional_authorization`, `puv_id`, `status`, `submitted_at`) VALUES
(58, 18, 'driver', 'uploads/requirements/req_67bd764bcff683.00120028.jpeg', 'uploads/requirements/req_67bd764bd06005.46089286.jpeg', 'uploads/requirements/req_67bd764bd0ea41.06015614.jpeg', 'uploads/requirements/req_67bd764bd15c89.85589956.jpeg', 'Rejected', '2025-02-25 07:50:35'),
(59, 18, '', 'uploads/requirements/req_67bd7740015be0.58409859.jpeg', 'uploads/requirements/req_67bd774001d8b8.79761269.jpeg', 'uploads/requirements/req_67bd7740028e17.62643672.jpeg', 'uploads/requirements/req_67bd7740033903.95119357.jpeg', 'Verified', '2025-02-25 07:54:40'),
(60, 18, '', 'uploads/requirements/req_67bd7910e532b7.32120007.jpeg', 'uploads/requirements/req_67bd7910e5a900.52522347.jpeg', 'uploads/requirements/req_67bd7910e68827.91561326.jpeg', 'uploads/requirements/req_67bd7910e6f035.84925444.jpeg', 'Verified', '2025-02-25 08:02:24'),
(61, 18, '', 'uploads/requirements/req_67bd7a339535a1.79994100.jpeg', 'uploads/requirements/req_67bd7a339587a7.90629452.jpeg', 'uploads/requirements/req_67bd7a33963d92.49596351.jpeg', 'uploads/requirements/req_67bd7a3396fa86.39097352.jpeg', 'Verified', '2025-02-25 08:07:15'),
(62, 18, '', 'uploads/requirements/req_67bd7c52475632.16495430.jpeg', 'uploads/requirements/req_67bd7c5247aa14.48075611.jpeg', 'uploads/requirements/req_67bd7c5247f210.35470518.jpeg', 'uploads/requirements/req_67bd7c52482c90.53329560.jpeg', 'Verified', '2025-02-25 08:16:18'),
(63, 18, '', 'uploads/requirements/req_67bd7d99730c73.67638134.jpeg', 'uploads/requirements/req_67bd7d99739353.94344480.jpeg', 'uploads/requirements/req_67bd7d9973fcd4.38649457.jpeg', 'uploads/requirements/req_67bd7d9974cea5.03333852.jpeg', 'Verified', '2025-02-25 08:21:45'),
(64, 18, '', 'uploads/requirements/req_67bd7feb76f895.93575255.jpeg', 'uploads/requirements/req_67bd7feb778876.91233465.jpeg', 'uploads/requirements/req_67bd7feb784d40.11945357.jpeg', 'uploads/requirements/req_67bd7feb78dbe3.95829146.jpeg', 'Verified', '2025-02-25 08:31:39'),
(65, 18, '', 'uploads/requirements/req_67bd8241566979.09872590.jpeg', 'uploads/requirements/req_67bd824157d1e9.54415487.jpeg', 'uploads/requirements/req_67bd8241583182.17321082.jpeg', 'uploads/requirements/req_67bd8241586ea5.72635332.jpeg', 'Verified', '2025-02-25 08:41:37'),
(66, 18, '', 'uploads/requirements/req_67bd8dbd570bb4.66513587.jpeg', 'uploads/requirements/req_67bd8dbd576902.34317194.jpeg', 'uploads/requirements/req_67bd8dbd57d0c8.96460980.jpeg', 'uploads/requirements/req_67bd8dbd583069.28514899.jpeg', 'Verified', '2025-02-25 09:30:37'),
(67, 18, '', 'uploads/requirements/req_67bd91ea95d052.32507734.jpeg', 'uploads/requirements/req_67bd91ea9634a9.40421309.jpeg', 'uploads/requirements/req_67bd91ea96a6d6.80179007.jpeg', 'uploads/requirements/req_67bd91ea972092.23459798.jpeg', 'Verified', '2025-02-25 09:48:26'),
(68, 17, 'Driver', 'uploads/requirements/req_67ddfc97779f96.11190772.jpg', 'uploads/requirements/req_67ddfc9777fe68.19723440.jpg', 'uploads/requirements/req_67ddfc9778f032.84732896.jpg', 'uploads/requirements/req_67ddfc97795206.38642419.jpg', 'Verified', '2025-03-21 23:56:07'),
(69, 17, 'Operator', 'uploads/requirements/req_67ddfeecd5f3c0.16577843.jpg', 'uploads/requirements/req_67ddfeecd67e56.47711738.jpeg', 'uploads/requirements/req_67ddfeecd79b10.66309457.jpg', 'uploads/requirements/req_67ddfeecd80ac6.22877694.jpg', 'Verified', '2025-03-22 00:06:04'),
(70, 1, 'Driver', 'download.jpeg', 'Copy of JeepniGo.jpg', 'download.jpeg', 'download (1).jpeg', 'Rejected', '2025-03-22 07:00:51'),
(71, 1, 'Driver', 'FINAL SP 101 SG 2023 L1.pdf', 'Untitled design (1).png', 'download (1).jpeg', 'download (1).jpeg', 'Verified', '2025-03-22 07:05:54'),
(72, 19, 'Driver', 'Copy of JeepniGo.jpg', 'JeepniGo Validation Survey.pdf', 'Untitled design.png', 'Copy of JeepniGo.jpg', 'Verified', '2025-03-22 07:21:44'),
(73, 20, 'Driver', 'Copy of Copy of DFD FINAL.png', 'download (1).jpeg', 'BURLAT_GRADES.png', 'Commemorative_Andres_Bonifacio_PHP10_Peso_Coin.jpg', 'Verified', '2025-03-23 07:07:06'),
(74, 24, 'Driver', 'Atomic-Habits-.pdf', 'Copy of JeepniGo.jpg', 'Copy of JeepniGo.jpg', 'Copy of JeepniGo.jpg', 'Verified', '2025-03-23 09:41:28'),
(75, 28, 'Driver', 'Untitled design (1).png', '4736-8941-7265-9782.pdf', 'Copy of JeepniGo.jpg', 'Untitled design.png', 'Verified', '2025-03-29 00:29:17'),
(76, 32, 'Driver', 'Screenshot 2025-03-29 080305.png', 'Screenshot 2025-02-16 225844.png', 'Screenshot 2025-02-18 232911.png', 'Screenshot 2025-02-19 002926.png', 'Verified', '2025-03-29 09:35:44'),
(77, 33, 'Operator', 'Untitled design (2).png', 'Untitled design (1).png', 'JEEPNIGO CHAPTER1.pdf', 'download.jpeg', 'Verified', '2025-04-04 13:19:16'),
(78, 34, 'Operator', 'Screenshot 2025-02-09 220944.png', 'Screenshot 2025-02-16 211151.png', 'Screenshot 2025-02-08 121659.png', 'Screenshot 2025-02-07 235748.png', 'Verified', '2025-04-17 09:25:56'),
(79, 35, 'Operator', 'Screenshot 2025-02-07 235748.png', 'Screenshot 2025-02-07 235748.png', 'Screenshot 2025-02-08 121507.png', 'Screenshot 2025-02-08 121522.png', 'Verified', '2025-05-03 01:44:49'),
(80, 39, 'Operator', 'Class schedule.png', 'Screenshot 2025-02-07 235748.png', 'Screenshot 2025-02-08 121507.png', 'Screenshot 2025-02-08 121522.png', 'Verified', '2025-06-21 07:36:21'),
(81, 40, 'Driver', 'DRIVER\'S LICENSE.jpg', 'CETOS CERT.jpg', 'PROVISIONAL AUTHORITY.jpg', 'puv id.jpg', 'Verified', '2025-08-06 02:12:10'),
(82, 40, 'Driver', 'DRIVER\'S LICENSE.jpg', 'CETOS CERT.jpg', 'PROVISIONAL AUTHORITY.jpg', 'puv id.jpg', 'Verified', '2025-08-06 02:14:47'),
(83, 40, 'Driver', 'DRIVER\'S LICENSE.jpg', 'CETOS CERT.jpg', 'PROVISIONAL AUTHORITY.jpg', 'puv id.jpg', 'Verified', '2025-08-06 02:18:03');

-- --------------------------------------------------------

--
-- Table structure for table `treasurer_payment_details`
--

CREATE TABLE `treasurer_payment_details` (
  `id` int(11) NOT NULL,
  `treasurer_id` int(11) NOT NULL,
  `gcash_number` varchar(11) NOT NULL,
  `gcash_name` varchar(100) NOT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account` varchar(50) DEFAULT NULL,
  `bank_account_name` varchar(100) DEFAULT NULL,
  `office_address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `treasurer_payment_details`
--

INSERT INTO `treasurer_payment_details` (`id`, `treasurer_id`, `gcash_number`, `gcash_name`, `bank_name`, `bank_account`, `bank_account_name`, `office_address`, `created_at`, `updated_at`) VALUES
(1, 27, '09913731309', 'gigi', 'gigigig', '121313546548', 'valdez', 'Office', '2025-05-09 09:48:53', '2025-05-11 08:31:41');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `firstName` varchar(50) NOT NULL,
  `middleName` varchar(50) DEFAULT NULL,
  `lastName` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `userType` varchar(50) NOT NULL DEFAULT 'passenger',
  `profileImage` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` varchar(50) NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `firstName`, `middleName`, `lastName`, `email`, `password`, `userType`, `profileImage`, `created_at`, `updated_at`, `status`) VALUES
(1, 'John', 'M', 'Doe', 'john.doe@example.com', '123', 'passenger', NULL, '2024-12-18 08:20:34', '2025-05-01 05:59:14', 'Active'),
(2, 'Jane', 'A', 'Smith', 'jane.smith@example.com', '123', 'manager', NULL, '2024-12-18 08:20:34', '2025-04-26 16:28:12', 'Active'),
(17, 'MARK', 'POL', 'BURLAT', 'markpol@gmail.com', '111', 'operator', NULL, '2025-02-01 07:14:33', '2025-03-22 00:06:12', 'Active'),
(18, 'POL', 'POL', 'POL', 'mmm@gmail.com', '123', 'driver', NULL, '2025-02-08 05:26:38', '2025-03-22 06:24:45', 'Active'),
(19, 'AYOS', 'KA', 'BAI', 'sd@gmail.com', '123', 'driver', NULL, '2025-03-22 07:21:02', '2025-03-22 07:24:07', 'Active'),
(20, 'gigi', 'c', 'valdez', 'gigi@gmail.com', '123', 'driver', NULL, '2025-03-23 07:03:13', '2025-03-23 07:07:34', 'Active'),
(21, 'Gigi', NULL, 'Valdez', 'valdez@gmail.com', '123', 'passenger', NULL, '2025-03-23 09:10:46', '2025-03-23 09:10:46', 'Active'),
(22, 'Ryan', NULL, 'Bulacito', 'Ryan@gmail.com', '123', 'passenger', NULL, '2025-03-23 09:11:30', '2025-03-23 09:11:30', 'Active'),
(23, 'ryan', 'miguel', 'Tonzo', 'Ryantonzo@GMAIL.COM', 'iLOVERYAN', 'passenger', NULL, '2025-03-23 09:12:31', '2025-03-23 09:12:31', 'Active'),
(24, 'markkk', NULL, 'mark', 'mark123@gmail.com', '123', 'driver', NULL, '2025-03-23 09:13:59', '2025-05-24 09:23:27', 'Active'),
(27, 'John', 'Middle', 'Doe', 'johnmark@example.com', '123', 'treasurer', NULL, '2025-03-28 15:34:40', '2025-03-28 15:34:40', 'Active'),
(28, 'Driver', NULL, 'Doy', 'doy@gmail.com', '123', 'driver', NULL, '2025-03-29 00:26:59', '2025-11-06 16:42:04', 'Active'),
(32, 'gigi', NULL, 'valdez', 'gigi1@gmail.com', '123', 'driver', NULL, '2025-03-29 09:33:14', '2025-03-29 09:36:23', 'Active'),
(33, 'OPO', NULL, 'WHY', 'why@gmail.com', '123', 'operator', NULL, '2025-04-04 13:16:47', '2025-04-04 13:20:57', 'Active'),
(34, 'Operator', NULL, 'MANN', 'joe@gmail.com', '123', 'operator', NULL, '2025-04-17 09:25:05', '2025-05-11 08:40:50', 'Active'),
(35, 's', NULL, 's', 'staff@gmail.com', '123', 'operator', NULL, '2025-05-03 00:32:50', '2025-05-03 01:44:58', 'Active'),
(36, 'Jef', NULL, 'Hoe', 'bh@gmail.com', '123', 'passenger', NULL, '2025-05-22 06:47:48', '2025-11-08 15:41:17', 'Active'),
(37, 'yyyy', NULL, 'yyyyy', 'yu@gmail.com', '$2y$10$R3l/GhUNw0p593UW9iXPcudVz4gK8zonvXdXW9Sh/RyYhm6cyfg92', 'passenger', NULL, '2025-05-22 06:49:08', '2025-05-22 06:49:08', 'Active'),
(38, 'Markpol', NULL, 'Burlat', 'mpb@gmail.com', '$2y$10$FIyuCwsLeEsGcf8/WNpv6Ow0O4C.WPqlODrd4LJvZIcTxR7y1rrIa', 'passenger', NULL, '2025-05-22 07:33:37', '2025-05-22 07:33:37', 'Active'),
(39, 'Dexter', NULL, 'Boy', 'dex@gmail.com', '123', 'passenger', NULL, '2025-06-21 07:33:15', '2025-11-03 15:22:55', 'Active'),
(40, 'jake', NULL, 'jun', 'jj@gmail.com', '123', 'passenger', NULL, '2025-08-06 02:09:52', '2025-11-03 15:22:42', 'Active'),
(41, 'Admin', NULL, 'User', 'admin@jeepnigo.local', '123', 'admin', NULL, '2025-09-14 11:36:20', '2025-09-29 15:47:52', 'Active'),
(42, 'Dan', NULL, 'Concepcion', 'dangreyconcepcion312@gmail.com', '123456', 'passenger', NULL, '2025-10-16 11:05:35', '2025-10-19 10:18:14', 'Active'),
(43, 'Emilio', NULL, 'Amarillo', 'kwak@gmail.com', '123456', 'driver', NULL, '2025-10-19 03:07:27', '2025-11-03 15:18:11', 'Active'),
(44, 'Pepe', NULL, 'Labor Jr.', 'pepelabor@gmail.com', '123', 'driver', NULL, '2025-11-03 04:26:01', '2025-11-03 15:08:46', 'Active'),
(45, 'Marven', NULL, 'Amorin', 'Marven123@gmail.com', '123', 'passenger', NULL, '2025-11-03 05:22:38', '2025-11-03 05:22:38', 'Active'),
(46, 'Edmark', NULL, 'Sarmiento', 'dodoy@gmail.com', '123', 'passenger', NULL, '2025-11-03 06:57:14', '2025-11-03 06:57:14', 'Active'),
(47, 'Kaye ', NULL, 'Escobar', 'escobarkaye1@gmail.com', 'ambot22', 'passenger', NULL, '2025-11-08 14:00:05', '2025-11-08 14:00:05', 'Active'),
(48, 'KANE', NULL, 'ESCOBAR', 'kaneescobar1@gmail.com', '129548100348', 'passenger', NULL, '2025-11-08 14:06:04', '2025-11-08 14:06:04', 'Active'),
(49, 'Dhonna Lyne ', NULL, 'Chavez ', 'dhonnachavez@gmail.com', 'chavez0708', 'passenger', NULL, '2025-11-08 23:30:09', '2025-11-08 23:30:09', 'Active'),
(50, 'Marielle ', NULL, 'Sarona', 'masarona15@gmail.com', 'virgo091506', 'passenger', NULL, '2025-11-08 23:30:19', '2025-11-08 23:30:19', 'Active'),
(51, 'Bea', NULL, 'Jamboy', 'jamboybea6@gmail.com', 'bebebebebe', 'passenger', NULL, '2025-11-08 23:32:09', '2025-11-08 23:32:09', 'Active'),
(52, 'Orly', NULL, 'Matos', 'Orlymatos2006@gmail.com', 'orlymatos2006', 'passenger', NULL, '2025-11-08 23:49:17', '2025-11-08 23:49:17', 'Active'),
(53, 'Exequiel ', NULL, 'Ollet', 'exequielollet10@gmail.com', 'ollet200410', 'passenger', NULL, '2025-11-09 00:22:59', '2025-11-09 00:22:59', 'Active'),
(54, 'iverson ', NULL, 'maglangit', 'ivxr0303@gmail.com', 'iverson123', 'passenger', NULL, '2025-11-09 00:36:26', '2025-11-09 00:36:26', 'Active'),
(55, 'WILLY', NULL, 'CODINO', 'willycodino2005@gmail.com', 'WillA^_^18.05', 'passenger', NULL, '2025-11-09 00:41:29', '2025-11-09 00:41:29', 'Active'),
(56, 'Deric', NULL, 'Manlupig', 'der1@gmail.com', '123', 'passenger', NULL, '2025-11-09 00:46:53', '2025-11-09 00:46:53', 'Active'),
(57, 'Crisnard', NULL, 'Cifra', 'crisnardcifra1@gmail.com', '123456789Ten!', 'passenger', NULL, '2025-11-09 01:14:50', '2025-11-09 01:14:50', 'Active'),
(58, 'Kevin', NULL, 'Nepomuceno ', 'kevinnepomuceno08@gmail.com', 'Kevinray12345', 'passenger', NULL, '2025-11-09 01:15:16', '2025-11-09 01:15:16', 'Active'),
(59, 'Johayr ', NULL, 'Tasil ', 'johayr.tasil0529@gmail.com', 'qwertykeypod123', 'passenger', NULL, '2025-11-09 01:28:15', '2025-11-09 01:28:15', 'Active'),
(60, 'Ezikiel', NULL, 'Sumilhig', 'zekkylyn@gmail.com', '071307zee', 'passenger', NULL, '2025-11-09 01:33:38', '2025-11-09 01:33:38', 'Active'),
(61, 'Elthon John ', NULL, 'LEBUMFACIL ', 'elthonlebumfacil@gmail.com', 'Elthon69', 'passenger', NULL, '2025-11-09 05:03:19', '2025-11-09 05:03:19', 'Active'),
(62, 'Norben', NULL, 'William', 'norbs123@gmail.com', '123', 'passenger', NULL, '2025-11-09 07:37:18', '2025-11-09 07:37:18', 'Active'),
(63, 'James Brian', NULL, 'Ibanez', 'brian22@gmail.com', '123', 'passenger', NULL, '2025-11-09 07:40:57', '2025-11-09 07:40:57', 'Active'),
(64, 'Ardian', NULL, 'Talledo', 'ardiantalledo@gmail.com', 'gegegege123', 'passenger', NULL, '2025-11-09 10:25:54', '2025-11-09 10:25:54', 'Active'),
(65, 'alex', NULL, 'cayacap', 'alexcayacap200712@gmail.com', '123456789', 'passenger', NULL, '2025-11-09 11:30:32', '2025-11-09 11:30:32', 'Active'),
(66, 'Zalhamzah Yashin', NULL, 'Sarigan', 'zalhamzahyashinsarigan@gmail.com', 'zalhamzah', 'passenger', NULL, '2025-11-11 00:15:56', '2025-11-11 00:15:56', 'Active'),
(67, 'Lloyd', NULL, 'Bustria', 'User@gmail.com', 'z33ttJf24Cu8qwH', 'passenger', NULL, '2026-01-29 11:00:40', '2026-01-29 11:00:40', 'Active'),
(68, 'Emirence', NULL, 'Gumban', 'emirencegumban@gmail.com', '123', 'passenger', NULL, '2026-03-03 17:30:35', '2026-03-03 17:30:35', 'Active'),
(69, 'lows', NULL, 'lows', 'lowsy@gmail.com', 'lows', 'passenger', NULL, '2026-03-10 15:36:22', '2026-03-10 15:36:22', 'Active'),
(70, 'Anon', NULL, 'GG', 'user@example.com', '123456', 'passenger', NULL, '2026-03-11 11:29:26', '2026-03-11 11:29:26', 'Active'),
(71, 'Mireign', NULL, 'Yuri', 'mireign.yuri@gmail.com', 'Ilovemuffy@11', 'passenger', NULL, '2026-03-11 14:39:03', '2026-03-11 14:39:03', 'Active'),
(72, 'Von', NULL, 'Costuna', 'vgcostuna@gmail.com', 'Vongabriel31!', 'passenger', NULL, '2026-03-11 14:40:49', '2026-03-11 14:40:49', 'Active'),
(73, 'Brenstar John', NULL, 'Dulalas', 'dulalasb@gmail.com', '123', 'passenger', NULL, '2026-03-22 13:43:17', '2026-03-22 13:43:17', 'Active'),
(74, 'Carl', NULL, 'Concepcion', 'carlconcepcion@gmail.com', '123456789', 'passenger', NULL, '2026-04-06 06:16:05', '2026-04-06 06:16:05', 'Active'),
(75, 'Red', NULL, 'Mungcal', 'reggieboymungcal@gmail.com', '...agiluz...', 'passenger', NULL, '2026-05-26 00:06:22', '2026-05-26 00:06:22', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `user_applications`
--

CREATE TABLE `user_applications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('driver','operator') NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_receipts`
--

CREATE TABLE `user_receipts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `receipt_number` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `payment_date` datetime NOT NULL,
  `status` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `boarding_events`
--
ALTER TABLE `boarding_events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `boundary_payments`
--
ALTER TABLE `boundary_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_operator_id` (`operator_id`),
  ADD KEY `idx_driver_id` (`driver_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `cooperative_applications`
--
ALTER TABLE `cooperative_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `cooperative_fund_payments`
--
ALTER TABLE `cooperative_fund_payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `fare_payments`
--
ALTER TABLE `fare_payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jeepneys`
--
ALTER TABLE `jeepneys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `plate_number` (`plate_number`),
  ADD UNIQUE KEY `body_number` (`body_number`);

--
-- Indexes for table `jeepney_assignments`
--
ALTER TABLE `jeepney_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `driver_id` (`driver_id`),
  ADD KEY `fk_operator_id` (`operator_id`);

--
-- Indexes for table `jeepney_locations`
--
ALTER TABLE `jeepney_locations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `membership_payments`
--
ALTER TABLE `membership_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_receipt_number` (`receipt_number`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `membership_requirements`
--
ALTER TABLE `membership_requirements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orientation_attendees`
--
ALTER TABLE `orientation_attendees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `orientation_id` (`orientation_id`);

--
-- Indexes for table `orientation_requests`
--
ALTER TABLE `orientation_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `orientation_schedule`
--
ALTER TABLE `orientation_schedule`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `passenger_id` (`passenger_id`),
  ADD KEY `driver_id` (`driver_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `submitted_requirements`
--
ALTER TABLE `submitted_requirements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `treasurer_payment_details`
--
ALTER TABLE `treasurer_payment_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `treasurer_id` (`treasurer_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `email_2` (`email`);

--
-- Indexes for table `user_applications`
--
ALTER TABLE `user_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_receipts`
--
ALTER TABLE `user_receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_payment` (`payment_id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `boarding_events`
--
ALTER TABLE `boarding_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `boundary_payments`
--
ALTER TABLE `boundary_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `cooperative_applications`
--
ALTER TABLE `cooperative_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `cooperative_fund_payments`
--
ALTER TABLE `cooperative_fund_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `fare_payments`
--
ALTER TABLE `fare_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `jeepneys`
--
ALTER TABLE `jeepneys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jeepney_assignments`
--
ALTER TABLE `jeepney_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `jeepney_locations`
--
ALTER TABLE `jeepney_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `membership_payments`
--
ALTER TABLE `membership_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `membership_requirements`
--
ALTER TABLE `membership_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `orientation_attendees`
--
ALTER TABLE `orientation_attendees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `orientation_requests`
--
ALTER TABLE `orientation_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `orientation_schedule`
--
ALTER TABLE `orientation_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `submitted_requirements`
--
ALTER TABLE `submitted_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT for table `treasurer_payment_details`
--
ALTER TABLE `treasurer_payment_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `user_applications`
--
ALTER TABLE `user_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_receipts`
--
ALTER TABLE `user_receipts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cooperative_applications`
--
ALTER TABLE `cooperative_applications`
  ADD CONSTRAINT `cooperative_applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `jeepney_assignments`
--
ALTER TABLE `jeepney_assignments`
  ADD CONSTRAINT `fk_operator_id` FOREIGN KEY (`operator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jeepney_assignments_ibfk_1` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `membership_payments`
--
ALTER TABLE `membership_payments`
  ADD CONSTRAINT `membership_payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `orientation_attendees`
--
ALTER TABLE `orientation_attendees`
  ADD CONSTRAINT `orientation_attendees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `orientation_attendees_ibfk_2` FOREIGN KEY (`orientation_id`) REFERENCES `orientation_schedule` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orientation_requests`
--
ALTER TABLE `orientation_requests`
  ADD CONSTRAINT `orientation_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `submitted_requirements`
--
ALTER TABLE `submitted_requirements`
  ADD CONSTRAINT `submitted_requirements_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `treasurer_payment_details`
--
ALTER TABLE `treasurer_payment_details`
  ADD CONSTRAINT `treasurer_payment_details_ibfk_1` FOREIGN KEY (`treasurer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_applications`
--
ALTER TABLE `user_applications`
  ADD CONSTRAINT `user_applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_receipts`
--
ALTER TABLE `user_receipts`
  ADD CONSTRAINT `user_receipts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_receipts_ibfk_2` FOREIGN KEY (`payment_id`) REFERENCES `membership_payments` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
