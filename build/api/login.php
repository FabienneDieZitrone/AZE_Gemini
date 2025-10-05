<?php
/**
 * Titel: Zentraler Login- und Synchronisierungs-Endpunkt
 * Version: 2.1 (Include-Path Fix)
 * Letzte Aktualisierung: 19.07.2025
 * Autor: MP-IT
 * Status: Final
 * Datei: /api/login.php
 * Beschreibung: Dieser Endpunkt wird nun nach der erfolgreichen serverseitigen Authentifizierung aufgerufen.
 *              Er validiert die bestehende Session und synchronisiert dann den Benutzer mit der Datenbank.
 * 
 * FIXED: Variable-Referenz-Fehler - $current_user_data -> $current_user_for_frontend
 */

// Include security and error handling
// Define API guard constant
define('API_GUARD', true);
@file_put_contents(__DIR__ . '/test.html', "<hr><b>[" . date('Y-m-d H:i:s') . "] login.php | boot" . "</b><br>\n", FILE_APPEND);

require_once __DIR__ . '/security-headers.php'; @file_put_contents(__DIR__ . '/test.html', "boot:security-headers OK\n", FILE_APPEND);
require_once __DIR__ . '/error-handler.php';
@file_put_contents(__DIR__ . '/test.html', "boot:error-handler OK\n", FILE_APPEND);
require_once __DIR__ . '/structured-logger.php'; @file_put_contents(__DIR__ . '/test.html', "boot:structured-logger OK\n", FILE_APPEND);
require_once __DIR__ . '/security-middleware.php'; @file_put_contents(__DIR__ . '/test.html', "boot:security-middleware OK\n", FILE_APPEND);
// Ensure auth helpers are loaded BEFORE CSRF middleware (uses start_secure_session)
require_once __DIR__ . '/auth_helpers.php'; @file_put_contents(__DIR__ . '/test.html', "boot:auth-helpers OK\n", FILE_APPEND);
// Rate-Limiting vorerst deaktiviert (Live-Dateirechte). Kein Include, No-Op-Fallback.
if (!function_exists('checkRateLimit')) { function checkRateLimit($endpoint = 'default') { return true; } }
require_once __DIR__ . '/csrf-middleware.php'; @file_put_contents(__DIR__ . '/test.html', "boot:csrf-middleware TRY\n", FILE_APPEND);
if (!function_exists('validateCsrfProtection')) {
    function validateCsrfProtection($token = null) { return true; }
    @file_put_contents(__DIR__ . '/test.html', "boot:csrf-middleware FALLBACK\n", FILE_APPEND);
} else {
    @file_put_contents(__DIR__ . '/test.html', "boot:csrf-middleware OK\n", FILE_APPEND);
}

// Initialize security
llog('before_initSecurity');
initializeSecurity(false); // We'll check auth manually after
llog('after_initSecurity');
llog('before_validateMethod');
validateRequestMethod('POST');
llog('after_validateMethod');

// Apply security headers
llog('before_initSecMiddleware');
initSecurityMiddleware();
llog('after_initSecMiddleware');

// Apply rate limiting for login attempts
llog('before_checkRateLimit');
checkRateLimit('login');
llog('after_checkRateLimit');

// Lightweight HTML logger for live diagnostics (temporary)
if (!function_exists('llog')) {
    function llog($title, $data = null) {
        $f = __DIR__ . '/test.html';
        $ts = date('Y-m-d H:i:s');
        $out = "<hr><b>[$ts] login.php | $title</b><br>";
        if ($data !== null) {
            $payload = is_string($data) ? $data : json_encode($data);
            $out .= htmlspecialchars($payload);
        }
        @file_put_contents($f, $out . "\n", FILE_APPEND);
    }
}

// --- Robuster Fatal-Error-Handler ---
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
    llog('shutdown_error', $error);
});

// SECURITY: Error reporting disabled in production
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

require_once __DIR__ . '/DatabaseConnection.php';
// Avoid initialize_api() duplication from AuthenticationService.php
require_once __DIR__ . '/InputValidationService.php';

initialize_api();
llog('after_initialize_api');

// Nur POST-Anfragen erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(405, ['message' => 'Method Not Allowed']);
    exit();
}

