<?php
/**
 * Approvals API
 * - Nimmt Änderungsanträge entgegen: edit | delete | create (falls Schema dies erlaubt)
 * - Speichert Anträge in approval_requests (status=pending)
 * - Bringt Insert/Update in Einklang mit schema.sql (requested_at, UUID-IDs, entry_id)
 */
define('API_GUARD', true);

require_once __DIR__ . '/security-headers.php';
require_once __DIR__ . '/error-handler.php';
require_once __DIR__ . '/security-middleware.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/csrf-middleware.php';
require_once __DIR__ . '/DatabaseConnection.php';
require_once __DIR__ . '/InputValidationService.php';

// Security / CORS / Methoden
initializeSecurity(false);
// Erlaube GET (lesen), POST (anlegen), PATCH (verarbeiten)
validateRequestMethod(['GET','POST','PATCH']);
initSecurityMiddleware();

// CSRF prüfen NUR für state-changing Methoden (POST/PATCH), nicht für GET
$__method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($__method, ['POST','PATCH'], true)) {
    if (!validateCsrfProtection()) {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $refHost = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
        $sameOrigin = ($refHost === $host) || empty($refHost);
        if (!$sameOrigin) {
            exit; // Fehler wurde bereits gesendet
        }
    }
}

initialize_api();

$db = DatabaseConnection::getInstance();
$conn = $db->getConnection();

