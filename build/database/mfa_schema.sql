-- MFA (Multi-Factor Authentication) Database Schema Extension
-- For AZE-Gemini Project - Issue #115
-- Version: 1.0
-- Date: 2025-08-06
-- Author: Claude Code
-- Description: Adds MFA support to existing users table and creates MFA settings table

-- Add MFA columns to existing users table
ALTER TABLE `users` 
ADD COLUMN `mfa_enabled` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Whether MFA is enabled for this user',
ADD COLUMN `mfa_secret` VARCHAR(255) NULL COMMENT 'Encrypted TOTP secret',
ADD COLUMN `mfa_secret_iv` VARCHAR(255) NULL COMMENT 'Initialization vector for secret encryption',
ADD COLUMN `mfa_backup_codes` TEXT NULL COMMENT 'Encrypted JSON array of backup codes',
ADD COLUMN `mfa_backup_codes_iv` VARCHAR(255) NULL COMMENT 'Initialization vector for backup codes',
ADD COLUMN `mfa_setup_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When MFA was first set up',
ADD COLUMN `mfa_last_used` TIMESTAMP NULL DEFAULT NULL COMMENT 'Last time MFA was successfully used';

-- Create MFA settings table for additional configuration
CREATE TABLE `user_mfa_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `method` enum('totp','sms','email') NOT NULL DEFAULT 'totp',
  `is_primary` BOOLEAN NOT NULL DEFAULT TRUE,
  `backup_codes_remaining` INT NOT NULL DEFAULT 8,
  `last_backup_code_used_at` TIMESTAMP NULL DEFAULT NULL,
  `failed_attempts` INT NOT NULL DEFAULT 0,
  `last_failed_attempt_at` TIMESTAMP NULL DEFAULT NULL,
  `locked_until` TIMESTAMP NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_method_unique` (`user_id`, `method`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create MFA audit log table for security tracking
CREATE TABLE `mfa_audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` enum('setup','verify_success','verify_fail','backup_code_used','disabled','reset') NOT NULL,
  `method_used` enum('totp','backup_code') NULL,
  `ip_address` varchar(45) NULL,
  `user_agent` TEXT NULL,
  `details` JSON NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id_action` (`user_id`, `action`),
  KEY `created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create index for performance optimization
CREATE INDEX `idx_users_mfa_enabled` ON `users` (`mfa_enabled`);
CREATE INDEX `idx_users_mfa_secret` ON `users` (`mfa_secret`);
CREATE INDEX `idx_mfa_settings_user_primary` ON `user_mfa_settings` (`user_id`, `is_primary`);

-- Add MFA configuration to global_settings
ALTER TABLE `global_settings`
ADD COLUMN `mfa_required_roles` JSON NULL DEFAULT NULL COMMENT 'Roles that require MFA',
ADD COLUMN `mfa_grace_period_hours` INT NOT NULL DEFAULT 24 COMMENT 'Hours before MFA is required for new users',
ADD COLUMN `mfa_backup_codes_count` INT NOT NULL DEFAULT 8 COMMENT 'Number of backup codes to generate',
ADD COLUMN `mfa_max_failed_attempts` INT NOT NULL DEFAULT 5 COMMENT 'Max failed MFA attempts before lockout',
ADD COLUMN `mfa_lockout_duration_minutes` INT NOT NULL DEFAULT 30 COMMENT 'Lockout duration in minutes';

-- Update global_settings with MFA defaults
UPDATE `global_settings` 
SET 
  `mfa_required_roles` = '["Admin", "Bereichsleiter"]',
  `mfa_grace_period_hours` = 24,
  `mfa_backup_codes_count` = 8,
  `mfa_max_failed_attempts` = 5,
  `mfa_lockout_duration_minutes` = 30
WHERE `id` = 1;

-- Create stored procedure for MFA cleanup (remove expired lockouts)
DELIMITER //
CREATE PROCEDURE CleanupExpiredMFALockouts()
BEGIN
    UPDATE user_mfa_settings 
    SET locked_until = NULL, failed_attempts = 0 
    WHERE locked_until IS NOT NULL AND locked_until < NOW();
END //
DELIMITER ;

COMMIT;