<?php
/**
 * MFA Setup API Endpoint
 * Handles TOTP secret generation, QR codes, and backup codes
 */

require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/security-middleware.php';
require_once __DIR__ . '/../config/mfa.php';

// Apply security headers
apply_security_headers();

// Initialize API
$response = initialize_api();
if ($response !== true) {
    send_json_response($response);
}

// Check if user is authenticated
$session = $response;
if (!isset($_SESSION['user_id'])) {
    send_json_response(['success' => false, 'error' => 'Not authenticated'], 401);
}

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handle_get_mfa_status($user_id);
        break;
        
    case 'POST':
        $data = get_json_input();
        $action = $data['action'] ?? '';
        
        switch ($action) {
            case 'generate':
                handle_generate_secret($user_id);
                break;
                
            case 'verify_setup':
                handle_verify_setup($user_id, $data);
                break;
                
            case 'generate_backup_codes':
                handle_generate_backup_codes($user_id);
                break;
                
            default:
                send_json_response(['success' => false, 'error' => 'Invalid action'], 400);
        }
        break;
        
    default:
        send_json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}

/**
 * Get current MFA status for user
 */
function handle_get_mfa_status($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT mfa_enabled, mfa_setup_completed, mfa_backup_codes_viewed,
                   mfa_last_used, role
            FROM users 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            send_json_response(['success' => false, 'error' => 'User not found'], 404);
        }
        
        // Check if MFA is required for this role
        $mfa_required = in_array($user['role'], MFA_REQUIRED_ROLES);
        
        // Check grace period
        $in_grace_period = false;
        if ($mfa_required && !$user['mfa_enabled']) {
            $stmt = $pdo->prepare("
                SELECT created_at FROM users WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            $created = $stmt->fetchColumn();
            
            $grace_end = strtotime($created) + (MFA_GRACE_PERIOD_DAYS * 86400);
            $in_grace_period = time() < $grace_end;
        }
        
        send_json_response([
            'success' => true,
            'mfa_enabled' => (bool)$user['mfa_enabled'],
            'mfa_setup_completed' => (bool)$user['mfa_setup_completed'],
            'mfa_backup_codes_viewed' => (bool)$user['mfa_backup_codes_viewed'],
            'mfa_required' => $mfa_required,
            'in_grace_period' => $in_grace_period,
            'grace_period_days' => MFA_GRACE_PERIOD_DAYS
        ]);
        
    } catch (Exception $e) {
        error_log("MFA status error: " . $e->getMessage());
        send_json_response(['success' => false, 'error' => 'Failed to get MFA status'], 500);
    }
}

/**
 * Generate new TOTP secret and QR code
 */
