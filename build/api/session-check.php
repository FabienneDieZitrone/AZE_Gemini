<?php
/**
 * Session Debug Endpoint
 * Checks current session status
 */

// Start session with same settings as auth_helpers
ini_set('session.cookie_lifetime', 0);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'] ?? '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://aze.mikropartner.de');
header('Access-Control-Allow-Credentials: true');

$response = [
    'session_id' => session_id(),
    'session_status' => session_status(),
    'session_data' => $_SESSION,
    'cookies' => $_COOKIE,
    'server_info' => [
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'not set',
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'not set',
        'HTTPS' => $_SERVER['HTTPS'] ?? 'not set'
    ]
];

// Check if user is logged in
if (isset($_SESSION['user'])) {
    $response['logged_in'] = true;
    $response['user_info'] = [
        'id' => $_SESSION['user']['id'] ?? 'not set',
        'email' => $_SESSION['user']['email'] ?? 'not set',
        'name' => $_SESSION['user']['name'] ?? 'not set',
        'role' => $_SESSION['user']['role'] ?? 'not set'
    ];
} else {
    $response['logged_in'] = false;
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>