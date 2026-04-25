-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 25, 2026 at 05:03 AM
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
-- Database: `campuscare_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `counselor_id` int(11) DEFAULT NULL,
  `service` varchar(100) NOT NULL,
  `counselor` varchar(100) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('Pending','Approved','Cancelled','Rejected') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `user_id`, `counselor_id`, `service`, `counselor`, `appointment_date`, `appointment_time`, `status`, `created_at`) VALUES
(12, 66, 44, 'Psychological Testing', 'Raul Lecaros', '2026-04-27', '10:00:00', 'Approved', '2026-04-24 11:17:28'),
(13, 66, 44, 'Counseling', 'Raul Lecaros', '2026-04-28', '12:00:00', 'Rejected', '2026-04-24 11:22:02'),
(14, 66, 44, 'Psychological Testing', 'Raul Lecaros', '2026-04-30', '10:00:00', 'Approved', '2026-04-24 11:25:27'),
(15, 66, 44, 'Psychological Testing', 'Raul Lecaros', '2026-04-30', '12:00:00', 'Pending', '2026-04-24 11:26:42'),
(16, 66, 44, 'Counseling', 'Raul Lecaros', '2026-04-30', '11:00:00', 'Pending', '2026-04-24 11:27:57'),
(17, 63, 44, 'Psychological Testing', 'Raul Lecaros', '2026-04-27', '10:00:00', 'Pending', '2026-04-24 12:37:48'),
(18, 63, 44, 'Psychological Testing', 'Raul Lecaros', '2026-04-27', '09:00:00', 'Pending', '2026-04-24 13:15:35');

-- --------------------------------------------------------

--
-- Table structure for table `counselor_availability`
--

CREATE TABLE `counselor_availability` (
  `id` int(11) NOT NULL,
  `counselor_id` int(11) NOT NULL,
  `day` varchar(20) NOT NULL,
  `start_time` varchar(20) NOT NULL,
  `end_time` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `counselor_availability`
--

INSERT INTO `counselor_availability` (`id`, `counselor_id`, `day`, `start_time`, `end_time`, `created_at`) VALUES
(2, 44, 'Monday', '9:00 AM', '11:00 AM', '2026-04-20 05:54:14'),
(4, 44, 'Tuesday', '08:00', '12:00', '2026-04-20 15:18:01'),
(7, 44, 'Thursday', '08:00:00', '12:00:00', '2026-04-22 00:48:10');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `event_date` date NOT NULL,
  `event_time` time NOT NULL,
  `location` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `title`, `event_date`, `event_time`, `location`, `description`, `created_at`) VALUES
(2, 'Relapse', '2026-04-21', '13:02:00', 'Room 202', 'Wellness', '2026-04-20 15:00:37'),
(4, 'Maureal', '2026-04-24', '11:30:00', 'COT', 'Brownbag', '2026-04-22 01:28:45'),
(5, 'Session Day', '2026-04-24', '11:00:00', 'CON', 'Brownbag', '2026-04-24 02:09:51');

-- --------------------------------------------------------

--
-- Table structure for table `event_participants`
--

CREATE TABLE `event_participants` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_participants`
--

INSERT INTO `event_participants` (`id`, `event_id`, `user_id`, `joined_at`) VALUES
(4, 2, 53, '2026-04-20 15:01:49'),
(6, 2, 46, '2026-04-22 00:32:03'),
(7, 4, 59, '2026-04-22 01:33:22'),
(8, 2, 66, '2026-04-24 11:20:28');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mental_health_tests`
--

CREATE TABLE `mental_health_tests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `answer_1` varchar(100) DEFAULT NULL,
  `answer_2` varchar(100) DEFAULT NULL,
  `result_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mental_health_tests`
--

INSERT INTO `mental_health_tests` (`id`, `user_id`, `answer_1`, `answer_2`, `result_text`, `created_at`) VALUES
(2, 6, 'Not at all', 'Not at all', 'Stress Level: Low | Score: 0/15 | You seem to be doing well. Keep up with healthy habits and self-care routines.', '2026-04-13 08:58:27'),
(8, 66, 'Nearly every day', 'Not at all', 'Stress Level: High | Score: 12/15 | We recommend scheduling an appointment with the guidance office for support.', '2026-04-24 11:16:46'),
(9, 66, 'More than half the days', 'Nearly every day', 'Stress Level: High | Score: 11/15 | We recommend scheduling an appointment with the guidance office for support.', '2026-04-24 11:21:41'),
(10, 66, 'More than half the days', 'Nearly every day', 'Stress Level: High | Score: 13/15 | We recommend scheduling an appointment with the guidance office for support.', '2026-04-24 11:25:12');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `email`, `token_hash`, `expires_at`, `used_at`, `created_at`) VALUES
(5, 64, 'frenzenrivera@buksu.edu.ph', '$2y$10$vkIdmq1NUI0XidpCXxDB7uzzyE8Z9oJ596o74oqUoFFQQo2Gzjr4a', '2026-04-24 14:30:51', NULL, '2026-04-24 20:15:51'),
(6, 44, '2201101665@buksu.edu.ph', '$2y$10$TYTHXzyqh2Ym9j.8PoXdquCisFqM.YznL02v2KBuiuqCRanHAEzyO', '2026-04-24 15:33:35', NULL, '2026-04-24 21:18:35'),
(8, 63, '2401101665@student.buksu.edu.ph', '$2y$10$/mQHLm.00rEfUZTQOF4V6OYB0ojevlx7FuVS8/RaudpkwaKrIWxPa', '2026-04-25 05:07:59', NULL, '2026-04-25 10:52:59'),
(9, 66, '2301110636@student.buksu.edu.ph', '$2y$10$v9rZAU0J6zLHmfn8y7bhi.RqmuK1QW6yZgUQl0D93ICeEisZcXIdu', '2026-04-25 05:12:40', NULL, '2026-04-25 10:57:40');

