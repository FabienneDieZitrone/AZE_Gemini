<?php
/**
 * Titel: API-Endpunkt für Genehmigungen
 * Version: 1.6 (BFF-Architektur)
 * Autor: MP-IT
 * Datei: /api/approvals.php
 * Beschreibung: Gesichert durch serverseitige Session-Prüfung. Verwendet den Benutzernamen aus der Session.
 */

// Define API guard constant
define('API_GUARD', true);

// Robuster Fatal-Error-Handler, um leere Antworten zu verhindern
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode(['message' => 'Fatal PHP Error', 'error_details' => $error]);
        exit;
    }
});

// Fehlerberichterstattung für die Entwicklung aktivieren
// SECURITY: Error reporting disabled in production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

require_once __DIR__ . '/db-init.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/security-middleware.php';

initialize_api();

// Apply security headers
initSecurityMiddleware();

// Stellt sicher, dass der Benutzer authentifiziert ist.
$user_from_session = verify_session_and_get_user();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handle_get($conn, $user_from_session);
        break;
    case 'POST':
        handle_post($conn, $user_from_session);
        break;
    case 'PATCH':
        handle_patch($conn, $user_from_session);
        break;
    default:
        send_response(405, ['message' => 'Method Not Allowed']);
        break;
}

$conn->close();

function handle_get($conn, $current_user) {
    // TODO: Berechtigungsprüfung (z.B. nur Vorgesetzte sehen Anträge)
    $stmt = $conn->prepare("SELECT * FROM approval_requests WHERE status = 'pending'");
    $stmt->execute();
    $result = $stmt->get_result();
    $requests = $result->fetch_all(MYSQLI_ASSOC);

    // Hydrate requests with full entry data
    foreach ($requests as $i => $req) {
        $entry_id = $req['entry_id'];
        $entry_stmt = $conn->prepare("SELECT id, user_id AS userId, username, date, start_time AS startTime, stop_time AS stopTime, location, role, created_at AS createdAt, updated_by AS updatedBy, updated_at AS updatedAt FROM time_entries WHERE id = ?");
        if (!$entry_stmt) {
             error_log('Prepare failed for SELECT entry details in approvals: ' . $conn->error);
             continue; // Skip this request if entry can't be fetched
        }
        $entry_stmt->bind_param("i", $entry_id);
        $entry_stmt->execute();
        $entry_result = $entry_stmt->get_result();
        $entry_data = $entry_result->fetch_assoc();
        
        $requests[$i]['entry'] = $entry_data;
        $requests[$i]['newData'] = json_decode($req['new_data']);
        $requests[$i]['reasonData'] = json_decode($req['reason_data']);
        $entry_stmt->close();
    }
    
    send_response(200, $requests);
    $stmt->close();
}

