<?php
/**
 * MFA Verification API Endpoint
 * Issue #115: Multi-Factor Authentication Implementation
 * Version: 2.0 - Complete Production Implementation
 * Date: 2025-08-06
 * Author: Claude Code
 * 
 * This endpoint handles TOTP verification, backup code verification, and MFA activation
 */

// Define API guard constant
define('API_GUARD', true);

require_once __DIR__ . '/security-headers.php';
require_once __DIR__ . '/error-handler.php';
require_once __DIR__ . '/structured-logger.php';
require_once __DIR__ . '/security-middleware.php';
require_once __DIR__ . '/rate-limiting.php';
require_once __DIR__ . '/csrf-middleware.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/validation.php';

// Initialize security
initializeSecurity(true); // Require authentication
validateRequestMethod('POST');

// Apply security headers
initSecurityMiddleware();

// Apply rate limiting for MFA verification
checkRateLimit('mfa_verify');

// Validate CSRF protection
if (!validateCsrfProtection()) {
    exit();
}

initialize_api();

// Get authenticated user
$session_user = verify_session_and_get_user();
if (!$session_user) {
    send_response(401, ['message' => 'Authentication required']);
    exit();
}

// Parse request body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    send_response(400, ['message' => 'Invalid JSON in request body']);
    exit();
}

// Validate input
$user_id = InputValidator::sanitizeInt($input['userId'] ?? 0);
$code = InputValidator::sanitizeString($input['code'] ?? '');
$use_backup = filter_var($input['useBackup'] ?? false, FILTER_VALIDATE_BOOLEAN);
$is_setup_verification = filter_var($input['isSetupVerification'] ?? false, FILTER_VALIDATE_BOOLEAN);

// Authorization check
if ($user_id !== (int)$session_user['id']) {
    send_response(403, ['message' => 'Not authorized to verify MFA for this user']);
    exit();
}

// Validate code format
if (empty($code)) {
    send_response(400, ['message' => 'Verification code is required']);
    exit();
}

if ($use_backup && !preg_match('/^[A-F0-9]{8}$/', strtoupper($code))) {
    send_response(400, ['message' => 'Invalid backup code format']);
    exit();
}

if (!$use_backup && !preg_match('/^\d{6}$/', $code)) {
    send_response(400, ['message' => 'TOTP code must be 6 digits']);
    exit();
}

