<?php
/**
 * CSRF Token Endpoint
 * Generiert und validiert CSRF-Tokens für sichere Formulare
 */

// Define API guard constant
define('API_GUARD', true);

require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/security-middleware.php';
require_once __DIR__ . '/csrf-middleware.php';
require_once __DIR__ . '/rate-limiting.php';

initialize_api();

// Apply security headers
initSecurityMiddleware();

// Apply rate limiting for CSRF endpoint
checkRateLimit('csrf');

// CSRF token functions are now handled by csrf-middleware.php

// Handle request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Generate new token using enhanced middleware
    $token = generateCsrfToken();
    
    // Add rate limiting headers
    addRateLimitHeaders('csrf');
    
    send_response(200, [
        'csrf_token' => $token,
        'token_name' => 'csrf_token',
        'expires_in' => 3600 // 1 hour
    ]);
} else {
    send_response(405, ['message' => 'Method not allowed']);
}