function handle_post($conn, $current_user) {
    try {
        $required_fields = ['type', 'entryId'];
        $optional_fields = ['newData' => null, 'reasonData' => null];
        $data = InputValidator::validateJsonInput($required_fields, $optional_fields);
        
        // Validate entryId is a positive integer
        if (!InputValidator::isValidId($data['entryId'])) {
            send_response(400, ['message' => 'Invalid entryId format']);
            return;
        }
        
        // Validate type is allowed
        $allowed_types = ['edit', 'delete', 'add'];
        if (!in_array($data['type'], $allowed_types)) {
            send_response(400, ['message' => 'Invalid type. Allowed: ' . implode(', ', $allowed_types)]);
            return;
        }
        
    } catch (InvalidArgumentException $e) {
        send_response(400, ['message' => 'Validation error: ' . $e->getMessage()]);
        return;
    } catch (Exception $e) {
        error_log('Validation error in approvals.php: ' . $e->getMessage());
        send_response(500, ['message' => 'Server error during validation']);
        return;
    }
    
    $requested_by = $current_user['name']; // Verwende den Anzeigenamen aus der Session
    
    $entry_stmt = $conn->prepare("SELECT * FROM time_entries WHERE id = ?");
    $entry_stmt->bind_param("i", $data['entryId']);
    $entry_stmt->execute();
    $original_entry = $entry_stmt->get_result()->fetch_assoc();
    if (!$original_entry) {
        send_response(404, ['message' => 'Original entry not found.']);
        return;
    }
    $entry_stmt->close();
    
    $stmt = $conn->prepare("INSERT INTO approval_requests (id, type, entry_id, original_entry_data, new_data, reason_data, requested_by, requested_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')");
    if (!$stmt) {
        $error_msg = 'Prepare failed for INSERT approval_requests: ' . $conn->error;
        error_log($error_msg);
        send_response(500, ['message' => 'Datenbankfehler beim Erstellen des Genehmigungsantrags.', 'details' => $error_msg]);
        return;
    }
    
    $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
    $type = $data['type'];
    $entry_id = $data['entryId'];
    $original_entry_json = json_encode($original_entry);
    $new_data_json = isset($data['newData']) ? json_encode($data['newData']) : null;
    $reason_data_json = isset($data['reasonData']) ? json_encode($data['reasonData']) : null;

    $stmt->bind_param("ssissss", $uuid, $type, $entry_id, $original_entry_json, $new_data_json, $reason_data_json, $requested_by);

    if ($stmt->execute()) {
        send_response(201, ['message' => 'Approval request created.', 'id' => $uuid]);
    } else {
        $error_msg = 'Insert failed for approval_requests: ' . $stmt->error;
        error_log($error_msg);
        send_response(500, ['message' => 'Datenbankfehler beim Speichern des Genehmigungsantrags.', 'details' => $error_msg]);
    }
    $stmt->close();
}

function handle_patch($conn, $current_user) {
    try {
        $required_fields = ['requestId', 'finalStatus'];
        $data = InputValidator::validateJsonInput($required_fields);
        
        // Validate finalStatus is allowed
        $allowed_statuses = ['genehmigt', 'abgelehnt'];
        if (!in_array($data['finalStatus'], $allowed_statuses)) {
            send_response(400, ['message' => 'Invalid finalStatus. Allowed: ' . implode(', ', $allowed_statuses)]);
            return;
        }
        
        // Validate requestId format (UUID)
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $data['requestId'])) {
            send_response(400, ['message' => 'Invalid requestId format']);
            return;
        }
        
    } catch (InvalidArgumentException $e) {
        send_response(400, ['message' => 'Validation error: ' . $e->getMessage()]);
        return;
    } catch (Exception $e) {
        error_log('Validation error in approvals.php PATCH: ' . $e->getMessage());
        send_response(500, ['message' => 'Server error during validation']);
        return;
    }
    
    $resolved_by = $current_user['name']; // Verwende Anzeigenamen aus der Session
    $request_id = $data['requestId'];
    $final_status = $data['finalStatus'];

    // 1. Update the approval request status
    $stmt = $conn->prepare("UPDATE approval_requests SET status = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?");
    $stmt->bind_param("sss", $final_status, $resolved_by, $request_id);
    $stmt->execute();
    $stmt->close();

    // 2. If approved, apply the change
    if ($final_status === 'genehmigt') {
        $req_stmt = $conn->prepare("SELECT * FROM approval_requests WHERE id = ?");
        $req_stmt->bind_param("s", $request_id);
        $req_stmt->execute();
        $request = $req_stmt->get_result()->fetch_assoc();
        $req_stmt->close();

        if ($request['type'] === 'edit') {
            $new_data = json_decode($request['new_data'], true);
            $update_stmt = $conn->prepare("UPDATE time_entries SET start_time = ?, stop_time = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
            $update_stmt->bind_param("sssi", $new_data['startTime'], $new_data['stopTime'], $resolved_by, $request['entry_id']);
            $update_stmt->execute();
            $update_stmt->close();
        } elseif ($request['type'] === 'delete') {
            $delete_stmt = $conn->prepare("DELETE FROM time_entries WHERE id = ?");
            $delete_stmt->bind_param("i", $request['entry_id']);
            $delete_stmt->execute();
            $delete_stmt->close();
        }
    }

    send_response(200, ['message' => "Request {$final_status}."]);
}