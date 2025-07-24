<?php
/**
 * Titel: API-Endpunkt für Änderungshistorie
 * Version: 1.5 (BFF-Architektur)
 * Autor: MP-IT
 * Datei: /api/history.php
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

if ($method == 'GET') {
    handle_get($conn, $user_from_session);
} else {
    send_response(405, ['message' => 'Method Not Allowed']);
}

$conn->close();

function handle_get($conn, $current_user) {
    // TODO: Berechtigungsprüfung (z.B. basierend auf $current_user['role'])
    
    $stmt = $conn->prepare("SELECT * FROM approval_requests WHERE status != 'pending' ORDER BY resolved_at DESC");
    if (!$stmt) {
        $error_msg = 'Prepare failed for SELECT history: ' . $conn->error;
        error_log($error_msg);
        send_response(500, ['message' => 'Datenbankfehler beim Abrufen der Änderungshistorie.', 'details' => $error_msg]);
        return;
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $history_items = [];
    
    while ($row = $result->fetch_assoc()) {
        $original_entry = json_decode($row['original_entry_data'], true);
        
        // Frontend erwartet das TimeEntry-Format unter 'entry'
        $entry_for_frontend = [
            'id' => (int)$original_entry['id'],
            'userId' => (int)$original_entry['user_id'],
            'username' => $original_entry['username'],
            'date' => $original_entry['date'],
            'startTime' => $original_entry['start_time'],
            'stopTime' => $original_entry['stop_time'],
            'location' => $original_entry['location'],
            'role' => $original_entry['role'],
            'createdAt' => $original_entry['created_at'],
            'updatedBy' => $original_entry['updated_by'],
            'updatedAt' => $original_entry['updated_at'],
        ];

        $history_items[] = [
            'id' => $row['id'],
            'type' => $row['type'],
            'entry' => $entry_for_frontend,
            'newData' => json_decode($row['new_data']),
            'reasonData' => json_decode($row['reason_data']),
            'requestedBy' => $row['requested_by'],
            'finalStatus' => $row['status'], // 'genehmigt' or 'abgelehnt'
            'resolvedAt' => $row['resolved_at'],
            'resolvedBy' => $row['resolved_by'],
        ];
    }
    
    send_response(200, $history_items);
    $stmt->close();
}