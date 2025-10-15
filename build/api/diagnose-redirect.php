<?php
// Diagnose redirect issue with aggressive output capture
ob_start();

$results = [];

// Test 1: Load config.php
ob_start();
require_once __DIR__ . '/../config.php';
$configOutput = ob_get_clean();
$results['config_bytes'] = strlen($configOutput);
$results['config_hex'] = bin2hex(substr($configOutput, 0, 100));

// Test 2: Load auth_helpers.php
ob_start();
require_once __DIR__ . '/auth_helpers.php';
$helpersOutput = ob_get_clean();
$results['helpers_bytes'] = strlen($helpersOutput);
$results['helpers_hex'] = bin2hex(substr($helpersOutput, 0, 100));

// Test 3: Load auth-oauth-client.php
ob_start();
require_once __DIR__ . '/auth-oauth-client.php';
$oauthOutput = ob_get_clean();
$results['oauth_bytes'] = strlen($oauthOutput);
$results['oauth_hex'] = bin2hex(substr($oauthOutput, 0, 100));

// Test 4: Try to generate auth URL
try {
    session_name('AZE_SESSION');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
    @session_start();
    $url = getAuthorizationUrl();
    $results['auth_url_length'] = strlen($url);
    $results['auth_url_valid'] = str_starts_with($url, 'https://login.microsoftonline.com/');

    // Test 5: Check if headers already sent
    $results['headers_sent'] = headers_sent($file, $line);
    $results['headers_sent_file'] = $file ?? 'none';
    $results['headers_sent_line'] = $line ?? 0;
} catch (Throwable $e) {
    $results['error'] = $e->getMessage();
}

// Output all diagnostic info
$allOutput = ob_get_clean();
header('Content-Type: application/json');
echo json_encode([
    'diagnostics' => $results,
    'total_buffered_bytes' => strlen($allOutput),
    'buffered_hex' => bin2hex(substr($allOutput, 0, 200))
], JSON_PRETTY_PRINT);