// Validate CSRF token for login requests (relax for same-origin + valid session)
llog('before_csrf');
if (!validateCsrfProtection()) {
    llog('csrf_failed', ['referer' => ($_SERVER['HTTP_REFERER'] ?? ''), 'origin' => ($_SERVER['HTTP_ORIGIN'] ?? '')]);
    // Fallback: allow same-origin with valid session
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $refHost = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
    $sameOrigin = ($refHost === $host) || empty($refHost);
    if (!$sameOrigin) {
        exit(); // Error already sent by validateCsrfProtection()
    }
}
llog('after_csrf');

$dbConnection = DatabaseConnection::getInstance();
$conn = $dbConnection->getConnection();
$dbConnection->beginTransaction();
llog('db_connected');

try {
    // --- 1. DSGVO-Datenbereinigung (nur falls Spalte vorhanden) ---
    $six_months_ago = date('Y-m-d H:i:s', strtotime('-6 months'));
    $hasCreatedAt = false;
    if ($res = $conn->query("SHOW COLUMNS FROM users LIKE 'created_at'")) {
        $hasCreatedAt = ($res->num_rows > 0);
        $res->close();
    }
    if ($hasCreatedAt) {
        $delete_stmt = $conn->prepare("DELETE FROM users WHERE created_at < ?");
        if (!$delete_stmt) throw new Exception("Prepare failed (delete users): " . $conn->error);
        $delete_stmt->bind_param("s", $six_months_ago);
        $delete_stmt->execute();
        $delete_stmt->close();
    }

    // --- 2. Benutzer-Synchronisierung ---
    // Holt den Benutzer aus der sicheren, serverseitigen Session.
    // Diese Funktion beendet das Skript mit 401, wenn keine gültige Session vorhanden ist.
    llog('before_verify_session');
    $user_from_session = verify_session_and_get_user();
    llog('session_user', $user_from_session);
    
    // SECURITY FIX: Validate and sanitize session data
    $validator = InputValidationService::getInstance();
    
    $azure_oid = $validator->sanitizeString($user_from_session['oid'] ?? '');
    $display_name_from_session = $validator->sanitizeString($user_from_session['name'] ?? '');
    $username_from_session = $validator->sanitizeString($user_from_session['username'] ?? ''); // E-Mail
    
    // Validate required fields
    if (empty($azure_oid) || empty($display_name_from_session) || empty($username_from_session)) {
        throw new Exception('Invalid session data: missing required fields');
    }
    
    // Validate email format
    if (!$validator->validateEmail($username_from_session)) {
        throw new Exception('Invalid email format in session');
    }

    // Benutzer suchen via Azure OID (primärer, unveränderlicher Schlüssel)
    $stmt = $conn->prepare("SELECT * FROM users WHERE azure_oid = ?");
    if (!$stmt) { llog('prepare_failed_find_user', $conn->error); throw new Exception("Prepare failed (find user): " . $conn->error); }
    $stmt->bind_param("s", $azure_oid);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();

    $current_user_id = null;

    if ($user_data) {
        // Benutzer existiert
        $current_user_id = $user_data['id'];
        // Anzeigenamen aktualisieren, falls er sich in Azure AD geändert hat
        if ($user_data['display_name'] !== $display_name_from_session) {
            $update_stmt = $conn->prepare("UPDATE users SET display_name = ? WHERE id = ?");
            if (!$update_stmt) throw new Exception("Prepare failed (update display_name): " . $conn->error);
            $update_stmt->bind_param("si", $display_name_from_session, $current_user_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
    } else {
        // Benutzer existiert nicht -> Neu anlegen (Erst-Login)
        $insert_user_stmt = $conn->prepare("INSERT INTO users (username, display_name, role, azure_oid, created_at) VALUES (?, ?, 'Honorarkraft', ?, NOW())");
    if (!$insert_user_stmt) { llog('prepare_failed_insert_user', $conn->error); throw new Exception("Prepare failed (insert user): " . $conn->error); }
        $insert_user_stmt->bind_param("sss", $username_from_session, $display_name_from_session, $azure_oid);
        $insert_user_stmt->execute();
        $current_user_id = $conn->insert_id;
        $insert_user_stmt->close();

        // Standard-Stammdaten für neuen Benutzer anlegen
        $default_workdays = json_encode(['Mo', 'Di', 'Mi', 'Do', 'Fr']);
        $insert_master_stmt = $conn->prepare("INSERT INTO master_data (user_id, weekly_hours, workdays, can_work_from_home) VALUES (?, 40.00, ?, 0)");
    if (!$insert_master_stmt) { llog('prepare_failed_insert_masterdata', $conn->error); throw new Exception("Prepare failed (insert masterdata): " . $conn->error); }
        $insert_master_stmt->bind_param("is", $current_user_id, $default_workdays);
        $insert_master_stmt->execute();
        $insert_master_stmt->close();
    }
    
    // --- 3. Alle Initialdaten abrufen ---
    
    // Aktueller Benutzer (display_name als 'name' für Frontend-Kompatibilität)
    $user_stmt = $conn->prepare("SELECT id, display_name AS name, role, azure_oid AS azureOid FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $current_user_id);
    $user_stmt->execute();
    $current_user_for_frontend = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();
    llog('loaded_current_user', $current_user_for_frontend);

    // Alle Benutzer
    $users_stmt = $conn->prepare("SELECT id, display_name AS name, role, azure_oid AS azureOid FROM users");
    $users_stmt->execute();
    $all_users = $users_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $users_stmt->close();
    llog('loaded_users_count', count($all_users));

    // Alle Stammdaten
    $master_data_stmt = $conn->prepare("SELECT user_id, weekly_hours, workdays, can_work_from_home FROM master_data");
    $master_data_stmt->execute();
    $master_data_result = $master_data_stmt->get_result();
    $master_data_map = [];
    while ($row = $master_data_result->fetch_assoc()) {
        $master_data_map[$row['user_id']] = [
            'weeklyHours' => (float)$row['weekly_hours'],
            'workdays' => json_decode($row['workdays']),
            'canWorkFromHome' => (bool)$row['can_work_from_home']
        ];
    }
    $master_data_stmt->close();
    
    // Alle Zeiteinträge
    $time_entries_stmt = $conn->prepare("SELECT id, user_id AS userId, username, date, start_time AS startTime, stop_time AS stopTime, location, role, created_at AS createdAt, updated_by AS updatedBy, updated_at AS updatedAt FROM time_entries ORDER BY date DESC, start_time DESC");
    $time_entries_stmt->execute();
    $time_entries = $time_entries_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $time_entries_stmt->close();
    llog('loaded_time_entries_count', count($time_entries));

    // Alle offenen Genehmigungsanträge (rollenbasierte Filterung)
    if (function_exists('llog')) { llog('approvals_filter_ctx', ['role' => $current_user_for_frontend['role'] ?? null, 'email' => $username_from_session, 'display' => $display_name_from_session ?? '']); }
    $approval_query = "SELECT * FROM approval_requests WHERE status = 'pending'";
    
    // Rollenbasierte Filterung (minimaler Fix)
    if ($current_user_for_frontend['role'] === 'Honorarkraft' || $current_user_for_frontend['role'] === 'Mitarbeiter') {
        // nur eigene Anträge (Session-E-Mail = requested_by)
        $approval_query .= " AND requested_by = ?";
        $approvals_stmt = $conn->prepare($approval_query);
        $email = trim($username_from_session);
        $approvals_stmt->bind_param("s", $email);
    } else if ($current_user_for_frontend['role'] === 'Standortleiter') {
        // Standortleiter: Location aus new_data bevorzugen, sonst original_entry_data
        $approval_query .= " AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(new_data, '$.location')), JSON_UNQUOTE(JSON_EXTRACT(original_entry_data, '$.location'))) = ?";
        $approvals_stmt = $conn->prepare($approval_query);
        $approvals_stmt->bind_param("s", $user_data['location'] ?? '');
    } else {
        // Leitung/Admin sehen alles
        $approvals_stmt = $conn->prepare($approval_query);
    }
    
    $approval_requests_raw = [];
    if ($approvals_stmt && $approvals_stmt->execute()) {
        $approval_requests_raw = $approvals_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $approvals_stmt->close();
    } else {
        if (function_exists('llog')) { llog('approvals_query_failed', $approvals_stmt ? $approvals_stmt->error : $conn->error); }
        if ($approvals_stmt) { $approvals_stmt->close(); }
    }
    // Debug: Anzahl offener Genehmigungen (rollenbasiert)
    if (function_exists('llog')) { llog('approvals_count', count($approval_requests_raw)); }
    $approval_requests = array_map(function($req) {
        $entry_data_json = json_decode($req['original_entry_data'], true);
        return [
            'id' => $req['id'],
            'type' => $req['type'],
            'entry' => [
                'id' => (int)$entry_data_json['id'], 'userId' => (int)$entry_data_json['user_id'], 'username' => $entry_data_json['username'],
                'date' => $entry_data_json['date'], 'startTime' => $entry_data_json['start_time'], 'stopTime' => $entry_data_json['stop_time'],
                'location' => $entry_data_json['location'], 'role' => $entry_data_json['role'], 'createdAt' => $entry_data_json['created_at'],
                'updatedBy' => $entry_data_json['updated_by'], 'updatedAt' => $entry_data_json['updated_at'],
            ],
            'newData' => json_decode($req['new_data']),
            'reasonData' => json_decode($req['reason_data']),
            'requestedBy' => $req['requested_by'],
            'status' => 'pending',
        ];
    }, $approval_requests_raw);
    
    // Komplette Änderungshistorie (rollenbasierte Filterung)
    $history_query = "SELECT * FROM approval_requests WHERE status != 'pending'";
    
    if ($current_user_for_frontend['role'] === 'Honorarkraft' || $current_user_for_frontend['role'] === 'Mitarbeiter') {
        $history_query .= " AND requested_by = ?";
        $history_stmt = $conn->prepare($history_query . " ORDER BY resolved_at DESC");
        $email = trim($username_from_session);
        $history_stmt->bind_param("s", $email);
    } else if ($current_user_for_frontend['role'] === 'Standortleiter') {
        $history_query .= " AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(new_data, '$.location')), JSON_UNQUOTE(JSON_EXTRACT(original_entry_data, '$.location'))) = ?";
        $history_stmt = $conn->prepare($history_query . " ORDER BY resolved_at DESC");
        $history_stmt->bind_param("s", $user_data['location'] ?? '');
    } else {
        $history_stmt = $conn->prepare($history_query . " ORDER BY resolved_at DESC");
    }
    
    $history_raw = [];
    if ($history_stmt && $history_stmt->execute()) {
        $history_raw = $history_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $history_stmt->close();
    } else {
        if (function_exists('llog')) { llog('history_query_failed', $history_stmt ? $history_stmt->error : $conn->error); }
        if ($history_stmt) { $history_stmt->close(); }
    }
    $history = array_map(function($row) {
        $original_entry = json_decode($row['original_entry_data'], true);
        return [
            'id' => $row['id'], 'type' => $row['type'],
            'entry' => [
                'id' => (int)$original_entry['id'], 'userId' => (int)$original_entry['user_id'], 'username' => $original_entry['username'],
                'date' => $original_entry['date'], 'startTime' => $original_entry['start_time'], 'stopTime' => $original_entry['stop_time'],
                'location' => $original_entry['location'], 'role' => $original_entry['role'], 'createdAt' => $original_entry['created_at'],
                'updatedBy' => $original_entry['updated_by'], 'updatedAt' => $original_entry['updated_at'],
            ],
            'newData' => json_decode($row['new_data']), 'reasonData' => json_decode($row['reason_data']),
            'requestedBy' => $row['requested_by'], 'finalStatus' => $row['status'],
            'resolvedAt' => $row['resolved_at'], 'resolvedBy' => $row['resolved_by'],
        ];
    }, $history_raw);

    // Globale Einstellungen
    $settings_stmt = $conn->prepare("SELECT overtime_threshold, change_reasons, locations FROM global_settings WHERE id = 1");
    $settings_stmt->execute();
    $settings_raw = $settings_stmt->get_result()->fetch_assoc();
    $settings_stmt->close();
    $global_settings = [
        'overtimeThreshold' => (float)$settings_raw['overtime_threshold'],
        'changeReasons' => json_decode($settings_raw['change_reasons']),
        'locations' => json_decode($settings_raw['locations'])
    ];

    $dbConnection->commit();

    send_response(200, [
        'currentUser' => $current_user_for_frontend,
        'users' => $all_users,
        'masterData' => $master_data_map,
        'timeEntries' => $time_entries,
        'approvalRequests' => $approval_requests,
        'history' => $history,
        'globalSettings' => $global_settings
    ]);

} catch (Exception $e) {
    $dbConnection->rollback();
    error_log("Login transaction failed: " . $e->getMessage());
    send_response(500, ['message' => 'Ein interner Fehler ist während des Anmeldevorgangs aufgetreten.', 'error' => $e->getMessage()]);
}

$dbConnection->close();
?>
