<?php
// Aggressive output buffering and cleaning
ob_start();

// Load dependencies
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/auth-oauth-client.php';

// Clean any output that was generated during require
ob_end_clean();

// Start fresh output buffer
ob_start();

// Start session
session_name('AZE_SESSION');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'] ?? 'aze.mikropartner.de',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

try {
    // Generate authorization URL
    $state = bin2hex(random_bytes(32));
    $_SESSION['oauth2state'] = $state;

    $authUrl = OAUTH_AUTHORIZE_ENDPOINT . '?' . http_build_query([
        'client_id' => OAUTH_CLIENT_ID,
        'response_type' => 'code',
        'redirect_uri' => OAUTH_REDIRECT_URI,
        'response_mode' => 'query',
        'scope' => OAUTH_SCOPES,
        'state' => $state
    ]);

    // Clean output buffer and send redirect
    ob_end_clean();
    header('Location: ' . $authUrl, true, 302);
    exit;
} catch (Throwable $e) {
    ob_end_clean();
    header('Content-Type: text/plain', true, 500);
    echo 'OAuth initialization failed: ' . $e->getMessage();
    exit;
}