function handle_generate_secret($user_id) {
    global $pdo;
    
    try {
        // Check if user already has MFA enabled
        $stmt = $pdo->prepare("SELECT mfa_enabled FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $mfa_enabled = $stmt->fetchColumn();
        
        if ($mfa_enabled) {
            send_json_response(['success' => false, 'error' => 'MFA already enabled'], 400);
        }
        
        // Generate new secret
        $secret = generate_totp_secret();
        
        // Get user email for QR code
        $stmt = $pdo->prepare("SELECT email FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $email = $stmt->fetchColumn();
        
        // Generate QR code data
        $issuer = MFA_ISSUER;
        $qr_data = generate_qr_code_data($email, $secret, $issuer);
        
        // Store temporary secret (not activated yet)
        $encrypted_secret = encrypt_mfa_secret($secret);
        $stmt = $pdo->prepare("
            UPDATE users 
            SET mfa_temp_secret = ?, mfa_temp_secret_created = NOW()
            WHERE user_id = ?
        ");
        $stmt->execute([$encrypted_secret, $user_id]);
        
        // Log setup attempt
        log_mfa_event($user_id, 'setup_started', 'TOTP secret generated');
        
        send_json_response([
            'success' => true,
            'secret' => $secret,
            'qr_code' => $qr_data,
            'manual_entry_key' => format_secret_for_display($secret),
            'issuer' => $issuer
        ]);
        
    } catch (Exception $e) {
        error_log("MFA generate secret error: " . $e->getMessage());
        send_json_response(['success' => false, 'error' => 'Failed to generate secret'], 500);
    }
}

/**
 * Verify TOTP code during setup
 */
function handle_verify_setup($user_id, $data) {
    global $pdo;
    
    $code = $data['code'] ?? '';
    
    if (!preg_match('/^\d{6}$/', $code)) {
        send_json_response(['success' => false, 'error' => 'Invalid code format'], 400);
    }
    
    try {
        // Get temporary secret
        $stmt = $pdo->prepare("
            SELECT mfa_temp_secret, mfa_temp_secret_created 
            FROM users 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user['mfa_temp_secret']) {
            send_json_response(['success' => false, 'error' => 'No setup in progress'], 400);
        }
        
        // Check if temp secret expired (30 minutes)
        $created = strtotime($user['mfa_temp_secret_created']);
        if (time() - $created > 1800) {
            send_json_response(['success' => false, 'error' => 'Setup session expired'], 400);
        }
        
        // Decrypt and verify code
        $secret = decrypt_mfa_secret($user['mfa_temp_secret']);
        
        if (!verify_totp_code($secret, $code)) {
            log_mfa_event($user_id, 'setup_failed', 'Invalid verification code');
            send_json_response(['success' => false, 'error' => 'Invalid code'], 400);
        }
        
        // Activate MFA
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            UPDATE users 
            SET mfa_secret = mfa_temp_secret,
                mfa_enabled = 1,
                mfa_setup_completed = 1,
                mfa_enabled_at = NOW(),
                mfa_temp_secret = NULL,
                mfa_temp_secret_created = NULL
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        
        // Generate backup codes
        $backup_codes = generate_backup_codes();
        $encrypted_codes = encrypt_backup_codes($backup_codes);
        
        $stmt = $pdo->prepare("
            UPDATE users 
            SET mfa_backup_codes = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$encrypted_codes, $user_id]);
        
        $pdo->commit();
        
        // Log success
        log_mfa_event($user_id, 'setup_completed', 'MFA successfully enabled');
        
        send_json_response([
            'success' => true,
            'backup_codes' => $backup_codes,
            'message' => 'MFA successfully enabled'
        ]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        error_log("MFA verify setup error: " . $e->getMessage());
        send_json_response(['success' => false, 'error' => 'Failed to enable MFA'], 500);
    }
}

/**
 * Generate new backup codes
 */
function handle_generate_backup_codes($user_id) {
    global $pdo;
    
    try {
        // Check if MFA is enabled
        $stmt = $pdo->prepare("SELECT mfa_enabled FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $mfa_enabled = $stmt->fetchColumn();
        
        if (!$mfa_enabled) {
            send_json_response(['success' => false, 'error' => 'MFA not enabled'], 400);
        }
        
        // Generate new backup codes
        $backup_codes = generate_backup_codes();
        $encrypted_codes = encrypt_backup_codes($backup_codes);
        
        // Update in database
        $stmt = $pdo->prepare("
            UPDATE users 
            SET mfa_backup_codes = ?,
                mfa_backup_codes_generated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->execute([$encrypted_codes, $user_id]);
        
        // Log event
        log_mfa_event($user_id, 'backup_codes_regenerated', 'New backup codes generated');
        
        send_json_response([
            'success' => true,
            'backup_codes' => $backup_codes,
            'message' => 'New backup codes generated'
        ]);
        
    } catch (Exception $e) {
        error_log("MFA backup codes error: " . $e->getMessage());
        send_json_response(['success' => false, 'error' => 'Failed to generate backup codes'], 500);
    }
}

/**
 * Generate TOTP secret
 */
function generate_totp_secret($length = 32) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    
    for ($i = 0; $i < $length; $i++) {
        $secret .= $chars[random_int(0, 31)];
    }
    
    return $secret;
}

/**
 * Generate QR code data URL
 */
function generate_qr_code_data($email, $secret, $issuer) {
    $otpauth = sprintf(
        'otpauth://totp/%s:%s?secret=%s&issuer=%s',
        rawurlencode($issuer),
        rawurlencode($email),
        $secret,
        rawurlencode($issuer)
    );
    
    return $otpauth;
}

/**
 * Format secret for manual entry
 */
function format_secret_for_display($secret) {
    return implode(' ', str_split($secret, 4));
}

/**
 * Generate backup codes
 */
function generate_backup_codes($count = 8) {
    $codes = [];
    
    for ($i = 0; $i < $count; $i++) {
        $code = '';
        for ($j = 0; $j < 8; $j++) {
            $code .= random_int(0, 9);
        }
        $codes[] = $code;
    }
    
    return $codes;
}

/**
 * Encrypt MFA secret
 */
function encrypt_mfa_secret($secret) {
    $key = hash('sha256', MFA_ENCRYPTION_KEY, true);
    $iv = openssl_random_pseudo_bytes(16);
    
    $encrypted = openssl_encrypt($secret, 'aes-256-cbc', $key, 0, $iv);
    
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt MFA secret
 */
function decrypt_mfa_secret($encrypted) {
    $data = base64_decode($encrypted);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    
    $key = hash('sha256', MFA_ENCRYPTION_KEY, true);
    
    return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
}

/**
 * Encrypt backup codes
 */
function encrypt_backup_codes($codes) {
    return encrypt_mfa_secret(json_encode($codes));
}

/**
 * Verify TOTP code
 */
function verify_totp_code($secret, $code, $window = 1) {
    $timestamp = floor(time() / 30);
    
    for ($i = -$window; $i <= $window; $i++) {
        $test_time = $timestamp + $i;
        $valid_code = generate_totp_code($secret, $test_time);
        
        if (hash_equals($valid_code, $code)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Generate TOTP code
 */
function generate_totp_code($secret, $timestamp) {
    $key = base32_decode($secret);
    $time = pack('N*', 0) . pack('N*', $timestamp);
    
    $hash = hash_hmac('sha1', $time, $key, true);
    $offset = ord($hash[19]) & 0xf;
    
    $code = (
        ((ord($hash[$offset]) & 0x7f) << 24) |
        ((ord($hash[$offset + 1]) & 0xff) << 16) |
        ((ord($hash[$offset + 2]) & 0xff) << 8) |
        (ord($hash[$offset + 3]) & 0xff)
    ) % 1000000;
    
    return str_pad($code, 6, '0', STR_PAD_LEFT);
}

/**
 * Base32 decode
 */
function base32_decode($input) {
    $map = [
        'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5, 'G' => 6, 'H' => 7,
        'I' => 8, 'J' => 9, 'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15,
        'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19, 'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23,
        'Y' => 24, 'Z' => 25, '2' => 26, '3' => 27, '4' => 28, '5' => 29, '6' => 30, '7' => 31
    ];
    
    $input = str_replace('=', '', strtoupper($input));
    $output = '';
    $buffer = 0;
    $bits = 0;
    
    for ($i = 0; $i < strlen($input); $i++) {
        $buffer = ($buffer << 5) | $map[$input[$i]];
        $bits += 5;
        
        if ($bits >= 8) {
            $bits -= 8;
            $output .= chr(($buffer >> $bits) & 0xff);
        }
    }
    
    return $output;
}

/**
 * Log MFA event
 */
function log_mfa_event($user_id, $event_type, $details) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO mfa_audit_log (user_id, event_type, details, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $user_id,
            $event_type,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("MFA audit log error: " . $e->getMessage());
    }
}
?>