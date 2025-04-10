-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 10, 2025 at 11:23 PM
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
-- Database: `4ps_profiling_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `activities`
--

CREATE TABLE `activities` (
  `activity_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `activity_type` enum('health_check','education','family_development_session','community_meeting','other') NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `barangay_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Stores activities for 4Ps Profiling System';

--
-- Dumping data for table `activities`
--

INSERT INTO `activities` (`activity_id`, `title`, `description`, `activity_type`, `start_date`, `end_date`, `attachments`, `barangay_id`, `created_by`, `created_at`, `updated_at`) VALUES
(33, 'deweqe', 'wqwqw', 'education', '2025-04-01', '2025-04-12', '[]', 2, 27, '2025-04-10 20:35:37', '2025-04-10 20:35:37'),
(34, 'Activity 1', 'sdasdas', 'education', '2025-04-01', '2025-04-16', '{\"documents\":[\"67f831b03d0c3_Employee_Profile (2).pdf\"],\"images\":[\"67f831b03d302_agri1.png\"]}', 2, 27, '2025-04-10 21:01:36', '2025-04-10 21:01:36');

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`log_id`, `user_id`, `activity_type`, `description`, `created_at`) VALUES
(51, 26, 'CREATE_ACTIVITY', 'Created new activity: asasas', '2025-04-11 02:08:49'),
(52, 26, 'CREATE_ACTIVITY', 'Created new activity: activity3', '2025-04-11 02:09:38'),
(53, 26, 'CREATE_ACTIVITY', 'Created new activity: activity1', '2025-04-11 02:11:24'),
(54, 26, 'CREATE_ACTIVITY', 'Created new activity: activity2', '2025-04-11 02:12:33'),
(55, 26, 'CREATE_ACTIVITY', 'Created new activity: acitvity4', '2025-04-11 02:15:35'),
(56, 26, 'CREATE_ACTIVITY', 'Created new activity: activity 5', '2025-04-11 02:17:59'),
(57, 26, 'CREATE_ACTIVITY', 'Created new activity: activity 1', '2025-04-11 02:23:15'),
(58, 26, 'CREATE_ACTIVITY', 'Created new activity: activity 2', '2025-04-11 02:23:21'),
(59, 26, 'CREATE_ACTIVITY', 'Created new activity: activity 3', '2025-04-11 02:23:27'),
(60, 26, 'CREATE_ACTIVITY', 'Created new activity: activity 4', '2025-04-11 02:23:31'),
(61, 26, 'CREATE_ACTIVITY', 'Created new activity: activity 5', '2025-04-11 02:23:38'),
(62, 26, 'CREATE_ACTIVITY', 'Created new activity: activity 6', '2025-04-11 02:33:36'),
(63, 26, 'DELETE_ACTIVITY', 'Deleted activity ID: 8', '2025-04-11 02:41:10'),
(64, 26, 'DELETE_ACTIVITY', 'Deleted activity ID: 9', '2025-04-11 02:41:42'),
(65, 26, 'DELETE_ACTIVITY', 'Deleted activity ID: 10', '2025-04-11 02:43:33'),
(66, 26, 'DELETE_ACTIVITY', 'Deleted activity ID: 11', '2025-04-11 02:44:15'),
(67, 26, 'CREATE_ACTIVITY', 'Created new activity: activity 1', '2025-04-11 03:17:28'),
(68, 26, 'CREATE_ACTIVITY', 'Created new activity: activity 1', '2025-04-11 03:18:29'),
(69, 26, 'CREATE_ACTIVITY', 'Created new activity: activity 1', '2025-04-11 03:19:23'),
(70, 26, 'CREATE_ACTIVITY', 'Created new activity: activity 1', '2025-04-11 03:19:47'),
(71, 26, 'CREATE_ACTIVITY', 'Created new activity: activity 1', '2025-04-11 03:49:57'),
(72, 26, 'CREATE_ACTIVITY', 'Created new activity: activity 1', '2025-04-11 03:50:12'),
(73, 26, 'CREATE_ACTIVITY', 'Created new activity: ACTIVITY 3', '2025-04-11 03:51:16'),
(74, 26, 'CREATE_ACTIVITY', 'Created new activity: AASSA', '2025-04-11 03:51:58'),
(75, 26, 'CREATE_ACTIVITY', 'Created new activity: ASASA', '2025-04-11 03:52:58'),
(76, 26, 'CREATE_ACTIVITY', 'Created new activity: ASASA', '2025-04-11 03:53:29'),
(77, 26, 'CREATE_ACTIVITY', 'Created new activity: ASASA', '2025-04-11 03:53:41'),
(78, 26, 'CREATE_ACTIVITY', 'Created new activity: ASASA', '2025-04-11 03:53:54'),
(79, 26, 'DELETE_ACTIVITY', 'Deleted activity ID: 22', '2025-04-11 03:54:05'),
(80, 26, 'DELETE_ACTIVITY', 'Deleted activity ID: 23', '2025-04-11 03:54:14'),
(81, 26, 'DELETE_ACTIVITY', 'Deleted activity ID: 24', '2025-04-11 03:54:20'),
(82, 26, 'DELETE_ACTIVITY', 'Deleted activity ID: 25', '2025-04-11 03:54:29'),
(83, 26, 'CREATE_ACTIVITY', 'Created new activity: ASDAS', '2025-04-11 03:54:45'),
(84, 26, 'CREATE_ACTIVITY', 'Created new activity: SASA', '2025-04-11 03:55:16'),
(85, 26, 'CREATE_ACTIVITY', 'Created new activity: SASAS', '2025-04-11 03:56:21'),
(86, 26, 'DELETE_ACTIVITY', 'Deleted activity ID: 26', '2025-04-11 03:56:55'),
(87, 26, 'DELETE_ACTIVITY', 'Deleted activity ID: 27', '2025-04-11 03:57:00'),
(88, 26, 'DELETE_ACTIVITY', 'Deleted activity ID: 28', '2025-04-11 03:57:06'),
(89, 26, 'CREATE_ACTIVITY', 'Created new activity: SASAS', '2025-04-11 03:57:18'),
(90, 26, 'CREATE_ACTIVITY', 'Created new activity: WQWQW', '2025-04-11 03:58:01'),
(91, 26, 'DELETE_ACTIVITY', 'Deleted activity ID: 30', '2025-04-11 03:58:56'),
(92, 26, 'DELETE_ACTIVITY', 'Deleted activity ID: 29', '2025-04-11 03:59:06'),
(93, 26, 'CREATE_ACTIVITY', 'Created new activity: SASAS', '2025-04-11 04:12:06'),
(94, 26, 'CREATE_ACTIVITY', 'Created new activity: DASAS for barangay ID: 2', '2025-04-11 04:19:36'),
(95, 26, 'DELETE_ACTIVITY', 'Deleted activity ID: 31', '2025-04-11 04:19:48'),
(96, 1, 'verification', 'Approved user ID 27 (not added to beneficiaries)', '2025-04-11 04:35:08'),
(97, 27, 'CREATE_ACTIVITY', 'Created new activity: deweqe for barangay ID: 2', '2025-04-11 04:35:37'),
(98, 27, 'CREATE_ACTIVITY', 'Created new activity: Activity 1 for barangay ID: 2', '2025-04-11 05:01:36');

-- --------------------------------------------------------

--
-- Table structure for table `activity_submissions`
--

CREATE TABLE `activity_submissions` (
  `submission_id` int(11) NOT NULL,
  `activity_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `response_type` enum('participation','feedback','question') NOT NULL,
  `comments` text DEFAULT NULL,
  `attendance_status` enum('yes','maybe','no') NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `barangays`
