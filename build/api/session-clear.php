<?php
/**
 * Session Clear Endpoint
 * Based on working health.php
 */

// Security
define('API_GUARD', true);

// Include required files
require_once __DIR__ . '/security-headers.php';

// Set response header
header('Content-Type: text/plain');

// Start session
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// Clear session
$_SESSION = array();

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Response
echo "Session cleared successfully!\n";
echo "Please return to: https://aze.mikropartner.de/\n";
echo "\nNote: You may need to clear your browser cookies if the problem persists.";
?>