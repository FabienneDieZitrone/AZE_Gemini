<?php
/**
 * Titel: API-Endpunkt für Zeiteinträge
 * Version: 1.5 (BFF-Architektur)
 * Autor: MP-IT
 * Datei: /api/time-entries.php
 * Beschreibung: Gesichert durch serverseitige Session-Prüfung.
 */

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

require_once __DIR__ . '/db-init.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/validation.php';

initialize_api();


// Stellt sicher, dass der Benutzer authentifiziert ist.
$user_from_session = verify_session_and_get_user();

// CRITICAL: Get user ID from session
if (!isset($user_from_session['id'])) {
    // Try to get ID from database if not in session
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
    
    // If still no ID, log error
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
    // PUT disabled - Apache blocks it, using POST with action=stop instead
    default:
        send_response(405, ['message' => 'Method Not Allowed']);
        break;
}

$conn->close();

function handle_get($conn, $current_user) {
    // QUICK-FIX: Check if this is a running timer check request
    if (isset($_GET['action']) && $_GET['action'] === 'check_running') {
        handle_check_running_timer($conn, $current_user);
        return;
    }
    
    // TODO: Verfeinern, um nur Einträge anzuzeigen, die der Benutzer sehen darf
    // (z.B. eigene Einträge oder die von unterstellten Mitarbeitern), basierend auf $current_user['role'].
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

    send_response(200, $entries);
    $stmt->close();
}

function handle_post($conn, $current_user) {
    // WORKAROUND: Handle stop action via POST instead of PUT
    if (isset($_GET['action']) && $_GET['action'] === 'stop') {
        handle_stop_timer($conn, $current_user);
        return;
    }
    
    try {
        // Allow stopTime to be optional (NULL for running timers)
        $required_fields = ['userId', 'username', 'date', 'startTime', 'location', 'role', 'updatedBy'];
        $optional_fields = ['stopTime' => null];
        $data = InputValidator::validateJsonInput($required_fields, $optional_fields);
        
        // Additional business logic validation
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
        
        // Only validate stopTime if it's not NULL (running timer)
        if ($data['stopTime'] !== null && !InputValidator::isValidTime($data['stopTime'])) {
            send_response(400, ['message' => 'Invalid stopTime format. Expected HH:MM:SS']);
            return;
        }
        
        // Username validation temporarily disabled - Azure AD names have spaces
        // if (!InputValidator::isValidUsername($data['username'])) {
        //     send_response(400, ['message' => 'Invalid username format']);
        //     return;
        // }
        
    } catch (InvalidArgumentException $e) {
        send_response(400, ['message' => 'Validation error: ' . $e->getMessage()]);
        return;
    } catch (Exception $e) {
        error_log('Validation error in time-entries: ' . $e->getMessage());
        send_response(500, ['message' => 'Server error during validation']);
        return;
    }
    
    // Sicherheitsprüfung: Darf der angemeldete Benutzer für die angegebene userId posten?
    // (Entweder für sich selbst oder als Vorgesetzter)
    if ($data['userId'] != $current_user['id']) {
        // TODO: Hier Berechtigungslogik für Vorgesetzte einfügen
        // send_response(403, ['message' => 'Forbidden: You cannot create entries for other users.']);
        // return;
    }


    // CRITICAL FIX: Stop all existing running timers for this user before starting a new one
    if ($data['stopTime'] === null) {
        // This is a new timer start - stop all existing running timers
        $stop_stmt = $conn->prepare("UPDATE time_entries SET stop_time = NOW(), updated_by = 'System Auto-Stop', updated_at = NOW() WHERE user_id = ? AND stop_time IS NULL");
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

    // Handle NULL stopTime for running timers
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
        $data['stopTime'], // Can be NULL for running timers
        $data['location'], 
        $data['role'],
        $data['updatedBy']
    );

    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        $data['id'] = $new_id;
        send_response(201, $data);
    } else {
        $error_msg = 'Insert failed for time_entries: ' . $stmt->error;
        error_log($error_msg);
        send_response(500, ['message' => 'Datenbankfehler beim Speichern des Zeiteintrags.', 'details' => $error_msg]);
    }

    $stmt->close();
}

/**
 * Handle stop timer action (workaround for PUT method blocked by Apache)
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
    
    // First check if the timer exists and belongs to the user
    $check_stmt = $conn->prepare("SELECT id, stop_time FROM time_entries WHERE id = ? AND user_id = ?");
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
    
    if ($timer['stop_time'] !== null) {
        // Timer already stopped - this is OK, just return success
        send_response(200, ['message' => 'Timer already stopped', 'id' => $data['id'], 'alreadyStopped' => true]);
        return;
    }
    
    // Update stopTime for a running timer
    $stmt = $conn->prepare("UPDATE time_entries SET stop_time = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
    if (!$stmt) {
        $error_msg = 'Prepare failed for UPDATE time_entries: ' . $conn->error;
        error_log($error_msg);
        send_response(500, ['message' => 'Database error']);
        return;
    }
    
    $stmt->bind_param("ssii", $data['stopTime'], $data['updatedBy'], $data['id'], $current_user['id']);
    
    if ($stmt->execute()) {
        // Also stop any other running timers for this user (safety measure)
        $cleanup_stmt = $conn->prepare("UPDATE time_entries SET stop_time = NOW(), updated_by = 'System Cleanup', updated_at = NOW() WHERE user_id = ? AND id != ? AND stop_time IS NULL");
        if ($cleanup_stmt) {
            $cleanup_stmt->bind_param("ii", $current_user['id'], $data['id']);
            $cleanup_stmt->execute();
            $cleaned_up = $cleanup_stmt->affected_rows;
            if ($cleaned_up > 0) {
                error_log("Cleaned up $cleaned_up additional running timers for user " . $current_user['id']);
            }
            $cleanup_stmt->close();
        }
        
        send_response(200, ['message' => 'Timer stopped successfully', 'id' => $data['id']]);
    } else {
        error_log('Failed to update timer: ' . $stmt->error);
        send_response(500, ['message' => 'Failed to update timer']);
    }
    
    $stmt->close();
}

/**
 * Check for running timer (stopTime = NULL)
 */
function handle_check_running_timer($conn, $current_user) {
    $stmt = $conn->prepare("SELECT id, user_id AS userId, username, date, start_time AS startTime, location, role, created_at AS createdAt FROM time_entries WHERE user_id = ? AND stop_time IS NULL ORDER BY created_at DESC LIMIT 1");
    
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