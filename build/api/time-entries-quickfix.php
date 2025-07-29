<?php
/**
 * QUICK FIX für Issue #29 - Timer Stop Problem
 * Verwendet '00:00:00' statt NULL für laufende Timer
 * da stop_time NOT NULL in der DB ist
 */

// Original includes
require_once __DIR__ . '/error-handler.php';

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

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

require_once __DIR__ . '/security-headers.php';
require_once __DIR__ . '/db-init.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/validation.php';

initialize_api();

$user_from_session = verify_session_and_get_user();

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
    // Check running timer - FIXED to use 00:00:00
    if (isset($_GET['action']) && $_GET['action'] === 'check_running') {
        $stmt = $conn->prepare("SELECT id, user_id AS userId, username, date, start_time AS startTime, location, role, created_at AS createdAt FROM time_entries WHERE user_id = ? AND stop_time = '00:00:00' ORDER BY created_at DESC LIMIT 1");
        
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
        return;
    }
    
    // Get all entries
    $stmt = $conn->prepare("SELECT id, user_id AS userId, username, date, start_time AS startTime, stop_time AS stopTime, location, role, created_at AS createdAt, updated_by AS updatedBy, updated_at AS updatedAt FROM time_entries ORDER BY date DESC, start_time DESC");
    if (!$stmt) {
        $error_msg = 'Prepare failed for SELECT time_entries: ' . $conn->error;
        error_log($error_msg);
        send_response(500, ['message' => 'Datenbankfehler beim Abrufen der Zeiteinträge.', 'details' => $error_msg]);
        return;
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $entries = $result->fetch_all(MYSQLI_ASSOC);
    
    // Convert 00:00:00 to null for frontend
    foreach ($entries as &$entry) {
        if ($entry['stopTime'] === '00:00:00') {
            $entry['stopTime'] = null;
        }
    }

    send_response(200, $entries);
    $stmt->close();
}

function handle_post($conn, $current_user) {
    // Handle stop action
    if (isset($_GET['action']) && $_GET['action'] === 'stop') {
        try {
            $required_fields = ['id'];
            $optional_fields = ['stopTime' => null, 'updatedBy' => $current_user['name']];
            $data = InputValidator::validateJsonInput($required_fields, $optional_fields);
            
            if (!InputValidator::isValidId($data['id'])) {
                send_response(400, ['message' => 'Invalid id format']);
                return;
            }
            
            // Use current time if no stopTime provided
            $stopTime = $data['stopTime'] ?? date('H:i:s');
            
            if (!InputValidator::isValidTime($stopTime)) {
                send_response(400, ['message' => 'Invalid stopTime format. Expected HH:MM:SS']);
                return;
            }
            
        } catch (Exception $e) {
            send_response(400, ['message' => 'Validation error: ' . $e->getMessage()]);
            return;
        }
        
        // FIXED: Update using 00:00:00 check
        $stmt = $conn->prepare("UPDATE time_entries SET stop_time = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND user_id = ? AND stop_time = '00:00:00'");
        if (!$stmt) {
            $error_msg = 'Prepare failed for UPDATE time_entries: ' . $conn->error;
            error_log($error_msg);
            send_response(500, ['message' => 'Database error']);
            return;
        }
        
        $stmt->bind_param("ssii", $stopTime, $data['updatedBy'], $data['id'], $current_user['id']);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                send_response(200, ['message' => 'Timer stopped successfully', 'id' => $data['id']]);
            } else {
                // Check if timer exists but was already stopped
                $check_stmt = $conn->prepare("SELECT stop_time FROM time_entries WHERE id = ? AND user_id = ?");
                $check_stmt->bind_param("ii", $data['id'], $current_user['id']);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    if ($row['stop_time'] !== '00:00:00') {
                        send_response(200, ['message' => 'Timer was already stopped', 'id' => $data['id']]);
                    } else {
                        send_response(404, ['message' => 'Timer not found or not owned by user']);
                    }
                } else {
                    send_response(404, ['message' => 'Timer not found']);
                }
                $check_stmt->close();
            }
        } else {
            error_log('Failed to update timer: ' . $stmt->error);
            send_response(500, ['message' => 'Failed to update timer']);
        }
        
        $stmt->close();
        return;
    }
    
    // Create new timer
    try {
        $required_fields = ['userId', 'username', 'date', 'startTime', 'location', 'role', 'updatedBy'];
        $optional_fields = ['stopTime' => null];
        $data = InputValidator::validateJsonInput($required_fields, $optional_fields);
        
        // Validations...
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
        
        // Convert null to 00:00:00 for running timers
        if ($data['stopTime'] === null) {
            $data['stopTime'] = '00:00:00';
            
            // Stop all existing running timers
            $stop_stmt = $conn->prepare("UPDATE time_entries SET stop_time = NOW(), updated_by = 'System Auto-Stop', updated_at = NOW() WHERE user_id = ? AND stop_time = '00:00:00'");
            if ($stop_stmt) {
                $stop_stmt->bind_param("i", $data['userId']);
                $stop_stmt->execute();
                $stopped_count = $stop_stmt->affected_rows;
                if ($stopped_count > 0) {
                    error_log("Auto-stopped $stopped_count running timer(s) for user " . $data['userId']);
                }
                $stop_stmt->close();
            }
        } elseif (!InputValidator::isValidTime($data['stopTime'])) {
            send_response(400, ['message' => 'Invalid stopTime format. Expected HH:MM:SS']);
            return;
        }
        
    } catch (InvalidArgumentException $e) {
        send_response(400, ['message' => 'Validation error: ' . $e->getMessage()]);
        return;
    } catch (Exception $e) {
        error_log('Validation error in time-entries: ' . $e->getMessage());
        send_response(500, ['message' => 'Server error during validation']);
        return;
    }

    $stmt = $conn->prepare("INSERT INTO time_entries (user_id, username, date, start_time, stop_time, location, role, updated_by, updated_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
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
        $data['stopTime'],
        $data['location'], 
        $data['role'],
        $data['updatedBy']
    );

    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        $data['id'] = $new_id;
        // Convert back to null for frontend
        if ($data['stopTime'] === '00:00:00') {
            $data['stopTime'] = null;
        }
        send_response(201, $data);
    } else {
        $error_msg = 'Insert failed for time_entries: ' . $stmt->error;
        error_log($error_msg);
        send_response(500, ['message' => 'Datenbankfehler beim Speichern des Zeiteintrags.', 'details' => $error_msg]);
    }

    $stmt->close();
}
?>