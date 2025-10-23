<?php
/**
 * Onboarding Complete API
 *
 * Speichert den Heimat-Standort für einen neuen Mitarbeiter
 * und markiert das Onboarding als "Standort ausgewählt"
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

try {
    // Session und User prüfen
    $sessionUser = verify_session_and_get_user();
    $userId = $sessionUser['id'] ?? null;

    if (!$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Nicht authentifiziert']);
        exit;
    }

    // Request-Daten lesen
    $input = json_decode(file_get_contents('php://input'), true);
    $homeLocation = trim($input['homeLocation'] ?? '');

    if (!$homeLocation) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Heimat-Standort fehlt']);
        exit;
    }

    // Datenbank-Update
    $conn = DatabaseConnection::getInstance()->getConnection();

    // KRITISCH: Setze onboarding_completed = 1 damit User die App nutzen kann!
    // Speichere Heimat-Standort und setze pending_since für Standortleiter-Benachrichtigung
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("
        UPDATE users
        SET home_location = ?,
            pending_since = ?,
            created_via_onboarding = 1,
            onboarding_completed = 1
        WHERE id = ?
    ");
    $stmt->bind_param('ssi', $homeLocation, $now, $userId);

    if (!$stmt->execute()) {
        throw new Exception('Fehler beim Speichern des Standorts');
    }
    $stmt->close();

    // Erstelle Minimal-Masterdata (damit User sofort tracken kann)
    $stmt = $conn->prepare("
        INSERT INTO master_data (user_id, weekly_hours, workdays, can_work_from_home)
        VALUES (?, 40, ?, 1)
        ON DUPLICATE KEY UPDATE user_id = user_id
    ");
    $defaultWorkdays = json_encode(['Mo', 'Di', 'Mi', 'Do', 'Fr']);
    $stmt->bind_param('is', $userId, $defaultWorkdays);
    $stmt->execute();
    $stmt->close();

    // Finde Standortleiter des gewählten Standorts
    $standortleiterNotified = false;
    $stmt = $conn->prepare("
        SELECT id, display_name FROM users
        WHERE role = 'Standortleiter'
        AND home_location = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $homeLocation);
    $stmt->execute();
    $stmt->bind_result($standortleiterId, $standortleiterName);

    if ($stmt->fetch()) {
        $standortleiterNotified = true;
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Onboarding abgeschlossen',
        'homeLocation' => $homeLocation,
        'standortleiterNotified' => $standortleiterNotified,
        'nextSteps' => [
            'Sie können jetzt Ihre Arbeitszeit erfassen',
            'Ihr Standortleiter wird bei der nächsten Anmeldung benachrichtigt',
            'Die Stammdaten werden dann vervollständigt'
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
