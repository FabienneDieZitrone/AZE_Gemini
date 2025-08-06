<?php
/**
 * MFA Configuration File
 * Issue #115: Multi-Factor Authentication Implementation
 * Date: 2025-08-06
 * Author: Claude Code
 */

// Prevent direct access
if (!defined('API_GUARD')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access not allowed');
}

class MFAConfig {
    /**
     * MFA Configuration settings
     */
    const SETTINGS = [
        // TOTP Settings
        'totp' => [
            'issuer' => 'AZE Zeiterfassung',
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
            'window' => 1, // Allow 1 time step in either direction for clock drift
        ],
        
        // Security Settings
        'security' => [
            'max_failed_attempts' => 5,
            'lockout_duration_minutes' => 30,
            'secret_length' => 32, // Base32 characters
            'backup_codes_count' => 8,
            'backup_code_length' => 8,
        ],
        
        // Encryption Settings
        'encryption' => [
            'method' => 'AES-256-CBC',
            'key_env_var' => 'MFA_ENCRYPTION_KEY',
            'fallback_key_env_var' => 'ENCRYPTION_KEY',
        ],
        
        // Rate Limiting
        'rate_limiting' => [
            'setup_attempts_per_hour' => 3,
            'verify_attempts_per_minute' => 10,
            'backup_access_per_hour' => 5,
        ],
        
        // Role-based Requirements
        'role_requirements' => [
            'required_roles' => ['Admin', 'Bereichsleiter'],
            'grace_period_hours' => 24, // Hours before MFA becomes mandatory
            'enforcement_levels' => [
                'optional' => ['Honorarkraft', 'Mitarbeiter'],
                'recommended' => ['Standortleiter'],
                'required' => ['Admin', 'Bereichsleiter']
            ]
        ],
        
        // UI Settings
        'ui' => [
            'qr_code_size' => 256,
            'qr_code_error_correction' => 'M',
            'session_timeout_minutes' => 480, // 8 hours
            'remember_device_days' => 30,
        ],
        
        // Audit Settings
        'audit' => [
            'log_all_attempts' => true,
            'log_successful_verifications' => true,
            'log_lockouts' => true,
            'log_backup_code_usage' => true,
            'retention_days' => 90,
        ]
    ];

    /**
     * Get MFA configuration
     */
    public static function get(string $key = null, $default = null) {
        if ($key === null) {
            return self::SETTINGS;
        }
        
        $keys = explode('.', $key);
        $value = self::SETTINGS;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }

    /**
     * Get encryption key from environment
     */
    public static function getEncryptionKey(): string {
        $primary_key = $_ENV[self::get('encryption.key_env_var')] ?? null;
        $fallback_key = $_ENV[self::get('encryption.fallback_key_env_var')] ?? null;
        
        $key = $primary_key ?: $fallback_key;
        
        if (!$key) {
            throw new Exception('MFA encryption key not configured. Please set ' . 
                               self::get('encryption.key_env_var') . ' or ' . 
                               self::get('encryption.fallback_key_env_var') . ' environment variable.');
        }
        
        return $key;
    }

    /**
     * Validate MFA configuration
     */
    public static function validate(): array {
        $errors = [];
        
        // Check encryption key
        try {
            self::getEncryptionKey();
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
        
        // Check required extensions
        if (!extension_loaded('openssl')) {
            $errors[] = 'OpenSSL extension is required for MFA encryption';
        }
        
        if (!function_exists('random_bytes')) {
            $errors[] = 'random_bytes function is required for secure random generation';
        }
        
        // Check hash_hmac support
        if (!function_exists('hash_hmac') || !in_array('sha1', hash_algos())) {
            $errors[] = 'HMAC-SHA1 support is required for TOTP generation';
        }
        
        return $errors;
    }

    /**
     * Get role-based MFA requirement
     */
    public static function getMFARequirement(string $role): string {
        $enforcement = self::get('role_requirements.enforcement_levels');
        
        foreach ($enforcement as $level => $roles) {
            if (in_array($role, $roles)) {
                return $level;
            }
        }
        
        return 'optional';
    }

    /**
     * Check if MFA is required for role
     */
    public static function isMFARequired(string $role): bool {
        return self::getMFARequirement($role) === 'required';
    }

    /**
     * Check if user is in grace period
     */
    public static function isInGracePeriod(string $created_at): bool {
        $grace_hours = self::get('role_requirements.grace_period_hours');
        $created_timestamp = strtotime($created_at);
        $grace_end = $created_timestamp + ($grace_hours * 3600);
        
        return time() < $grace_end;
    }
}

// Validate configuration on load
$mfa_errors = MFAConfig::validate();
if (!empty($mfa_errors)) {
    error_log('MFA Configuration errors: ' . implode(', ', $mfa_errors));
    if (defined('MFA_STRICT_MODE') && MFA_STRICT_MODE) {
        throw new Exception('MFA configuration is invalid: ' . implode(', ', $mfa_errors));
    }
}
?>