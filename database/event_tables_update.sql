-- Event Check-ins Table
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

-- Event Feedback Table
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

-- Event Comments Table
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
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update events table to add category column if it doesn't exist
ALTER TABLE `events` 
ADD COLUMN IF NOT EXISTS `category` varchar(50) DEFAULT 'General' AFTER `description`,
ADD COLUMN IF NOT EXISTS `image_url` varchar(255) DEFAULT NULL AFTER `category`,
ADD COLUMN IF NOT EXISTS `max_participants` int(10) UNSIGNED DEFAULT NULL AFTER `image_url`,
ADD INDEX IF NOT EXISTS `idx_category` (`category`),
ADD INDEX IF NOT EXISTS `idx_starts_at` (`starts_at`);
