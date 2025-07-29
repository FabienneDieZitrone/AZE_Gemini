<?php
/**
 * DEBUG VERSION - Comprehensive debugging for time-entries.php
 * This will find the EXACT point of failure
 */

// Custom error handler that logs EVERYTHING
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'error_level' => $errno,
        'message' => $errstr,
        'file' => $errfile,
        'line' => $errline,
        'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
    ];
    file_put_contents(__DIR__ . '/debug_error.log', json_encode($log_entry) . "\n", FILE_APPEND);
    return true; // Don't execute PHP's internal error handler
});

// Exception handler
set_exception_handler(function($exception) {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => 'exception',
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ];
    file_put_contents(__DIR__ . '/debug_exception.log', json_encode($log_entry) . "\n", FILE_APPEND);
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Exception: ' . $exception->getMessage()]);
    exit;
});

// Shutdown handler for fatal errors
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'fatal',
            'error' => $error
        ];
        file_put_contents(__DIR__ . '/debug_fatal.log', json_encode($log_entry) . "\n", FILE_APPEND);
        
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode(['fatal_error' => $error]);
        exit;
    }
});

// Start debugging
$debug_log = [];
$debug_log[] = ['step' => 'start', 'time' => microtime(true), 'memory' => memory_get_usage()];

// Log ALL superglobals
$debug_log[] = [
    'step' => 'superglobals',
    'SERVER' => $_SERVER,
    'GET' => $_GET,
    'POST' => $_POST,
    'COOKIE' => $_COOKIE,
    'SESSION' => isset($_SESSION) ? $_SESSION : 'not_started',
    'FILES' => $_FILES,
    'ENV' => $_ENV,
    'php_input' => file_get_contents('php://input'),
    'headers' => getallheaders()
];

// Enable ALL error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);

// Check if files exist
$required_files = [
    'error-handler.php',
    'security-headers.php',
    'db-init.php',
    'auth_helpers.php',
    'validation.php'
];

foreach ($required_files as $file) {
    $full_path = __DIR__ . '/' . $file;
    $debug_log[] = [
        'step' => 'check_file',
        'file' => $file,
        'exists' => file_exists($full_path),
        'readable' => is_readable($full_path),
        'path' => $full_path
    ];
    
    if (!file_exists($full_path)) {
        die(json_encode(['error' => "Required file missing: $file"]));
    }
}

// Include files one by one with debugging
try {
    $debug_log[] = ['step' => 'include_error_handler', 'time' => microtime(true)];
    require_once __DIR__ . '/error-handler.php';
    
    $debug_log[] = ['step' => 'include_security_headers', 'time' => microtime(true)];
    require_once __DIR__ . '/security-headers.php';
    
    $debug_log[] = ['step' => 'include_db_init', 'time' => microtime(true)];
    require_once __DIR__ . '/db-init.php';
    
    $debug_log[] = ['step' => 'include_auth_helpers', 'time' => microtime(true)];
    require_once __DIR__ . '/auth_helpers.php';
    
    $debug_log[] = ['step' => 'include_validation', 'time' => microtime(true)];
    require_once __DIR__ . '/validation.php';
} catch (Exception $e) {
    $debug_log[] = ['step' => 'include_error', 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()];
    die(json_encode(['error' => 'Include failed', 'details' => $debug_log]));
}

// Initialize API
try {
    $debug_log[] = ['step' => 'initialize_api', 'time' => microtime(true)];
    initialize_api();
    $debug_log[] = ['step' => 'initialize_api_complete', 'time' => microtime(true)];
} catch (Exception $e) {
    $debug_log[] = ['step' => 'initialize_api_error', 'error' => $e->getMessage()];
    die(json_encode(['error' => 'Initialize API failed', 'details' => $debug_log]));
}

// Verify session
try {
    $debug_log[] = ['step' => 'verify_session', 'time' => microtime(true)];
    $user_from_session = verify_session_and_get_user();
    $debug_log[] = ['step' => 'verify_session_complete', 'user' => $user_from_session];
} catch (Exception $e) {
    $debug_log[] = ['step' => 'verify_session_error', 'error' => $e->getMessage()];
    die(json_encode(['error' => 'Session verification failed', 'details' => $debug_log]));
}

