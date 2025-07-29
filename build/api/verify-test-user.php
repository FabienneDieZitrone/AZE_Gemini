<?php
// AZE System Verification Script
// This script checks if the test user exists and has proper session data

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helpers.php';

header('Content-Type: application/json');

$email = 'azetestclaude@mikropartner.de';

// Check database for user
$stmt = $conn->prepare("SELECT id, azure_oid, username, display_name, role, created_at FROM users WHERE username = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

$response = [
    'timestamp' => date('Y-m-d H:i:s'),
    'user_exists' => false,
    'session_valid' => false,
    'session_data' => null,
    'recent_timers' => []
];

if ($user = $result->fetch_assoc()) {
    $response['user_exists'] = true;
    $response['user_data'] = $user;
    
    // Check for recent time entries
    $timer_stmt = $conn->prepare("SELECT id, date, start_time, stop_time, location FROM time_entries WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $timer_stmt->bind_param("i", $user['id']);
    $timer_stmt->execute();
    $timer_result = $timer_stmt->get_result();
    
    while ($timer = $timer_result->fetch_assoc()) {
        $response['recent_timers'][] = $timer;
    }
    $timer_stmt->close();
}

$stmt->close();

// Check session (if called with valid session cookie)
session_start();
if (isset($_SESSION['user'])) {
    $response['session_valid'] = true;
    $response['session_data'] = [
        'user_id' => $_SESSION['user']['id'] ?? null,
        'user_name' => $_SESSION['user']['name'] ?? null,
        'user_email' => $_SESSION['user']['username'] ?? null
    ];
}

$conn->close();

echo json_encode($response, JSON_PRETTY_PRINT);
?>