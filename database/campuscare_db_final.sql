-- CampusCare Optimized Database Schema (v2.0)
-- Date: May 4, 2026
-- Purpose: Fully normalized schema addressing redundancy and data integrity issues
-- Changes: 
--   - Removed denormalized name/email fields (use JOINs instead)
--   - Added status lookup tables for referrals and intake forms
--   - Created dedicated signatures table
--   - Added missing FK constraints
--   - Consolidated metadata storage patterns

CREATE DATABASE IF NOT EXISTS `campuscare_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `campuscare_db`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ============================================================================
-- CORE LOOKUP TABLES
-- ============================================================================

--
-- Table structure for table `account_statuses`
--
CREATE TABLE `account_statuses` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `display_name` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `account_statuses` (`id`, `code`, `display_name`) VALUES
(1, 'active', 'Active'),
(2, 'inactive', 'Inactive');

--
-- Table structure for table `roles`
--
CREATE TABLE `roles` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `code` varchar(30) NOT NULL,
  `display_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`id`, `code`, `display_name`) VALUES
(1, 'administrator', 'Administrator'),
(2, 'counselor', 'Counselor'),
(3, 'facilitator', 'Facilitator'),
(4, 'student', 'Student'),
(5, 'instructor', 'Instructor');

--
-- Table structure for table `appointment_statuses`
--
CREATE TABLE `appointment_statuses` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `display_name` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `appointment_statuses` (`id`, `code`, `display_name`) VALUES
(1, 'pending', 'Pending'),
(2, 'approved', 'Approved'),
(3, 'cancelled', 'Cancelled'),
(4, 'rejected', 'Rejected');

-- NEW: Referral Status Lookup Table (NORMALIZED)
CREATE TABLE `referral_statuses` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `code` varchar(30) NOT NULL UNIQUE,
  `display_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `referral_statuses` (`id`, `code`, `display_name`, `description`) VALUES
(1, 'pending', 'Pending', 'Referral submitted, awaiting counselor action'),
(2, 'received', 'Received', 'Counselor has received the referral'),
(3, 'in_progress', 'In Progress', 'Counselor is actively working with the student'),
(4, 'completed', 'Completed', 'Referral process completed'),
(5, 'cancelled', 'Cancelled', 'Referral was cancelled'),
(6, 'rejected', 'Rejected', 'Referral was rejected by counselor');

-- NEW: Intake Form Status Lookup Table (NORMALIZED)
CREATE TABLE `referral_intake_statuses` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `code` varchar(30) NOT NULL UNIQUE,
  `display_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `referral_intake_statuses` (`id`, `code`, `display_name`, `description`) VALUES
(1, 'pending', 'Pending', 'Intake form not yet started'),
(2, 'in_progress', 'In Progress', 'Student is filling out the form'),
(3, 'submitted', 'Submitted', 'Form submitted, awaiting counselor review'),
(4, 'approved', 'Approved', 'Counselor approved the intake'),
(5, 'needs_revision', 'Needs Revision', 'Counselor requested revisions'),
(6, 'rejected', 'Rejected', 'Form rejected, cannot proceed');

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

INSERT INTO `services` (`id`, `code`, `display_name`, `description`, `is_active`) VALUES
(1, 'counseling', 'Counseling', 'One-on-one counseling session', 1),
(2, 'psych-testing', 'Psychological Testing', 'Mental health and psychological test session', 1);

-- ============================================================================
-- CORE ENTITY TABLES
-- ============================================================================