$method = $_SERVER['REQUEST_METHOD'];
$debug_log[] = ['step' => 'method_detected', 'method' => $method];

// Handle POST request with extensive debugging
if ($method === 'POST') {
    $debug_log[] = ['step' => 'handle_post_start', 'time' => microtime(true)];
    
    // Check if this is a stop action
    if (isset($_GET['action']) && $_GET['action'] === 'stop') {
        $debug_log[] = ['step' => 'stop_action_detected'];
        die(json_encode(['debug' => 'Stop action would be handled', 'log' => $debug_log]));
    }
    
    // Get raw input
    $raw_input = file_get_contents('php://input');
    $debug_log[] = ['step' => 'raw_input', 'length' => strlen($raw_input), 'content' => $raw_input];
    
    // Try to decode JSON
    $json_data = json_decode($raw_input, true);
    $json_error = json_last_error();
    $debug_log[] = [
        'step' => 'json_decode',
        'success' => $json_error === JSON_ERROR_NONE,
        'error' => json_last_error_msg(),
        'data' => $json_data
    ];
    
    // Test validation
    try {
        $debug_log[] = ['step' => 'validation_start'];
        $required_fields = ['userId', 'username', 'date', 'startTime', 'location', 'role', 'updatedBy'];
        $optional_fields = ['stopTime' => null];
        
        // Check if InputValidator exists
        if (!class_exists('InputValidator')) {
            $debug_log[] = ['step' => 'validation_error', 'error' => 'InputValidator class not found'];
            die(json_encode(['error' => 'InputValidator not found', 'debug' => $debug_log]));
        }
        
        // Check if method exists
        if (!method_exists('InputValidator', 'validateJsonInput')) {
            $debug_log[] = ['step' => 'validation_error', 'error' => 'validateJsonInput method not found'];
            die(json_encode(['error' => 'validateJsonInput not found', 'debug' => $debug_log]));
        }
        
        $data = InputValidator::validateJsonInput($required_fields, $optional_fields);
        $debug_log[] = ['step' => 'validation_complete', 'data' => $data];
        
    } catch (InvalidArgumentException $e) {
        $debug_log[] = ['step' => 'validation_invalid_argument', 'error' => $e->getMessage()];
        die(json_encode(['error' => 'Validation failed', 'message' => $e->getMessage(), 'debug' => $debug_log]));
    } catch (Exception $e) {
        $debug_log[] = [
            'step' => 'validation_exception',
            'error' => $e->getMessage(),
            'type' => get_class($e),
            'trace' => $e->getTraceAsString()
        ];
        die(json_encode(['error' => 'Validation exception', 'debug' => $debug_log]));
    }
    
    // If we get here, validation passed
    $debug_log[] = ['step' => 'post_validation_passed'];
    
    // Test database connection
    if (!isset($conn)) {
        $debug_log[] = ['step' => 'db_error', 'error' => '$conn not set'];
        die(json_encode(['error' => 'Database connection not available', 'debug' => $debug_log]));
    }
    
    if (!$conn instanceof mysqli) {
        $debug_log[] = ['step' => 'db_error', 'error' => '$conn is not mysqli instance', 'type' => gettype($conn)];
        die(json_encode(['error' => 'Invalid database connection', 'debug' => $debug_log]));
    }
    
    // Test prepare statement
    $sql = "INSERT INTO time_entries (user_id, username, date, start_time, stop_time, location, role, updated_by, updated_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    $debug_log[] = ['step' => 'prepare_sql', 'sql' => $sql];
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $debug_log[] = [
            'step' => 'prepare_failed',
            'error' => $conn->error,
            'errno' => $conn->errno,
            'sqlstate' => $conn->sqlstate
        ];
        die(json_encode(['error' => 'Prepare failed', 'debug' => $debug_log]));
    }
    
    $debug_log[] = ['step' => 'prepare_success'];
    
    // Success - return debug info
    die(json_encode([
        'success' => 'Debug complete - no errors found',
        'debug' => $debug_log,
        'would_insert' => $data
    ]));
}

// For other methods
die(json_encode([
    'method' => $method,
    'debug' => $debug_log,
    'message' => 'Only POST method is debugged in detail'
]));