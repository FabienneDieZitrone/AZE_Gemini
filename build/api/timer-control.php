<?php
/**
 * Server-First Timer Control API
 * Handles starting and stopping time tracking with immediate DB persistence
 * Resolves Issue #1: No data loss on logout
 */

// Define API guard constant
define('API_GUARD', true);

// Error handling
require_once __DIR__ . '/error-handler.php';
require_once __DIR__ . '/security-headers.php';
require_once __DIR__ . '/security-middleware.php';

// SECURITY: Error reporting disabled in production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

require_once __DIR__ . '/db-init.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/validation.php';

initialize_api();

// Apply security headers
initSecurityMiddleware();

// Authentication required
$user_from_session = verify_session_and_get_user();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handle_get_running_timer($conn, $user_from_session);
        break;
    case 'POST':
        handle_timer_action($conn, $user_from_session);
        break;
    default:
        send_response(405, ['message' => 'Method Not Allowed']);
        break;
}

$conn->close();

/**
 * Get current running timer for user
 */
function handle_get_running_timer($conn, $current_user) {
    $stmt = $conn->prepare("SELECT id, user_id AS userId, username, date, start_time AS startTime, location, role, created_at AS createdAt FROM time_entries WHERE user_id = ? AND status = 'running' ORDER BY created_at DESC LIMIT 1");
    
    if (!$stmt) {
        error_log('Prepare failed for SELECT running timer: ' . $conn->error);
        send_response(500, ['message' => 'Database error']);
        return;
    }
    
    $stmt->bind_param("i", $current_user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $running_timer = $result->fetch_assoc();
    
    send_response(200, [
        'hasRunningTimer' => !empty($running_timer),
        'runningTimer' => $running_timer
    ]);
    
    $stmt->close();
}

/**
 * Start or stop timer
 */
function handle_timer_action($conn, $current_user) {
    try {
        $required_fields = ['action'];
        $optional_fields = ['location' => 'Zentrale Berlin'];
        $data = InputValidator::validateJsonInput($required_fields, $optional_fields);
        
        $allowed_actions = ['start', 'stop'];
        if (!in_array($data['action'], $allowed_actions)) {
            send_response(400, ['message' => 'Invalid action. Allowed: start, stop']);
            return;
        }
        
    } catch (Exception $e) {
        send_response(400, ['message' => 'Validation error: ' . $e->getMessage()]);
        return;
    }
    
    if ($data['action'] === 'start') {
        handle_start_timer($conn, $current_user, $data);
    } else {
        handle_stop_timer($conn, $current_user);
    }
}

/**
 * Start timer - immediately save to DB
 */
function handle_start_timer($conn, $current_user, $data) {
    // Check if user already has running timer
    $check_stmt = $conn->prepare("SELECT id FROM time_entries WHERE user_id = ? AND status = 'running'");
    $check_stmt->bind_param("i", $current_user['id']);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    if ($existing) {
        send_response(400, ['message' => 'Timer already running. Stop current timer first.']);
        return;
    }
    
    // Create new running entry
    $now = new DateTime();
    $stmt = $conn->prepare("INSERT INTO time_entries (user_id, username, date, start_time, stop_time, location, role, status, updated_by, created_at, updated_at) VALUES (?, ?, ?, ?, NULL, ?, ?, 'running', ?, NOW(), NOW())");
    
    if (!$stmt) {
        error_log('Prepare failed for INSERT running timer: ' . $conn->error);
        send_response(500, ['message' => 'Database error']);
        return;
    }
    
    $date = $now->format('Y-m-d');
    $start_time = $now->format('H:i:s');
    
    $stmt->bind_param("issssss", 
        $current_user['id'],
        $current_user['name'],
        $date,
        $start_time,
        $data['location'],
        $current_user['role'],
        $current_user['name']
    );
    
    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        send_response(201, [
            'message' => 'Timer started',
            'timerId' => $new_id,
            'startTime' => $start_time,
            'date' => $date
        ]);
    } else {
        error_log('Failed to start timer: ' . $stmt->error);
        send_response(500, ['message' => 'Failed to start timer']);
    }
    
    $stmt->close();
}

/**
 * Stop timer - update existing running entry
 */
function handle_stop_timer($conn, $current_user) {
    // Find running timer
    $find_stmt = $conn->prepare("SELECT id, start_time, date FROM time_entries WHERE user_id = ? AND status = 'running' ORDER BY created_at DESC LIMIT 1");
    $find_stmt->bind_param("i", $current_user['id']);
    $find_stmt->execute();
    $running_entry = $find_stmt->get_result()->fetch_assoc();
    $find_stmt->close();
    
    if (!$running_entry) {
        send_response(400, ['message' => 'No running timer found']);
        return;
    }
    
    // Update with stop time
    $now = new DateTime();
    $stop_time = $now->format('H:i:s');
    
    $update_stmt = $conn->prepare("UPDATE time_entries SET stop_time = ?, status = 'completed', updated_at = NOW() WHERE id = ?");
    $update_stmt->bind_param("si", $stop_time, $running_entry['id']);
    
    if ($update_stmt->execute()) {
        send_response(200, [
            'message' => 'Timer stopped',
            'timerId' => $running_entry['id'],
            'startTime' => $running_entry['start_time'],
            'stopTime' => $stop_time,
            'date' => $running_entry['date']
        ]);
    } else {
        error_log('Failed to stop timer: ' . $update_stmt->error);
        send_response(500, ['message' => 'Failed to stop timer']);
    }
    
    $update_stmt->close();
}
?>