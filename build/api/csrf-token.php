<?php
// Prevent direct access and ensure consistent headers
define('API_GUARD', true);

require_once __DIR__ . '/security-headers.php';
require_once __DIR__ . '/security-middleware.php';
require_once __DIR__ . '/csrf-middleware.php';
require_once __DIR__ . '/auth_helpers.php';

initialize_api();
initSecurityMiddleware();

// Start a secure session to bind the token to the session
start_secure_session();

// Generate or reuse a CSRF token for this session
$token = getCsrfToken(true);

// Respond with JSON token; cookie for double-submit is set by middleware
send_response(200, [
    'csrfToken' => $token
]);
?>