--
-- Table structure for table `users`
--
CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `student_id` varchar(100) DEFAULT NULL COMMENT 'Institution-assigned ID (unique)',
  `student_number` varchar(50) DEFAULT NULL COMMENT 'Legacy field, deprecated in favor of student_id',
  `email` varchar(150) NOT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `college` varchar(150) DEFAULT NULL,
  `program` varchar(150) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` tinyint(3) UNSIGNED NOT NULL,
  `account_status_id` tinyint(3) UNSIGNED NOT NULL,
  `last_login_at` timestamp NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `login_attempt_count` int DEFAULT 0,
  `locked_until` timestamp NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `full_name`, `student_number`, `email`, `password_hash`, `role_id`, `account_status_id`, `created_at`, `updated_at`) VALUES
(44, 'Raul Lecaros', '22011', '2201101665@buksu.edu.ph', '$2y$10$RLp7fIl0uEf8mzp2lJhmIuru9KHdEa34mz.fA2zZz1iMEfLCf/0XC', 2, 1, '2026-04-16 20:19:13', '2026-04-16 20:19:13'),
(48, 'Jp Pausal', '24022', '24022101665@buksu.edu.ph', '$2y$10$E3QshZFK84h53xH82BRMouP/N7rKQYd9HkUwRsjxwJ/DQTPDqaLnG', 5, 1, '2026-04-16 21:52:55', '2026-04-16 21:52:55'),
(51, 'Carlitos Simene', '451', '2145@student.buksu.edu.ph', '$2y$10$XxIKe9.YSdTpqr3OC90kDOk9TbycZOopjeCo3JUuSWePE/zjjiuU6', 4, 1, '2026-04-20 11:42:50', '2026-04-20 11:42:50'),
(56, 'Sen', '555', 'sen@buksu.edu.ph', '$2y$10$FqfVc0Dt7j73DJvpW0JAFemwWI2IEptRZN9HIdnyUh8BbHGOYQgi.', 5, 1, '2026-04-21 00:27:00', '2026-04-21 00:27:00'),
(63, 'FRENZEN RIVERA', 'GOOG-7D1D9EBF08', '2401101665@student.buksu.edu.ph', '$2y$10$8y2Iv9fHZsWvqzOJjyBmiepaG4hmmV1CYkhmji.uJ/06ooebiu77K', 4, 1, '2026-04-24 10:06:07', '2026-04-24 10:06:07'),
(64, 'Frenzen Rivera', '2002', 'frenzenrivera@buksu.edu.ph', '$2y$10$GZW0yFNWOpOZwEG5YCkxRe9OnVELx4Fa9Kh2tHZ23QS0UamUgxRkK', 1, 1, '2026-04-24 10:07:49', '2026-04-24 10:07:49'),
(66, 'Toff Darell Vergara', NULL, '2301110636@student.buksu.edu.ph', '$2y$10$4KQWW61r35BTqPS2AU7DuO0dG0g8ty0V.LqSsRXDmr.i78F8/qY9u', 4, 1, '2026-04-24 19:15:26', '2026-04-24 19:15:26'),
(67, 'Gil Cagande', NULL, 'gilcagande@buksu.edu.ph', '$2y$10$ToMSUIPKRlptl52QPHnbbuvN0jy8r1KLHfhCWJAPPfW6UPwhpT8Hu', 1, 1, '2026-04-24 20:17:07', '2026-04-24 20:17:07');

-- ============================================================================
-- APPOINTMENT & COUNSELING TABLES
-- ============================================================================

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
  `approved_by_user_id` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `appointments` (`id`, `student_user_id`, `counselor_user_id`, `service_id`, `appointment_date`, `appointment_time`, `status_id`, `notes`, `created_at`, `updated_at`) VALUES
(12, 66, 44, 2, '2026-04-27', '10:00:00', 2, NULL, '2026-04-24 19:17:28', '2026-04-24 19:17:28'),
(13, 66, 44, 1, '2026-04-28', '12:00:00', 4, NULL, '2026-04-24 19:22:02', '2026-04-24 19:22:02'),
(14, 66, 44, 2, '2026-04-30', '10:00:00', 2, NULL, '2026-04-24 19:25:27', '2026-04-24 19:25:27'),
(15, 66, 44, 2, '2026-04-30', '12:00:00', 1, NULL, '2026-04-24 19:26:42', '2026-04-24 19:26:42'),
(16, 66, 44, 1, '2026-04-30', '11:00:00', 1, NULL, '2026-04-24 19:27:57', '2026-04-24 19:27:57'),
(17, 63, 44, 2, '2026-04-27', '10:00:00', 1, NULL, '2026-04-24 20:37:48', '2026-04-24 20:37:48'),
(18, 63, 44, 2, '2026-04-27', '09:00:00', 1, NULL, '2026-04-24 21:15:35', '2026-04-24 21:15:35');

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

--
-- Table structure for table `appointment_audit`
--
CREATE TABLE IF NOT EXISTS `appointment_audit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `metadata` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`appointment_id`),
  INDEX (`user_id`),
  CONSTRAINT `fk_appointment_audit_appointment`
    FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_appointment_audit_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `appointment_notes`
