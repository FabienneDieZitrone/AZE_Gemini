<?php
/**
 * Titel: Zentraler Login- und Synchronisierungs-Endpunkt
 * Version: 2.0 (BFF-Architektur)
 * Letzte Aktualisierung: 19.07.2025
 * Autor: MP-IT
 * Status: Final
 * Datei: /api/login.php
 * Beschreibung: Dieser Endpunkt wird nun nach der erfolgreichen serverseitigen Authentifizierung aufgerufen.
 *              Er validiert die bestehende Session und synchronisiert dann den Benutzer mit der Datenbank.
 */

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
});

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helpers.php';

initialize_api();

// Nur POST-Anfragen erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(405, ['message' => 'Method Not Allowed']);
    exit();
}

$conn->begin_transaction();

try {
    // --- 1. DSGVO-konforme Datenbereinigung ---
    $six_months_ago = date('Y-m-d H:i:s', strtotime('-6 months'));
    $delete_stmt = $conn->prepare("DELETE FROM users WHERE created_at < ?");
    if (!$delete_stmt) throw new Exception("Prepare failed (delete users): " . $conn->error);
    $delete_stmt->bind_param("s", $six_months_ago);
    $delete_stmt->execute();
    $delete_stmt->close();

    // --- 2. Benutzer-Synchronisierung ---
    // Holt den Benutzer aus der sicheren, serverseitigen Session.
    // Diese Funktion beendet das Skript mit 401, wenn keine gültige Session vorhanden ist.
    $user_from_session = verify_session_and_get_user();
    $azure_oid = $user_from_session['oid'];
    $display_name_from_session = $user_from_session['name'];
    $username_from_session = $user_from_session['username']; // E-Mail

    // Benutzer suchen via Azure OID (primärer, unveränderlicher Schlüssel)
    $stmt = $conn->prepare("SELECT * FROM users WHERE azure_oid = ?");
    if (!$stmt) throw new Exception("Prepare failed (find user): " . $conn->error);
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
        if (!$insert_user_stmt) throw new Exception("Prepare failed (insert user): " . $conn->error);
        $insert_user_stmt->bind_param("sss", $username_from_session, $display_name_from_session, $azure_oid);
        $insert_user_stmt->execute();
        $current_user_id = $conn->insert_id;
        $insert_user_stmt->close();

        // Standard-Stammdaten für neuen Benutzer anlegen
        $default_workdays = json_encode(['Mo', 'Di', 'Mi', 'Do', 'Fr']);
        $insert_master_stmt = $conn->prepare("INSERT INTO master_data (user_id, weekly_hours, workdays, can_work_from_home) VALUES (?, 40.00, ?, 0)");
        if (!$insert_master_stmt) throw new Exception("Prepare failed (insert masterdata): " . $conn->error);
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

    // Alle Benutzer
    $users_stmt = $conn->prepare("SELECT id, display_name AS name, role, azure_oid AS azureOid FROM users");
    $users_stmt->execute();
    $all_users = $users_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $users_stmt->close();

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

    // Alle offenen Genehmigungsanträge
    $approvals_stmt = $conn->prepare("SELECT * FROM approval_requests WHERE status = 'pending'");
    $approvals_stmt->execute();
    $approval_requests_raw = $approvals_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $approvals_stmt->close();
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
    
    // Komplette Änderungshistorie
    $history_stmt = $conn->prepare("SELECT * FROM approval_requests WHERE status != 'pending' ORDER BY resolved_at DESC");
    $history_stmt->execute();
    $history_raw = $history_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $history_stmt->close();
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

    $conn->commit();

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
    $conn->rollback();
    error_log("Login transaction failed: " . $e->getMessage());
    send_response(500, ['message' => 'Ein interner Fehler ist während des Anmeldevorgangs aufgetreten.', 'error' => $e->getMessage()]);
}

$conn->close();
?>