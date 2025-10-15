<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/auth-oauth-client.php';

session_name('AZE_SESSION');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
@session_start();

try {
    $url = getAuthorizationUrl();
    header('Location: ' . $url, true, 302);
} catch (Throwable $e) {
    header('Content-Type: text/plain', true, 500);
    echo 'Error: ' . $e->getMessage();
}
exit;
?>