--
CREATE TABLE IF NOT EXISTS `appointment_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `note` text NOT NULL,
  `is_private` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`appointment_id`),
  INDEX (`user_id`),
  CONSTRAINT `fk_appointment_notes_appointment`
    FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_appointment_notes_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

INSERT INTO `counselor_availability` (`id`, `counselor_user_id`, `weekday`, `start_time`, `end_time`, `created_at`) VALUES
(1, 44, 1, '09:00:00', '11:00:00', '2026-04-20 13:54:14'),
(2, 44, 2, '08:00:00', '00:00:00', '2026-04-20 23:18:01'),
(3, 44, 4, '08:00:00', '12:00:00', '2026-04-22 08:48:10');

-- ============================================================================
-- EVENT & COMMUNITY TABLES
-- ============================================================================

--
-- Table structure for table `events`
--
CREATE TABLE `events` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT 'General',
  `location` varchar(150) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `max_participants` int(10) UNSIGNED DEFAULT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime DEFAULT NULL,
  `created_by_user_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `events` (`id`, `title`, `description`, `location`, `starts_at`, `ends_at`, `created_by_user_id`, `created_at`) VALUES
(2, 'Relapse', 'Wellness', 'Room 202', '2026-04-21 13:02:00', NULL, NULL, '2026-04-20 23:00:37'),
(4, 'Maureal', 'Brownbag', 'COT', '2026-04-24 11:30:00', NULL, NULL, '2026-04-22 09:28:45'),
(5, 'Session Day', 'Brownbag', 'CON', '2026-04-24 11:00:00', NULL, NULL, '2026-04-24 10:09:51');

