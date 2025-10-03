<?php
/**
 * Approvals API
 * - Nimmt Änderungsanträge entgegen: edit | delete | create
 * - Speichert Anträge in approval_requests (status=pending)
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
validateRequestMethod(['POST']);
initSecurityMiddleware();

// CSRF prüfen (Same-Origin-Fallback wie in login.php)
if (!validateCsrfProtection()) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $refHost = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
    $sameOrigin = ($refHost === $host) || empty($refHost);
    if (!$sameOrigin) {
        exit; // Fehler wurde bereits gesendet
    }
}

initialize_api();

$db = DatabaseConnection::getInstance();
$conn = $db->getConnection();

try {
    // Session / Nutzer ermitteln
    $user = verify_session_and_get_user();
    $requestedBy = $user['username'] ?? ($user['name'] ?? 'unknown');

    // Payload lesen
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];

    $type = $data['type'] ?? '';
    if (!in_array($type, ['edit','delete','create'], true)) {
        send_response(400, ['message' => 'Invalid type']);
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
    } else {
        // create: optional Basisdaten (leer oder aus newData ableiten)
        $original = [];
    }

    // Insert in approval_requests
    $sql = "INSERT INTO approval_requests (type, original_entry_data, new_data, reason_data, requested_by, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { send_response(500, ['message' => 'Database error (prepare)']); }
    $origJson = json_encode($original, JSON_UNESCAPED_UNICODE);
    $newJson = json_encode($newData, JSON_UNESCAPED_UNICODE);
    $reasonJson = json_encode($reasonData, JSON_UNESCAPED_UNICODE);
    $stmt->bind_param('sssss', $type, $origJson, $newJson, $reasonJson, $requestedBy);
    if (!$stmt->execute()) {
        $stmt->close();
        send_response(500, ['message' => 'Database error (execute)']);
    }
    $requestId = $conn->insert_id;
    $stmt->close();

    send_response(200, ['requestId' => (string)$requestId, 'status' => 'pending']);
} catch (Throwable $e) {
    send_response(500, ['message' => 'Internal error', 'error' => $e->getMessage()]);
}

?>

