<?php
/**
 * Minimales Debug-Script um den 500 Error zu finden
 * Testet Schritt für Schritt die Komponenten
 */

// Alles anzeigen
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-errors.log');

// Custom error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo "ERROR [$errno]: $errstr in $errfile on line $errline\n";
    return true;
});

// Shutdown handler für fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "FATAL ERROR: {$error['message']} in {$error['file']} on line {$error['line']}\n";
    }
});

echo "=== Minimal Debug für time-entries.php ===\n\n";

// Step 1: Check basic PHP functionality
echo "Step 1: PHP Basic Check\n";
echo "PHP Version: " . phpversion() . "\n";
echo "SAPI: " . php_sapi_name() . "\n\n";

// Step 2: Check includes one by one
echo "Step 2: Testing includes...\n";

// Test error-handler.php
echo "Loading error-handler.php... ";
if (file_exists(__DIR__ . '/error-handler.php')) {
    try {
        require_once __DIR__ . '/error-handler.php';
        echo "OK\n";
    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    } catch (Error $e) {
        echo "FATAL: " . $e->getMessage() . "\n";
    }
} else {
    echo "FILE NOT FOUND\n";
}

// Test security-headers.php
echo "Loading security-headers.php... ";
if (file_exists(__DIR__ . '/security-headers.php')) {
    try {
        require_once __DIR__ . '/security-headers.php';
        echo "OK\n";
    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    } catch (Error $e) {
        echo "FATAL: " . $e->getMessage() . "\n";
    }
} else {
    echo "FILE NOT FOUND\n";
}

// Test db-init.php
echo "Loading db-init.php... ";
if (file_exists(__DIR__ . '/db-init.php')) {
    try {
        require_once __DIR__ . '/db-init.php';
        echo "OK\n";
        if (isset($conn)) {
            echo "  - Database connection object created\n";
        } else {
            echo "  - WARNING: \$conn not set\n";
        }
    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    } catch (Error $e) {
        echo "FATAL: " . $e->getMessage() . "\n";
    }
} else {
    echo "FILE NOT FOUND\n";
}

// Test auth_helpers.php
echo "Loading auth_helpers.php... ";
if (file_exists(__DIR__ . '/auth_helpers.php')) {
    try {
        require_once __DIR__ . '/auth_helpers.php';
        echo "OK\n";
        // Check if functions exist
        $functions = ['initialize_api', 'send_response', 'start_secure_session', 'verify_session_and_get_user'];
        foreach ($functions as $func) {
            if (function_exists($func)) {
                echo "  - Function $func exists\n";
            } else {
                echo "  - WARNING: Function $func missing\n";
            }
        }
    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    } catch (Error $e) {
        echo "FATAL: " . $e->getMessage() . "\n";
    }
} else {
    echo "FILE NOT FOUND\n";
}

// Test validation.php
echo "Loading validation.php... ";
if (file_exists(__DIR__ . '/validation.php')) {
    try {
        require_once __DIR__ . '/validation.php';
        echo "OK\n";
        if (class_exists('InputValidator')) {
            echo "  - InputValidator class exists\n";
        } else {
            echo "  - WARNING: InputValidator class missing\n";
        }
    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    } catch (Error $e) {
        echo "FATAL: " . $e->getMessage() . "\n";
    }
} else {
    echo "FILE NOT FOUND\n";
}

// Step 3: Test POST data handling
echo "\nStep 3: Testing POST data handling...\n";
$test_json = '{"userId":1,"username":"Test User","date":"2025-07-29","startTime":"09:00:00","stopTime":"17:00:00","location":"Office","role":"Mitarbeiter","updatedBy":"Test User"}';
echo "Test JSON: " . substr($test_json, 0, 50) . "...\n";

$parsed = json_decode($test_json, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "JSON parsing: OK\n";
} else {
    echo "JSON parsing: FAILED - " . json_last_error_msg() . "\n";
}

// Step 4: Test session
echo "\nStep 4: Testing session...\n";
if (function_exists('start_secure_session')) {
    try {
        start_secure_session();
        echo "Session started successfully\n";
        echo "Session ID: " . session_id() . "\n";
    } catch (Exception $e) {
        echo "Session start failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "start_secure_session function not available\n";
}

// Step 5: Check for specific errors in time-entries.php
echo "\nStep 5: Checking time-entries.php syntax...\n";
$syntax_check = shell_exec('php -l ' . escapeshellarg(__DIR__ . '/time-entries.php') . ' 2>&1');
echo $syntax_check . "\n";

// Step 6: Memory and limits
echo "\nStep 6: PHP Limits:\n";
echo "Memory limit: " . ini_get('memory_limit') . "\n";
echo "Max execution time: " . ini_get('max_execution_time') . "\n";
echo "Post max size: " . ini_get('post_max_size') . "\n";

echo "\n=== Debug Complete ===\n";
?>