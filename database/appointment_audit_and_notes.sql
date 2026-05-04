-- Migration: appointment audit and internal notes
-- Standard schema:
--   appointment_audit: appointment_id, user_id (nullable), action, metadata (JSON/text), created_at
--   appointment_notes: appointment_id, user_id (nullable), note, is_private, created_at
-- Run: mysql -u root -p campuscare_db < appointment_audit_and_notes.sql

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
