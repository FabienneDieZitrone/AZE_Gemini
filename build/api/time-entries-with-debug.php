<?php
/**
 * Temporary version of time-entries.php with debug validation
 */

// Enhanced error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log everything
$debug_info = [
    'start_time' => microtime(true),
    'memory_start' => memory_get_usage(),
    'steps' => []
];

function debug_log($step, $data = null) {
    global $debug_info;
    $debug_info['steps'][] = [
        'step' => $step,
        'data' => $data,
        'time' => microtime(true) - $debug_info['start_time'],
        'memory' => memory_get_usage()
    ];
}

// Error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    debug_log('php_error', [
        'errno' => $errno,
        'errstr' => $errstr,
        'errfile' => basename($errfile),
        'errline' => $errline
    ]);
    return true;
});

debug_log('start');

// Include files
try {
    debug_log('include_start');
    require_once __DIR__ . '/error-handler.php';
    require_once __DIR__ . '/security-headers.php';
    require_once __DIR__ . '/db-init.php';
    require_once __DIR__ . '/auth_helpers.php';
    require_once __DIR__ . '/validation-debug.php'; // Use debug version
    debug_log('include_complete');
} catch (Exception $e) {
    debug_log('include_error', $e->getMessage());
    die(json_encode(['error' => 'Include failed', 'debug' => $debug_info]));
}

// Initialize
try {
    debug_log('initialize_api');
    initialize_api();
    debug_log('initialize_api_complete');
} catch (Exception $e) {
    debug_log('initialize_api_error', $e->getMessage());
    die(json_encode(['error' => 'Initialize failed', 'debug' => $debug_info]));
}

// Verify session
try {
    debug_log('verify_session');
    $user_from_session = verify_session_and_get_user();
    debug_log('verify_session_complete', ['user_id' => $user_from_session['id'] ?? null]);
} catch (Exception $e) {
    debug_log('verify_session_error', $e->getMessage());
    die(json_encode(['error' => 'Session verification failed', 'debug' => $debug_info]));
}

$method = $_SERVER['REQUEST_METHOD'];
debug_log('method', $method);

// Only handle POST for debugging
if ($method !== 'POST') {
    die(json_encode(['error' => 'Only POST is being debugged', 'method' => $method]));
}

// Handle POST
debug_log('handle_post_start');

// Check for stop action
if (isset($_GET['action']) && $_GET['action'] === 'stop') {
    debug_log('stop_action');
    die(json_encode(['action' => 'stop', 'debug' => $debug_info]));
}

// Get raw input
$raw_input = file_get_contents('php://input');
debug_log('raw_input', ['length' => strlen($raw_input), 'first_100' => substr($raw_input, 0, 100)]);

// Validate input
try {
    debug_log('validation_start');
    $required_fields = ['userId', 'username', 'date', 'startTime', 'location', 'role', 'updatedBy'];
    $optional_fields = ['stopTime' => null];
    
    $data = InputValidator::validateJsonInput($required_fields, $optional_fields);
    debug_log('validation_complete', $data);
    
    // Get validation debug log
    $validation_log = InputValidator::getDebugLog();
    debug_log('validation_internal_log', $validation_log);
    
} catch (InvalidArgumentException $e) {
    debug_log('validation_invalid_argument', $e->getMessage());
    $validation_log = InputValidator::getDebugLog();
    die(json_encode([
        'error' => 'Validation failed: ' . $e->getMessage(),
        'debug' => $debug_info,
        'validation_debug' => $validation_log
    ]));
} catch (Exception $e) {
    debug_log('validation_exception', [
        'message' => $e->getMessage(),
        'type' => get_class($e),
        'trace' => $e->getTraceAsString()
    ]);
    $validation_log = InputValidator::getDebugLog();
    die(json_encode([
        'error' => 'Validation exception: ' . $e->getMessage(),
        'debug' => $debug_info,
        'validation_debug' => $validation_log
    ]));
}

// Additional validation
debug_log('additional_validation_start');

if (!InputValidator::isValidId($data['userId'])) {
    debug_log('invalid_userId');
    die(json_encode(['error' => 'Invalid userId', 'debug' => $debug_info]));
}

if (!InputValidator::isValidDate($data['date'])) {
    debug_log('invalid_date');
    die(json_encode(['error' => 'Invalid date', 'debug' => $debug_info]));
}

if (!InputValidator::isValidTime($data['startTime'])) {
    debug_log('invalid_startTime');
    die(json_encode(['error' => 'Invalid startTime', 'debug' => $debug_info]));
}

if ($data['stopTime'] !== null && !InputValidator::isValidTime($data['stopTime'])) {
    debug_log('invalid_stopTime');
    die(json_encode(['error' => 'Invalid stopTime', 'debug' => $debug_info]));
}

debug_log('additional_validation_complete');

// Database operation
debug_log('database_start');

if (!isset($conn) || !$conn instanceof mysqli) {
    debug_log('no_database_connection');
    die(json_encode(['error' => 'No database connection', 'debug' => $debug_info]));
}

$sql = "INSERT INTO time_entries (user_id, username, date, start_time, stop_time, location, role, updated_by, updated_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
debug_log('prepare_statement', $sql);

$stmt = $conn->prepare($sql);
if (!$stmt) {
    debug_log('prepare_failed', ['error' => $conn->error, 'errno' => $conn->errno]);
    die(json_encode(['error' => 'Prepare failed: ' . $conn->error, 'debug' => $debug_info]));
}

debug_log('bind_params');
$bind_result = $stmt->bind_param("isssssss", 
    $data['userId'], 
    $data['username'], 
    $data['date'], 
    $data['startTime'], 
    $data['stopTime'],
    $data['location'], 
    $data['role'],
    $data['updatedBy']
);

if (!$bind_result) {
    debug_log('bind_failed', $stmt->error);
    die(json_encode(['error' => 'Bind failed: ' . $stmt->error, 'debug' => $debug_info]));
}

debug_log('execute');
if ($stmt->execute()) {
    $new_id = $conn->insert_id;
    debug_log('execute_success', ['id' => $new_id]);
    $data['id'] = $new_id;
    
    $response = [
        'success' => true,
        'data' => $data,
        'debug' => $debug_info,
        'validation_debug' => InputValidator::getDebugLog()
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
} else {
    debug_log('execute_failed', ['error' => $stmt->error, 'errno' => $stmt->errno]);
    die(json_encode([
        'error' => 'Execute failed: ' . $stmt->error,
        'debug' => $debug_info
    ]));
}

$stmt->close();
$conn->close();