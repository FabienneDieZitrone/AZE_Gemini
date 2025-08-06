-- MFA Database Schema
-- Add MFA-related columns and tables to existing AZE Gemini database

-- Add MFA columns to users table
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `mfa_enabled` TINYINT(1) DEFAULT 0 COMMENT 'Whether MFA is enabled',
ADD COLUMN IF NOT EXISTS `mfa_secret` VARCHAR(255) DEFAULT NULL COMMENT 'Encrypted TOTP secret',
ADD COLUMN IF NOT EXISTS `mfa_backup_codes` TEXT DEFAULT NULL COMMENT 'Encrypted backup codes JSON',
ADD COLUMN IF NOT EXISTS `mfa_setup_completed` TINYINT(1) DEFAULT 0 COMMENT 'Whether initial setup is complete',
ADD COLUMN IF NOT EXISTS `mfa_enabled_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When MFA was enabled',
ADD COLUMN IF NOT EXISTS `mfa_last_used` TIMESTAMP NULL DEFAULT NULL COMMENT 'Last successful MFA verification',
ADD COLUMN IF NOT EXISTS `mfa_backup_codes_viewed` TINYINT(1) DEFAULT 0 COMMENT 'Whether user viewed backup codes',
ADD COLUMN IF NOT EXISTS `mfa_temp_secret` VARCHAR(255) DEFAULT NULL COMMENT 'Temporary secret during setup',
ADD COLUMN IF NOT EXISTS `mfa_temp_secret_created` TIMESTAMP NULL DEFAULT NULL COMMENT 'When temp secret was created',
ADD COLUMN IF NOT EXISTS `mfa_backup_codes_generated_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When backup codes were generated';

-- Create indexes for MFA columns
ALTER TABLE `users`
ADD INDEX IF NOT EXISTS `idx_mfa_enabled` (`mfa_enabled`),
ADD INDEX IF NOT EXISTS `idx_mfa_setup_completed` (`mfa_setup_completed`);

-- MFA Audit Log table
CREATE TABLE IF NOT EXISTS `mfa_audit_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `event_type` VARCHAR(50) NOT NULL COMMENT 'Event type: setup_started, setup_completed, verification_success, verification_failed, etc.',
  `details` TEXT DEFAULT NULL COMMENT 'Additional event details',
  `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP address of the event',
  `user_agent` TEXT DEFAULT NULL COMMENT 'User agent string',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_event_type` (`event_type`),
  INDEX `idx_created_at` (`created_at`),
  CONSTRAINT `fk_mfa_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MFA event audit log';

-- MFA Lockouts table
CREATE TABLE IF NOT EXISTS `mfa_lockouts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `locked_until` TIMESTAMP NOT NULL COMMENT 'When the lockout expires',
  `reason` VARCHAR(255) DEFAULT NULL COMMENT 'Reason for lockout',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_locked_until` (`locked_until`),
  CONSTRAINT `fk_mfa_lockout_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MFA account lockouts';

-- MFA Trusted Devices table
CREATE TABLE IF NOT EXISTS `mfa_trusted_devices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token` VARCHAR(64) NOT NULL COMMENT 'SHA256 hash of device token',
  `device_name` VARCHAR(255) DEFAULT NULL COMMENT 'Device identifier',
  `last_used` TIMESTAMP NULL DEFAULT NULL COMMENT 'Last time device was used',
  `expires_at` TIMESTAMP NOT NULL COMMENT 'When device trust expires',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_token` (`token`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_expires_at` (`expires_at`),
  CONSTRAINT `fk_mfa_device_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MFA trusted devices';

-- Clean up expired lockouts (can be run periodically)
DELIMITER $$
CREATE EVENT IF NOT EXISTS `cleanup_mfa_lockouts`
ON SCHEDULE EVERY 1 HOUR
DO
BEGIN
  DELETE FROM `mfa_lockouts` WHERE `locked_until` < NOW();
  DELETE FROM `mfa_trusted_devices` WHERE `expires_at` < NOW();
END$$
DELIMITER ;

-- Sample data for testing (REMOVE IN PRODUCTION)
-- This creates a test user with MFA enabled
-- Secret: JBSWY3DPEHPK3PXP (for Google Authenticator testing)
-- INSERT INTO `users` (`email`, `mfa_enabled`, `mfa_secret`, `mfa_setup_completed`) 
-- VALUES ('mfa-test@example.com', 1, 'encrypted_secret_here', 1);