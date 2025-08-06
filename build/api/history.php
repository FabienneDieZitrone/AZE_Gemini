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

// SECURITY: Error reporting disabled in production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// Define API guard constant
define('API_GUARD', true);

require_once __DIR__ . '/db-init.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/auth-middleware.php';
require_once __DIR__ . '/security-middleware.php';

initialize_api();

// Apply security headers
initSecurityMiddleware();

// Stellt sicher, dass der Benutzer authentifiziert und autorisiert ist.
$user_from_session = authorize_request();

$method = $_SERVER['REQUEST_METHOD'];

if ($method == 'GET') {
    handle_get($conn, $user_from_session);
} else {
    send_response(405, ['message' => 'Method Not Allowed']);
}

$conn->close();

function handle_get($conn, $current_user) {
    // Pagination parameters
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(10, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    // Rollenbasierte Filterung implementiert with pagination
    $base_query = "SELECT * FROM approval_requests WHERE status != 'pending'";
    $count_query = "SELECT COUNT(*) as total FROM approval_requests WHERE status != 'pending'";
    
    // Berechtigungsprüfung basierend auf Rolle
    if ($current_user['role'] === 'Honorarkraft' || $current_user['role'] === 'Mitarbeiter') {
        // Honorarkraft und Mitarbeiter sehen nur ihre eigene Historie
        $where_clause = " AND requested_by = ?";
        $query = $base_query . $where_clause . " ORDER BY resolved_at DESC LIMIT ? OFFSET ?";
        $count_query_full = $count_query . $where_clause;
        
        $stmt = $conn->prepare($query);
        $count_stmt = $conn->prepare($count_query_full);
        
        if (!$stmt || !$count_stmt) {
            $error_msg = 'Prepare failed for SELECT history: ' . $conn->error;
            error_log($error_msg);
            send_response(500, ['message' => 'Datenbankfehler beim Abrufen der Änderungshistorie.', 'details' => $error_msg]);
            return;
        }
        
        $stmt->bind_param("sii", $current_user['username'], $limit, $offset);
        $count_stmt->bind_param("s", $current_user['username']);
        
    } else if ($current_user['role'] === 'Standortleiter') {
        // Standortleiter sehen Historie ihrer Location
        $where_clause = " AND JSON_EXTRACT(original_entry_data, '$.location') = ?";
        $query = $base_query . $where_clause . " ORDER BY resolved_at DESC LIMIT ? OFFSET ?";
        $count_query_full = $count_query . $where_clause;
        
        $stmt = $conn->prepare($query);
        $count_stmt = $conn->prepare($count_query_full);
        
        if (!$stmt || !$count_stmt) {
            $error_msg = 'Prepare failed for SELECT history: ' . $conn->error;
            error_log($error_msg);
            send_response(500, ['message' => 'Datenbankfehler beim Abrufen der Änderungshistorie.', 'details' => $error_msg]);
            return;
        }
        
        $stmt->bind_param("sii", $current_user['location'], $limit, $offset);
        $count_stmt->bind_param("s", $current_user['location']);
        
    } else {
        // Bereichsleiter und Admin sehen alle Historie
        $query = $base_query . " ORDER BY resolved_at DESC LIMIT ? OFFSET ?";
        
        $stmt = $conn->prepare($query);
        $count_stmt = $conn->prepare($count_query);
        
        if (!$stmt || !$count_stmt) {
            $error_msg = 'Prepare failed for SELECT history: ' . $conn->error;
            error_log($error_msg);
            send_response(500, ['message' => 'Datenbankfehler beim Abrufen der Änderungshistorie.', 'details' => $error_msg]);
            return;
        }
        
        $stmt->bind_param("ii", $limit, $offset);
    }
    
    // Execute both queries
    $stmt->execute();
    $result = $stmt->get_result();
    
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_count = $count_result->fetch_assoc()['total'];
    
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
    
    // Return paginated response
    $response = [
        'data' => $history_items,
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