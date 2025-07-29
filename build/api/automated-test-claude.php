<?php
/**
 * Automatisierter Test für Claude Account
 * Testet Login, Session, Timer-Funktionalität
 */

// Test Configuration
$BASE_URL = 'https://aze.mikropartner.de';
$TEST_USER = 'azetestclaude@mikropartner.de';
$TEST_PASS = 'a1b2c3d4';

// Initialize test results
$tests = [];
$passed = 0;
$failed = 0;

function runTest($name, $test_func) {
    global $tests, $passed, $failed;
    
    echo "\n🧪 Testing: $name\n";
    $start = microtime(true);
    
    try {
        $result = $test_func();
        $duration = round((microtime(true) - $start) * 1000, 2);
        
        if ($result['success']) {
            echo "✅ PASSED ({$duration}ms)\n";
            $passed++;
        } else {
            echo "❌ FAILED: {$result['message']} ({$duration}ms)\n";
            $failed++;
        }
        
        $tests[$name] = $result;
        $tests[$name]['duration'] = $duration;
        
    } catch (Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
        $failed++;
        $tests[$name] = [
            'success' => false,
            'message' => $e->getMessage(),
            'duration' => round((microtime(true) - $start) * 1000, 2)
        ];
    }
    
    return $result;
}

// Helper function for API calls
function apiCall($endpoint, $method = 'GET', $data = null, $cookies = '') {
    global $BASE_URL;
    
    $ch = curl_init($BASE_URL . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
    }
    
    if ($cookies) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookies);
    }
    
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    // Extract cookies
    preg_match_all('/Set-Cookie: (.*?);/i', $headers, $matches);
    $response_cookies = implode('; ', array_map(function($cookie) {
        return explode(';', $cookie)[0];
    }, $matches[1]));
    
    return [
        'code' => $http_code,
        'body' => $body,
        'headers' => $headers,
        'cookies' => $response_cookies
    ];
}

echo "🚀 AZE System Automated Test Suite\n";
echo "================================\n";
echo "Test User: $TEST_USER\n";
echo "Base URL: $BASE_URL\n";

// Test 1: API Health Check
runTest('API Health Check', function() {
    $response = apiCall('/api/health.php');
    
    if ($response['code'] !== 200) {
        return ['success' => false, 'message' => "HTTP {$response['code']}"];
    }
    
    $data = json_decode($response['body'], true);
    return [
        'success' => isset($data['status']) && $data['status'] === 'ok',
        'message' => 'API is healthy',
        'data' => $data
    ];
});

// Test 2: Check Test User in Database
runTest('Test User Database Check', function() {
    $response = apiCall('/api/verify-test-user.php');
    
    if ($response['code'] !== 200) {
        return ['success' => false, 'message' => "HTTP {$response['code']}"];
    }
    
    // Check if response contains user info
    $body = $response['body'];
    $hasUser = strpos($body, 'azetestclaude@mikropartner.de') !== false;
    
    return [
        'success' => $hasUser,
        'message' => $hasUser ? 'Test user found in database' : 'Test user not found',
        'data' => ['response_length' => strlen($body)]
    ];
});

// Test 3: OAuth Login Flow (Simulated)
$session_cookies = '';
runTest('OAuth Login Simulation', function() use (&$session_cookies) {
    // Since we can't actually do OAuth, we'll test the endpoints
    $response = apiCall('/api/auth-start.php');
    
    if ($response['code'] !== 302) {
        return ['success' => false, 'message' => "Expected redirect, got HTTP {$response['code']}"];
    }
    
    // Extract redirect URL
    preg_match('/Location: (.+)/', $response['headers'], $matches);
    $redirect_url = trim($matches[1] ?? '');
    
    // Check if it's a Microsoft OAuth URL
    $is_oauth = strpos($redirect_url, 'login.microsoftonline.com') !== false;
    
    // Save any cookies
    $session_cookies = $response['cookies'];
    
    return [
        'success' => $is_oauth,
        'message' => 'OAuth redirect working',
        'data' => ['redirect_url' => $redirect_url]
    ];
});

