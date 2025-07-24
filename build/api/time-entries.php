<?php
/**
 * Titel: API-Endpunkt für Zeiteinträge
 * Version: 1.5 (BFF-Architektur)
 * Autor: MP-IT
 * Datei: /api/time-entries.php
 * Beschreibung: Gesichert durch serverseitige Session-Prüfung.
 */

// Robuster Fatal-Error-Handler, um leere Antworten zu verhindern
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        // Strukturierte Fehlerausgabe für besseres Debugging im Frontend
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

// Fehlerberichterstattung für die Entwicklung aktivieren
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helpers.php';

initialize_api();

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
    default:
        send_response(405, ['message' => 'Method Not Allowed']);
        break;
}

$conn->close();

function handle_get($conn, $current_user) {
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
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required_fields = ['userId', 'username', 'date', 'startTime', 'stopTime', 'location', 'role', 'updatedBy'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field])) {
            send_response(400, ['message' => "Bad Request: Missing field '{$field}'"]);
            return;
        }
    }
    
    // Sicherheitsprüfung: Darf der angemeldete Benutzer für die angegebene userId posten?
    // (Entweder für sich selbst oder als Vorgesetzter)
    if ($data['userId'] != $current_user['id']) {
        // TODO: Hier Berechtigungslogik für Vorgesetzte einfügen
        // send_response(403, ['message' => 'Forbidden: You cannot create entries for other users.']);
        // return;
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
        send_response(201, $data);
    } else {
        $error_msg = 'Insert failed for time_entries: ' . $stmt->error;
        error_log($error_msg);
        send_response(500, ['message' => 'Datenbankfehler beim Speichern des Zeiteintrags.', 'details' => $error_msg]);
    }

    $stmt->close();
}