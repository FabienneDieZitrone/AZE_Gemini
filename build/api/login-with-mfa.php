<?php
/**
 * Enhanced Login Endpoint with MFA Integration
 * Issue #115: Multi-Factor Authentication Implementation
 * Version: 2.1 - Integrated with existing login.php
 * Date: 2025-08-06
 * Author: Claude Code
 * 
 * This endpoint extends the existing login functionality with MFA verification
 */

// Include security and error handling
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
initializeSecurity(false); // We'll check auth manually after
validateRequestMethod('POST');

// Apply security headers
initSecurityMiddleware();

// Apply rate limiting for login attempts
checkRateLimit('login');

// Robust fatal error handler
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode(['message' => 'Fatal PHP Error', 'error_details' => $error]);
        exit;
    }
});

// SECURITY: Error reporting disabled in production
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

initialize_api();

// Only POST requests allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(405, ['message' => 'Method Not Allowed']);
    exit();
}

// Validate CSRF token for login requests
if (!validateCsrfProtection()) {
    exit();
}

// Parse request body to check for MFA verification
$input = json_decode(file_get_contents('php://input'), true);
$mfa_code = InputValidator::sanitizeString($input['mfaCode'] ?? '');
$use_backup_code = filter_var($input['useBackupCode'] ?? false, FILTER_VALIDATE_BOOLEAN);
$skip_mfa_check = filter_var($input['skipMfaCheck'] ?? false, FILTER_VALIDATE_BOOLEAN);

$conn = get_db_connection();
$conn->begin_transaction();