// UUID v4 Generator (rein PHP, ausreichend für IDs in approval_requests)
function generate_uuid_v4(): string {
    $data = random_bytes(16);
    // Setze Version (0100) und Variant (10)
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// Prüfer für Schema-Fähigkeiten (ob CREATE erlaubt ist und ob entry_id NULL erlaubt)
function approvals_supports_create(mysqli $conn): bool {
    if ($res = $conn->query("SHOW COLUMNS FROM approval_requests LIKE 'type'")) {
        if ($row = $res->fetch_assoc()) {
            $type = strtolower($row['Type'] ?? '');
            $res->close();
            return strpos($type, "'create'") !== false; // enum enthält 'create'
        }
        $res->close();
    }
    return false;
}

function approvals_entry_id_nullable(mysqli $conn): bool {
    if ($res = $conn->query("SHOW COLUMNS FROM approval_requests LIKE 'entry_id'")) {
        if ($row = $res->fetch_assoc()) {
            $nullable = strtolower($row['Null'] ?? '') === 'yes';
            $res->close();
            return $nullable;
        }
        $res->close();
    }
    return false;
}

function time_entries_has_status(mysqli $conn): bool {
    if ($res = $conn->query("SHOW COLUMNS FROM time_entries LIKE 'status'")) {
        $has = $res->num_rows > 0;
        $res->close();
        return $has;
    }
    return false;
}

function approval_requests_id_is_varchar(mysqli $conn): bool {
    if ($res = $conn->query("SHOW COLUMNS FROM approval_requests LIKE 'id'")) {
        if ($row = $res->fetch_assoc()) {
            $type = strtolower($row['Type'] ?? '');
            $res->close();
            return strpos($type, 'varchar') !== false;
        }
        $res->close();
    }
    return true; // Default zu varchar, wie in schema.sql
}

function approval_requests_has_requested_at(mysqli $conn): bool {
    if ($res = $conn->query("SHOW COLUMNS FROM approval_requests LIKE 'requested_at'")) {
        $has = $res->num_rows > 0;
        $res->close();
        return $has;
    }
    return false;
}

try {
    // Session / Nutzer ermitteln
    $sessionUser = verify_session_and_get_user();
    // requested_by IMMER auf Session-E-Mail setzen (passt zur Filterung in login.php)
    $requestedBy = $sessionUser['username'] ?? ($sessionUser['name'] ?? 'unknown');
    // Optionales Normalisieren (Trim/Lowercase Email)
    if (!empty($requestedBy)) { $requestedBy = trim($requestedBy); }
    $userRole = $sessionUser['role'] ?? 'Mitarbeiter';

    $method = $_SERVER['REQUEST_METHOD'] ?? 'POST';
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];

    if ($method === 'GET') {
        // Pending-Anträge lesen (rollenbasiert wie in login.php)
        $approval_query = "SELECT id, type, original_entry_data, new_data, reason_data, requested_by, status FROM approval_requests WHERE status = 'pending'";
        $role = $userRole;
        if (in_array($role, ['Honorarkraft','Mitarbeiter'], true)) {
            $q = $approval_query . " AND (requested_by = ? OR requested_by = ?) ORDER BY requested_at DESC";
            $stmt = $conn->prepare($q);
            $displayName = $sessionUser['name'] ?? '';
            $email = $requestedBy;
            $stmt->bind_param('ss', $email, $displayName);
        } else if ($role === 'Standortleiter') {
            // Standortleiter: Filter per Location
            $q = $approval_query . " AND JSON_EXTRACT(original_entry_data, '$.location') = ? ORDER BY requested_at DESC";
            $stmt = $conn->prepare($q);
            $loc = $sessionUser['location'] ?? '';
            $stmt->bind_param('s', $loc);
        } else {
            $stmt = $conn->prepare($approval_query . " ORDER BY requested_at DESC");
        }
        if (!$stmt) { send_response(500, ['message' => 'Database error (prepare)']); }
        if (!$stmt->execute()) { $e = $stmt->error; $stmt->close(); send_response(500, ['message' => 'Database error (execute)', 'error' => $e]); }
        $raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $items = array_map(function($req) {
            $entry_data_json = json_decode($req['original_entry_data'] ?? '[]', true) ?: [];
            return [
                'id' => (string)$req['id'],
                'type' => $req['type'],
                'entry' => [
                    'id' => (int)($entry_data_json['id'] ?? 0),
                    'userId' => (int)($entry_data_json['user_id'] ?? 0),
                    'username' => $entry_data_json['username'] ?? '',
                    'date' => $entry_data_json['date'] ?? '',
                    'startTime' => $entry_data_json['start_time'] ?? '',
                    'stopTime' => $entry_data_json['stop_time'] ?? '',
                    'location' => $entry_data_json['location'] ?? '',
                    'role' => $entry_data_json['role'] ?? 'Mitarbeiter',
                    'createdAt' => $entry_data_json['created_at'] ?? '',
                    'updatedBy' => $entry_data_json['updated_by'] ?? '',
                    'updatedAt' => $entry_data_json['updated_at'] ?? '',
                ],
                'newData' => json_decode($req['new_data'] ?? 'null', true),
                'reasonData' => json_decode($req['reason_data'] ?? 'null', true),
                'requestedBy' => $req['requested_by'],
                'status' => 'pending'
            ];
        }, $raw);
        send_response(200, ['items' => $items, 'count' => count($items)]);
    }

    if ($method === 'POST') {
        // Antrag erfassen
        $type = $data['type'] ?? '';
        $allowedTypes = ['edit','delete'];
        $supportsCreate = approvals_supports_create($conn);
        if ($supportsCreate) { $allowedTypes[] = 'create'; }
        if (!in_array($type, $allowedTypes, true)) {
            send_response(400, ['message' => $supportsCreate ? 'Invalid type' : 'Invalid type (create not supported by schema)']);
        }

        $entryId = isset($data['entryId']) ? (int)$data['entryId'] : null;
        $newData = isset($data['newData']) && is_array($data['newData']) ? $data['newData'] : [];
        $reasonData = isset($data['reasonData']) && is_array($data['reasonData']) ? $data['reasonData'] : [];

        // Originaldaten je nach Typ
        $original = [];
        if (in_array($type, ['edit','delete'], true)) {
            if (!$entryId || $entryId <= 0) {
                send_response(400, ['message' => 'entryId required for edit/delete']);
            }
            $s = $conn->prepare("SELECT id, user_id, username, date, start_time, stop_time, location, role, created_at, updated_by, updated_at FROM time_entries WHERE id = ? LIMIT 1");
            $s->bind_param('i', $entryId);
            $s->execute();
            $res = $s->get_result();
            $row = $res->fetch_assoc();
            $s->close();
            if (!$row) {
                send_response(404, ['message' => 'Original entry not found']);
            }
            $original = $row;
        }

        // Insert in approval_requests (ID dynamisch: UUID oder AUTO_INCREMENT)
        $useVarcharId = approval_requests_id_is_varchar($conn);
        $id = $useVarcharId ? generate_uuid_v4() : null;
        $hasRequestedAt = approval_requests_has_requested_at($conn);
        $origJson = json_encode($original, JSON_UNESCAPED_UNICODE);
        $newJson = json_encode($newData, JSON_UNESCAPED_UNICODE);
        $reasonJson = json_encode($reasonData, JSON_UNESCAPED_UNICODE);

        if ($type === 'create') {
            // Erlaubt nur, wenn Schema es zulässt und entry_id NULL ist
            if (!approvals_entry_id_nullable($conn)) {
                send_response(422, ['message' => 'Schema erlaubt keinen create-Antrag (entry_id ist NOT NULL). Bitte Schema aktualisieren.']);
            }
            if ($useVarcharId) {
                if ($hasRequestedAt) {
                    $sql = "INSERT INTO approval_requests (id, type, entry_id, original_entry_data, new_data, reason_data, requested_by, requested_at, status) VALUES (?, 'create', NULL, ?, ?, ?, ?, NOW(), 'pending')";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) { send_response(500, ['message' => 'Database error (prepare)']); }
                    $stmt->bind_param('sssss', $id, $origJson, $newJson, $reasonJson, $requestedBy);
                } else {
                    $sql = "INSERT INTO approval_requests (id, type, entry_id, original_entry_data, new_data, reason_data, requested_by, created_at, status) VALUES (?, 'create', NULL, ?, ?, ?, ?, NOW(), 'pending')";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) { send_response(500, ['message' => 'Database error (prepare)']); }
                    $stmt->bind_param('sssss', $id, $origJson, $newJson, $reasonJson, $requestedBy);
                }
            } else {
                if ($hasRequestedAt) {
                    $sql = "INSERT INTO approval_requests (type, entry_id, original_entry_data, new_data, reason_data, requested_by, requested_at, status) VALUES ('create', NULL, ?, ?, ?, ?, NOW(), 'pending')";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) { send_response(500, ['message' => 'Database error (prepare)']); }
                    $stmt->bind_param('ssss', $origJson, $newJson, $reasonJson, $requestedBy);
                } else {
                    $sql = "INSERT INTO approval_requests (type, entry_id, original_entry_data, new_data, reason_data, requested_by, created_at, status) VALUES ('create', NULL, ?, ?, ?, ?, NOW(), 'pending')";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) { send_response(500, ['message' => 'Database error (prepare)']); }
                    $stmt->bind_param('ssss', $origJson, $newJson, $reasonJson, $requestedBy);
                }
            }
        } else {
            if ($useVarcharId) {
                if ($hasRequestedAt) {
                    $sql = "INSERT INTO approval_requests (id, type, entry_id, original_entry_data, new_data, reason_data, requested_by, requested_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) { send_response(500, ['message' => 'Database error (prepare)']); }
                    $stmt->bind_param('ssissss', $id, $type, $entryId, $origJson, $newJson, $reasonJson, $requestedBy);
                } else {
                    $sql = "INSERT INTO approval_requests (id, type, entry_id, original_entry_data, new_data, reason_data, requested_by, created_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) { send_response(500, ['message' => 'Database error (prepare)']); }
                    $stmt->bind_param('ssissss', $id, $type, $entryId, $origJson, $newJson, $reasonJson, $requestedBy);
                }
            } else {
                if ($hasRequestedAt) {
                    $sql = "INSERT INTO approval_requests (type, entry_id, original_entry_data, new_data, reason_data, requested_by, requested_at, status) VALUES (?, ?, ?, ?, ?, ?, NOW(), 'pending')";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) { send_response(500, ['message' => 'Database error (prepare)']); }
                    $stmt->bind_param('sissss', $type, $entryId, $origJson, $newJson, $reasonJson, $requestedBy);
                } else {
                    $sql = "INSERT INTO approval_requests (type, entry_id, original_entry_data, new_data, reason_data, requested_by, created_at, status) VALUES (?, ?, ?, ?, ?, ?, NOW(), 'pending')";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) { send_response(500, ['message' => 'Database error (prepare)']); }
                    $stmt->bind_param('sissss', $type, $entryId, $origJson, $newJson, $reasonJson, $requestedBy);
                }
            }
        }

        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            send_response(500, ['message' => 'Database error (execute)', 'error' => $err]);
        }
        $stmt->close();
        // requestId: entweder UUID oder AUTO_INCREMENT letzte ID
        $requestId = $useVarcharId ? $id : (string)$conn->insert_id;
        send_response(200, ['requestId' => $requestId, 'status' => 'pending']);
    }

    if ($method === 'PATCH') {
        // Antrag verarbeiten
        // Nur Admin / Leitung darf verarbeiten
        if (!in_array($userRole, ['Admin','Bereichsleiter','Standortleiter'], true)) {
            send_response(403, ['message' => 'Keine Berechtigung zur Genehmigung/Ablehnung']);
        }

        $requestId = $data['requestId'] ?? '';
        $finalStatus = $data['finalStatus'] ?? '';
        if (!$requestId || !in_array($finalStatus, ['genehmigt','abgelehnt'], true)) {
            send_response(400, ['message' => 'Ungültige Parameter']);
        }

        // Antrag laden
        $stmt = $conn->prepare("SELECT id, type, original_entry_data, new_data FROM approval_requests WHERE id = ? AND status = 'pending' LIMIT 1");
        $stmt->bind_param('s', $requestId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        if (!$row) {
            send_response(404, ['message' => 'Antrag nicht gefunden oder nicht mehr pending']);
        }

        $type = $row['type'];
        $orig = json_decode($row['original_entry_data'] ?? '[]', true) ?: [];
        $newd = json_decode($row['new_data'] ?? '[]', true) ?: [];

        $conn->begin_transaction();
        try {
            if ($finalStatus === 'genehmigt') {
                if ($type === 'create') {
                    // Neuen Eintrag anlegen (status-Spalte optional behandeln)
                    $userId = (int)($newd['userId'] ?? 0);
                    $username = $newd['username'] ?? '';
                    $date = $newd['date'] ?? date('Y-m-d');
                    $start = $newd['startTime'] ?? '00:00:00';
                    $stop  = $newd['stopTime'] ?? '00:00:00';
                    $location = $newd['location'] ?? '';
                    $role = $newd['role'] ?? 'Mitarbeiter';
                    $updBy = $sessionUser['username'] ?? ($sessionUser['name'] ?? 'system');
                    if (time_entries_has_status($conn)) {
                        $sqlIns = "INSERT INTO time_entries (user_id, username, date, start_time, stop_time, location, role, created_at, updated_by, updated_at, status) VALUES (?,?,?,?,?,?,?,NOW(),?,NOW(),'completed')";
                        $ins = $conn->prepare($sqlIns);
                        $ins->bind_param('isssssss', $userId, $username, $date, $start, $stop, $location, $role, $updBy);
                    } else {
                        $sqlIns = "INSERT INTO time_entries (user_id, username, date, start_time, stop_time, location, role, created_at, updated_by, updated_at) VALUES (?,?,?,?,?,?,?,NOW(),?,NOW())";
                        $ins = $conn->prepare($sqlIns);
                        $ins->bind_param('isssssss', $userId, $username, $date, $start, $stop, $location, $role, $updBy);
                    }
                    if (!$ins->execute()) { throw new Exception('Insert time_entries failed'); }
                    $ins->close();
                } elseif ($type === 'edit') {
                    // Zeiten ändern
                    $id = (int)($orig['id'] ?? 0);
                    $start = $newd['startTime'] ?? $orig['start_time'];
                    $stop  = $newd['stopTime']  ?? $orig['stop_time'];
                    $updBy = $sessionUser['username'] ?? ($sessionUser['name'] ?? 'system');
                    $sqlUp = "UPDATE time_entries SET start_time = ?, stop_time = ?, updated_by = ?, updated_at = NOW() WHERE id = ?";
                    $up = $conn->prepare($sqlUp);
                    $up->bind_param('sssi', $start, $stop, $updBy, $id);
                    if (!$up->execute()) { throw new Exception('Update time_entries failed'); }
                    $up->close();
                } elseif ($type === 'delete') {
                    // Eintrag löschen
                    $id = (int)($orig['id'] ?? 0);
                    $del = $conn->prepare("DELETE FROM time_entries WHERE id = ? LIMIT 1");
                    $del->bind_param('i', $id);
                    if (!$del->execute()) { throw new Exception('Delete time_entries failed'); }
                    $del->close();
                }
            }
            // Antrag finalisieren
            $resBy = $sessionUser['username'] ?? ($sessionUser['name'] ?? 'system');
            $upd = $conn->prepare("UPDATE approval_requests SET status = ?, resolved_at = NOW(), resolved_by = ? WHERE id = ?");
            $upd->bind_param('sss', $finalStatus, $resBy, $requestId);
            if (!$upd->execute()) { throw new Exception('Update approval_requests failed'); }
            $upd->close();

            $conn->commit();
            send_response(200, ['success' => true]);
        } catch (Throwable $e) {
            $conn->rollback();
            send_response(500, ['message' => 'Verarbeitung fehlgeschlagen', 'error' => $e->getMessage()]);
        }
    }

    send_response(405, ['message' => 'Method not allowed']);
} catch (Throwable $e) {
    send_response(500, ['message' => 'Internal error', 'error' => $e->getMessage()]);
}

?>
