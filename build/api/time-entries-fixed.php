<?php
/**
 * TEMPORARY FIX for Issue #29 - Timer stop race condition
 * This version handles the NOT NULL constraint on stop_time column
 * by using '00:00:00' as a marker for running timers
 */

// Error handling
require_once __DIR__ . '/error-handler.php';

// Robuster Fatal-Error-Handler
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode([
            'message' => 'Fatal PHP Error',
            'error_details' => [
                'type' => $error['type'],
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
            ]
        ]);
        exit;
    }
});

// SECURITY: Error reporting disabled in production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// Include files in correct order
require_once __DIR__ . '/db-init.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/validation.php';

// Initialize API
initialize_api();

// Verify user is authenticated
$user_from_session = verify_session_and_get_user();

// Get user ID from session
if (!isset($user_from_session['id'])) {
    if (isset($user_from_session['oid']) && isset($conn)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE oid = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $user_from_session['oid']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $user_from_session['id'] = $row['id'];
            }
            $stmt->close();
        }
    }
    
    if (!isset($user_from_session['id'])) {
        error_log('CRITICAL: No user ID in session for user: ' . json_encode($user_from_session));
    }
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handle_get($conn, $user_from_session);
        break;
    case 'POST':
        handle_post($conn, $user_from_session);
        break;
    default:
        send_response(405, ['message' => 'Method Not Allowed']);
        break;
}

$conn->close();