--
-- Table structure for table `event_participants`
--
CREATE TABLE `event_participants` (
  `event_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `joined_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `event_participants` (`event_id`, `user_id`, `joined_at`) VALUES
(2, 66, '2026-04-24 19:20:28');

--
-- Table structure for table `event_checkins`
--
CREATE TABLE IF NOT EXISTS `event_checkins` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `checked_in_at` datetime NOT NULL DEFAULT current_timestamp(),
  `location` varchar(255) DEFAULT NULL COMMENT 'GPS location or room name',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_event_user_checkin` (`event_id`, `user_id`),
  KEY `idx_event_id` (`event_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `event_feedback`
--
CREATE TABLE IF NOT EXISTS `event_feedback` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `rating` int(1) NOT NULL COMMENT '1-5 star rating',
  `feedback` text NOT NULL,
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_event_user_feedback` (`event_id`, `user_id`),
  KEY `idx_event_id` (`event_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_rating` (`rating`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `event_comments`
--
CREATE TABLE IF NOT EXISTS `event_comments` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `comment` text NOT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'For nested comments',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_event_id` (`event_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_event_comments_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_event_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_event_comments_parent` FOREIGN KEY (`parent_id`) REFERENCES `event_comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MENTAL HEALTH & ASSESSMENT TABLES
-- ============================================================================

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

INSERT INTO `mental_health_tests` (`id`, `code`, `title`, `description`, `is_active`, `created_at`) VALUES
(1, 'stress-screen-v1', 'Stress Screening', 'Basic stress-level assessment for students.', 1, '2026-04-25 11:19:11');

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

INSERT INTO `mental_health_test_questions` (`id`, `test_id`, `question_text`, `display_order`, `created_at`) VALUES
(1, 1, 'Over the last two weeks, how often have you felt nervous or stressed?', 1, '2026-04-25 11:19:11'),
(2, 1, 'Over the last two weeks, how often did stress affect your daily activities?', 2, '2026-04-25 11:19:11');

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

INSERT INTO `mental_health_test_attempts` (`id`, `test_id`, `user_id`, `result_score`, `result_level`, `result_text`, `created_at`) VALUES
(8, 1, 66, NULL, NULL, 'Stress Level: High | Score: 12/15 | We recommend scheduling an appointment with the guidance office for support.', '2026-04-24 19:16:46'),
(9, 1, 66, NULL, NULL, 'Stress Level: High | Score: 11/15 | We recommend scheduling an appointment with the guidance office for support.', '2026-04-24 19:21:41'),
(10, 1, 66, NULL, NULL, 'Stress Level: High | Score: 13/15 | We recommend scheduling an appointment with the guidance office for support.', '2026-04-24 19:25:12');

--
-- Table structure for table `mental_health_test_answers`
--
CREATE TABLE `mental_health_test_answers` (
  `attempt_id` int(10) UNSIGNED NOT NULL,
  `question_id` int(10) UNSIGNED NOT NULL,
  `answer_value` varchar(120) NOT NULL,
  `answer_score` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `mental_health_test_answers` (`attempt_id`, `question_id`, `answer_value`, `answer_score`) VALUES
(8, 1, 'Nearly every day', NULL),
(8, 2, 'Not at all', NULL),
(9, 1, 'More than half the days', NULL),
(9, 2, 'Nearly every day', NULL),
(10, 1, 'More than half the days', NULL),
(10, 2, 'Nearly every day', NULL);

-- ============================================================================
-- REFERRAL & INTAKE SYSTEM (NORMALIZED v2.0)
-- ============================================================================

--
-- Table structure for table `referral_forms` (OPTIMIZED - NO DENORMALIZATION)
--
CREATE TABLE IF NOT EXISTS `referral_forms` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `submitted_by_user_id` INT UNSIGNED NOT NULL COMMENT 'FK: users.id (faculty/instructor)',
  `referred_to_counselor_id` INT UNSIGNED DEFAULT NULL COMMENT 'FK: users.id (counselor)',
  `student_user_id` INT UNSIGNED DEFAULT NULL COMMENT 'FK: users.id (student) - NULL if external student',
  `external_student_name` VARCHAR(160) DEFAULT NULL COMMENT 'For non-enrolled students',
  `external_student_email` VARCHAR(160) DEFAULT NULL COMMENT 'For external student contact',
  `course_year_section` VARCHAR(160) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `date_received` DATE DEFAULT NULL,
  `received_by_user_id` INT UNSIGNED DEFAULT NULL COMMENT 'FK: users.id (counselor who received)',
  `reasons_json` LONGTEXT NOT NULL COMMENT 'JSON array of referral reasons',
  `other_reason` TEXT DEFAULT NULL,
  `actions_taken` TEXT DEFAULT NULL,
  `actions_datetime` DATETIME DEFAULT NULL,
  `intake_form_id` INT UNSIGNED DEFAULT NULL COMMENT 'FK: referral_intake_forms.id (if completed)',
  `status_id` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'FK: referral_statuses.id',
  `email_notification_sent` BOOLEAN DEFAULT FALSE,
  `email_notification_date` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_referral_submitter` (`submitted_by_user_id`),
  KEY `idx_referral_student` (`student_user_id`),
  KEY `idx_referral_status` (`status_id`),
  KEY `idx_referral_counselor` (`referred_to_counselor_id`),
  KEY `idx_referral_external` (`external_student_email`),
  KEY `idx_referral_created` (`created_at`),
  CONSTRAINT `fk_referral_submitter` FOREIGN KEY (`submitted_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_referral_student` FOREIGN KEY (`student_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_referral_counselor` FOREIGN KEY (`referred_to_counselor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_referral_received_by` FOREIGN KEY (`received_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_referral_status` FOREIGN KEY (`status_id`) REFERENCES `referral_statuses` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- NEW: Dedicated Signatures Table (NORMALIZED)
CREATE TABLE IF NOT EXISTS `referral_signatures` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `referral_form_id` INT UNSIGNED NOT NULL,
  `signature_type` ENUM('faculty', 'counselor') NOT NULL COMMENT 'Who signed',
  `signature_format` ENUM('typed', 'drawn') NOT NULL COMMENT 'How it was signed',
  `signature_data` LONGTEXT NOT NULL COMMENT 'Base64 for drawn, text for typed',
  `signer_user_id` INT UNSIGNED DEFAULT NULL COMMENT 'FK: users.id (who signed)',
  `signer_name` VARCHAR(160) NOT NULL COMMENT 'Name as signed (may differ from user profile)',
  `signed_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_referral_form` (`referral_form_id`),
  KEY `idx_signature_type` (`signature_type`),
  KEY `idx_signer` (`signer_user_id`),
  CONSTRAINT `fk_referral_signature_form` FOREIGN KEY (`referral_form_id`) REFERENCES `referral_forms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_referral_signature_signer` FOREIGN KEY (`signer_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `referral_intake_forms` (OPTIMIZED - NO DENORMALIZATION)
--
CREATE TABLE IF NOT EXISTS `referral_intake_forms` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `referral_id` INT UNSIGNED NOT NULL COMMENT 'FK: referral_forms.id',
  `student_user_id` INT UNSIGNED DEFAULT NULL COMMENT 'FK: users.id (student)',
  `intake_datetime` DATETIME NOT NULL,
  
  -- Pre-counseling questions
  `why_visiting` TEXT DEFAULT NULL,
  `what_concerns` TEXT DEFAULT NULL,
  `how_long` VARCHAR(160) DEFAULT NULL,
  `previous_counseling` BOOLEAN DEFAULT FALSE,
  `emergency_contact` VARCHAR(160) DEFAULT NULL,
  
  -- Status tracking
  `completed_by_student` BOOLEAN DEFAULT FALSE,
  `reviewed_by_counselor_id` INT UNSIGNED DEFAULT NULL COMMENT 'FK: users.id',
  `counselor_notes` TEXT DEFAULT NULL,
  `counselor_approved` BOOLEAN DEFAULT FALSE,
  `approval_datetime` DATETIME DEFAULT NULL,
  
  `status_id` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'FK: referral_intake_statuses.id',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_intake_referral` (`referral_id`),
  KEY `idx_intake_student` (`student_user_id`),
  KEY `idx_intake_status` (`status_id`),
  KEY `idx_intake_reviewed_by` (`reviewed_by_counselor_id`),
  KEY `idx_intake_created` (`created_at`),
  
  CONSTRAINT `fk_intake_referral` FOREIGN KEY (`referral_id`) REFERENCES `referral_forms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_intake_student` FOREIGN KEY (`student_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_intake_reviewed_by` FOREIGN KEY (`reviewed_by_counselor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_intake_status` FOREIGN KEY (`status_id`) REFERENCES `referral_intake_statuses` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- SECURITY & AUTHENTICATION TABLES
-- ============================================================================

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

INSERT INTO `password_reset_tokens` (`id`, `user_id`, `token_hash`, `expires_at`, `used_at`, `created_at`) VALUES
(1, 64, '$2y$10$vkIdmq1NUI0XidpCXxDB7uzzyE8Z9oJ596o74oqUoFFQQo2Gzjr4a', '2026-04-24 14:30:51', NULL, '2026-04-24 20:15:51'),
(2, 44, '$2y$10$TYTHXzyqh2Ym9j.8PoXdquCisFqM.YznL02v2KBuiuqCRanHAEzyO', '2026-04-24 15:33:35', NULL, '2026-04-24 21:18:35'),
(3, 63, '$2y$10$/mQHLm.00rEfUZTQOF4V6OYB0ojevlx7FuVS8/RaudpkwaKrIWxPa', '2026-04-25 05:07:59', NULL, '2026-04-25 10:52:59'),
(4, 66, '$2y$10$v9rZAU0J6zLHmfn8y7bhi.RqmuK1QW6yZgUQl0D93ICeEisZcXIdu', '2026-04-25 05:12:40', NULL, '2026-04-25 10:57:40');

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

-- ============================================================================
-- USER PREFERENCES & ACTIVITY TABLES
-- ============================================================================

--
-- Table structure for table `user_preferences`
--
CREATE TABLE IF NOT EXISTS `user_preferences` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` int NOT NULL UNIQUE,
  `dark_mode_enabled` tinyint(1) DEFAULT 0,
  `notifications_enabled` tinyint(1) DEFAULT 1,
  `notifications_in_app` tinyint(1) DEFAULT 1,
  `notifications_email` tinyint(1) DEFAULT 0,
  `notify_appointments` tinyint(1) DEFAULT 1,
  `notify_events` tinyint(1) DEFAULT 1,
  `notify_system` tinyint(1) DEFAULT 1,
  `notification_timing` varchar(10) DEFAULT '24h',
  `privacy_profile_visible` tinyint(1) DEFAULT 1,
  `privacy_data_sharing` tinyint(1) DEFAULT 0,
  `session_idle_timeout_minutes` int DEFAULT 60,
  `trusted_browser_enabled` tinyint(1) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_user_id` (`user_id`)
);

--
-- Table structure for table `user_sessions`
--
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` int NOT NULL,
  `session_id` varchar(255) NOT NULL UNIQUE,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `device_name` varchar(255) DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `browser` varchar(100) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL,
  `is_trusted` tinyint(1) DEFAULT 0,
  `last_activity` timestamp DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL,
  `logged_out_at` timestamp NULL,
  `status` enum('active', 'expired', 'logged_out', 'forced_logout') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_session_id` (`session_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created_at` (`created_at`)
);

--
-- Table structure for table `user_notifications`
--
CREATE TABLE IF NOT EXISTS `user_notifications` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` int NOT NULL,
  `type` enum('appointment', 'event', 'system', 'security', 'message') DEFAULT 'system',
  `title` varchar(255) NOT NULL,
  `message` text,
  `related_id` int DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `event_key` varchar(190) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `is_archived` tinyint(1) DEFAULT 0,
  `action_url` varchar(255) DEFAULT NULL,
  `read_at` timestamp NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_type` (`type`),
  INDEX `idx_is_read` (`is_read`),
  INDEX `idx_created_at` (`created_at`),
  UNIQUE KEY `idx_user_event_key` (`user_id`, `event_key`)
);

--
-- Table structure for table `user_activity_log`
--
CREATE TABLE IF NOT EXISTS `user_activity_log` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` int NOT NULL,
  `action_type` varchar(50) DEFAULT NULL,
  `action_description` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `status` enum('success', 'failed', 'suspicious') DEFAULT 'success',
  `metadata` json DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_action_type` (`action_type`),
  INDEX `idx_created_at` (`created_at`)
);

--
-- Table structure for table `user_feedback`
--
CREATE TABLE `user_feedback` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- INDEXES FOR OPTIMIZATION
-- ============================================================================

ALTER TABLE `account_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD UNIQUE KEY `display_name` (`display_name`);

ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD UNIQUE KEY `display_name` (`display_name`);

ALTER TABLE `appointment_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD UNIQUE KEY `display_name` (`display_name`);

ALTER TABLE `referral_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

ALTER TABLE `referral_intake_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD UNIQUE KEY `display_name` (`display_name`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD UNIQUE KEY `uq_users_student_id` (`student_id`),
  ADD UNIQUE KEY `uq_users_student_number` (`student_number`),
  ADD KEY `idx_users_role_id` (`role_id`),
  ADD KEY `idx_users_account_status_id` (`account_status_id`);

ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_appointments_student` (`student_user_id`),
  ADD KEY `idx_appointments_counselor` (`counselor_user_id`),
  ADD KEY `idx_appointments_service` (`service_id`),
  ADD KEY `idx_appointments_status` (`status_id`),
  ADD KEY `idx_appointments_date_time` (`appointment_date`,`appointment_time`),
  ADD KEY `idx_appointments_approved_by` (`approved_by_user_id`);

ALTER TABLE `appointment_feedback`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_appointment_feedback_appointment` (`appointment_id`),
  ADD KEY `idx_appointment_feedback_author` (`author_user_id`);

ALTER TABLE `counselor_availability`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_counselor_timeslot` (`counselor_user_id`,`weekday`,`start_time`,`end_time`),
  ADD KEY `idx_counselor_availability_counselor` (`counselor_user_id`);

ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_events_starts_at` (`starts_at`),
  ADD KEY `idx_events_created_by` (`created_by_user_id`),
  ADD KEY `idx_events_category` (`category`);

ALTER TABLE `event_participants`
  ADD PRIMARY KEY (`event_id`,`user_id`),
  ADD KEY `idx_event_participants_user` (`user_id`);

ALTER TABLE `event_checkins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_event_user_checkin` (`event_id`, `user_id`),
  ADD KEY `idx_event_id` (`event_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD CONSTRAINT `fk_event_checkins_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_event_checkins_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `event_feedback`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_event_user_feedback` (`event_id`, `user_id`),
  ADD KEY `idx_event_id` (`event_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_rating` (`rating`),
  ADD CONSTRAINT `fk_event_feedback_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_event_feedback_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `mental_health_tests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

ALTER TABLE `mental_health_test_questions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_mhtq_test_order` (`test_id`,`display_order`),
  ADD CONSTRAINT `fk_mhtq_test_id` FOREIGN KEY (`test_id`) REFERENCES `mental_health_tests` (`id`) ON DELETE CASCADE;

ALTER TABLE `mental_health_test_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mhta_test_id` (`test_id`),
  ADD KEY `idx_mhta_user_id` (`user_id`),
  ADD CONSTRAINT `fk_mhta_test_id` FOREIGN KEY (`test_id`) REFERENCES `mental_health_tests` (`id`),
  ADD CONSTRAINT `fk_mhta_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `mental_health_test_answers`
  ADD PRIMARY KEY (`attempt_id`,`question_id`),
  ADD KEY `fk_mhta_question_id` (`question_id`),
  ADD CONSTRAINT `fk_mhta_attempt_id` FOREIGN KEY (`attempt_id`) REFERENCES `mental_health_test_attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mhta_question_id` FOREIGN KEY (`question_id`) REFERENCES `mental_health_test_questions` (`id`) ON DELETE CASCADE;

ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_password_reset_tokens_user` (`user_id`),
  ADD KEY `idx_password_reset_tokens_expires_at` (`expires_at`),
  ADD CONSTRAINT `fk_password_reset_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `registration_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_registration_verifications_email` (`email`),
  ADD KEY `idx_registration_verifications_expires_at` (`expires_at`),
  ADD KEY `idx_registration_verifications_role_id` (`role_id`),
  ADD CONSTRAINT `fk_registration_verifications_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

ALTER TABLE `user_feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_feedback_user` (`user_id`),
  ADD CONSTRAINT `fk_user_feedback_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- ============================================================================
-- AUTO_INCREMENT SEQUENCES
-- ============================================================================

ALTER TABLE `account_statuses` MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
ALTER TABLE `roles` MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
ALTER TABLE `appointment_statuses` MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
ALTER TABLE `referral_statuses` MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
ALTER TABLE `referral_intake_statuses` MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
ALTER TABLE `services` MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
ALTER TABLE `users` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;
ALTER TABLE `appointments` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;
ALTER TABLE `appointment_feedback` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `counselor_availability` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `events` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
ALTER TABLE `mental_health_tests` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
ALTER TABLE `mental_health_test_questions` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
ALTER TABLE `mental_health_test_attempts` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
ALTER TABLE `password_reset_tokens` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
ALTER TABLE `registration_verifications` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `user_feedback` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

-- ============================================================================
-- FOREIGN KEY CONSTRAINTS
-- ============================================================================

ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  ADD CONSTRAINT `fk_users_account_status_id` FOREIGN KEY (`account_status_id`) REFERENCES `account_statuses` (`id`);

ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appointments_student` FOREIGN KEY (`student_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_appointments_counselor` FOREIGN KEY (`counselor_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_appointments_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  ADD CONSTRAINT `fk_appointments_status` FOREIGN KEY (`status_id`) REFERENCES `appointment_statuses` (`id`),
  ADD CONSTRAINT `fk_appointments_approved_by` FOREIGN KEY (`approved_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `appointment_feedback`
  ADD CONSTRAINT `fk_appointment_feedback_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_appointment_feedback_author` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `counselor_availability`
  ADD CONSTRAINT `fk_counselor_availability_counselor` FOREIGN KEY (`counselor_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `events`
  ADD CONSTRAINT `fk_events_created_by` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `event_participants`
  ADD CONSTRAINT `fk_event_participants_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_event_participants_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- ============================================================================
-- OPTIMIZATION NOTES & MIGRATION GUIDE
-- ============================================================================

/*
NORMALIZATION IMPROVEMENTS IN THIS VERSION:
============================================================================

1. REMOVED DENORMALIZATION FROM referral_forms:
   - Removed: submitted_by_name, submitted_by_role (fetch from users table via JOIN)
   - Removed: referred_to_counselor_name (fetch from users table via JOIN)
   - Removed: student_name, student_email for internal students (fetch from users table via JOIN)
   - Kept: external_student_name, external_student_email (for non-enrolled students)
   - Benefit: Single source of truth; updates to user names instantly reflected

2. REMOVED DENORMALIZATION FROM referral_intake_forms:
   - Removed: student_name, student_email (fetch from users table via JOIN)
   - Removed: reviewed_by_counselor_name (fetch from users table via JOIN)
   - Benefit: Automatic consistency with users table

3. CREATED referral_signatures TABLE:
   - Replaces 4 denormalized columns in referral_forms
   - Tables: faculty_signature_typed, faculty_signature_drawn, counselor_signature_typed, counselor_signature_drawn
   - New Structure: referral_signatures (referral_form_id, signature_type, signature_format, signature_data, signed_at)
   - Benefit: Audit trail, multiple signatures, signature history

4. ADDED STATUS LOOKUP TABLES:
   - referral_statuses: Replaces hardcoded 'Pending', 'In Progress', etc.
   - referral_intake_statuses: Replaces hardcoded status strings
   - Benefit: Consistent status values, easier reporting and validation

5. IMPROVED CONSTRAINTS:
   - Added FK on referral_forms.status_id → referral_statuses.id
   - Added FK on referral_intake_forms.status_id → referral_intake_statuses.id
   - Added FK on event_comments.parent_id → event_comments.id (prevents orphan comments)
   - Added missing FK on appointments.approved_by_user_id

6. METADATA NORMALIZATION:
   - referral_forms.reasons_json remains JSON for flexibility (array of reason codes)
   - consolidated appointment_audit and appointment_notes with proper structure

MIGRATION FROM OLD SCHEMA TO OPTIMIZED:
============================================================================

Step 1: Create new tables
   - referral_signatures
   - referral_statuses (with data migration)
   - referral_intake_statuses (with data migration)

Step 2: Migrate referral_forms data
   - INSERT INTO referral_signatures (SELECT from old columns)
   - ALTER TABLE referral_forms DROP columns (faculty_signature_typed, etc.)
   - ALTER TABLE referral_forms ADD status_id with default value
   - UPDATE referral_forms SET status_id = (SELECT id FROM referral_statuses WHERE code = LOWER(old_status))

Step 3: Migrate referral_intake_forms data
   - UPDATE referral_intake_forms SET status_id = (SELECT id FROM referral_intake_statuses WHERE code = LOWER(old_status))

Step 4: Update application queries
   - Replace raw status strings with FK lookups
   - Use JOIN to get user names instead of stored denormalized values
   - Update signature storage to use new referral_signatures table

QUERY EXAMPLES (OPTIMIZED JOINS):
============================================================================

Get referral with all related data:
   SELECT 
       rf.id, rf.course_year_section, rf.description,
       u1.full_name as submitted_by_name, u1.email as submitted_by_email,
       u2.full_name as counselor_name, u2.email as counselor_email,
       u3.full_name as student_name, u3.email as student_email,
       rs.display_name as status_name
   FROM referral_forms rf
   LEFT JOIN users u1 ON rf.submitted_by_user_id = u1.id
   LEFT JOIN users u2 ON rf.referred_to_counselor_id = u2.id
   LEFT JOIN users u3 ON rf.student_user_id = u3.id
   LEFT JOIN referral_statuses rs ON rf.status_id = rs.id
   WHERE rf.id = ?;

Get signatures for a referral:
   SELECT signature_type, signature_format, signed_at, signer_name
   FROM referral_signatures
   WHERE referral_form_id = ?
   ORDER BY signed_at DESC;

Get intake form with counselor info:
   SELECT 
       rif.id, rif.why_visiting, rif.what_concerns,
       u1.full_name as student_name, u1.email,
       u2.full_name as counselor_name,
       ris.display_name as status_name
   FROM referral_intake_forms rif
   LEFT JOIN users u1 ON rif.student_user_id = u1.id
   LEFT JOIN users u2 ON rif.reviewed_by_counselor_id = u2.id
   LEFT JOIN referral_intake_statuses ris ON rif.status_id = ris.id
   WHERE rif.id = ?;

PERFORMANCE BENEFITS:
============================================================================
- Reduced data redundancy: ~30% storage savings for referral forms
- Improved query performance: Fewer columns to fetch, better index usage
- Data consistency: Automatic updates to names when users update profiles
- Audit trail: Signature history preserved separately
- Scalability: Easier to extend (e.g., multiple signings, approval chains)

DATABASE SIZE ESTIMATE:
============================================================================
Before: ~40 KB (estimated)
After:  ~28 KB (estimated)
Savings: ~30% (from removing denormalized name/email columns)
*/
