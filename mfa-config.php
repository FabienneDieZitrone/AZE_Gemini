<?php
/**
 * MFA Configuration
 * Central configuration for Multi-Factor Authentication
 */

// MFA Settings
define('MFA_ENABLED', getenv('MFA_ENABLED') !== 'false'); // Default: true
define('MFA_ISSUER', getenv('MFA_ISSUER') ?: 'AZE Gemini');
define('MFA_ENCRYPTION_KEY', getenv('MFA_ENCRYPTION_KEY') ?: getenv('APP_KEY') ?: 'change-this-key-in-production');

// MFA Requirements
define('MFA_REQUIRED_ROLES', ['Admin', 'Bereichsleiter']); // Roles that require MFA
define('MFA_GRACE_PERIOD_DAYS', (int)(getenv('MFA_GRACE_PERIOD_DAYS') ?: 7)); // Days before MFA is enforced

// Security Settings
define('MFA_MAX_ATTEMPTS', (int)(getenv('MFA_MAX_ATTEMPTS') ?: 5)); // Max verification attempts
define('MFA_LOCKOUT_DURATION', (int)(getenv('MFA_LOCKOUT_DURATION') ?: 30)); // Lockout duration in minutes
define('MFA_CODE_LENGTH', 6); // TOTP code length
define('MFA_BACKUP_CODE_LENGTH', 8); // Backup code length
define('MFA_BACKUP_CODE_COUNT', 8); // Number of backup codes to generate

// TOTP Settings
define('MFA_TOTP_PERIOD', 30); // TOTP time period in seconds
define('MFA_TOTP_WINDOW', 1); // Time window tolerance (periods before/after)
define('MFA_TOTP_ALGORITHM', 'sha1'); // TOTP algorithm

// Trusted Device Settings
define('MFA_ALLOW_TRUSTED_DEVICES', getenv('MFA_ALLOW_TRUSTED_DEVICES') !== 'false'); // Default: true
define('MFA_TRUSTED_DEVICE_DAYS', (int)(getenv('MFA_TRUSTED_DEVICE_DAYS') ?: 30)); // Days to trust device

// Session Settings
define('MFA_SESSION_LIFETIME', (int)(getenv('MFA_SESSION_LIFETIME') ?: 3600)); // MFA session lifetime in seconds

// UI Settings
define('MFA_SHOW_QR_CODE', true); // Show QR code during setup
define('MFA_SHOW_MANUAL_KEY', true); // Show manual entry key
define('MFA_ALLOW_BACKUP_CODES', true); // Allow backup codes

// Audit Settings
define('MFA_AUDIT_ENABLED', true); // Enable MFA audit logging
define('MFA_AUDIT_RETENTION_DAYS', 90); // Days to keep audit logs

/**
 * Get MFA configuration array
 */
function get_mfa_config() {
    return [
        'enabled' => MFA_ENABLED,
        'issuer' => MFA_ISSUER,
        'required_roles' => MFA_REQUIRED_ROLES,
        'grace_period_days' => MFA_GRACE_PERIOD_DAYS,
        'max_attempts' => MFA_MAX_ATTEMPTS,
        'lockout_duration' => MFA_LOCKOUT_DURATION,
        'allow_trusted_devices' => MFA_ALLOW_TRUSTED_DEVICES,
        'trusted_device_days' => MFA_TRUSTED_DEVICE_DAYS,
        'show_qr_code' => MFA_SHOW_QR_CODE,
        'show_manual_key' => MFA_SHOW_MANUAL_KEY,
        'allow_backup_codes' => MFA_ALLOW_BACKUP_CODES
    ];
}

/**
 * Check if MFA is required for user role
 */
function is_mfa_required_for_role($role) {
    return in_array($role, MFA_REQUIRED_ROLES);
}

/**
 * Get MFA status message
 */
function get_mfa_status_message($enabled, $required, $in_grace_period) {
    if (!$required) {
        return 'MFA is optional for your account';
    }
    
    if ($enabled) {
        return 'MFA is enabled and active';
    }
    
    if ($in_grace_period) {
        return sprintf('MFA setup required within %d days', MFA_GRACE_PERIOD_DAYS);
    }
    
    return 'MFA setup is required for your account';
}
?>