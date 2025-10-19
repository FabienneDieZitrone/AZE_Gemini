<?php
/**
 * Auth Status Check Endpoint - Minimal version without middleware
 * Returns:
 *  - 204 No Content if a valid session exists
 *  - 401 Unauthorized if no valid session exists
 *
 * CRITICAL FIX (2025-10-19): session_name() MUST be the ABSOLUTE FIRST LINE
 */

// CRITICAL: Set session name as ABSOLUTE FIRST LINE (before ANY other code!)
session_name('AZE_SESSION');

// CORS headers
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === 'https://aze.mikropartner.de' || $origin === 'http://localhost:5173') {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
}

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Methods: GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    http_response_code(200);
    exit;
}
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',  // MUST be empty string for cookies to work correctly
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// Check if user exists in session
$hasUser = isset($_SESSION['user']) && !empty($_SESSION['user']['oid']);

if ($hasUser) {
    // Valid session
    http_response_code(204);
    exit;
}

// No valid session
http_response_code(401);
exit;