function handle_get($conn, $current_user) {
    // Check if this is a running timer check request
    if (isset($_GET['action']) && $_GET['action'] === 'check_running') {
        handle_check_running_timer($conn, $current_user);
        return;
    }
    
    // Get all time entries, converting '00:00:00' to NULL for running timers
    $stmt = $conn->prepare("
        SELECT 
            id, 
            user_id AS userId, 
            username, 
            date, 
            start_time AS startTime, 
            CASE 
                WHEN stop_time = '00:00:00' THEN NULL 
                ELSE stop_time 
            END AS stopTime, 
            location, 
            role, 
            created_at AS createdAt, 
            updated_by AS updatedBy, 
            updated_at AS updatedAt 
        FROM time_entries 
        ORDER BY date DESC, start_time DESC
    ");
    
    if (!$stmt) {
        $error_msg = 'Prepare failed for SELECT time_entries: ' . $conn->error;
        error_log($error_msg);
        send_response(500, ['message' => 'Datenbankfehler beim Abrufen der Zeiteinträge.', 'details' => $error_msg]);
        return;
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $entries = $result->fetch_all(MYSQLI_ASSOC);

    send_response(200, $entries);
    $stmt->close();
}

function handle_post($conn, $current_user) {
    // Handle stop action via POST
    if (isset($_GET['action']) && $_GET['action'] === 'stop') {
        handle_stop_timer($conn, $current_user);
        return;
    }
    
    try {
        $required_fields = ['userId', 'username', 'date', 'startTime', 'location', 'role', 'updatedBy'];
        $optional_fields = ['stopTime' => null];
        $data = InputValidator::validateJsonInput($required_fields, $optional_fields);
        
        // Validation
        if (!InputValidator::isValidId($data['userId'])) {
            send_response(400, ['message' => 'Invalid userId format']);
            return;
        }
        
        if (!InputValidator::isValidDate($data['date'])) {
            send_response(400, ['message' => 'Invalid date format. Expected YYYY-MM-DD']);
            return;
        }
        
        if (!InputValidator::isValidTime($data['startTime'])) {
            send_response(400, ['message' => 'Invalid startTime format. Expected HH:MM:SS']);
            return;
        }
        
        if ($data['stopTime'] !== null && !InputValidator::isValidTime($data['stopTime'])) {
            send_response(400, ['message' => 'Invalid stopTime format. Expected HH:MM:SS']);
            return;
        }
        
    } catch (Exception $e) {
        send_response(400, ['message' => 'Validation error: ' . $e->getMessage()]);
        return;
    }

    // CRITICAL FIX: Stop all existing running timers before starting a new one
    if ($data['stopTime'] === null) {
        // Stop all timers with stop_time = '00:00:00' (our running marker)
        $stop_stmt = $conn->prepare("
            UPDATE time_entries 
            SET stop_time = TIME(NOW()), 
                updated_by = 'System Auto-Stop', 
                updated_at = NOW() 
            WHERE user_id = ? AND stop_time = '00:00:00'
        ");
        if ($stop_stmt) {
            $stop_stmt->bind_param("i", $data['userId']);
            $stop_stmt->execute();
            $stopped_count = $stop_stmt->affected_rows;
            if ($stopped_count > 0) {
                error_log("Auto-stopped $stopped_count running timer(s) for user " . $data['userId']);
            }
            $stop_stmt->close();
        }
    }

    // Convert NULL stopTime to '00:00:00' for database insertion (running timer marker)
    $db_stop_time = $data['stopTime'] ?? '00:00:00';

    $stmt = $conn->prepare("
        INSERT INTO time_entries 
        (user_id, username, date, start_time, stop_time, location, role, updated_by, updated_at, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    if (!$stmt) {
        $error_msg = 'Prepare failed for INSERT time_entries: ' . $conn->error;
        error_log($error_msg);
        send_response(500, ['message' => 'Datenbankfehler beim Vorbereiten des Zeiteintrags.', 'details' => $error_msg]);
        return;
    }

    $stmt->bind_param("isssssss", 
        $data['userId'], 
        $data['username'], 
        $data['date'], 
        $data['startTime'], 
        $db_stop_time,  // Use '00:00:00' instead of NULL
        $data['location'], 
        $data['role'],
        $data['updatedBy']
    );

    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        $data['id'] = $new_id;
        // Return original stopTime (null) to frontend, not the DB workaround
        send_response(201, $data);
    } else {
        $error_msg = 'Insert failed for time_entries: ' . $stmt->error;
        error_log($error_msg);
        send_response(500, ['message' => 'Datenbankfehler beim Speichern des Zeiteintrags.', 'details' => $error_msg]);
    }

    $stmt->close();
}

/**
 * Handle stop timer action - FIXED VERSION
 */
function handle_stop_timer($conn, $current_user) {
    try {
        $required_fields = ['id'];
        $optional_fields = ['stopTime' => null, 'updatedBy' => $current_user['name']];
        $data = InputValidator::validateJsonInput($required_fields, $optional_fields);
        
        if (!InputValidator::isValidId($data['id'])) {
            send_response(400, ['message' => 'Invalid id format']);
            return;
        }
        
        if ($data['stopTime'] !== null && !InputValidator::isValidTime($data['stopTime'])) {
            send_response(400, ['message' => 'Invalid stopTime format. Expected HH:MM:SS']);
            return;
        }
        
    } catch (Exception $e) {
        send_response(400, ['message' => 'Validation error: ' . $e->getMessage()]);
        return;
    }
    
    // Check if timer exists and is running (stop_time = '00:00:00')
    $check_stmt = $conn->prepare("
        SELECT id, stop_time 
        FROM time_entries 
        WHERE id = ? AND user_id = ?
    ");
    
    if (!$check_stmt) {
        error_log('Prepare failed for SELECT time_entries: ' . $conn->error);
        send_response(500, ['message' => 'Database error']);
        return;
    }
    
    $check_stmt->bind_param("ii", $data['id'], $current_user['id']);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $timer = $result->fetch_assoc();
    $check_stmt->close();
    
    if (!$timer) {
        send_response(404, ['message' => 'Timer entry not found or not owned by user']);
        return;
    }
    
    // Check if already stopped (not '00:00:00')
    if ($timer['stop_time'] !== '00:00:00') {
        send_response(200, ['message' => 'Timer already stopped', 'id' => $data['id'], 'alreadyStopped' => true]);
        return;
    }
    
    // Use provided stopTime or current time
    $stop_time_value = $data['stopTime'] ?? date('H:i:s');
    
    // Update timer with actual stop time
    $stmt = $conn->prepare("
        UPDATE time_entries 
        SET stop_time = ?, 
            updated_by = ?, 
            updated_at = NOW() 
        WHERE id = ? AND user_id = ? AND stop_time = '00:00:00'
    ");
    
    if (!$stmt) {
        $error_msg = 'Prepare failed for UPDATE time_entries: ' . $conn->error;
        error_log($error_msg);
        send_response(500, ['message' => 'Database error']);
        return;
    }
    
    $stmt->bind_param("ssii", $stop_time_value, $data['updatedBy'], $data['id'], $current_user['id']);
    
    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        
        if ($affected === 0) {
            // No rows updated - timer might have been stopped by another request
            error_log("WARNING: No rows updated for timer stop. Timer ID: {$data['id']}, User: {$current_user['id']}");
        }
        
        // Clean up any other running timers for this user
        $cleanup_stmt = $conn->prepare("
            UPDATE time_entries 
            SET stop_time = TIME(NOW()), 
                updated_by = 'System Cleanup', 
                updated_at = NOW() 
            WHERE user_id = ? AND id != ? AND stop_time = '00:00:00'
        ");
        
        if ($cleanup_stmt) {
            $cleanup_stmt->bind_param("ii", $current_user['id'], $data['id']);
            $cleanup_stmt->execute();
            $cleaned_up = $cleanup_stmt->affected_rows;
            if ($cleaned_up > 0) {
                error_log("Cleaned up $cleaned_up additional running timers for user " . $current_user['id']);
            }
            $cleanup_stmt->close();
        }
        
        send_response(200, [
            'message' => 'Timer stopped successfully',
            'id' => $data['id'],
            'affected_rows' => $affected
        ]);
    } else {
        error_log('Failed to update timer: ' . $stmt->error);
        send_response(500, ['message' => 'Failed to update timer']);
    }
    
    $stmt->close();
}

/**
 * Check for running timer (stop_time = '00:00:00')
 */
function handle_check_running_timer($conn, $current_user) {
    $stmt = $conn->prepare("
        SELECT 
            id, 
            user_id AS userId, 
            username, 
            date, 
            start_time AS startTime, 
            location, 
            role, 
            created_at AS createdAt 
        FROM time_entries 
        WHERE user_id = ? AND stop_time = '00:00:00' 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    
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