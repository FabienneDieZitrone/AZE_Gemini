<?php
/**
 * Test script to debug POST requests to time-entries.php
 * Simulates both curl and browser requests
 */

// First, let's check the current session
session_start();
echo "=== CURRENT SESSION STATE ===\n";
echo "Session ID: " . session_id() . "\n";
echo "Session data: " . json_encode($_SESSION) . "\n\n";

// Test data
$test_data = [
    'userId' => 1,
    'username' => 'Test User',
    'date' => '2025-07-29',
    'startTime' => '09:00:00',
    'stopTime' => null,
    'location' => 'office',
    'role' => 'employee',
    'updatedBy' => 'Test User'
];

echo "=== TEST DATA ===\n";
echo json_encode($test_data, JSON_PRETTY_PRINT) . "\n\n";

// Function to make requests
function makeRequest($url, $data, $cookies = '') {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    if ($cookies) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookies);
    }
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    return [
        'code' => $http_code,
        'headers' => $header,
        'body' => $body
    ];
}

// Test 1: Without session (like curl)
echo "=== TEST 1: REQUEST WITHOUT SESSION ===\n";
$result1 = makeRequest('http://localhost:8000/api/time-entries-debug.php', $test_data);
echo "HTTP Code: " . $result1['code'] . "\n";
echo "Response: " . $result1['body'] . "\n\n";

// Test 2: With fake session cookie
echo "=== TEST 2: REQUEST WITH FAKE SESSION ===\n";
$result2 = makeRequest('http://localhost:8000/api/time-entries-debug.php', $test_data, 'PHPSESSID=fakesession123');
echo "HTTP Code: " . $result2['code'] . "\n";
echo "Response: " . $result2['body'] . "\n\n";

// Test 3: With real session cookie (if we have one)
if (session_id()) {
    echo "=== TEST 3: REQUEST WITH REAL SESSION ===\n";
    $result3 = makeRequest('http://localhost:8000/api/time-entries-debug.php', $test_data, 'PHPSESSID=' . session_id());
    echo "HTTP Code: " . $result3['code'] . "\n";
    echo "Response: " . $result3['body'] . "\n\n";
}

// Test 4: Direct include test (simulates internal call)
echo "=== TEST 4: DIRECT INCLUDE TEST ===\n";
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';
// Temporarily override php://input
$temp_input = json_encode($test_data);
// We can't override php://input directly, so we'll modify the debug script

// Test 5: Check all required files
echo "=== TEST 5: FILE CHECK ===\n";
$files = [
    'error-handler.php',
    'security-headers.php', 
    'db-init.php',
    'auth_helpers.php',
    'validation.php',
    'time-entries.php',
    'time-entries-debug.php'
];

foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    echo "$file: " . (file_exists($path) ? "EXISTS" : "MISSING") . "\n";
    if (file_exists($path)) {
        echo "  - Size: " . filesize($path) . " bytes\n";
        echo "  - Permissions: " . substr(sprintf('%o', fileperms($path)), -4) . "\n";
    }
}

// Test 6: Check database connection
echo "\n=== TEST 6: DATABASE CONNECTION ===\n";
try {
    require_once __DIR__ . '/db-init.php';
    initialize_api();
    echo "Database connection: SUCCESS\n";
    echo "Database selected: " . $conn->get_connection_stats()['db_name'] ?? 'unknown' . "\n";
} catch (Exception $e) {
    echo "Database connection: FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
}

// Test 7: Check validation library
echo "\n=== TEST 7: VALIDATION LIBRARY ===\n";
try {
    require_once __DIR__ . '/validation.php';
    echo "InputValidator class exists: " . (class_exists('InputValidator') ? 'YES' : 'NO') . "\n";
    echo "validateJsonInput method exists: " . (method_exists('InputValidator', 'validateJsonInput') ? 'YES' : 'NO') . "\n";
    
    // Test validation directly
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $GLOBALS['php_input_content'] = json_encode($test_data);
    $validated = InputValidator::validateJsonInput(['userId', 'username'], ['stopTime' => null]);
    echo "Validation test: PASSED\n";
    echo "Validated data: " . json_encode($validated) . "\n";
} catch (Exception $e) {
    echo "Validation test: FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Type: " . get_class($e) . "\n";
}

echo "\n=== END OF TESTS ===\n";