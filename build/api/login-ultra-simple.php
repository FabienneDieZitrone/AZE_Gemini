<?php
/**
 * Ultra Simple Login - Direct, no dependencies
 */

// Basic headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://aze.mikropartner.de');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit();
}

// Start session directly
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// Check session
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['message' => 'No session']);
    exit();
}

// Minimal response
$response = [
    'currentUser' => [
        'id' => 1,
        'name' => $_SESSION['user']['name'] ?? 'Test User',
        'role' => 'Mitarbeiter',
        'azureOid' => $_SESSION['user']['oid'] ?? 'test'
    ],
    'users' => [],
    'masterData' => new stdClass(),
    'timeEntries' => [],
    'approvalRequests' => [],
    'history' => [],
    'globalSettings' => [
        'overtimeThreshold' => 8.0,
        'changeReasons' => ['Vergessen', 'Fehler', 'Nachträglich'],
        'locations' => ['Büro', 'Home-Office', 'Außendienst']
    ]
];

echo json_encode($response);
?>