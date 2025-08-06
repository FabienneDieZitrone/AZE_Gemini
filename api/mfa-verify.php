<?php
/**
 * MFA Verification API Endpoint
 * Handles TOTP and backup code verification
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

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    send_json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}

$data = get_json_input();
$user_id = $data['user_id'] ?? null;
$code = $data['code'] ?? '';
$remember_device = $data['remember_device'] ?? false;

// Validate input
if (!$user_id || !$code) {
    send_json_response(['success' => false, 'error' => 'Missing required fields'], 400);
}

// Clean code input
$code = preg_replace('/[^0-9]/', '', $code);

// Check if user is locked out
if (is_user_locked_out($user_id)) {
    $lockout_info = get_lockout_info($user_id);
    send_json_response([
        'success' => false,
        'error' => 'Account temporarily locked',
        'locked_until' => $lockout_info['locked_until'],
        'attempts_remaining' => 0
    ], 429);
}

// Get user MFA data
$user = get_user_mfa_data($user_id);

if (!$user || !$user['mfa_enabled']) {
    send_json_response(['success' => false, 'error' => 'MFA not enabled'], 400);
}

// Verify code
$verification_result = verify_mfa_code($user, $code);

if ($verification_result['success']) {
    // Clear failed attempts
    clear_failed_attempts($user_id);
    
    // Update last used timestamp
    update_mfa_last_used($user_id);
    
    // Set up session
    setup_mfa_session($user_id);
    
    // Log successful verification
    log_mfa_event($user_id, 'verification_success', $verification_result['method']);
    
    // Handle device trust if requested
    $trust_token = null;
    if ($remember_device && MFA_ALLOW_TRUSTED_DEVICES) {
        $trust_token = create_device_trust_token($user_id);
    }
    
    send_json_response([
        'success' => true,
        'message' => 'MFA verification successful',
        'trust_token' => $trust_token
    ]);
} else {
    // Record failed attempt
    $attempts_remaining = record_failed_attempt($user_id);
    
    // Log failed verification
    log_mfa_event($user_id, 'verification_failed', 'Invalid code');
    
    if ($attempts_remaining === 0) {
        send_json_response([
            'success' => false,
            'error' => 'Too many failed attempts. Account locked.',
            'locked' => true,
            'attempts_remaining' => 0
        ], 429);
    } else {
        send_json_response([
            'success' => false,
            'error' => 'Invalid code',
            'attempts_remaining' => $attempts_remaining
        ], 400);
    }
}

/**
 * Get user MFA data
 */
function get_user_mfa_data($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT user_id, mfa_enabled, mfa_secret, mfa_backup_codes
            FROM users 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching user MFA data: " . $e->getMessage());
        return null;
    }
}

/**
 * Verify MFA code (TOTP or backup)
 */
function verify_mfa_code($user, $code) {
    // Try TOTP first (6 digits)
    if (strlen($code) === 6) {
        $secret = decrypt_mfa_secret($user['mfa_secret']);
        if (verify_totp_code($secret, $code)) {
            return ['success' => true, 'method' => 'totp'];
        }
    }
    
    // Try backup code (8 digits)
    if (strlen($code) === 8) {
        if (verify_backup_code($user['user_id'], $code)) {
            return ['success' => true, 'method' => 'backup_code'];
        }
    }
    
    return ['success' => false];
}

/**
 * Verify backup code
 */
function verify_backup_code($user_id, $code) {
    global $pdo;
    
    try {
        // Get encrypted backup codes
        $stmt = $pdo->prepare("SELECT mfa_backup_codes FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $encrypted_codes = $stmt->fetchColumn();
        
        if (!$encrypted_codes) {
            return false;
        }
        
        // Decrypt and check codes
        $codes = json_decode(decrypt_mfa_secret($encrypted_codes), true);
        
        if (!is_array($codes)) {
            return false;
        }
        
        // Find and remove used code
        $key = array_search($code, $codes);
        
        if ($key === false) {
            return false;
        }
        
        // Remove used code
        unset($codes[$key]);
        $codes = array_values($codes); // Re-index array
        
        // Update backup codes
        $encrypted_codes = encrypt_mfa_secret(json_encode($codes));
        $stmt = $pdo->prepare("
            UPDATE users 
            SET mfa_backup_codes = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$encrypted_codes, $user_id]);
        
        // Log backup code usage
        log_mfa_event($user_id, 'backup_code_used', 'Remaining codes: ' . count($codes));
        
        return true;
        
    } catch (Exception $e) {
        error_log("Backup code verification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user is locked out
 */
function is_user_locked_out($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM mfa_lockouts 
            WHERE user_id = ? AND locked_until > NOW()
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        error_log("Lockout check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get lockout information
 */
function get_lockout_info($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT locked_until 
            FROM mfa_lockouts 
            WHERE user_id = ? AND locked_until > NOW()
            ORDER BY locked_until DESC
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'locked_until' => $result ? $result['locked_until'] : null
        ];
    } catch (Exception $e) {
        error_log("Get lockout info error: " . $e->getMessage());
        return ['locked_until' => null];
    }
}

/**
 * Record failed attempt
 */
function record_failed_attempt($user_id) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Count recent failed attempts
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM mfa_audit_log 
            WHERE user_id = ? 
                AND event_type = 'verification_failed'
                AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        ");
        $stmt->execute([$user_id]);
        $failed_count = $stmt->fetchColumn() + 1; // +1 for current attempt
        
        $max_attempts = MFA_MAX_ATTEMPTS;
        $remaining = max(0, $max_attempts - $failed_count);
        
        // Lock account if max attempts reached
        if ($remaining === 0) {
            $lockout_minutes = MFA_LOCKOUT_DURATION;
            $stmt = $pdo->prepare("
                INSERT INTO mfa_lockouts (user_id, locked_until, reason)
                VALUES (?, DATE_ADD(NOW(), INTERVAL ? MINUTE), ?)
            ");
            $stmt->execute([
                $user_id,
                $lockout_minutes,
                'Max MFA attempts exceeded'
            ]);
        }
        
        $pdo->commit();
        
        return $remaining;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        error_log("Record failed attempt error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Clear failed attempts
 */
function clear_failed_attempts($user_id) {
    global $pdo;
    
    try {
        // Remove any active lockouts
        $stmt = $pdo->prepare("
            DELETE FROM mfa_lockouts 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
    } catch (Exception $e) {
        error_log("Clear failed attempts error: " . $e->getMessage());
    }
}

/**
 * Update MFA last used timestamp
 */
function update_mfa_last_used($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET mfa_last_used = NOW()
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
    } catch (Exception $e) {
        error_log("Update MFA last used error: " . $e->getMessage());
    }
}

/**
 * Set up MFA session
 */
function setup_mfa_session($user_id) {
    $_SESSION['mfa_verified'] = true;
    $_SESSION['mfa_verified_at'] = time();
}

/**
 * Create device trust token
 */
function create_device_trust_token($user_id) {
    global $pdo;
    
    try {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+' . MFA_TRUSTED_DEVICE_DAYS . ' days'));
        
        $stmt = $pdo->prepare("
            INSERT INTO mfa_trusted_devices (user_id, token, device_name, expires_at)
            VALUES (?, ?, ?, ?)
        ");
        
        $device_name = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Device';
        $stmt->execute([$user_id, hash('sha256', $token), $device_name, $expires]);
        
        return $token;
        
    } catch (Exception $e) {
        error_log("Create device trust token error: " . $e->getMessage());
        return null;
    }
}

// Include shared functions from mfa-setup.php
require_once __DIR__ . '/mfa-setup.php';
?>