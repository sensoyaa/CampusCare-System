-- User Preferences and Settings Tables Migration
-- Created: May 2026
-- Purpose: Store persistent user preferences including dark mode, notifications, session management, privacy, and security settings

-- User preferences table (general settings)
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

-- User sessions table (track active and historical sessions)
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

-- User notifications table (store actual notifications)
CREATE TABLE IF NOT EXISTS `user_notifications` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` int NOT NULL,
  `type` enum('appointment', 'event', 'system', 'security', 'message') DEFAULT 'system',
  `title` varchar(255) NOT NULL,
  `message` text,
  `related_id` int DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
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
  INDEX `idx_created_at` (`created_at`)
);

-- User activity log table (track user actions for security and session management)
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

-- Add columns to users table if they don't exist (for tracking purposes)
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `last_login_at` timestamp NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `last_login_ip` varchar(45) DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `login_attempt_count` int DEFAULT 0;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `locked_until` timestamp NULL;
