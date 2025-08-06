<?php
/**
 * MFA Setup API Endpoint
 * Issue #115: Multi-Factor Authentication Implementation
 * Version: 2.0 - Complete Production Implementation
 * Date: 2025-08-06
 * Author: Claude Code
 * 
 * This endpoint handles TOTP setup, QR code generation, and backup codes
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

// Apply rate limiting for MFA setup
checkRateLimit('mfa_setup');

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

// Authorization check
if ($user_id !== (int)$session_user['id']) {
    send_response(403, ['message' => 'Not authorized to set up MFA for this user']);
    exit();
}

try {
    $conn = get_db_connection();
    $conn->begin_transaction();

    // Check if MFA is already enabled
    $check_stmt = $conn->prepare("
        SELECT mfa_enabled, mfa_secret, role, display_name, username 
        FROM users 
        WHERE id = ?
    ");
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $user_data = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if (!$user_data) {
        throw new Exception('User not found');
    }

    if ($user_data['mfa_enabled']) {
        send_response(400, ['message' => 'MFA is already enabled for this user']);
        exit();
    }

    // Generate secure TOTP secret (160 bits = 32 Base32 chars)
    $secret = generateTOTPSecret();
    
    // Generate backup recovery codes
    $backup_codes = generateBackupCodes();
    
    // Encrypt sensitive data
    $encryption_key = getEncryptionKey();
    
    // Encrypt TOTP secret
    $secret_iv = random_bytes(16);
    $encrypted_secret = openssl_encrypt($secret, 'AES-256-CBC', $encryption_key, 0, $secret_iv);
    
    // Encrypt backup codes
    $backup_codes_json = json_encode($backup_codes);
    $backup_iv = random_bytes(16);
    $encrypted_backup_codes = openssl_encrypt($backup_codes_json, 'AES-256-CBC', $encryption_key, 0, $backup_iv);

    // Store encrypted data in database (but don't enable MFA yet)
    $update_stmt = $conn->prepare("
        UPDATE users 
        SET mfa_secret = ?, 
            mfa_secret_iv = ?, 
            mfa_backup_codes = ?, 
            mfa_backup_codes_iv = ?
        WHERE id = ?
    ");
    $update_stmt->bind_param(
        "ssssi",
        base64_encode($encrypted_secret),
        base64_encode($secret_iv),
        base64_encode($encrypted_backup_codes),
        base64_encode($backup_iv),
        $user_id
    );
    $update_stmt->execute();
    $update_stmt->close();

    // Create MFA settings record
    $settings_stmt = $conn->prepare("
        INSERT INTO user_mfa_settings (user_id, method, is_primary, backup_codes_remaining) 
        VALUES (?, 'totp', 1, ?)
        ON DUPLICATE KEY UPDATE 
        backup_codes_remaining = VALUES(backup_codes_remaining),
        failed_attempts = 0,
        locked_until = NULL
    ");
    $backup_count = count($backup_codes);
    $settings_stmt->bind_param("ii", $user_id, $backup_count);
    $settings_stmt->execute();
    $settings_stmt->close();

    // Generate QR code URL for Google Authenticator
    $issuer = 'AZE Zeiterfassung';
    $account_name = $user_data['display_name'] . ' (' . $user_data['username'] . ')';
    
    $qr_code_url = sprintf(
        'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
        urlencode($issuer),
        urlencode($account_name),
        $secret,
        urlencode($issuer)
    );

    // Log MFA setup initiation
    auditLog($conn, $user_id, 'setup', null, [
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);

    $conn->commit();

    // Log successful MFA setup initiation
    StructuredLogger::log('info', 'MFA setup initiated successfully', [
        'user_id' => $user_id,
        'username' => $user_data['username'],
        'role' => $user_data['role']
    ]);

    // Return setup data (secret will be needed for verification)
    send_response(200, [
        'qrCodeUrl' => $qr_code_url,
        'secret' => $secret, // Only returned once for manual entry
        'backupCodes' => $backup_codes,
        'issuer' => $issuer,
        'accountName' => $account_name
    ]);

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    
    StructuredLogger::log('error', 'MFA setup failed', [
        'user_id' => $user_id,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    send_response(500, ['message' => 'Failed to set up MFA. Please try again.']);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

/**
 * Generate a cryptographically secure TOTP secret
 */
function generateTOTPSecret($length = 32): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // RFC 4648 Base32 alphabet
    $secret = '';
    $alphabetLength = strlen($alphabet);
    
    for ($i = 0; $i < $length; $i++) {
        $secret .= $alphabet[random_int(0, $alphabetLength - 1)];
    }
    
    return $secret;
}

/**
 * Generate cryptographically secure backup codes
 */
function generateBackupCodes($count = 8): array {
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        // Generate 8-character alphanumeric codes
        $code = '';
        for ($j = 0; $j < 8; $j++) {
            $code .= dechex(random_int(0, 15));
        }
        $codes[] = strtoupper($code);
    }
    return $codes;
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