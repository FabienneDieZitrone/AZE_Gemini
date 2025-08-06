<?php
/**
 * Titel: API-Endpunkt für Benutzer
 * Version: 1.7 (BFF-Architektur)
 * Autor: MP-IT
 * Datei: /api/users.php
 * Beschreibung: Gesichert durch serverseitige Session-Prüfung.
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
require_once __DIR__ . '/security-middleware.php';
require_once __DIR__ . '/rate-limiting.php';
require_once __DIR__ . '/csrf-middleware.php';

// --- Hilfsfunktionen & Header ---
initialize_api();

// Apply security headers
initSecurityMiddleware();

// Apply rate limiting
checkRateLimit('users');

// Validate CSRF for state-changing operations
if (requiresCsrfProtection()) {
    validateCsrfProtection();
}

// Stellt sicher, dass der Benutzer authentifiziert und autorisiert ist.
require_once __DIR__ . '/auth-middleware.php';
$user_from_session = authorize_request();

// --- Logik basierend auf der Request-Methode ---
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handle_get($conn, $current_user);
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

function handle_get($conn, $current_user) {
    // Pagination parameters
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(10, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    // Rollenbasierte Filterung implementiert with pagination
    $base_query = "SELECT id, display_name AS name, role, azure_oid AS azureOid FROM users";
    $count_query = "SELECT COUNT(*) as total FROM users";
    
    // Berechtigungsprüfung basierend auf Rolle
    if ($current_user['role'] === 'Honorarkraft') {
        // Honorarkraft sieht nur sich selbst
        $where_clause = " WHERE id = ?";
        $query = $base_query . $where_clause . " ORDER BY display_name ASC LIMIT ? OFFSET ?";
        $count_query_full = $count_query . $where_clause;
        
        $stmt = $conn->prepare($query);
        $count_stmt = $conn->prepare($count_query_full);
        
        if (!$stmt || !$count_stmt) {
            $error_msg = 'Prepare failed for SELECT users (Honorarkraft): ' . $conn->error;
            error_log($error_msg);
            send_response(500, ['message' => 'Datenbankfehler beim Abrufen der Benutzer.', 'details' => $error_msg]);
            return;
        }
        
        $stmt->bind_param("iii", $current_user['id'], $limit, $offset);
        $count_stmt->bind_param("i", $current_user['id']);
        
    } else if ($current_user['role'] === 'Mitarbeiter') {
        // Mitarbeiter sehen alle außer Honorarkräfte
        $where_clause = " WHERE role != 'Honorarkraft' OR id = ?";
        $query = $base_query . $where_clause . " ORDER BY display_name ASC LIMIT ? OFFSET ?";
        $count_query_full = $count_query . $where_clause;
        
        $stmt = $conn->prepare($query);
        $count_stmt = $conn->prepare($count_query_full);
        
        if (!$stmt || !$count_stmt) {
            $error_msg = 'Prepare failed for SELECT users (Mitarbeiter): ' . $conn->error;
            error_log($error_msg);
            send_response(500, ['message' => 'Datenbankfehler beim Abrufen der Benutzer.', 'details' => $error_msg]);
            return;
        }
        
        $stmt->bind_param("iii", $current_user['id'], $limit, $offset);
        $count_stmt->bind_param("i", $current_user['id']);
        
    } else if ($current_user['role'] === 'Standortleiter') {
        // Standortleiter sehen alle ihrer Location (benötigt location-Spalte in users)
        // Vorerst: Alle außer andere Standortleiter anderer Locations
        $query = $base_query . " ORDER BY display_name ASC LIMIT ? OFFSET ?";
        
        $stmt = $conn->prepare($query);
        $count_stmt = $conn->prepare($count_query);
        
        if (!$stmt || !$count_stmt) {
            $error_msg = 'Prepare failed for SELECT users (Standortleiter): ' . $conn->error;
            error_log($error_msg);
            send_response(500, ['message' => 'Datenbankfehler beim Abrufen der Benutzer.', 'details' => $error_msg]);
            return;
        }
        
        $stmt->bind_param("ii", $limit, $offset);
        
    } else {
        // Bereichsleiter und Admin sehen alle Benutzer
        $query = $base_query . " ORDER BY display_name ASC LIMIT ? OFFSET ?";
        
        $stmt = $conn->prepare($query);
        $count_stmt = $conn->prepare($count_query);
        
        if (!$stmt || !$count_stmt) {
            $error_msg = 'Prepare failed for SELECT users: ' . $conn->error;
            error_log($error_msg);
            send_response(500, ['message' => 'Datenbankfehler beim Abrufen der Benutzer.', 'details' => $error_msg]);
            return;
        }
        
        $stmt->bind_param("ii", $limit, $offset);
    }
    
    // Execute both queries
    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result->fetch_all(MYSQLI_ASSOC);
    
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_count = $count_result->fetch_assoc()['total'];
    
    // Return paginated response
    $response = [
        'data' => $users,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total_count,
            'pages' => (int)ceil($total_count / $limit),
            'hasNext' => $page < ceil($total_count / $limit),
            'hasPrev' => $page > 1
        ]
    ];
    
    send_response(200, $response);
    $stmt->close();
    $count_stmt->close();
}

function handle_patch($conn, $current_user) {
    // SECURITY FIX: Only Admin can change user roles
    if ($current_user['role'] !== 'Admin') {
        send_response(403, ['message' => 'Forbidden: Only Admin users can change user roles']);
        return;
    }
    
    try {
        $required_fields = ['userId', 'newRole'];
        $data = InputValidator::validateJsonInput($required_fields);
        
        // Validate userId is a positive integer
        if (!InputValidator::isValidId($data['userId'])) {
            send_response(400, ['message' => 'Invalid userId format']);
            return;
        }
        
        // Validate newRole is one of allowed values (German role names)
        $allowed_roles = ['Honorarkraft', 'Mitarbeiter', 'Standortleiter', 'Bereichsleiter', 'Admin'];
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