<?php
/**
 * Server Diagnostic Tool
 */

header('Content-Type: text/plain');

echo "=== SERVER DIAGNOSTIC REPORT ===\n\n";

// PHP Version
echo "PHP Version: " . phpversion() . "\n";
echo "PHP SAPI: " . php_sapi_name() . "\n\n";

// Session Configuration
echo "SESSION CONFIGURATION:\n";
echo "session.save_handler: " . ini_get('session.save_handler') . "\n";
echo "session.save_path: " . ini_get('session.save_path') . "\n";
echo "session.use_cookies: " . ini_get('session.use_cookies') . "\n";
echo "session.cookie_secure: " . ini_get('session.cookie_secure') . "\n";
echo "session.cookie_httponly: " . ini_get('session.cookie_httponly') . "\n";
echo "session.cookie_samesite: " . ini_get('session.cookie_samesite') . "\n\n";

// File Permissions
echo "FILE PERMISSIONS:\n";
$files = [
    'health.php' => __DIR__ . '/health.php',
    'login.php' => __DIR__ . '/login.php',
    'this file' => __FILE__
];

foreach ($files as $name => $file) {
    if (file_exists($file)) {
        $perms = fileperms($file);
        echo "$name: " . substr(sprintf('%o', $perms), -4) . " ";
        echo "Owner: " . fileowner($file) . " ";
        echo "Group: " . filegroup($file) . "\n";
    } else {
        echo "$name: NOT FOUND\n";
    }
}

echo "\n";

// Error Reporting
echo "ERROR REPORTING:\n";
echo "error_reporting: " . error_reporting() . "\n";
echo "display_errors: " . ini_get('display_errors') . "\n";
echo "log_errors: " . ini_get('log_errors') . "\n";
echo "error_log: " . ini_get('error_log') . "\n\n";

// Extensions
echo "LOADED EXTENSIONS:\n";
$extensions = get_loaded_extensions();
echo implode(', ', $extensions) . "\n\n";

// Test Session
echo "SESSION TEST:\n";
session_start();
$_SESSION['test'] = 'diagnostic_' . time();
echo "Session ID: " . session_id() . "\n";
echo "Session test value set: " . $_SESSION['test'] . "\n";

echo "\n=== END OF DIAGNOSTIC ===\n";
?>