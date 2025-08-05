<?php
/**
 * Force Logout - Clear all sessions
 */

// Start session with correct parameters
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

// Completely destroy session
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear any other cookies
setcookie('PHPSESSID', '', time() - 3600, '/', '.mikropartner.de', true, true);
setcookie('PHPSESSID', '', time() - 3600, '/', 'aze.mikropartner.de', true, true);

// Redirect to home page
header('Location: /');
exit();
?>