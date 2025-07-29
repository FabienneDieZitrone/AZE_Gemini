<?php
/**
 * AZE System Comprehensive Test Script
 * Tests Microsoft Login, Session Management, User Creation and Timer Functionality
 * 
 * Test User: azetestclaude@mikropartner.de
 * Password: a1b2c3d4
 */

// Configuration
$BASE_URL = 'https://aze.mikropartner.de';
$API_BASE = $BASE_URL . '/api';

// Test results storage
$test_results = [];
$session_cookie = null;

// Color output helpers
function success($msg) {
    echo "\033[32m✓ $msg\033[0m\n";
}

function error($msg) {
    echo "\033[31m✗ $msg\033[0m\n";
}

function info($msg) {
    echo "\033[34mℹ $msg\033[0m\n";
}

function warning($msg) {
    echo "\033[33m⚠ $msg\033[0m\n";
}

// HTTP request helper
function makeRequest($url, $method = 'GET', $data = null, $cookies = null) {
    $ch = curl_init($url);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json'
    ];
    
    if ($cookies) {
        $headers[] = 'Cookie: ' . $cookies;
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($data && ($method === 'POST' || $method === 'PUT')) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    // Extract cookies from header
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $header, $matches);
    $cookies = array();
    foreach($matches[1] as $item) {
        parse_str($item, $cookie);
        $cookies = array_merge($cookies, $cookie);
    }
    
    return [
        'code' => $http_code,
        'headers' => $header,
        'body' => $body,
        'cookies' => $cookies,
        'json' => json_decode($body, true)
    ];
}

// Test 1: Check API Health
function testApiHealth() {
    global $API_BASE;
    
    info("\n=== Test 1: API Health Check ===");
    
    $endpoints = [
        '/health.php' => 'Health Check Endpoint',
        '/login.php' => 'Login Endpoint',
        '/time-entries.php' => 'Time Entries Endpoint'
    ];
    
    foreach ($endpoints as $endpoint => $name) {
        $response = makeRequest($API_BASE . $endpoint, 'GET');
        
        if ($response['code'] === 200 || $response['code'] === 401) {
            success("$name is reachable (HTTP {$response['code']})");
        } else {
            error("$name returned unexpected status: HTTP {$response['code']}");
        }
    }
}

// Test 2: Simulate OAuth Login Flow
function testOAuthFlow() {
    global $API_BASE, $session_cookie, $test_results;
    
    info("\n=== Test 2: OAuth Login Flow Simulation ===");
    
    // Step 1: Get auth start URL
    $response = makeRequest($API_BASE . '/auth-start.php', 'GET', null, null);
    
    if ($response['code'] === 302) {
        success("Auth start redirects correctly");
        
        // Extract session cookie
        if (isset($response['cookies']['PHPSESSID'])) {
            $session_cookie = 'PHPSESSID=' . $response['cookies']['PHPSESSID'];
            success("Session cookie created: " . substr($response['cookies']['PHPSESSID'], 0, 10) . "...");
        }
        
        // Get Location header
        preg_match('/Location: (.+)/', $response['headers'], $matches);
        if (isset($matches[1])) {
            $auth_url = trim($matches[1]);
            info("Auth URL: " . substr($auth_url, 0, 80) . "...");
            
            // Extract state parameter
            if (preg_match('/state=([^&]+)/', $auth_url, $state_matches)) {
                $test_results['oauth_state'] = $state_matches[1];
                success("OAuth state parameter extracted");
            }
        }
    } else {
        error("Auth start failed with HTTP {$response['code']}");
    }
    
    warning("Note: Cannot complete actual OAuth flow without browser interaction");
    info("Manual steps required:");
    info("1. User logs in with: azetestclaude@mikropartner.de / a1b2c3d4");
    info("2. Microsoft redirects back with authorization code");
    info("3. Callback exchanges code for tokens and creates session");
}

// Test 3: Test Login API (Alternative Method)
function testLoginApi() {
    global $API_BASE, $session_cookie, $test_results;
    
    info("\n=== Test 3: Direct Login API Test ===");
    
    // First check if login endpoint exists
    $response = makeRequest($API_BASE . '/login.php', 'GET');
    
    if ($response['code'] === 405) {
        info("Login endpoint expects POST method");
        
        // Try POST with test credentials
        $login_data = [
            'username' => 'azetestclaude@mikropartner.de',
            'password' => 'a1b2c3d4'
        ];
        
        $response = makeRequest($API_BASE . '/login.php', 'POST', $login_data);
        
        if ($response['code'] === 200) {
            success("Login successful!");
            
            if (isset($response['json']['user'])) {
                $user = $response['json']['user'];
                success("User data received:");
                info("  - ID: " . ($user['id'] ?? 'N/A'));
                info("  - Name: " . ($user['name'] ?? 'N/A'));
                info("  - Email: " . ($user['username'] ?? 'N/A'));
                info("  - Role: " . ($user['role'] ?? 'N/A'));
                
                $test_results['user'] = $user;
            }
            
            // Update session cookie if provided
            if (isset($response['cookies']['PHPSESSID'])) {
                $session_cookie = 'PHPSESSID=' . $response['cookies']['PHPSESSID'];
                success("New session cookie obtained");
            }
        } else {
            warning("Login returned HTTP {$response['code']}");
            if ($response['json']) {
                info("Response: " . json_encode($response['json']));
            }
        }
    } else {
        warning("Login endpoint returned HTTP {$response['code']}");
    }
}

