-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 25, 2026 at 05:32 AM
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
-- Database: `campuscare_db_normalized`
--

-- --------------------------------------------------------

--
-- Table structure for table `account_statuses`
--

CREATE TABLE `account_statuses` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `display_name` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `account_statuses`
--

INSERT INTO `account_statuses` (`id`, `code`, `display_name`) VALUES
(1, 'active', 'Active'),
(2, 'inactive', 'Inactive');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_user_id` int(10) UNSIGNED NOT NULL,
  `counselor_user_id` int(10) UNSIGNED DEFAULT NULL,
  `service_id` smallint(5) UNSIGNED NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status_id` tinyint(3) UNSIGNED NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `student_user_id`, `counselor_user_id`, `service_id`, `appointment_date`, `appointment_time`, `status_id`, `notes`, `created_at`, `updated_at`) VALUES
(12, 66, 44, 2, '2026-04-27', '10:00:00', 2, NULL, '2026-04-24 19:17:28', '2026-04-24 19:17:28'),
(13, 66, 44, 1, '2026-04-28', '12:00:00', 4, NULL, '2026-04-24 19:22:02', '2026-04-24 19:22:02'),
(14, 66, 44, 2, '2026-04-30', '10:00:00', 2, NULL, '2026-04-24 19:25:27', '2026-04-24 19:25:27'),
(15, 66, 44, 2, '2026-04-30', '12:00:00', 1, NULL, '2026-04-24 19:26:42', '2026-04-24 19:26:42'),
(16, 66, 44, 1, '2026-04-30', '11:00:00', 1, NULL, '2026-04-24 19:27:57', '2026-04-24 19:27:57'),
(17, 63, 44, 2, '2026-04-27', '10:00:00', 1, NULL, '2026-04-24 20:37:48', '2026-04-24 20:37:48'),
(18, 63, 44, 2, '2026-04-27', '09:00:00', 1, NULL, '2026-04-24 21:15:35', '2026-04-24 21:15:35');

-- --------------------------------------------------------

--
-- Table structure for table `appointment_feedback`
--