try {
    $conn = get_db_connection();
    $conn->begin_transaction();

    // Get user MFA data and settings
    $user_stmt = $conn->prepare("
        SELECT u.id, u.mfa_enabled, u.mfa_secret, u.mfa_secret_iv, 
               u.mfa_backup_codes, u.mfa_backup_codes_iv, u.username, u.display_name, u.role,
               s.failed_attempts, s.locked_until, s.backup_codes_remaining
        FROM users u
        LEFT JOIN user_mfa_settings s ON u.id = s.user_id AND s.method = 'totp'
        WHERE u.id = ?
    ");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_data = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();

    if (!$user_data) {
        throw new Exception('User not found');
    }

    // Check if user is locked out
    if ($user_data['locked_until'] && strtotime($user_data['locked_until']) > time()) {
        $remaining_minutes = ceil((strtotime($user_data['locked_until']) - time()) / 60);
        send_response(429, [
            'message' => "Account temporarily locked due to too many failed attempts. Try again in {$remaining_minutes} minutes.",
            'locked_until' => $user_data['locked_until']
        ]);
        exit();
    }

    // Check if MFA is set up
    if (!$user_data['mfa_secret']) {
        send_response(400, ['message' => 'MFA is not set up for this user']);
        exit();
    }

    // Get MFA configuration
    $settings_stmt = $conn->prepare("
        SELECT mfa_max_failed_attempts, mfa_lockout_duration_minutes 
        FROM global_settings WHERE id = 1
    ");
    $settings_stmt->execute();
    $mfa_config = $settings_stmt->get_result()->fetch_assoc();
    $settings_stmt->close();

    $max_attempts = $mfa_config['mfa_max_failed_attempts'] ?? 5;
    $lockout_duration = $mfa_config['mfa_lockout_duration_minutes'] ?? 30;

    $is_valid = false;
    $method_used = null;

    // Decrypt stored data
    $encryption_key = getEncryptionKey();

    if ($use_backup) {
        // Verify backup code
        if ($user_data['backup_codes_remaining'] <= 0) {
            send_response(400, ['message' => 'No backup codes remaining']);
            exit();
        }

        $encrypted_backup_codes = base64_decode($user_data['mfa_backup_codes']);
        $backup_iv = base64_decode($user_data['mfa_backup_codes_iv']);
        $backup_codes_json = openssl_decrypt($encrypted_backup_codes, 'AES-256-CBC', $encryption_key, 0, $backup_iv);
        
        if ($backup_codes_json === false) {
            throw new Exception('Failed to decrypt backup codes');
        }

        $backup_codes = json_decode($backup_codes_json, true);
        $code_upper = strtoupper($code);

        if (in_array($code_upper, $backup_codes)) {
            $is_valid = true;
            $method_used = 'backup_code';

            // Remove used backup code
            $backup_codes = array_values(array_filter($backup_codes, function($c) use ($code_upper) {
                return $c !== $code_upper;
            }));

            // Re-encrypt remaining codes
            $new_backup_iv = random_bytes(16);
            $new_encrypted_backup_codes = openssl_encrypt(
                json_encode($backup_codes), 
                'AES-256-CBC', 
                $encryption_key, 
                0, 
                $new_backup_iv
            );

            // Update database
            $update_backup_stmt = $conn->prepare("
                UPDATE users 
                SET mfa_backup_codes = ?, mfa_backup_codes_iv = ?, mfa_last_used = NOW()
                WHERE id = ?
            ");
            $update_backup_stmt->bind_param(
                "ssi", 
                base64_encode($new_encrypted_backup_codes),
                base64_encode($new_backup_iv),
                $user_id
            );
            $update_backup_stmt->execute();
            $update_backup_stmt->close();

            // Update backup codes count
            $update_count_stmt = $conn->prepare("
                UPDATE user_mfa_settings 
                SET backup_codes_remaining = backup_codes_remaining - 1,
                    last_backup_code_used_at = NOW()
                WHERE user_id = ? AND method = 'totp'
            ");
            $update_count_stmt->bind_param("i", $user_id);
            $update_count_stmt->execute();
            $update_count_stmt->close();
        }
    } else {
        // Verify TOTP code
        $encrypted_secret = base64_decode($user_data['mfa_secret']);
        $secret_iv = base64_decode($user_data['mfa_secret_iv']);
        $secret = openssl_decrypt($encrypted_secret, 'AES-256-CBC', $encryption_key, 0, $secret_iv);

        if ($secret === false) {
            throw new Exception('Failed to decrypt TOTP secret');
        }

        if (verifyTOTP($secret, $code)) {
            $is_valid = true;
            $method_used = 'totp';

            // Update last used timestamp
            $update_used_stmt = $conn->prepare("
                UPDATE users SET mfa_last_used = NOW() WHERE id = ?
            ");
            $update_used_stmt->bind_param("i", $user_id);
            $update_used_stmt->execute();
            $update_used_stmt->close();
        }
    }

    if ($is_valid) {
        // Reset failed attempts
        $reset_attempts_stmt = $conn->prepare("
            UPDATE user_mfa_settings 
            SET failed_attempts = 0, locked_until = NULL 
            WHERE user_id = ? AND method = 'totp'
        ");
        $reset_attempts_stmt->bind_param("i", $user_id);
        $reset_attempts_stmt->execute();
        $reset_attempts_stmt->close();

        // Enable MFA if this is setup verification and MFA isn't already enabled
        if ($is_setup_verification && !$user_data['mfa_enabled']) {
            $enable_stmt = $conn->prepare("
                UPDATE users SET mfa_enabled = 1, mfa_setup_at = NOW() WHERE id = ?
            ");
            $enable_stmt->bind_param("i", $user_id);
            $enable_stmt->execute();
            $enable_stmt->close();
        }

        // Log successful verification
        auditLog($conn, $user_id, $is_setup_verification ? 'setup' : 'verify_success', $method_used, [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'is_setup' => $is_setup_verification
        ]);

        $conn->commit();

        StructuredLogger::log('info', 'MFA verification successful', [
            'user_id' => $user_id,
            'username' => $user_data['username'],
            'method' => $method_used,
            'is_setup' => $is_setup_verification
        ]);

        send_response(200, [
            'success' => true,
            'method' => $method_used,
            'backupCodesRemaining' => $use_backup ? count($backup_codes) : $user_data['backup_codes_remaining']
        ]);

    } else {
        // Handle failed verification
        $failed_attempts = ($user_data['failed_attempts'] ?? 0) + 1;
        $locked_until = null;

        if ($failed_attempts >= $max_attempts) {
            $locked_until = date('Y-m-d H:i:s', time() + ($lockout_duration * 60));
        }

        $update_failures_stmt = $conn->prepare("
            UPDATE user_mfa_settings 
            SET failed_attempts = ?, last_failed_attempt_at = NOW(), locked_until = ?
            WHERE user_id = ? AND method = 'totp'
        ");
        $update_failures_stmt->bind_param("isi", $failed_attempts, $locked_until, $user_id);
        $update_failures_stmt->execute();
        $update_failures_stmt->close();

        // Log failed verification
        auditLog($conn, $user_id, 'verify_fail', $method_used, [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'failed_attempts' => $failed_attempts,
            'locked_until' => $locked_until
        ]);

        $conn->commit();

        StructuredLogger::log('warning', 'MFA verification failed', [
            'user_id' => $user_id,
            'username' => $user_data['username'],
            'method' => $use_backup ? 'backup_code' : 'totp',
            'failed_attempts' => $failed_attempts,
            'locked' => $locked_until !== null
        ]);

        $message = 'Invalid verification code';
        if ($locked_until) {
            $message = 'Too many failed attempts. Account temporarily locked.';
        } elseif ($failed_attempts > 2) {
            $remaining = $max_attempts - $failed_attempts;
            $message = "Invalid code. {$remaining} attempts remaining before lockout.";
        }

        send_response(400, [
            'success' => false,
            'message' => $message,
            'failed_attempts' => $failed_attempts,
            'locked_until' => $locked_until
        ]);
    }

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    
    StructuredLogger::log('error', 'MFA verification error', [
        'user_id' => $user_id,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    send_response(500, ['message' => 'MFA verification failed. Please try again.']);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

/**
 * Verify TOTP code using time-based algorithm
 */
function verifyTOTP($secret, $code, $window = 1): bool {
    $time_step = 30; // TOTP time step in seconds
    $current_time = floor(time() / $time_step);
    
    // Check current time window and adjacent windows for clock drift tolerance
    for ($i = -$window; $i <= $window; $i++) {
        $test_time = $current_time + $i;
        if (generateTOTP($secret, $test_time) === $code) {
            return true;
        }
    }
    
    return false;
}

/**
 * Generate TOTP code for a given secret and time
 */
function generateTOTP($secret, $time): string {
    // Convert Base32 secret to binary
    $secret_binary = base32_decode($secret);
    
    // Pack time as 8-byte big-endian
    $time_binary = pack('N*', 0) . pack('N*', $time);
    
    // Generate HMAC-SHA1 hash
    $hash = hash_hmac('sha1', $time_binary, $secret_binary, true);
    
    // Dynamic truncation
    $offset = ord($hash[19]) & 0x0f;
    $code = (
        ((ord($hash[$offset+0]) & 0x7f) << 24) |
        ((ord($hash[$offset+1]) & 0xff) << 16) |
        ((ord($hash[$offset+2]) & 0xff) << 8) |
        (ord($hash[$offset+3]) & 0xff)
    ) % 1000000;
    
    return str_pad($code, 6, '0', STR_PAD_LEFT);
}

/**
 * Decode Base32 string to binary
 */
function base32_decode($secret): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = strtoupper($secret);
    $secret = str_replace('=', '', $secret);
    
    $binary = '';
    $buffer = 0;
    $buffer_length = 0;
    
    for ($i = 0; $i < strlen($secret); $i++) {
        $char = $secret[$i];
        $pos = strpos($alphabet, $char);
        
        if ($pos === false) {
            continue; // Skip invalid characters
        }
        
        $buffer = ($buffer << 5) | $pos;
        $buffer_length += 5;
        
        if ($buffer_length >= 8) {
            $binary .= chr(($buffer >> ($buffer_length - 8)) & 0xFF);
            $buffer_length -= 8;
        }
    }
    
    return $binary;
}

/**
 * Get encryption key from environment
 */
function getEncryptionKey(): string {
    $key = $_ENV['MFA_ENCRYPTION_KEY'] ?? $_ENV['ENCRYPTION_KEY'] ?? null;
    if (!$key) {
        throw new Exception('Encryption key not configured');
    }
    return $key;
}

/**
 * Log MFA audit events
 */
function auditLog($conn, $user_id, $action, $method_used = null, $additional_data = []): void {
    $stmt = $conn->prepare("
        INSERT INTO mfa_audit_log (user_id, action, method_used, ip_address, user_agent, details) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $details = json_encode($additional_data);
    $stmt->bind_param(
        "isssss",
        $user_id,
        $action,
        $method_used,
        $additional_data['ip_address'] ?? null,
        $additional_data['user_agent'] ?? null,
        $details
    );
    $stmt->execute();
    $stmt->close();
}

function send_response($status_code, $data) {
    http_response_code($status_code);
    echo json_encode($data);
    exit();
}
?>