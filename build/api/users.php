<?php
/**
 * Titel: API-Endpunkt für Benutzer
 * Version: 1.7 (BFF-Architektur)
 * Autor: MP-IT
 * Datei: /api/users.php
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

require_once __DIR__ . '/db-init.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/validation.php';

// --- Hilfsfunktionen & Header ---
initialize_api();

// Stellt sicher, dass der Benutzer authentifiziert ist, bevor fortgefahren wird.
$user_from_session = verify_session_and_get_user();

// --- Logik basierend auf der Request-Methode ---
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handle_get($conn);
        break;
    case 'PATCH':
        handle_patch($conn, $user_from_session);
        break;
    default:
        send_response(405, ['message' => 'Method Not Allowed']);
        break;
}

$conn->close();

// --- Handler-Funktionen ---

function handle_get($conn) {
    // Gibt display_name als 'name' zurück, um Frontend-Kompatibilität zu wahren
    $stmt = $conn->prepare("SELECT id, display_name AS name, role, azure_oid AS azureOid FROM users");
    if (!$stmt) {
        $error_msg = 'Prepare failed for SELECT users: ' . $conn->error;
        error_log($error_msg);
        send_response(500, ['message' => 'Datenbankfehler beim Abrufen der Benutzer.', 'details' => $error_msg]);
        return;
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result->fetch_all(MYSQLI_ASSOC);
    
    send_response(200, $users);
    $stmt->close();
}

function handle_patch($conn, $current_user) {
    // TODO: Rollenbasierte Berechtigungsprüfung (z.B. nur Admin darf Rollen ändern)
    // $current_user['role'] kann hier für die Prüfung verwendet werden.
    
    try {
        $required_fields = ['userId', 'newRole'];
        $data = InputValidator::validateJsonInput($required_fields);
        
        // Validate userId is a positive integer
        if (!InputValidator::isValidId($data['userId'])) {
            send_response(400, ['message' => 'Invalid userId format']);
            return;
        }
        
        // Validate newRole is one of allowed values
        $allowed_roles = ['employee', 'manager', 'admin'];
        if (!in_array($data['newRole'], $allowed_roles)) {
            send_response(400, ['message' => 'Invalid role. Allowed: ' . implode(', ', $allowed_roles)]);
            return;
        }
        
    } catch (InvalidArgumentException $e) {
        send_response(400, ['message' => 'Validation error: ' . $e->getMessage()]);
        return;
    } catch (Exception $e) {
        error_log('Validation error in users.php: ' . $e->getMessage());
        send_response(500, ['message' => 'Server error during validation']);
        return;
    }

    $userId = $data['userId'];
    $newRole = $data['newRole'];

    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
    if (!$stmt) {
        $error_msg = 'Prepare failed for UPDATE users role: ' . $conn->error;
        error_log($error_msg);
        send_response(500, ['message' => 'Datenbankfehler beim Vorbereiten der Rollenänderung.', 'details' => $error_msg]);
        return;
    }

    $stmt->bind_param("si", $newRole, $userId);
    
    if ($stmt->execute()) {
        send_response(200, ['message' => 'User role updated successfully.']);
    } else {
        $error_msg = 'Update failed for users role: ' . $stmt->error;
        error_log($error_msg);
        send_response(500, ['message' => 'Datenbankfehler beim Ändern der Rolle.', 'details' => $error_msg]);
    }
    
    $stmt->close();
}