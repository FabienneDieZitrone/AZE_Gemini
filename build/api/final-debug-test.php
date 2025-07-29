<?php
/**
 * Final debug test to find the 500 error
 */

// Test configuration
$base_url = 'http://localhost:8000/api/';
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

// Function to make a request and format output
function testEndpoint($name, $url, $data, $session_cookie = null) {
    echo "\n=== TEST: $name ===\n";
    echo "URL: $url\n";
    echo "Data: " . json_encode($data) . "\n";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    if ($session_cookie) {
        curl_setopt($ch, CURLOPT_COOKIE, $session_cookie);
        echo "Cookie: $session_cookie\n";
    }
    
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    echo "HTTP Code: $http_code\n";
    echo "Headers:\n$headers\n";
    echo "Body:\n$body\n";
    
    // Try to decode JSON
    $json = json_decode($body, true);
    if ($json && isset($json['debug'])) {
        echo "\nDEBUG STEPS:\n";
        foreach ($json['debug']['steps'] as $step) {
            echo sprintf("  [%.3fms] %s", $step['time'] * 1000, $step['step']);
            if (isset($step['data'])) {
                echo " => " . json_encode($step['data']);
            }
            echo "\n";
        }
    }
    
    if ($json && isset($json['validation_debug'])) {
        echo "\nVALIDATION DEBUG:\n";
        foreach ($json['validation_debug'] as $step) {
            echo "  " . $step['step'];
            if (isset($step['data'])) {
                echo " => " . json_encode($step['data']);
            }
            echo "\n";
        }
    }
    
    return $http_code;
}

// Get a valid session first
echo "=== GETTING SESSION ===\n";
session_start();
$_SESSION['user'] = [
    'id' => 1,
    'email' => 'test@example.com',
    'name' => 'Test User',
    'role' => 'employee'
];
$session_id = session_id();
$session_cookie = "PHPSESSID=$session_id";
echo "Session ID: $session_id\n";
echo "Session data: " . json_encode($_SESSION) . "\n";

// Test 1: Debug endpoint without session
testEndpoint(
    "Debug endpoint - No session",
    $base_url . 'time-entries-debug.php',
    $test_data
);

// Test 2: Debug endpoint with session
testEndpoint(
    "Debug endpoint - With session",
    $base_url . 'time-entries-debug.php',
    $test_data,
    $session_cookie
);

// Test 3: Debug with validation endpoint
testEndpoint(
    "Debug with validation - With session",
    $base_url . 'time-entries-with-debug.php',
    $test_data,
    $session_cookie
);

// Test 4: Original endpoint (the one with 500 error)
testEndpoint(
    "Original endpoint - With session",
    $base_url . 'time-entries.php',
    $test_data,
    $session_cookie
);

// Test 5: Test with different data (maybe the issue is data-specific)
$test_data2 = $test_data;
$test_data2['stopTime'] = '17:00:00'; // With stopTime
testEndpoint(
    "Original endpoint - With stopTime",
    $base_url . 'time-entries.php',
    $test_data2,
    $session_cookie
);

// Test 6: Test with minimal data
$minimal_data = [
    'userId' => 1,
    'username' => 'Test',
    'date' => '2025-07-29',
    'startTime' => '09:00:00',
    'location' => 'office',
    'role' => 'employee',
    'updatedBy' => 'Test'
];
testEndpoint(
    "Original endpoint - Minimal data",
    $base_url . 'time-entries.php',
    $minimal_data,
    $session_cookie
);

echo "\n=== TEST COMPLETE ===\n";