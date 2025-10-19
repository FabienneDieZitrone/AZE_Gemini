<?php
/**
 * OAuth2 Start: Redirects user to Microsoft login
 *
 * CRITICAL FIX (2025-10-19): session_name() MUST be the ABSOLUTE FIRST LINE
 * to prevent PHP from auto-starting a session with default name PHPSESSID!
 */

// CRITICAL: Set session name as ABSOLUTE FIRST LINE (before ANY other code!)
session_name('AZE_SESSION');

// Start output buffering immediately
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/auth-oauth-client.php';

// Clean any output from require statements
ob_end_clean();

// Start fresh buffer for error handling
ob_start();
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',  // Empty = current domain automatically
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

try {
    // Generate CSRF state token
    $state = bin2hex(random_bytes(32));
    $_SESSION['oauth2state'] = $state;

    // Build authorization URL
    $authUrl = OAUTH_AUTHORIZE_ENDPOINT . '?' . http_build_query([
        'client_id' => OAUTH_CLIENT_ID,
        'response_type' => 'code',
        'redirect_uri' => OAUTH_REDIRECT_URI,
        'response_mode' => 'query',
        'scope' => OAUTH_SCOPES,
        'state' => $state
    ]);

    // Clean buffer and send redirect
    ob_end_clean();
    header('Location: ' . $authUrl, true, 302);
    exit;
} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['message' => 'Failed to start OAuth', 'error' => $e->getMessage()]);
    exit;
}