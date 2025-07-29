<?php
/**
 * Trace POST Error - Findet den exakten Punkt des 500 Errors
 */

// Maximale Fehlerausgabe
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log-Datei
$log_file = __DIR__ . '/post-error-trace.log';
file_put_contents($log_file, "=== TRACE START: " . date('Y-m-d H:i:s') . " ===\n");

function trace_log($msg) {
    global $log_file;
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $caller = isset($trace[1]) ? basename($trace[1]['file']) . ':' . $trace[1]['line'] : 'unknown';
    file_put_contents($log_file, "[" . date('H:i:s') . "] [$caller] $msg\n", FILE_APPEND);
}

// Override functions to trace execution
if (!function_exists('initialize_api_original')) {
    // Backup original functions
    trace_log("Setting up function overrides...");
    
    // Include originals first
    require_once __DIR__ . '/auth_helpers.php';
    
    // Rename originals
    runkit_function_copy('initialize_api', 'initialize_api_original');
    runkit_function_copy('send_response', 'send_response_original');
    runkit_function_copy('verify_session_and_get_user', 'verify_session_and_get_user_original');
    
    // Override with traced versions
    runkit_function_remove('initialize_api');
    runkit_function_add('initialize_api', '', 'trace_log("initialize_api() called"); initialize_api_original();');
    
    runkit_function_remove('send_response');
    runkit_function_add('send_response', '$code, $data=null', 'trace_log("send_response($code) called"); send_response_original($code, $data);');
    
    runkit_function_remove('verify_session_and_get_user');
    runkit_function_add('verify_session_and_get_user', '', 'trace_log("verify_session_and_get_user() called"); return verify_session_and_get_user_original();');
}

// Simulate POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_ORIGIN'] = 'https://aze.mikropartner.de';
$_SERVER['CONTENT_TYPE'] = 'application/json';

// Test data
$post_data = [
    'userId' => 1,
    'username' => 'Test User',
    'date' => '2025-07-29',
    'startTime' => '09:00:00',
    'stopTime' => '17:00:00',
    'location' => 'Office',
    'role' => 'Mitarbeiter',
    'updatedBy' => 'Test User'
];

// Mock session
session_start();
$_SESSION['user'] = [
    'id' => 1,
    'oid' => 'test-oid',
    'name' => 'Test User'
];
$_SESSION['last_activity'] = time();

trace_log("Request setup complete");
trace_log("POST data: " . json_encode($post_data));

// Create temp file for input
$temp = tmpfile();
fwrite($temp, json_encode($post_data));
fseek($temp, 0);

// Override stdin
$GLOBALS['stdin_override'] = $temp;

// Custom stream wrapper for testing
class TestInputStream {
    private $data;
    private $position = 0;
    
    public function stream_open($path, $mode, $options, &$opened_path) {
        if ($path === 'php://input') {
            $this->data = json_encode($GLOBALS['test_post_data']);
            $this->position = 0;
            return true;
        }
        return false;
    }
    
    public function stream_read($count) {
        $result = substr($this->data, $this->position, $count);
        $this->position += strlen($result);
        trace_log("php://input read: " . strlen($result) . " bytes");
        return $result;
    }
    
    public function stream_eof() {
        return $this->position >= strlen($this->data);
    }
    
    public function stream_stat() {
        return [];
    }
}

// Set global data
$GLOBALS['test_post_data'] = $post_data;

// Register wrapper
stream_wrapper_unregister("php");
stream_wrapper_register("php", "TestInputStream");

trace_log("Starting time-entries.php include...");

// Capture output
ob_start();
$error_output = '';

try {
    // Set custom error handler
    set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$error_output) {
        $error_output .= "ERROR[$errno]: $errstr in " . basename($errfile) . ":$errline\n";
        trace_log("ERROR[$errno]: $errstr in " . basename($errfile) . ":$errline");
        return true;
    });
    
    // Include the file
    trace_log("Including time-entries.php...");
    require __DIR__ . '/time-entries.php';
    trace_log("Include completed without exception");
    
} catch (Exception $e) {
    trace_log("Exception: " . $e->getMessage() . " in " . basename($e->getFile()) . ":" . $e->getLine());
    $error_output .= "Exception: " . $e->getMessage() . "\n";
} catch (Error $e) {
    trace_log("Fatal Error: " . $e->getMessage() . " in " . basename($e->getFile()) . ":" . $e->getLine());
    $error_output .= "Fatal Error: " . $e->getMessage() . "\n";
} finally {
    $output = ob_get_clean();
    restore_error_handler();
    stream_wrapper_restore("php");
}

trace_log("Output captured: " . strlen($output) . " bytes");
trace_log("Errors: " . ($error_output ?: 'none'));

// Show results
echo "=== Trace Results ===\n\n";
echo "Log file: $log_file\n\n";
echo "Output:\n$output\n\n";
echo "Errors:\n$error_output\n\n";
echo "Log contents:\n";
echo file_get_contents($log_file);

// Note about runkit
echo "\n\nNOTE: This script requires the runkit extension for function overriding.\n";
echo "Alternative: Manually add trace_log() calls to time-entries.php\n";
?>