--

CREATE TABLE `barangays` (
  `barangay_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `captain_name` varchar(100) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barangays`
--

INSERT INTO `barangays` (`barangay_id`, `name`, `captain_name`, `image_path`, `created_at`, `updated_at`) VALUES
(1, 'sasas', 'wqwq', 'assets/images/barangays/barangay_1744045360.jpg', '2025-04-08 01:02:40', NULL),
(2, 'barangay2', 'dsadas', 'assets/images/barangays/barangay_1744046150.jpg', '2025-04-08 01:15:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `beneficiaries`
--

CREATE TABLE `beneficiaries` (
  `beneficiary_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `firstname` varchar(50) NOT NULL,
  `lastname` varchar(50) NOT NULL,
  `age` int(11) NOT NULL,
  `year_level` varchar(50) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `barangay_id` int(11) NOT NULL,
  `parent_leader_id` int(11) NOT NULL,
  `household_size` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `beneficiaries`
--

INSERT INTO `beneficiaries` (`beneficiary_id`, `user_id`, `firstname`, `lastname`, `age`, `year_level`, `phone_number`, `barangay_id`, `parent_leader_id`, `household_size`, `created_at`, `updated_at`) VALUES
(13, 27, 'larry', 'Biaco', 12, 'Elementary - Grade 4', '', 2, 27, 1, '2025-04-10 20:35:52', '2025-04-10 20:35:52');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `status` enum('read','unread') DEFAULT 'unread',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `firstname` varchar(50) NOT NULL,
  `lastname` varchar(50) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('male','female','other','MALE','FEMALE','OTHER') NOT NULL,
  `civil_status` varchar(50) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `region` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `household_members` int(11) NOT NULL,
  `dependants` int(11) NOT NULL,
  `family_head` varchar(100) NOT NULL,
  `occupation` varchar(100) NOT NULL,
  `household_income` decimal(12,2) NOT NULL,
  `income_source` varchar(100) NOT NULL,
  `valid_id_path` varchar(255) NOT NULL,
  `proof_of_residency_path` varchar(255) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `account_status` enum('pending','active','suspended','deactivated') DEFAULT 'pending',
  `role` enum('resident','staff','admin') DEFAULT 'resident',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email`, `password`, `firstname`, `lastname`, `date_of_birth`, `gender`, `civil_status`, `phone_number`, `region`, `province`, `city`, `household_members`, `dependants`, `family_head`, `occupation`, `household_income`, `income_source`, `valid_id_path`, `proof_of_residency_path`, `profile_image`, `account_status`, `role`, `created_at`, `updated_at`, `last_login`, `barangay`) VALUES
(27, 'larrydenverbiaco@gmail.com', '$2y$10$jJmgVAdtPPKy/XH5Hj5SFe1b.6hN7nb8AeLWKAhZTDFbv8GSOsS.u', 'larry', 'denverr', '2025-04-10', 'male', 'SINGLE', '09123456789', '6', 'pavia', 'iloilo', 21, 3, 'jdajadasa', 'Web Design', 11111.00, 'EMPLOYMENT', 'uploads/ids/ID_67f82b633cf84.png', 'uploads/residency/PROOF_67f82b633cfd7.png', NULL, 'active', 'resident', '2025-04-10 20:34:43', '2025-04-10 20:43:48', '2025-04-10 20:43:48', '2');

-- --------------------------------------------------------

--
-- Table structure for table `verification_codes`
--

CREATE TABLE `verification_codes` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `code` varchar(10) NOT NULL,
  `purpose` enum('registration','password_reset','email_verification') DEFAULT 'registration',
  `is_used` tinyint(1) DEFAULT 0,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activities`
--
ALTER TABLE `activities`
  ADD PRIMARY KEY (`activity_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_activity_barangay` (`barangay_id`),
  ADD KEY `idx_activity_type` (`activity_type`),
  ADD KEY `idx_activity_date` (`start_date`,`end_date`);

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `activity_submissions`
--
ALTER TABLE `activity_submissions`
  ADD PRIMARY KEY (`submission_id`),
  ADD UNIQUE KEY `activity_user` (`activity_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `barangays`
--
ALTER TABLE `barangays`
  ADD PRIMARY KEY (`barangay_id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `unique_barangay_name` (`name`);

--
-- Indexes for table `beneficiaries`
--
ALTER TABLE `beneficiaries`
  ADD PRIMARY KEY (`beneficiary_id`),
  ADD KEY `barangay_id` (`barangay_id`),
  ADD KEY `parent_leader_id` (`parent_leader_id`),
  ADD KEY `fk_beneficiaries_users` (`user_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `verification_codes`
--
ALTER TABLE `verification_codes`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activities`
--
ALTER TABLE `activities`
  MODIFY `activity_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

--
-- AUTO_INCREMENT for table `activity_submissions`
--
ALTER TABLE `activity_submissions`
  MODIFY `submission_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `barangays`
--
ALTER TABLE `barangays`
  MODIFY `barangay_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `beneficiaries`
--
ALTER TABLE `beneficiaries`
  MODIFY `beneficiary_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `verification_codes`
--
ALTER TABLE `verification_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activities`
--
ALTER TABLE `activities`
  ADD CONSTRAINT `activities_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`barangay_id`),
  ADD CONSTRAINT `activities_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `activity_submissions`
--
ALTER TABLE `activity_submissions`
  ADD CONSTRAINT `activity_submissions_ibfk_1` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`activity_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `activity_submissions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `beneficiaries`
--
ALTER TABLE `beneficiaries`
  ADD CONSTRAINT `beneficiaries_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`barangay_id`),
  ADD CONSTRAINT `beneficiaries_ibfk_2` FOREIGN KEY (`parent_leader_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_beneficiaries_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
