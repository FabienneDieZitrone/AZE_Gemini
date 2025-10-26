<?php
/**
 * Delete User API
 *
 * Löscht einen Benutzer komplett aus dem System (users, master_data, time_entries, approval_requests)
 *
 * Sicherheit:
 * - Nur Admin darf Benutzer löschen
 * - Admin kann sich selbst NICHT löschen
 * - Cascade delete: Alle verknüpften Daten werden gelöscht
 */

define('API_GUARD', true);
require_once __DIR__ . '/error-handler.php';
require_once __DIR__ . '/security-middleware.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/csrf-middleware.php';
require_once __DIR__ . '/DatabaseConnection.php';

initialize_api();
initSecurityMiddleware();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// CSRF-Prüfung
$csrfOk = function_exists('validateCsrfToken') ? validateCsrfToken() : (function_exists('validateCsrfProtection') ? validateCsrfProtection() : true);
if (!$csrfOk) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF validation failed']);
    exit;
}

try {
    // Session und User prüfen
    $sessionUser = verify_session_and_get_user();
    $currentUserId = $sessionUser['id'] ?? null;
    $userRole = $sessionUser['role'] ?? '';

    if (!$currentUserId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Nicht authentifiziert']);
        exit;
    }

    // SECURITY: Nur Admin darf Benutzer löschen
    if ($userRole !== 'Admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Keine Berechtigung. Nur Admins dürfen Benutzer löschen.']);
        exit;
    }

    // Request-Daten lesen
    $input = json_decode(file_get_contents('php://input'), true);
    $userIdToDelete = isset($input['userId']) ? (int)$input['userId'] : null;

    if (!$userIdToDelete || $userIdToDelete <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ungültige User-ID']);
        exit;
    }

    // SECURITY: Admin kann sich selbst NICHT löschen
    if ($userIdToDelete === $currentUserId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Sie können sich nicht selbst löschen']);
        exit;
    }

    $conn = DatabaseConnection::getInstance()->getConnection();

    // Prüfe ob User existiert
    $stmt = $conn->prepare("SELECT id, username, display_name, role FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $userIdToDelete);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Benutzer nicht gefunden']);
        exit;
    }

    $userToDelete = $result->fetch_assoc();
    $stmt->close();

    // CASCADE DELETE: Lösche alle verknüpften Daten in einer Transaktion
    $conn->begin_transaction();

    try {
        // 1. Lösche master_data
        $stmt = $conn->prepare("DELETE FROM master_data WHERE user_id = ?");
        $stmt->bind_param('i', $userIdToDelete);
        $stmt->execute();
        $masterDataDeleted = $stmt->affected_rows;
        $stmt->close();

        // 2. Lösche time_entries
        $stmt = $conn->prepare("DELETE FROM time_entries WHERE user_id = ?");
        $stmt->bind_param('i', $userIdToDelete);
        $stmt->execute();
        $timeEntriesDeleted = $stmt->affected_rows;
        $stmt->close();

        // 3. Lösche approval_requests (by username)
        $username = $userToDelete['username'];
        $stmt = $conn->prepare("DELETE FROM approval_requests WHERE requested_by = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $approvalsDeleted = $stmt->affected_rows;
        $stmt->close();

        // 4. Lösche User selbst
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param('i', $userIdToDelete);
        $stmt->execute();
        $userDeleted = $stmt->affected_rows;
        $stmt->close();

        if ($userDeleted === 0) {
            throw new Exception('User konnte nicht gelöscht werden');
        }

        // Commit Transaktion
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Benutzer erfolgreich gelöscht',
            'deletedUser' => [
                'id' => $userToDelete['id'],
                'name' => $userToDelete['display_name'],
                'username' => $userToDelete['username'],
                'role' => $userToDelete['role']
            ],
            'deletedData' => [
                'masterData' => $masterDataDeleted,
                'timeEntries' => $timeEntriesDeleted,
                'approvalRequests' => $approvalsDeleted
            ]
        ]);

    } catch (Exception $e) {
        // Rollback bei Fehler
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fehler beim Löschen: ' . $e->getMessage()
    ]);
}
?>