-- --------------------------------------------------------

--
-- Table structure for table `session_feedback`
--

CREATE TABLE `session_feedback` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `counselor_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `notes` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `session_feedback`
--

INSERT INTO `session_feedback` (`id`, `appointment_id`, `counselor_id`, `student_id`, `notes`, `created_at`, `updated_at`) VALUES
(1, 5, 44, 51, 'see you', '2026-04-20 04:48:13', '2026-04-20 04:48:13');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `college` varchar(150) DEFAULT NULL,
  `program` varchar(150) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Administrator','Counsellor','Facilitator','Student','Instructor') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `student_id`, `email`, `password`, `role`, `created_at`, `status`) VALUES
(44, 'Raul Lecaros', '22011', '2201101665@buksu.edu.ph', '$2y$10$RLp7fIl0uEf8mzp2lJhmIuru9KHdEa34mz.fA2zZz1iMEfLCf/0XC', 'Counsellor', '2026-04-16 12:19:13', 'Active'),
(48, 'Jp Pausal', '24022', '24022101665@buksu.edu.ph', '$2y$10$E3QshZFK84h53xH82BRMouP/N7rKQYd9HkUwRsjxwJ/DQTPDqaLnG', 'Instructor', '2026-04-16 13:52:55', 'Active'),
(51, 'Carlitos Simene', '451', '2145@student.buksu.edu.ph', '$2y$10$XxIKe9.YSdTpqr3OC90kDOk9TbycZOopjeCo3JUuSWePE/zjjiuU6', 'Student', '2026-04-20 03:42:50', 'Active'),
(56, 'Sen', '555', 'sen@buksu.edu.ph', '$2y$10$FqfVc0Dt7j73DJvpW0JAFemwWI2IEptRZN9HIdnyUh8BbHGOYQgi.', 'Instructor', '2026-04-20 16:27:00', 'Active'),
(63, 'FRENZEN RIVERA', 'GOOG-7D1D9EBF08', '2401101665@student.buksu.edu.ph', '$2y$10$8y2Iv9fHZsWvqzOJjyBmiepaG4hmmV1CYkhmji.uJ/06ooebiu77K', 'Student', '2026-04-24 02:06:07', 'Active'),
(64, 'Frenzen Rivera', '2002', 'frenzenrivera@buksu.edu.ph', '$2y$10$GZW0yFNWOpOZwEG5YCkxRe9OnVELx4Fa9Kh2tHZ23QS0UamUgxRkK', 'Administrator', '2026-04-24 02:07:49', 'Active'),
(66, 'Toff Darell Vergara', '2301110636', '2301110636@student.buksu.edu.ph', '$2y$10$4KQWW61r35BTqPS2AU7DuO0dG0g8ty0V.LqSsRXDmr.i78F8/qY9u', 'Student', '2026-04-24 11:15:26', 'Active'),
(67, 'Gil Cagande', '2301110636', 'gilcagande@buksu.edu.ph', '$2y$10$ToMSUIPKRlptl52QPHnbbuvN0jy8r1KLHfhCWJAPPfW6UPwhpT8Hu', 'Administrator', '2026-04-24 12:17:07', 'Active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_appointments_counselor` (`counselor_id`);

--
-- Indexes for table `counselor_availability`
--
ALTER TABLE `counselor_availability`
  ADD PRIMARY KEY (`id`),
  ADD KEY `counselor_id` (`counselor_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `event_participants`
--
ALTER TABLE `event_participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_event_user` (`event_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `mental_health_tests`
--
ALTER TABLE `mental_health_tests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_password_resets_email` (`email`),
  ADD KEY `idx_password_resets_expires_at` (`expires_at`);

--
-- Indexes for table `session_feedback`
--
ALTER TABLE `session_feedback`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `appointment_id` (`appointment_id`),
  ADD KEY `counselor_id` (`counselor_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `counselor_availability`
--
ALTER TABLE `counselor_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `event_participants`
--
ALTER TABLE `event_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mental_health_tests`
--
ALTER TABLE `mental_health_tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `session_feedback`
--
ALTER TABLE `session_feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_appointments_counselor` FOREIGN KEY (`counselor_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `counselor_availability`
--
ALTER TABLE `counselor_availability`
  ADD CONSTRAINT `counselor_availability_ibfk_1` FOREIGN KEY (`counselor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_participants`
--
ALTER TABLE `event_participants`
  ADD CONSTRAINT `event_participants_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_participants_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `mental_health_tests`
--
ALTER TABLE `mental_health_tests`
  ADD CONSTRAINT `mental_health_tests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `session_feedback`
--
ALTER TABLE `session_feedback`
  ADD CONSTRAINT `session_feedback_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `session_feedback_ibfk_2` FOREIGN KEY (`counselor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `session_feedback_ibfk_3` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
