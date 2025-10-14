<?php
/**
 * OAuth Callback: Exchanges authorization code for tokens and creates user session
 *
 * CRITICAL FIX: Uses output buffering to prevent "headers already sent" errors
 */

// Start output buffering immediately
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/auth-oauth-client.php';

// Clean any output from require statements
ob_end_clean();

// Start fresh buffer for error handling
ob_start();

// Lightweight debug log (temporary)
if (!function_exists('aclog')) {
    function aclog($title, $data = null) {
        $f = __DIR__ . '/callback-debug.log';
        $ts = date('Y-m-d H:i:s');
        $out = "[$ts] $title";
        if ($data !== null) {
            $payload = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE);
            $out .= ": $payload";
        }
        @file_put_contents($f, $out . "\n", FILE_APPEND);
    }
}

// CRITICAL: Set session name BEFORE any session operations!
// Start secure session INLINE to guarantee AZE_SESSION name
session_name('AZE_SESSION');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
} else {
    // Session already active - ensure it's the right one by migrating
    $migrate = $_SESSION ?? null;
    session_write_close();
    @session_start();
    if (is_array($migrate)) {
        foreach (['oauth2state'] as $k) {
            if (isset($migrate[$k])) {
                $_SESSION[$k] = $migrate[$k];
            }
        }
    }
}

// Initialize session timestamps
if (!isset($_SESSION['created_at'])) { $_SESSION['created_at'] = time(); }
if (!isset($_SESSION['last_activity'])) { $_SESSION['last_activity'] = time(); }

try {
    aclog('callback_start', $_GET);

    // Validate authorization code is present
    if (!isset($_GET['code'])) {
        aclog('error', 'No authorization code received');
        ob_end_clean();
        header('Location: /?error=no_code');
        exit;
    }

    $code = $_GET['code'];

    // Validate CSRF state parameter
    if (isset($_SESSION['oauth2state']) && isset($_GET['state'])) {
        if ($_SESSION['oauth2state'] !== $_GET['state']) {
            aclog('error', 'State mismatch - possible CSRF attack');
            ob_end_clean();
            header('Location: /?error=state_mismatch');
            exit;
        }
        unset($_SESSION['oauth2state']); // One-time use
    }

    // Exchange authorization code for tokens
    aclog('exchanging_code');
    $tokens = getTokensFromCode($code);
    $idToken = $tokens['id_token'] ?? '';

    if (empty($idToken)) {
        aclog('error', 'No id_token in response');
        ob_end_clean();
        header('Location: /?error=no_token');
        exit;
    }

    // Decode ID token (JWT) - no signature verification needed (direct from Azure)
    $parts = explode('.', $idToken);
    if (count($parts) !== 3) {
        aclog('error', 'Invalid JWT format');
        ob_end_clean();
        header('Location: /?error=invalid_token');
        exit;
    }

    $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    if (!$payload) {
        aclog('error', 'Failed to decode JWT payload');
        ob_end_clean();
        header('Location: /?error=token_decode_failed');
        exit;
    }

    // Extract user information
    $oid = $payload['oid'] ?? ($payload['sub'] ?? null);
    $name = $payload['name'] ?? '';
    $upn = $payload['preferred_username'] ?? ($payload['email'] ?? '');

    if (!$oid) {
        aclog('error', 'No user ID (oid) in token');
        ob_end_clean();
        header('Location: /?error=no_user_id');
        exit;
    }

    // Store user in session
    $_SESSION['user'] = [
        'oid' => $oid,
        'name' => $name ?: $upn,
        'username' => $upn,
        'azure_oid' => $oid
    ];

    aclog('login_success', $_SESSION['user']);

    // Clean old PHPSESSID cookie BEFORE closing session
    if (isset($_COOKIE['PHPSESSID'])) {
        // Use empty domain to work with current domain
        setcookie('PHPSESSID', '', time() - 3600, '/', '', true, true);
    }

    // Persist session to disk (auto-closes but data is saved)
    // Note: session_write_close() saves and locks the session file
    session_write_close();

    // Clean output buffer and redirect to app
    ob_end_clean();
    header('Location: /');
    exit;

} catch (Throwable $e) {
    aclog('exception', $e->getMessage() . ' | ' . $e->getTraceAsString());
    ob_end_clean();
    header('Location: /?error=auth_failed');
    exit;
}