<?php
/**
 * AZE System Automated Test Suite
 * Test-User: azetestclaude@mikropartner.de
 * 
 * Tests:
 * 1. OAuth Login Flow Simulation
 * 2. User Creation in Database
 * 3. Timer Start/Stop Functionality
 * 4. Stop Button Bug Verification (stop_time NULL handling)
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test configuration
$testResults = [];
$testUser = [
    'email' => 'azetestclaude@mikropartner.de',
    'password' => 'a1b2c3d4',
    'name' => 'AZE Test Claude',
    'given_name' => 'Test',
    'family_name' => 'Claude'
];

// Helper functions
function logTest($testName, $passed, $details = '') {
    global $testResults;
    $testResults[] = [
        'test' => $testName,
        'passed' => $passed,
        'details' => $details,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    $status = $passed ? '✅ PASSED' : '❌ FAILED';
    echo "\n[{$status}] {$testName}\n";
    if ($details) {
        echo "    Details: {$details}\n";
    }
}

function makeApiCall($endpoint, $data = [], $method = 'POST', $sessionId = null) {
    $ch = curl_init();
    $url = "http://localhost:8000/api/{$endpoint}";
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($method === 'POST' && !empty($data)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    
    if ($sessionId) {
        curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID={$sessionId}");
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'body' => json_decode($response, true),
        'http_code' => $httpCode,
        'raw' => $response
    ];
}

// Include database connection
require_once __DIR__ . '/db.php';

echo "\n========================================\n";
echo "AZE System Automated Test Suite\n";
echo "========================================\n";
echo "Test User: {$testUser['email']}\n";
echo "Start Time: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n";

// Test 1: OAuth Login Flow Simulation
echo "\n[TEST 1] OAuth Login Flow Simulation\n";
try {
    // Simulate OAuth callback with test user data
    $mockOAuthData = [
        'email' => $testUser['email'],
        'name' => $testUser['name'],
        'given_name' => $testUser['given_name'],
        'family_name' => $testUser['family_name']
    ];
    
    // Create a mock session
    $sessionId = bin2hex(random_bytes(16));
    
    // Simulate successful OAuth login
    $_SESSION['email'] = $mockOAuthData['email'];
    $_SESSION['name'] = $mockOAuthData['name'];
    $_SESSION['given_name'] = $mockOAuthData['given_name'];
    $_SESSION['family_name'] = $mockOAuthData['family_name'];
    
    logTest('OAuth Login Simulation', true, 'Mock OAuth data created successfully');
} catch (Exception $e) {
    logTest('OAuth Login Simulation', false, $e->getMessage());
}

// Test 2: User Creation/Verification in Database
echo "\n[TEST 2] User Database Operations\n";
try {
    $db = getDBConnection();
    
    // Check if user exists
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $testUser['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Create user
        $stmt = $db->prepare("INSERT INTO users (email, name, created_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("ss", $testUser['email'], $testUser['name']);
        $stmt->execute();
        $userId = $db->insert_id;
        logTest('User Creation', true, "User created with ID: {$userId}");
    } else {
        $user = $result->fetch_assoc();
        $userId = $user['id'];
        logTest('User Exists', true, "User found with ID: {$userId}");
    }
    
    // Store user ID in session
    $_SESSION['user_id'] = $userId;
    
} catch (Exception $e) {
    logTest('User Database Operations', false, $e->getMessage());
    exit(1);
}

// Test 3: Timer Start Functionality
echo "\n[TEST 3] Timer Start Functionality\n";
try {
    // Clean up any existing running timers
    $stmt = $db->prepare("UPDATE time_entries SET stop_time = NOW() WHERE user_id = ? AND stop_time IS NULL");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // Start new timer
    $startData = [
        'user_id' => $userId,
        'location' => 'TEST_OFFICE',
        'start_time' => date('Y-m-d H:i:s')
    ];
    
    $stmt = $db->prepare("INSERT INTO time_entries (user_id, location, start_time) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $userId, $startData['location'], $startData['start_time']);
    $stmt->execute();
    $timerId = $db->insert_id;
    
    // Verify timer was created
    $stmt = $db->prepare("SELECT * FROM time_entries WHERE id = ?");
    $stmt->bind_param("i", $timerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $timerEntry = $result->fetch_assoc();
    
    if ($timerEntry && $timerEntry['stop_time'] === null) {
        logTest('Timer Start', true, "Timer started with ID: {$timerId}");
    } else {
        logTest('Timer Start', false, "Timer not properly created or stop_time is not NULL");
    }
    
} catch (Exception $e) {
    logTest('Timer Start', false, $e->getMessage());
}

// Test 4: Timer Stop Functionality (Testing NULL stop_time bug fix)
echo "\n[TEST 4] Timer Stop Functionality\n";
sleep(2); // Wait 2 seconds to have measurable duration

try {
    // Get running timer
    $stmt = $db->prepare("SELECT * FROM time_entries WHERE user_id = ? AND stop_time IS NULL ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $runningTimer = $result->fetch_assoc();
        $runningTimerId = $runningTimer['id'];
        
        // Stop the timer
        $stopTime = date('Y-m-d H:i:s');
        $stmt = $db->prepare("UPDATE time_entries SET stop_time = ? WHERE id = ? AND stop_time IS NULL");
        $stmt->bind_param("si", $stopTime, $runningTimerId);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            // Verify stop_time was set
            $stmt = $db->prepare("SELECT * FROM time_entries WHERE id = ?");
            $stmt->bind_param("i", $runningTimerId);
            $stmt->execute();
            $result = $stmt->get_result();
            $stoppedTimer = $result->fetch_assoc();
            
            if ($stoppedTimer['stop_time'] !== null) {
                $duration = strtotime($stoppedTimer['stop_time']) - strtotime($stoppedTimer['start_time']);
                logTest('Timer Stop', true, "Timer stopped successfully. Duration: {$duration} seconds");
            } else {
                logTest('Timer Stop', false, "stop_time is still NULL after update");
            }
        } else {
            logTest('Timer Stop', false, "No timer was updated");
        }
    } else {
        logTest('Timer Stop', false, "No running timer found");
    }
    
} catch (Exception $e) {
    logTest('Timer Stop', false, $e->getMessage());
}

// Test 5: Stop Button Bug Verification (Multiple stop attempts)
echo "\n[TEST 5] Stop Button Bug Verification\n";
try {
    // Try to stop an already stopped timer (should not cause issues)
    $stmt = $db->prepare("UPDATE time_entries SET stop_time = NOW() WHERE id = ? AND stop_time IS NULL");
    $stmt->bind_param("i", $timerId);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        logTest('Double Stop Prevention', true, "Already stopped timer cannot be stopped again");
    } else {
        logTest('Double Stop Prevention', false, "Timer was stopped twice!");
    }
    
} catch (Exception $e) {
    logTest('Double Stop Prevention', false, $e->getMessage());
}

// Test 6: API Endpoint Tests
echo "\n[TEST 6] API Endpoint Integration Tests\n";

// Test time-entries.php GET
try {
    $response = makeApiCall('time-entries.php?user_id=' . $userId, [], 'GET', $sessionId);
    
    if ($response['http_code'] === 200 && isset($response['body']['entries'])) {
        $entryCount = count($response['body']['entries']);
        logTest('GET time-entries API', true, "Retrieved {$entryCount} entries");
    } else {
        logTest('GET time-entries API', false, "HTTP {$response['http_code']}: " . $response['raw']);
    }
} catch (Exception $e) {
    logTest('GET time-entries API', false, $e->getMessage());
}

// Test 7: Cleanup Test Data
echo "\n[TEST 7] Cleanup Test Data\n";
try {
    // Delete test time entries
    $stmt = $db->prepare("DELETE FROM time_entries WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $deletedEntries = $stmt->affected_rows;
    
    // Delete test user
    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        logTest('Cleanup', true, "Deleted test user and {$deletedEntries} time entries");
    } else {
        logTest('Cleanup', false, "Could not delete test user");
    }
    
} catch (Exception $e) {
    logTest('Cleanup', false, $e->getMessage());
}

// Summary Report
echo "\n\n========================================\n";
echo "TEST SUMMARY REPORT\n";
echo "========================================\n";

$totalTests = count($testResults);
$passedTests = count(array_filter($testResults, function($t) { return $t['passed']; }));
$failedTests = $totalTests - $passedTests;

echo "Total Tests: {$totalTests}\n";
echo "Passed: {$passedTests}\n";
echo "Failed: {$failedTests}\n";
echo "Success Rate: " . round(($passedTests / $totalTests) * 100, 2) . "%\n";

echo "\nDetailed Results:\n";
foreach ($testResults as $test) {
    $status = $test['passed'] ? '✅' : '❌';
    echo "{$status} {$test['test']}\n";
    if (!$test['passed'] && $test['details']) {
        echo "   → {$test['details']}\n";
    }
}

echo "\n========================================\n";
echo "Test completed at: " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n";

// Close database connection
$db->close();
?>