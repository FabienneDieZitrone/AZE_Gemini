<?php
/**
 * CSRF Protection Module
 * Provides CSRF token generation and validation
 * 
 * Usage: require_once 'csrf-protection.php';
 */

// Prevent direct access
if (!defined('API_GUARD')) {
    http_response_code(403);
    die('Direct access forbidden');
}

/**
 * Generate a new CSRF token for the current session
 * @return string The generated token
 */
function generateCsrfToken() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new Exception('Session must be started before generating CSRF token');
    }
    
    // Generate a cryptographically secure random token
    $token = bin2hex(random_bytes(32));
    
    // Store in session with timestamp
    $_SESSION['csrf_token'] = $token;
    $_SESSION['csrf_token_time'] = time();
    
    return $token;
}

/**
 * Get the current CSRF token, generating one if it doesn't exist
 * @return string The CSRF token
 */
function getCsrfToken() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new Exception('Session must be started before getting CSRF token');
    }
    
    // Check if token exists and is not expired (24 hours)
    if (!isset($_SESSION['csrf_token']) || 
        !isset($_SESSION['csrf_token_time']) ||
        (time() - $_SESSION['csrf_token_time']) > 86400) {
        return generateCsrfToken();
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Validate a CSRF token
 * @param string $token The token to validate
 * @return bool True if valid, false otherwise
 */
function validateCsrfToken($token) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    
    if (empty($token) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    // Check token expiration (24 hours)
    if (!isset($_SESSION['csrf_token_time']) || 
        (time() - $_SESSION['csrf_token_time']) > 86400) {
        return false;
    }
    
    // Constant-time comparison to prevent timing attacks
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Validate CSRF token from request
 * Checks both header and body for the token
 * @return bool True if valid
 */
function validateCsrfFromRequest() {
    $token = null;
    
    // Check custom header first
    $headers = getallheaders();
    if (isset($headers['X-CSRF-Token'])) {
        $token = $headers['X-CSRF-Token'];
    }
    
    // Check POST body as fallback
    if (!$token && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['csrf_token'])) {
            $token = $input['csrf_token'];
        }
    }
    
    return validateCsrfToken($token);
}

/**
 * Require valid CSRF token for the request
 * Terminates with 403 if invalid
 */
function requireCsrfToken() {
    // Skip CSRF check for safe methods
    $safeMethods = ['GET', 'HEAD', 'OPTIONS'];
    if (in_array($_SERVER['REQUEST_METHOD'], $safeMethods, true)) {
        return;
    }
    
    if (!validateCsrfFromRequest()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'CSRF validation failed',
            'message' => 'Invalid or missing CSRF token'
        ]);
        exit();
    }
}

/**
 * Add CSRF token to response (for initial page load)
 * @param array &$response The response array to modify
 */
function addCsrfTokenToResponse(&$response) {
    $response['csrfToken'] = getCsrfToken();
}