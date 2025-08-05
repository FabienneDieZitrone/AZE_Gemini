<?php
/**
 * CSRF Token Endpoint
 * Generiert und validiert CSRF-Tokens für sichere Formulare
 */

// Define API guard constant
define('API_GUARD', true);

require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/security-middleware.php';

initialize_api();

// Apply security headers
initSecurityMiddleware();

// CSRF Token Funktionen
function generateCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        start_secure_session();
    }
    
    // Generate a cryptographically secure random token
    $token = bin2hex(random_bytes(32));
    
    // Store token in session with timestamp
    $_SESSION['csrf_token'] = $token;
    $_SESSION['csrf_token_time'] = time();
    
    return $token;
}

function validateCsrfToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        start_secure_session();
    }
    
    // Check if token exists in session
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    // Check token age (24 hours)
    if (time() - $_SESSION['csrf_token_time'] > 86400) {
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
        return false;
    }
    
    // Validate token
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Handle request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Generate new token
    $token = generateCsrfToken();
    send_response(200, ['csrf_token' => $token]);
} else {
    send_response(405, ['message' => 'Method not allowed']);
}