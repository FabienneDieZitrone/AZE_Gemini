<?php
// Minimal auth status check - returns 401 if no session
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://aze.mikropartner.de');
header('Access-Control-Allow-Credentials: true');

session_start();

$hasUser = isset($_SESSION['user']) && !empty($_SESSION['user']['oid']);

if ($hasUser) {
    http_response_code(204);
    exit;
}

http_response_code(401);
exit;
?>
