<?php
// NO OUTPUT before header()!
error_reporting(0);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/auth-oauth-client.php';

// Start basic session
session_name('AZE_SESSION');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'] ?? '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
@session_start();

$authUrl = getAuthorizationUrl();
header('Location: ' . $authUrl, true, 302);
exit;
?>