// Test 4: Check Session
function testSession() {
    global $API_BASE, $session_cookie, $test_results;
    
    info("\n=== Test 4: Session Validation ===");
    
    if (!$session_cookie) {
        warning("No session cookie available - skipping session tests");
        return;
    }
    
    // Try to access protected endpoints
    $response = makeRequest($API_BASE . '/time-entries.php', 'GET', null, $session_cookie);
    
    if ($response['code'] === 200) {
        success("Session is valid - can access protected endpoints");
        
        if ($response['json'] && is_array($response['json'])) {
            info("Time entries retrieved: " . count($response['json']) . " entries");
        }
    } else if ($response['code'] === 401) {
        error("Session invalid - authentication required");
    } else {
        warning("Unexpected response: HTTP {$response['code']}");
    }
}

// Test 5: Test Timer Functionality
function testTimerFunctionality() {
    global $API_BASE, $session_cookie, $test_results;
    
    info("\n=== Test 5: Timer Functionality ===");
    
    if (!$session_cookie || !isset($test_results['user']['id'])) {
        warning("Cannot test timer without valid session and user ID");
        return;
    }
    
    $user = $test_results['user'];
    
    // Check for running timer
    info("Checking for running timer...");
    $response = makeRequest($API_BASE . '/time-entries.php?action=check_running', 'GET', null, $session_cookie);
    
    if ($response['code'] === 200 && $response['json']) {
        if ($response['json']['hasRunningTimer']) {
            warning("User has a running timer");
            info("Timer ID: " . $response['json']['runningTimer']['id']);
            $test_results['running_timer_id'] = $response['json']['runningTimer']['id'];
        } else {
            success("No running timer found");
        }
    }
    
    // Start a new timer
    info("\nStarting new timer...");
    $timer_data = [
        'userId' => $user['id'],
        'username' => $user['name'] ?? $user['username'],
        'date' => date('Y-m-d'),
        'startTime' => date('H:i:s'),
        'stopTime' => null, // Running timer
        'location' => 'Test Location',
        'role' => $user['role'] ?? 'Mitarbeiter',
        'updatedBy' => 'Test Script'
    ];
    
    $response = makeRequest($API_BASE . '/time-entries.php', 'POST', $timer_data, $session_cookie);
    
    if ($response['code'] === 201) {
        success("Timer started successfully!");
        if ($response['json'] && isset($response['json']['id'])) {
            $timer_id = $response['json']['id'];
            info("New timer ID: $timer_id");
            $test_results['new_timer_id'] = $timer_id;
            
            // Wait a moment
            sleep(2);
            
            // Stop the timer
            info("\nStopping timer...");
            $stop_data = [
                'id' => $timer_id,
                'stopTime' => date('H:i:s'),
                'updatedBy' => 'Test Script'
            ];
            
            $response = makeRequest($API_BASE . '/time-entries.php?action=stop', 'POST', $stop_data, $session_cookie);
            
            if ($response['code'] === 200) {
                success("Timer stopped successfully!");
            } else {
                error("Failed to stop timer: HTTP {$response['code']}");
            }
        }
    } else {
        error("Failed to start timer: HTTP {$response['code']}");
        if ($response['json']) {
            info("Error: " . json_encode($response['json']));
        }
    }
}

// Test 6: Database Verification
function testDatabaseVerification() {
    info("\n=== Test 6: Database Verification ===");
    
    info("Checking if test user exists in database...");
    
    // Create a special test endpoint to check database
    $test_db_script = '<?php
require_once __DIR__ . "/db.php";

header("Content-Type: application/json");

$email = "azetestclaude@mikropartner.de";
$stmt = $conn->prepare("SELECT id, azure_oid, username, display_name, role, created_at FROM users WHERE username = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    echo json_encode(["success" => true, "user" => $user]);
} else {
    echo json_encode(["success" => false, "message" => "User not found"]);
}

$stmt->close();
$conn->close();
?>';
    
    file_put_contents('/app/build/api/test-user-check.php', $test_db_script);
    
    $response = makeRequest($API_BASE . '/test-user-check.php', 'GET');
    
    if ($response['code'] === 200 && $response['json']) {
        if ($response['json']['success']) {
            success("User found in database!");
            $user = $response['json']['user'];
            info("  - Database ID: " . $user['id']);
            info("  - Azure OID: " . ($user['azure_oid'] ?? 'Not set'));
            info("  - Username: " . $user['username']);
            info("  - Display Name: " . ($user['display_name'] ?? 'Not set'));
            info("  - Role: " . $user['role']);
            info("  - Created: " . $user['created_at']);
        } else {
            warning("User not found in database - will be created on first login");
        }
    }
    
    // Clean up test file
    unlink('/app/build/api/test-user-check.php');
}

// Main test execution
function runAllTests() {
    info("=== AZE System Comprehensive Test Suite ===");
    info("Test User: azetestclaude@mikropartner.de");
    info("Starting tests...\n");
    
    testApiHealth();
    testOAuthFlow();
    testLoginApi();
    testSession();
    testTimerFunctionality();
    testDatabaseVerification();
    
    info("\n=== Test Summary ===");
    info("Tests completed. Please review results above.");
    
    warning("\n=== Important Notes ===");
    warning("1. OAuth flow requires manual browser interaction");
    warning("2. Test user should log in via: https://aze.mikropartner.de");
    warning("3. After login, user ID should be stored in session");
    warning("4. Timer functionality depends on valid session with user ID");
}

// Run tests
runAllTests();