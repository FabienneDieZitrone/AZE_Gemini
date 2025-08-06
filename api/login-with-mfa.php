<?php
/**
 * Enhanced Login Endpoint with MFA Support
 * Based on existing login.php with MFA integration
 */

define('API_GUARD', true);

require_once __DIR__ . '/security-headers.php';
require_once __DIR__ . '/error-handler.php';
require_once __DIR__ . '/structured-logger.php';
require_once __DIR__ . '/security-middleware.php';
require_once __DIR__ . '/rate-limiting.php';
require_once __DIR__ . '/csrf-middleware.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/../config/mfa.php';

// Initialize security
initializeSecurity(false);
validateRequestMethod('POST');

// Apply security headers
initSecurityMiddleware();

// Apply rate limiting for login attempts
checkRateLimit('login');

// Register error handler
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

// Disable error display in production
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

initialize_api();

// Validate CSRF token
if (!validateCsrfProtection()) {
    exit();
}

$conn->begin_transaction();

try {
    // DSGVO-compliant data cleanup
    $six_months_ago = date('Y-m-d H:i:s', strtotime('-6 months'));
    $delete_stmt = $conn->prepare("DELETE FROM users WHERE created_at < ?");
    if (!$delete_stmt) throw new Exception("Prepare failed (delete users): " . $conn->error);
    $delete_stmt->bind_param("s", $six_months_ago);
    $delete_stmt->execute();
    $delete_stmt->close();

    // Get user from secure session
    $user_from_session = verify_session_and_get_user();
    
    // Validate and sanitize session data
    require_once __DIR__ . '/validation.php';
    
    $azure_oid = InputValidator::sanitizeString($user_from_session['oid'] ?? '');
    $display_name_from_session = InputValidator::sanitizeString($user_from_session['name'] ?? '');
    $username_from_session = InputValidator::sanitizeString($user_from_session['username'] ?? '');
    
    // Validate required fields
    if (empty($azure_oid) || empty($display_name_from_session) || empty($username_from_session)) {
        throw new Exception('Invalid session data: missing required fields');
    }
    
    // Validate email format
    if (!InputValidator::isValidEmail($username_from_session)) {
        throw new Exception('Invalid email format in session');
    }

    // Check if user exists
    $sql_check = "SELECT user_id, azure_oid, full_name, email, role, created_at, updated_at, 
                         mfa_enabled, mfa_setup_completed, mfa_backup_codes_viewed
                  FROM users WHERE azure_oid = ?";
    $stmt_check = $conn->prepare($sql_check);
    if (!$stmt_check) throw new Exception("Prepare failed (check user): " . $conn->error);
    $stmt_check->bind_param("s", $azure_oid);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $existing_user = $result_check->fetch_assoc();
    $stmt_check->close();

    if ($existing_user) {
        // Update existing user
        $user_id = $existing_user['user_id'];
        
        // Update last login
        $sql_update = "UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE user_id = ?";
        $stmt_update = $conn->prepare($sql_update);
        if (!$stmt_update) throw new Exception("Prepare failed (update user): " . $conn->error);
        $stmt_update->bind_param("i", $user_id);
        $stmt_update->execute();
        $stmt_update->close();

    } else {
        // Create new user
        $standardrolle = 'Mitarbeiter';
        
        $sql_insert = "INSERT INTO users (azure_oid, full_name, email, username, role, created_at, updated_at) 
                      VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
        $stmt_insert = $conn->prepare($sql_insert);
        if (!$stmt_insert) throw new Exception("Prepare failed (insert user): " . $conn->error);
        $stmt_insert->bind_param("sssss", $azure_oid, $display_name_from_session, $username_from_session, 
                                $username_from_session, $standardrolle);
        $stmt_insert->execute();
        $user_id = $conn->insert_id;
        $stmt_insert->close();
        
        // Set default values for new user
        $existing_user = [
            'user_id' => $user_id,
            'azure_oid' => $azure_oid,
            'full_name' => $display_name_from_session,
            'email' => $username_from_session,
            'role' => $standardrolle,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'mfa_enabled' => false,
            'mfa_setup_completed' => false,
            'mfa_backup_codes_viewed' => false
        ];
    }

    // MFA CHECK
    $mfa_enabled = (bool)$existing_user['mfa_enabled'];
    $mfa_required = is_mfa_required_for_role($existing_user['role']);
    $mfa_setup_completed = (bool)$existing_user['mfa_setup_completed'];
    
    // Check grace period for MFA
    $in_grace_period = false;
    if ($mfa_required && !$mfa_enabled) {
        $created_at = strtotime($existing_user['created_at']);
        $grace_end = $created_at + (MFA_GRACE_PERIOD_DAYS * 86400);
        $in_grace_period = time() < $grace_end;
    }

    // Check if MFA verification is needed
    $needs_mfa_verification = false;
    $mfa_verified = false;
    
    if ($mfa_enabled && $mfa_setup_completed) {
        // Check if already verified in this session
        if (isset($_SESSION['mfa_verified']) && $_SESSION['mfa_verified'] === true) {
            // Check if MFA session hasn't expired
            $mfa_verified_at = $_SESSION['mfa_verified_at'] ?? 0;
            if (time() - $mfa_verified_at < MFA_SESSION_LIFETIME) {
                $mfa_verified = true;
            } else {
                // MFA session expired
                unset($_SESSION['mfa_verified']);
                unset($_SESSION['mfa_verified_at']);
                $needs_mfa_verification = true;
            }
        } else {
            $needs_mfa_verification = true;
        }
    }

    // Store user ID in session for MFA verification
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_role'] = $existing_user['role'];
    $_SESSION['user_email'] = $existing_user['email'];
    $_SESSION['user_full_name'] = $existing_user['full_name'];
    
    $conn->commit();
    
    // Log successful authentication
    structured_log('info', 'User authenticated', [
        'user_id' => $user_id,
        'email' => $existing_user['email'],
        'mfa_enabled' => $mfa_enabled,
        'mfa_required' => $mfa_required,
        'needs_mfa_verification' => $needs_mfa_verification
    ]);

    // Prepare response
    $response = [
        'message' => 'Sync complete',
        'user' => [
            'user_id' => $user_id,
            'email' => $existing_user['email'],
            'role' => $existing_user['role']
        ],
        'mfa' => [
            'enabled' => $mfa_enabled,
            'required' => $mfa_required,
            'setup_completed' => $mfa_setup_completed,
            'needs_verification' => $needs_mfa_verification,
            'verified' => $mfa_verified,
            'in_grace_period' => $in_grace_period,
            'grace_period_days' => $in_grace_period ? MFA_GRACE_PERIOD_DAYS : null,
            'backup_codes_viewed' => (bool)$existing_user['mfa_backup_codes_viewed']
        ]
    ];
    
    // If MFA is required but not set up and not in grace period, indicate setup required
    if ($mfa_required && !$mfa_enabled && !$in_grace_period) {
        $response['mfa']['setup_required'] = true;
        $response['message'] = 'MFA setup required';
    }
    
    send_response(200, $response);

} catch (Exception $e) {
    $conn->rollback();
    structured_log('error', 'Login failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    send_response(500, ['message' => 'Login failed', 'error' => $e->getMessage()]);
}

/**
 * Send standardized JSON response
 */
function send_response($status_code, $data) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}
?>