CREATE TABLE `appointment_feedback` (
  `id` int(10) UNSIGNED NOT NULL,
  `appointment_id` int(10) UNSIGNED NOT NULL,
  `author_user_id` int(10) UNSIGNED NOT NULL,
  `rating` tinyint(3) UNSIGNED DEFAULT NULL,
  `notes` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointment_statuses`
--

CREATE TABLE `appointment_statuses` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `display_name` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointment_statuses`
--

INSERT INTO `appointment_statuses` (`id`, `code`, `display_name`) VALUES
(1, 'pending', 'Pending'),
(2, 'approved', 'Approved'),
(3, 'cancelled', 'Cancelled'),
(4, 'rejected', 'Rejected');

-- --------------------------------------------------------

--
-- Table structure for table `counselor_availability`
--

CREATE TABLE `counselor_availability` (
  `id` int(10) UNSIGNED NOT NULL,
  `counselor_user_id` int(10) UNSIGNED NOT NULL,
  `weekday` tinyint(3) UNSIGNED NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `counselor_availability`
--

INSERT INTO `counselor_availability` (`id`, `counselor_user_id`, `weekday`, `start_time`, `end_time`, `created_at`) VALUES
(1, 44, 1, '09:00:00', '11:00:00', '2026-04-20 13:54:14'),
(2, 44, 2, '08:00:00', '00:00:00', '2026-04-20 23:18:01'),
(3, 44, 4, '08:00:00', '12:00:00', '2026-04-22 08:48:10');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(150) NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime DEFAULT NULL,
  `created_by_user_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `title`, `description`, `location`, `starts_at`, `ends_at`, `created_by_user_id`, `created_at`) VALUES
(2, 'Relapse', 'Wellness', 'Room 202', '2026-04-21 13:02:00', NULL, NULL, '2026-04-20 23:00:37'),
(4, 'Maureal', 'Brownbag', 'COT', '2026-04-24 11:30:00', NULL, NULL, '2026-04-22 09:28:45'),
(5, 'Session Day', 'Brownbag', 'CON', '2026-04-24 11:00:00', NULL, NULL, '2026-04-24 10:09:51');

-- --------------------------------------------------------

--
-- Table structure for table `event_participants`
--

CREATE TABLE `event_participants` (
  `event_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `joined_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `event_participants`
--

INSERT INTO `event_participants` (`event_id`, `user_id`, `joined_at`) VALUES
(2, 66, '2026-04-24 19:20:28');

-- --------------------------------------------------------

--
-- Table structure for table `mental_health_tests`
--

CREATE TABLE `mental_health_tests` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(40) NOT NULL,
  `title` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `mental_health_tests`
--

INSERT INTO `mental_health_tests` (`id`, `code`, `title`, `description`, `is_active`, `created_at`) VALUES
(1, 'stress-screen-v1', 'Stress Screening', 'Basic stress-level assessment for students.', 1, '2026-04-25 11:19:11');

-- --------------------------------------------------------

--
-- Table structure for table `mental_health_test_answers`
--

CREATE TABLE `mental_health_test_answers` (
  `attempt_id` int(10) UNSIGNED NOT NULL,
  `question_id` int(10) UNSIGNED NOT NULL,
  `answer_value` varchar(120) NOT NULL,
  `answer_score` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `mental_health_test_answers`
--

INSERT INTO `mental_health_test_answers` (`attempt_id`, `question_id`, `answer_value`, `answer_score`) VALUES
(8, 1, 'Nearly every day', NULL),
(8, 2, 'Not at all', NULL),
(9, 1, 'More than half the days', NULL),
(9, 2, 'Nearly every day', NULL),
(10, 1, 'More than half the days', NULL),
(10, 2, 'Nearly every day', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `mental_health_test_attempts`
--

CREATE TABLE `mental_health_test_attempts` (
  `id` int(10) UNSIGNED NOT NULL,
  `test_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `result_score` decimal(5,2) DEFAULT NULL,
  `result_level` varchar(50) DEFAULT NULL,
  `result_text` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `mental_health_test_attempts`
--

INSERT INTO `mental_health_test_attempts` (`id`, `test_id`, `user_id`, `result_score`, `result_level`, `result_text`, `created_at`) VALUES
(8, 1, 66, NULL, NULL, 'Stress Level: High | Score: 12/15 | We recommend scheduling an appointment with the guidance office for support.', '2026-04-24 19:16:46'),
(9, 1, 66, NULL, NULL, 'Stress Level: High | Score: 11/15 | We recommend scheduling an appointment with the guidance office for support.', '2026-04-24 19:21:41'),
(10, 1, 66, NULL, NULL, 'Stress Level: High | Score: 13/15 | We recommend scheduling an appointment with the guidance office for support.', '2026-04-24 19:25:12');

-- --------------------------------------------------------

--
-- Table structure for table `mental_health_test_questions`
--

CREATE TABLE `mental_health_test_questions` (
  `id` int(10) UNSIGNED NOT NULL,
  `test_id` int(10) UNSIGNED NOT NULL,
  `question_text` varchar(255) NOT NULL,
  `display_order` smallint(5) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `mental_health_test_questions`
--

INSERT INTO `mental_health_test_questions` (`id`, `test_id`, `question_text`, `display_order`, `created_at`) VALUES
(1, 1, 'Over the last two weeks, how often have you felt nervous or stressed?', 1, '2026-04-25 11:19:11'),
(2, 1, 'Over the last two weeks, how often did stress affect your daily activities?', 2, '2026-04-25 11:19:11');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_reset_tokens`
--

INSERT INTO `password_reset_tokens` (`id`, `user_id`, `token_hash`, `expires_at`, `used_at`, `created_at`) VALUES
(1, 64, '$2y$10$vkIdmq1NUI0XidpCXxDB7uzzyE8Z9oJ596o74oqUoFFQQo2Gzjr4a', '2026-04-24 14:30:51', NULL, '2026-04-24 20:15:51'),
(2, 44, '$2y$10$TYTHXzyqh2Ym9j.8PoXdquCisFqM.YznL02v2KBuiuqCRanHAEzyO', '2026-04-24 15:33:35', NULL, '2026-04-24 21:18:35'),
(3, 63, '$2y$10$/mQHLm.00rEfUZTQOF4V6OYB0ojevlx7FuVS8/RaudpkwaKrIWxPa', '2026-04-25 05:07:59', NULL, '2026-04-25 10:52:59'),
(4, 66, '$2y$10$v9rZAU0J6zLHmfn8y7bhi.RqmuK1QW6yZgUQl0D93ICeEisZcXIdu', '2026-04-25 05:12:40', NULL, '2026-04-25 10:57:40');

-- --------------------------------------------------------

--
-- Table structure for table `registration_verifications`
--

CREATE TABLE `registration_verifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `student_number` varchar(50) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` tinyint(3) UNSIGNED NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `code` varchar(30) NOT NULL,
  `display_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `code`, `display_name`) VALUES
(1, 'administrator', 'Administrator'),
(2, 'counselor', 'Counselor'),
(3, 'facilitator', 'Facilitator'),
(4, 'student', 'Student'),
(5, 'instructor', 'Instructor');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `code` varchar(40) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `code`, `display_name`, `description`, `is_active`) VALUES
(1, 'counseling', 'Counseling', 'One-on-one counseling session', 1),
(2, 'psych-testing', 'Psychological Testing', 'Mental health and psychological test session', 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `student_number` varchar(50) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `college` varchar(150) DEFAULT NULL,
  `program` varchar(150) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` tinyint(3) UNSIGNED NOT NULL,
  `account_status_id` tinyint(3) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `student_number`, `email`, `password_hash`, `role_id`, `account_status_id`, `created_at`, `updated_at`) VALUES
(44, 'Raul Lecaros', '22011', '2201101665@buksu.edu.ph', '$2y$10$RLp7fIl0uEf8mzp2lJhmIuru9KHdEa34mz.fA2zZz1iMEfLCf/0XC', 2, 1, '2026-04-16 20:19:13', '2026-04-16 20:19:13'),
(48, 'Jp Pausal', '24022', '24022101665@buksu.edu.ph', '$2y$10$E3QshZFK84h53xH82BRMouP/N7rKQYd9HkUwRsjxwJ/DQTPDqaLnG', 5, 1, '2026-04-16 21:52:55', '2026-04-16 21:52:55'),
(51, 'Carlitos Simene', '451', '2145@student.buksu.edu.ph', '$2y$10$XxIKe9.YSdTpqr3OC90kDOk9TbycZOopjeCo3JUuSWePE/zjjiuU6', 4, 1, '2026-04-20 11:42:50', '2026-04-20 11:42:50'),
(56, 'Sen', '555', 'sen@buksu.edu.ph', '$2y$10$FqfVc0Dt7j73DJvpW0JAFemwWI2IEptRZN9HIdnyUh8BbHGOYQgi.', 5, 1, '2026-04-21 00:27:00', '2026-04-21 00:27:00'),
(63, 'FRENZEN RIVERA', 'GOOG-7D1D9EBF08', '2401101665@student.buksu.edu.ph', '$2y$10$8y2Iv9fHZsWvqzOJjyBmiepaG4hmmV1CYkhmji.uJ/06ooebiu77K', 4, 1, '2026-04-24 10:06:07', '2026-04-24 10:06:07'),
(64, 'Frenzen Rivera', '2002', 'frenzenrivera@buksu.edu.ph', '$2y$10$GZW0yFNWOpOZwEG5YCkxRe9OnVELx4Fa9Kh2tHZ23QS0UamUgxRkK', 1, 1, '2026-04-24 10:07:49', '2026-04-24 10:07:49'),
(66, 'Toff Darell Vergara', NULL, '2301110636@student.buksu.edu.ph', '$2y$10$4KQWW61r35BTqPS2AU7DuO0dG0g8ty0V.LqSsRXDmr.i78F8/qY9u', 4, 1, '2026-04-24 19:15:26', '2026-04-24 19:15:26'),
(67, 'Gil Cagande', NULL, 'gilcagande@buksu.edu.ph', '$2y$10$ToMSUIPKRlptl52QPHnbbuvN0jy8r1KLHfhCWJAPPfW6UPwhpT8Hu', 1, 1, '2026-04-24 20:17:07', '2026-04-24 20:17:07');

-- --------------------------------------------------------

--
-- Table structure for table `user_feedback`
--

CREATE TABLE `user_feedback` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_statuses`
--
ALTER TABLE `account_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD UNIQUE KEY `display_name` (`display_name`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_appointments_student` (`student_user_id`),
  ADD KEY `idx_appointments_counselor` (`counselor_user_id`),
  ADD KEY `idx_appointments_service` (`service_id`),
  ADD KEY `idx_appointments_status` (`status_id`),
  ADD KEY `idx_appointments_date_time` (`appointment_date`,`appointment_time`);

--
-- Indexes for table `appointment_feedback`
--
ALTER TABLE `appointment_feedback`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_appointment_feedback_appointment` (`appointment_id`),
  ADD KEY `idx_appointment_feedback_author` (`author_user_id`);

--
-- Indexes for table `appointment_statuses`
--
ALTER TABLE `appointment_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD UNIQUE KEY `display_name` (`display_name`);

--
-- Indexes for table `counselor_availability`
--
ALTER TABLE `counselor_availability`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_counselor_timeslot` (`counselor_user_id`,`weekday`,`start_time`,`end_time`),
  ADD KEY `idx_counselor_availability_counselor` (`counselor_user_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_events_starts_at` (`starts_at`),
  ADD KEY `idx_events_created_by` (`created_by_user_id`);

--
-- Indexes for table `event_participants`
--
ALTER TABLE `event_participants`
  ADD PRIMARY KEY (`event_id`,`user_id`),
  ADD KEY `idx_event_participants_user` (`user_id`);

--
-- Indexes for table `mental_health_tests`
--
ALTER TABLE `mental_health_tests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `mental_health_test_answers`
--
ALTER TABLE `mental_health_test_answers`
  ADD PRIMARY KEY (`attempt_id`,`question_id`),
  ADD KEY `fk_mhta_question_id` (`question_id`);

--
-- Indexes for table `mental_health_test_attempts`
--
ALTER TABLE `mental_health_test_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mhta_test_id` (`test_id`),
  ADD KEY `idx_mhta_user_id` (`user_id`);

--
-- Indexes for table `mental_health_test_questions`
--
ALTER TABLE `mental_health_test_questions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_mhtq_test_order` (`test_id`,`display_order`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_password_reset_tokens_user` (`user_id`),
  ADD KEY `idx_password_reset_tokens_expires_at` (`expires_at`);

--
-- Indexes for table `registration_verifications`
--
ALTER TABLE `registration_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_registration_verifications_email` (`email`),
  ADD KEY `idx_registration_verifications_expires_at` (`expires_at`),
  ADD KEY `idx_registration_verifications_role_id` (`role_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD UNIQUE KEY `display_name` (`display_name`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD UNIQUE KEY `display_name` (`display_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD UNIQUE KEY `uq_users_student_number` (`student_number`),
  ADD KEY `idx_users_role_id` (`role_id`),
  ADD KEY `idx_users_account_status_id` (`account_status_id`);

--
-- Indexes for table `user_feedback`
--
ALTER TABLE `user_feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_feedback_user` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account_statuses`
--
ALTER TABLE `account_statuses`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `appointment_feedback`
--
ALTER TABLE `appointment_feedback`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointment_statuses`
--
ALTER TABLE `appointment_statuses`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `counselor_availability`
--
ALTER TABLE `counselor_availability`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `mental_health_tests`
--
ALTER TABLE `mental_health_tests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `mental_health_test_attempts`
--
ALTER TABLE `mental_health_test_attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `mental_health_test_questions`
--
ALTER TABLE `mental_health_test_questions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `registration_verifications`
--
ALTER TABLE `registration_verifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `user_feedback`
--
ALTER TABLE `user_feedback`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appointments_counselor` FOREIGN KEY (`counselor_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_appointments_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  ADD CONSTRAINT `fk_appointments_status` FOREIGN KEY (`status_id`) REFERENCES `appointment_statuses` (`id`),
  ADD CONSTRAINT `fk_appointments_student` FOREIGN KEY (`student_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `appointment_feedback`
--
ALTER TABLE `appointment_feedback`
  ADD CONSTRAINT `fk_appointment_feedback_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_appointment_feedback_author` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `counselor_availability`
--
ALTER TABLE `counselor_availability`
  ADD CONSTRAINT `fk_counselor_availability_counselor` FOREIGN KEY (`counselor_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `fk_events_created_by` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `event_participants`
--
ALTER TABLE `event_participants`
  ADD CONSTRAINT `fk_event_participants_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_event_participants_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mental_health_test_answers`
--
ALTER TABLE `mental_health_test_answers`
  ADD CONSTRAINT `fk_mhta_attempt_id` FOREIGN KEY (`attempt_id`) REFERENCES `mental_health_test_attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mhta_question_id` FOREIGN KEY (`question_id`) REFERENCES `mental_health_test_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mental_health_test_attempts`
--
ALTER TABLE `mental_health_test_attempts`
  ADD CONSTRAINT `fk_mhta_test_id` FOREIGN KEY (`test_id`) REFERENCES `mental_health_tests` (`id`),
  ADD CONSTRAINT `fk_mhta_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mental_health_test_questions`
--
ALTER TABLE `mental_health_test_questions`
  ADD CONSTRAINT `fk_mhtq_test_id` FOREIGN KEY (`test_id`) REFERENCES `mental_health_tests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `fk_password_reset_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `registration_verifications`
--
ALTER TABLE `registration_verifications`
  ADD CONSTRAINT `fk_registration_verifications_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_account_status_id` FOREIGN KEY (`account_status_id`) REFERENCES `account_statuses` (`id`),
  ADD CONSTRAINT `fk_users_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `user_feedback`
--
ALTER TABLE `user_feedback`
  ADD CONSTRAINT `fk_user_feedback_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