// Test 4: Check Migration Status
runTest('Migration Status Check', function() {
    $response = apiCall('/api/verify-migration-success.php');
    
    if ($response['code'] !== 200) {
        return ['success' => false, 'message' => "HTTP {$response['code']}"];
    }
    
    $data = json_decode($response['body'], true);
    
    if (!$data) {
        return ['success' => false, 'message' => 'Invalid JSON response'];
    }
    
    $migration_ok = $data['migration_status'] === 'completed' && 
                    $data['summary']['ready_for_production'] === true;
    
    return [
        'success' => $migration_ok,
        'message' => 'Migration completed successfully',
        'data' => $data
    ];
});

// Test 5: Timer Functionality Test
runTest('Timer API Endpoints', function() use ($session_cookies) {
    // Test time-entries endpoint (should return 401 without auth)
    $response = apiCall('/api/time-entries.php', 'GET', null, $session_cookies);
    
    // We expect 401 since we're not properly authenticated
    $expected_401 = $response['code'] === 401;
    
    if (!$expected_401) {
        return ['success' => false, 'message' => "Expected 401, got HTTP {$response['code']}"];
    }
    
    // Check for proper error message
    $data = json_decode($response['body'], true);
    $has_auth_error = isset($data['message']) && 
                      strpos($data['message'], 'Unauthorized') !== false;
    
    return [
        'success' => $has_auth_error,
        'message' => 'Timer API properly secured',
        'data' => $data
    ];
});

// Test 6: Direct Timer Functionality Page
runTest('Timer Functionality Page', function() {
    $response = apiCall('/api/test-timer-functionality.php');
    
    if ($response['code'] !== 200) {
        return ['success' => false, 'message' => "HTTP {$response['code']}"];
    }
    
    // Check if page contains expected elements
    $body = $response['body'];
    $has_timer_test = strpos($body, 'Timer Funktionalitäts-Test') !== false;
    $has_login_check = strpos($body, 'Nicht eingeloggt') !== false || 
                       strpos($body, 'Angemeldet als') !== false;
    
    return [
        'success' => $has_timer_test && $has_login_check,
        'message' => 'Timer test page accessible',
        'data' => ['page_length' => strlen($body)]
    ];
});

// Test 7: SQL NULL Functionality
runTest('Database NULL Support', function() {
    global $BASE_URL;
    
    // We'll check the debug endpoint
    $response = apiCall('/api/debug-stop-issue.php');
    
    if ($response['code'] !== 200) {
        return ['success' => false, 'message' => "HTTP {$response['code']}"];
    }
    
    $body = $response['body'];
    
    // Check if stop_time supports NULL
    $supports_null = strpos($body, 'stop_time ist NULLABLE') !== false ||
                     strpos($body, 'Null | YES') !== false;
    
    return [
        'success' => $supports_null,
        'message' => 'Database supports NULL for stop_time',
        'data' => ['debug_info' => substr($body, 0, 500)]
    ];
});

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 TEST SUMMARY\n";
echo str_repeat("=", 50) . "\n";
echo "Total Tests: " . ($passed + $failed) . "\n";
echo "✅ Passed: $passed\n";
echo "❌ Failed: $failed\n";
echo "Success Rate: " . round(($passed / ($passed + $failed)) * 100, 2) . "%\n";

// Detailed Results
echo "\n📋 DETAILED RESULTS:\n";
foreach ($tests as $name => $result) {
    $status = $result['success'] ? '✅' : '❌';
    echo "\n$status $name ({$result['duration']}ms)\n";
    if (!$result['success']) {
        echo "   Error: {$result['message']}\n";
    }
    if (isset($result['data'])) {
        echo "   Data: " . json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }
}

// Recommendations
echo "\n💡 RECOMMENDATIONS:\n";
echo "1. Login with browser: $BASE_URL\n";
echo "2. Use credentials: $TEST_USER / $TEST_PASS\n";
echo "3. Test timer at: $BASE_URL/api/test-timer-functionality.php\n";
echo "4. Check account at: $BASE_URL/api/test-claude-account.php\n";

// Save results
$report = [
    'timestamp' => date('Y-m-d H:i:s'),
    'summary' => [
        'total' => $passed + $failed,
        'passed' => $passed,
        'failed' => $failed,
        'success_rate' => round(($passed / ($passed + $failed)) * 100, 2)
    ],
    'tests' => $tests
];

file_put_contents(__DIR__ . '/test-results-' . date('Y-m-d-His') . '.json', 
                  json_encode($report, JSON_PRETTY_PRINT));

echo "\n✅ Test report saved to test-results-*.json\n";
?>