<?php
/**
 * Titel: API-Endpunkt für Stammdaten
 * Version: 1.5 (BFF-Architektur)
 * Autor: MP-IT
 * Datei: /api/masterdata.php
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
    case 'PUT':
        handle_put($conn, $user_from_session);
        break;
    default:
        send_response(405, ['message' => 'Method Not Allowed']);
        break;
}

$conn->close();

function handle_get($conn, $current_user) {
    // TODO: Berechtigungsprüfung (z.B. nur Vorgesetzte dürfen alle Stammdaten sehen)
    $stmt = $conn->prepare("SELECT user_id, weekly_hours, workdays, can_work_from_home FROM master_data");
    if (!$stmt) {
        $error_msg = 'Prepare failed for SELECT master_data: ' . $conn->error;
        error_log($error_msg);
        send_response(500, ['message' => 'Datenbankfehler beim Abrufen der Stammdaten.', 'details' => $error_msg]);
        return;
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $master_data_map = [];
    while ($row = $result->fetch_assoc()) {
        $user_id = $row['user_id'];
        $master_data_map[$user_id] = [
            'weeklyHours' => (float)$row['weekly_hours'],
            'workdays' => json_decode($row['workdays']),
            'canWorkFromHome' => (bool)$row['can_work_from_home']
        ];
    }
    
    send_response(200, $master_data_map);
    $stmt->close();
}

function handle_put($conn, $current_user) {
    // TODO: Berechtigungsprüfung (z.B. basierend auf $current_user['role'])
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['userId']) || !isset($data['weeklyHours']) || !isset($data['workdays']) || !isset($data['canWorkFromHome'])) {
        send_response(400, ['message' => 'Bad Request: Missing required fields.']);
        return;
    }

    $stmt = $conn->prepare("INSERT INTO master_data (user_id, weekly_hours, workdays, can_work_from_home) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE weekly_hours = VALUES(weekly_hours), workdays = VALUES(workdays), can_work_from_home = VALUES(can_work_from_home)");
    if (!$stmt) {
        $error_msg = 'Prepare failed for INSERT/UPDATE master_data: ' . $conn->error;
        error_log($error_msg);
        send_response(500, ['message' => 'Datenbankfehler beim Vorbereiten der Stammdaten-Aktualisierung.', 'details' => $error_msg]);
        return;
    }

    $workdays_json = json_encode($data['workdays']);
    
    $stmt->bind_param("idsi", 
        $data['userId'], 
        $data['weeklyHours'], 
        $workdays_json, 
        $data['canWorkFromHome']
    );

    if ($stmt->execute()) {
        send_response(200, ['message' => 'Master data updated successfully.']);
    } else {
        $error_msg = 'Update failed for master_data: ' . $stmt->error;
        error_log($error_msg);
        send_response(500, ['message' => 'Datenbankfehler beim Aktualisieren der Stammdaten.', 'details' => $error_msg]);
    }

    $stmt->close();
}