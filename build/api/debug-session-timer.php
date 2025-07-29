<?php
/**
 * Debug Script for Session and Timer Testing
 * This script helps verify the session management and timer functionality
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helpers.php';

header('Content-Type: application/json');

// Initialize response
$response = [
    'timestamp' => date('Y-m-d H:i:s'),
    'session_status' => 'not_started',
    'session_data' => null,
    'user_in_db' => false,
    'user_details' => null,
    'running_timers' => [],
    'recent_timers' => [],
    'test_user_email' => 'azetestclaude@mikropartner.de'
];

// Start session
session_start();
$response['session_status'] = 'started';
$response['session_id'] = session_id();

// Check session data
if (isset($_SESSION['user'])) {
    $response['session_status'] = 'active_with_user';
    $response['session_data'] = [
        'user_id' => $_SESSION['user']['id'] ?? 'NOT SET - CRITICAL!',
        'user_oid' => $_SESSION['user']['oid'] ?? null,
        'user_name' => $_SESSION['user']['name'] ?? null,
        'user_email' => $_SESSION['user']['username'] ?? null,
        'user_role' => $_SESSION['user']['role'] ?? null,
        'session_created' => $_SESSION['created'] ?? null,
        'last_activity' => $_SESSION['last_activity'] ?? null
    ];
}

// Check test user in database
$test_email = 'azetestclaude@mikropartner.de';
$stmt = $conn->prepare("SELECT id, azure_oid, username, display_name, role, created_at FROM users WHERE username = ?");
$stmt->bind_param("s", $test_email);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    $response['user_in_db'] = true;
    $response['user_details'] = $user;
    
    // Check for running timers for this user
    $timer_stmt = $conn->prepare("
        SELECT id, date, start_time, stop_time, location, created_at, updated_at 
        FROM time_entries 
        WHERE user_id = ? AND stop_time IS NULL
        ORDER BY created_at DESC
    ");
    $timer_stmt->bind_param("i", $user['id']);
    $timer_stmt->execute();
    $timer_result = $timer_stmt->get_result();
    
    while ($timer = $timer_result->fetch_assoc()) {
        $response['running_timers'][] = $timer;
    }
    $timer_stmt->close();
    
    // Get recent timers (last 5)
    $recent_stmt = $conn->prepare("
        SELECT id, date, start_time, stop_time, location, created_at, updated_at 
        FROM time_entries 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $recent_stmt->bind_param("i", $user['id']);
    $recent_stmt->execute();
    $recent_result = $recent_stmt->get_result();
    
    while ($timer = $recent_result->fetch_assoc()) {
        $response['recent_timers'][] = $timer;
    }
    $recent_stmt->close();
}

$stmt->close();

// Add diagnostics
$response['diagnostics'] = [
    'php_session_name' => session_name(),
    'session_save_path' => session_save_path(),
    'session_cookie_params' => session_get_cookie_params(),
    'critical_issues' => []
];

// Check for critical issues
if ($response['user_in_db'] && (!isset($_SESSION['user']['id']) || $_SESSION['user']['id'] === null)) {
    $response['diagnostics']['critical_issues'][] = 'User exists in DB but user ID not in session!';
}

if (isset($_SESSION['user']) && !isset($_SESSION['user']['id'])) {
    $response['diagnostics']['critical_issues'][] = 'Session has user data but missing user ID!';
}

// Test timer creation capability
if ($response['user_in_db'] && isset($_GET['test_timer'])) {
    $test_timer_data = [
        'user_id' => $user['id'],
        'username' => $user['display_name'] ?? $user['username'],
        'date' => date('Y-m-d'),
        'start_time' => date('H:i:s'),
        'location' => 'Debug Test',
        'role' => $user['role'],
        'updated_by' => 'Debug Script'
    ];
    
    $insert_stmt = $conn->prepare("
        INSERT INTO time_entries (user_id, username, date, start_time, stop_time, location, role, updated_by, updated_at, created_at) 
        VALUES (?, ?, ?, ?, NULL, ?, ?, ?, NOW(), NOW())
    ");
    
    if ($insert_stmt) {
        $insert_stmt->bind_param("issssss", 
            $test_timer_data['user_id'],
            $test_timer_data['username'],
            $test_timer_data['date'],
            $test_timer_data['start_time'],
            $test_timer_data['location'],
            $test_timer_data['role'],
            $test_timer_data['updated_by']
        );
        
        if ($insert_stmt->execute()) {
            $response['test_timer_created'] = [
                'success' => true,
                'timer_id' => $conn->insert_id,
                'data' => $test_timer_data
            ];
        } else {
            $response['test_timer_created'] = [
                'success' => false,
                'error' => $insert_stmt->error
            ];
        }
        $insert_stmt->close();
    }
}

$conn->close();

// Output formatted JSON
echo json_encode($response, JSON_PRETTY_PRINT);
?>