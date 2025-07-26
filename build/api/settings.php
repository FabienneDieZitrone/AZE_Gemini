<?php
/**
 * Titel: API-Endpunkt für Globale Einstellungen
 * Version: 1.5 (BFF-Architektur)
 * Autor: MP-IT
 * Datei: /api/settings.php
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

// SECURITY: Error reporting disabled in production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/validation.php';

initialize_api();

// Stellt sicher, dass der Benutzer authentifiziert ist.
$user_from_session = verify_session_and_get_user();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handle_get($conn);
        break;
    case 'PUT':
        handle_put($conn, $user_from_session);
        break;
    default:
        send_response(405, ['message' => 'Method Not Allowed']);
        break;
}

$conn->close();

function handle_get($conn) {
    $stmt = $conn->prepare("SELECT overtime_threshold, change_reasons, locations FROM global_settings WHERE id = 1");
    if (!$stmt) {
        $error_msg = 'Prepare failed for SELECT global_settings: ' . $conn->error;
        error_log($error_msg);
        send_response(500, ['message' => 'Datenbankfehler beim Abrufen der globalen Einstellungen.', 'details' => $error_msg]);
        return;
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $settings = $result->fetch_assoc();

    if ($settings) {
        $formatted_settings = [
            'overtimeThreshold' => (float)$settings['overtime_threshold'],
            'changeReasons' => json_decode($settings['change_reasons']),
            'locations' => json_decode($settings['locations'])
        ];
        send_response(200, $formatted_settings);
    } else {
        send_response(404, ['message' => 'Global settings not found.']);
    }
    
    $stmt->close();
}

function handle_put($conn, $current_user) {
    // Nur Admins dürfen die globalen Einstellungen ändern.
    if ($current_user['role'] !== 'Admin') {
        send_response(403, ['message' => 'Forbidden: You do not have permission to change global settings.']);
        return;
    }

    try {
        $required_fields = ['overtimeThreshold', 'changeReasons', 'locations'];
        $data = InputValidator::validateJsonInput($required_fields);
        
        // Validate overtimeThreshold is a positive number
        if (!is_numeric($data['overtimeThreshold']) || $data['overtimeThreshold'] < 0 || $data['overtimeThreshold'] > 100) {
            send_response(400, ['message' => 'Invalid overtimeThreshold. Must be between 0 and 100']);
            return;
        }
        
        // Validate changeReasons is an array
        if (!is_array($data['changeReasons'])) {
            send_response(400, ['message' => 'Invalid changeReasons format. Must be an array']);
            return;
        }
        
        // Validate locations is an array
        if (!is_array($data['locations'])) {
            send_response(400, ['message' => 'Invalid locations format. Must be an array']);
            return;
        }
        
    } catch (InvalidArgumentException $e) {
        send_response(400, ['message' => 'Validation error: ' . $e->getMessage()]);
        return;
    } catch (Exception $e) {
        error_log('Validation error in settings.php: ' . $e->getMessage());
        send_response(500, ['message' => 'Server error during validation']);
        return;
    }

    $stmt = $conn->prepare("UPDATE global_settings SET overtime_threshold = ?, change_reasons = ?, locations = ? WHERE id = 1");
    if (!$stmt) {
        $error_msg = 'Prepare failed for UPDATE global_settings: ' . $conn->error;
        error_log($error_msg);
        send_response(500, ['message' => 'Datenbankfehler beim Vorbereiten der Einstellungs-Aktualisierung.', 'details' => $error_msg]);
        return;
    }

    $change_reasons_json = json_encode($data['changeReasons']);
    $locations_json = json_encode($data['locations']);

    $stmt->bind_param("dss", 
        $data['overtimeThreshold'], 
        $change_reasons_json, 
        $locations_json
    );

    if ($stmt->execute()) {
        send_response(200, ['message' => 'Global settings updated successfully.']);
    } else {
        $error_msg = 'Update failed for global_settings: ' . $stmt->error;
        error_log($error_msg);
        send_response(500, ['message' => 'Datenbankfehler beim Speichern der globalen Einstellungen.', 'details' => $error_msg]);
    }
    
    $stmt->close();
}