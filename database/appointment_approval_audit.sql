-- Migration: appointment approval audit fields
-- Run: mysql -u root -p campuscare_db < appointment_approval_audit.sql

ALTER TABLE `appointments`
  ADD COLUMN `approved_by_user_id` int(11) DEFAULT NULL AFTER `status`,
  ADD COLUMN `approved_at` datetime DEFAULT NULL AFTER `approved_by_user_id`;