try {
    // --- 1. GDPR-compliant data cleanup ---
    $six_months_ago = date('Y-m-d H:i:s', strtotime('-6 months'));
    $delete_stmt = $conn->prepare("DELETE FROM users WHERE created_at < ?");
    if (!$delete_stmt) throw new Exception("Prepare failed (delete users): " . $conn->error);
    $delete_stmt->bind_param("s", $six_months_ago);
    $delete_stmt->execute();
    $delete_stmt->close();

    // --- 2. User Synchronization ---
    // Get user from secure, server-side session.
    // This function ends the script with 401 if no valid session exists.
    $user_from_session = verify_session_and_get_user();
    
    // SECURITY FIX: Validate and sanitize session data
    $azure_oid = InputValidator::sanitizeString($user_from_session['oid'] ?? '');
    $display_name_from_session = InputValidator::sanitizeString($user_from_session['name'] ?? '');
    $username_from_session = InputValidator::sanitizeString($user_from_session['username'] ?? ''); // Email
    
    // Validate required fields
    if (empty($azure_oid) || empty($display_name_from_session) || empty($username_from_session)) {
        throw new Exception('Invalid session data: missing required fields');
    }
    
    // Validate email format
    if (!InputValidator::isValidEmail($username_from_session)) {
        throw new Exception('Invalid email format in session');
    }

    // Find user by Azure OID (primary, immutable key)
    $stmt = $conn->prepare("
        SELECT u.*, s.failed_attempts, s.locked_until 
        FROM users u
        LEFT JOIN user_mfa_settings s ON u.id = s.user_id AND s.method = 'totp'
        WHERE u.azure_oid = ?
    ");
    if (!$stmt) throw new Exception("Prepare failed (find user): " . $conn->error);
    $stmt->bind_param("s", $azure_oid);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();

    $current_user_id = null;

    if ($user_data) {
        // User exists
        $current_user_id = $user_data['id'];
        
        // Update display name if it changed in Azure AD
        if ($user_data['display_name'] !== $display_name_from_session) {
            $update_stmt = $conn->prepare("UPDATE users SET display_name = ? WHERE id = ?");
            if (!$update_stmt) throw new Exception("Prepare failed (update display_name): " . $conn->error);
            $update_stmt->bind_param("si", $display_name_from_session, $current_user_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
        
        // --- 3. MFA CHECK ---
        if ($user_data['mfa_enabled'] && !$skip_mfa_check) {
            // Check if user is locked out
            if ($user_data['locked_until'] && strtotime($user_data['locked_until']) > time()) {
                $remaining_minutes = ceil((strtotime($user_data['locked_until']) - time()) / 60);
                send_response(429, [
                    'message' => "Account temporarily locked due to too many failed MFA attempts. Try again in {$remaining_minutes} minutes.",
                    'mfa_required' => true,
                    'locked_until' => $user_data['locked_until']
                ]);
                exit();
            }
            
            if (empty($mfa_code)) {
                // MFA is required but no code provided
                send_response(202, [
                    'message' => 'MFA verification required',
                    'mfa_required' => true,
                    'user_id' => $current_user_id,
                    'display_name' => $display_name_from_session,
                    'backup_codes_available' => ($user_data['mfa_backup_codes'] ? true : false)
                ]);
                exit();
            }
            
            // Verify MFA code
            $mfa_verification = verifyMFACode($conn, $current_user_id, $mfa_code, $use_backup_code);
            if (!$mfa_verification['success']) {
                send_response(400, [
                    'message' => $mfa_verification['message'],
                    'mfa_required' => true,
                    'failed_attempts' => $mfa_verification['failed_attempts'],
                    'locked_until' => $mfa_verification['locked_until']
                ]);
                exit();
            }
            
            // Log successful MFA verification
            StructuredLogger::log('info', 'MFA verification successful during login', [
                'user_id' => $current_user_id,
                'username' => $username_from_session,
                'method' => $use_backup_code ? 'backup_code' : 'totp'
            ]);
        }
        
    } else {
        // User doesn't exist -> Create new user (first login)
        $insert_user_stmt = $conn->prepare("INSERT INTO users (username, display_name, role, azure_oid, created_at) VALUES (?, ?, 'Honorarkraft', ?, NOW())");
        if (!$insert_user_stmt) throw new Exception("Prepare failed (insert user): " . $conn->error);
        $insert_user_stmt->bind_param("sss", $username_from_session, $display_name_from_session, $azure_oid);
        $insert_user_stmt->execute();
        $current_user_id = $conn->insert_id;
        $insert_user_stmt->close();

        // Create default master data for new user
        $default_workdays = json_encode(['Mo', 'Di', 'Mi', 'Do', 'Fr']);
        $insert_master_stmt = $conn->prepare("INSERT INTO master_data (user_id, weekly_hours, workdays, can_work_from_home) VALUES (?, 40.00, ?, 0)");
        if (!$insert_master_stmt) throw new Exception("Prepare failed (insert masterdata): " . $conn->error);
        $insert_master_stmt->bind_param("is", $current_user_id, $default_workdays);
        $insert_master_stmt->execute();
        $insert_master_stmt->close();
        
        // Log new user creation
        StructuredLogger::log('info', 'New user created during login', [
            'user_id' => $current_user_id,
            'username' => $username_from_session,
            'display_name' => $display_name_from_session
        ]);
    }
    
    // --- 4. Get all initial data ---
    
    // Current user (display_name as 'name' for frontend compatibility)
    $user_stmt = $conn->prepare("SELECT id, display_name AS name, role, azure_oid AS azureOid, mfa_enabled FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $current_user_id);
    $user_stmt->execute();
    $current_user_for_frontend = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();

    // All users
    $users_stmt = $conn->prepare("SELECT id, display_name AS name, role, azure_oid AS azureOid, mfa_enabled FROM users");
    $users_stmt->execute();
    $all_users = $users_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $users_stmt->close();

    // All master data
    $master_data_stmt = $conn->prepare("SELECT user_id, weekly_hours, workdays, can_work_from_home FROM master_data");
    $master_data_stmt->execute();
    $master_data_result = $master_data_stmt->get_result();
    $master_data_map = [];
    while ($row = $master_data_result->fetch_assoc()) {
        $master_data_map[$row['user_id']] = [
            'weeklyHours' => (float)$row['weekly_hours'],
            'workdays' => json_decode($row['workdays']),
            'canWorkFromHome' => (bool)$row['can_work_from_home']
        ];
    }
    $master_data_stmt->close();
    
    // All time entries
    $time_entries_stmt = $conn->prepare("SELECT id, user_id AS userId, username, date, start_time AS startTime, stop_time AS stopTime, location, role, created_at AS createdAt, updated_by AS updatedBy, updated_at AS updatedAt FROM time_entries ORDER BY date DESC, start_time DESC");
    $time_entries_stmt->execute();
    $time_entries = $time_entries_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $time_entries_stmt->close();

    // Get current user data for approval filtering
    $current_user_data = $current_user_for_frontend;

    // All pending approval requests (role-based filtering)
    $approval_query = "SELECT * FROM approval_requests WHERE status = 'pending'";
    
    // Role-based filtering
    if ($current_user_data['role'] === 'Honorarkraft' || $current_user_data['role'] === 'Mitarbeiter') {
        // Honorarkraft and Mitarbeiter see only their own requests
        $approval_query .= " AND requested_by = ?";
        $approvals_stmt = $conn->prepare($approval_query);
        $approvals_stmt->bind_param("s", $username_from_session);
    } else if ($current_user_data['role'] === 'Standortleiter') {
        // Standortleiter see requests from their location
        $approval_query .= " AND JSON_EXTRACT(original_entry_data, '$.location') = ?";
        $approvals_stmt = $conn->prepare($approval_query);
        // Note: This would need the user's location, which isn't available in current schema
        $approvals_stmt->bind_param("s", 'Zentrale Berlin'); // Default for now
    } else {
        // Bereichsleiter and Admin see everything
        $approvals_stmt = $conn->prepare($approval_query);
    }
    
    $approvals_stmt->execute();
    $approval_requests_raw = $approvals_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $approvals_stmt->close();
    $approval_requests = array_map(function($req) {
        $entry_data_json = json_decode($req['original_entry_data'], true);
        return [
            'id' => $req['id'],
            'type' => $req['type'],
            'entry' => [
                'id' => (int)$entry_data_json['id'], 'userId' => (int)$entry_data_json['user_id'], 'username' => $entry_data_json['username'],
                'date' => $entry_data_json['date'], 'startTime' => $entry_data_json['start_time'], 'stopTime' => $entry_data_json['stop_time'],
                'location' => $entry_data_json['location'], 'role' => $entry_data_json['role'], 'createdAt' => $entry_data_json['created_at'],
                'updatedBy' => $entry_data_json['updated_by'], 'updatedAt' => $entry_data_json['updated_at'],
            ],
            'newData' => json_decode($req['new_data']),
            'reasonData' => json_decode($req['reason_data']),
            'requestedBy' => $req['requested_by'],
            'status' => 'pending',
        ];
    }, $approval_requests_raw);
    
    // Complete change history (role-based filtering)
    $history_query = "SELECT * FROM approval_requests WHERE status != 'pending'";
    
    // Role-based filtering
    if ($current_user_data['role'] === 'Honorarkraft' || $current_user_data['role'] === 'Mitarbeiter') {
        // Honorarkraft and Mitarbeiter see only their own history
        $history_query .= " AND requested_by = ?";
        $history_stmt = $conn->prepare($history_query . " ORDER BY resolved_at DESC");
        $history_stmt->bind_param("s", $username_from_session);
    } else if ($current_user_data['role'] === 'Standortleiter') {
        // Standortleiter see history from their location
        $history_query .= " AND JSON_EXTRACT(original_entry_data, '$.location') = ?";
        $history_stmt = $conn->prepare($history_query . " ORDER BY resolved_at DESC");
        $history_stmt->bind_param("s", 'Zentrale Berlin'); // Default for now
    } else {
        // Bereichsleiter and Admin see everything
        $history_stmt = $conn->prepare($history_query . " ORDER BY resolved_at DESC");
    }
    
    $history_stmt->execute();
    $history_raw = $history_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $history_stmt->close();
    $history = array_map(function($row) {
        $original_entry = json_decode($row['original_entry_data'], true);
        return [
            'id' => $row['id'], 'type' => $row['type'],
            'entry' => [
                'id' => (int)$original_entry['id'], 'userId' => (int)$original_entry['user_id'], 'username' => $original_entry['username'],
                'date' => $original_entry['date'], 'startTime' => $original_entry['start_time'], 'stopTime' => $original_entry['stop_time'],
                'location' => $original_entry['location'], 'role' => $original_entry['role'], 'createdAt' => $original_entry['created_at'],
                'updatedBy' => $original_entry['updated_by'], 'updatedAt' => $original_entry['updated_at'],
            ],
            'newData' => json_decode($row['new_data']), 'reasonData' => json_decode($row['reason_data']),
            'requestedBy' => $row['requested_by'], 'finalStatus' => $row['status'],
            'resolvedAt' => $row['resolved_at'], 'resolvedBy' => $row['resolved_by'],
        ];
    }, $history_raw);

    // Global settings
    $settings_stmt = $conn->prepare("
        SELECT overtime_threshold, change_reasons, locations, 
               mfa_required_roles, mfa_grace_period_hours 
        FROM global_settings WHERE id = 1
    ");
    $settings_stmt->execute();
    $settings_raw = $settings_stmt->get_result()->fetch_assoc();
    $settings_stmt->close();
    $global_settings = [
        'overtimeThreshold' => (float)$settings_raw['overtime_threshold'],
        'changeReasons' => json_decode($settings_raw['change_reasons']),
        'locations' => json_decode($settings_raw['locations']),
        'mfaRequiredRoles' => json_decode($settings_raw['mfa_required_roles'] ?? '[]'),
        'mfaGracePeriodHours' => (int)($settings_raw['mfa_grace_period_hours'] ?? 24)
    ];

    $conn->commit();

    send_response(200, [
        'currentUser' => $current_user_for_frontend,
        'users' => $all_users,
        'masterData' => $master_data_map,
        'timeEntries' => $time_entries,
        'approvalRequests' => $approval_requests,
        'history' => $history,
        'globalSettings' => $global_settings
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Login transaction failed: " . $e->getMessage());
    send_response(500, ['message' => 'An internal error occurred during the login process.', 'error' => $e->getMessage()]);
} finally {
    $conn->close();
}

/**
 * Verify MFA code (TOTP or backup code)
 */
function verifyMFACode($conn, $user_id, $code, $use_backup_code): array {
    try {
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

        // Get user MFA data
        $user_stmt = $conn->prepare("
            SELECT u.mfa_secret, u.mfa_secret_iv, u.mfa_backup_codes, u.mfa_backup_codes_iv,
                   s.failed_attempts, s.backup_codes_remaining
            FROM users u
            LEFT JOIN user_mfa_settings s ON u.id = s.user_id AND s.method = 'totp'
            WHERE u.id = ?
        ");
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_data = $user_stmt->get_result()->fetch_assoc();
        $user_stmt->close();

        if (!$user_data || !$user_data['mfa_secret']) {
            return ['success' => false, 'message' => 'MFA not configured'];
        }

        $encryption_key = getEncryptionKey();
        $is_valid = false;

        if ($use_backup_code) {
            // Verify backup code
            if ($user_data['backup_codes_remaining'] <= 0) {
                return ['success' => false, 'message' => 'No backup codes remaining'];
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

                // Log backup code usage
                auditLog($conn, $user_id, 'backup_code_used', 'backup_code', [
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                ]);
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

            return ['success' => true, 'message' => 'MFA verification successful'];

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
            auditLog($conn, $user_id, 'verify_fail', $use_backup_code ? 'backup_code' : 'totp', [
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'failed_attempts' => $failed_attempts,
                'locked_until' => $locked_until
            ]);

            $message = 'Invalid verification code';
            if ($locked_until) {
                $message = 'Too many failed attempts. Account temporarily locked.';
            } elseif ($failed_attempts > 2) {
                $remaining = $max_attempts - $failed_attempts;
                $message = "Invalid code. {$remaining} attempts remaining before lockout.";
            }

            return [
                'success' => false,
                'message' => $message,
                'failed_attempts' => $failed_attempts,
                'locked_until' => $locked_until
            ];
        }

    } catch (Exception $e) {
        error_log("MFA verification error: " . $e->getMessage());
        return ['success' => false, 'message' => 'MFA verification failed'];
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