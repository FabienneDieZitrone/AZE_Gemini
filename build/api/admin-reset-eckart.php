<?php
/**
 * Admin Tool: Reset Eckart Hagen Onboarding Status
 *
 * Setzt pending_since für Eckart Hagen, damit er in der Pending-Liste erscheint
 */

define('API_GUARD', true);
require_once __DIR__ . '/security-middleware.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/DatabaseConnection.php';

initialize_api();
initSecurityMiddleware();

header('Content-Type: application/json; charset=utf-8');

try {
    // Nur Admin darf dieses Script ausführen
    $sessionUser = verify_session_and_get_user();
    $userRole = $sessionUser['role'] ?? '';

    if ($userRole !== 'Admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Nur Admin darf dieses Tool verwenden']);
        exit;
    }

    $conn = DatabaseConnection::getInstance()->getConnection();

    // Finde Eckart Hagen
    $stmt = $conn->prepare("
        SELECT id, username, display_name, home_location, onboarding_completed,
               created_via_onboarding, pending_since
        FROM users
        WHERE display_name LIKE '%Eckart%' OR display_name LIKE '%Hagen%'
        LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'User Eckart Hagen nicht gefunden'
        ]);
        exit;
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    $userId = $user['id'];
    $now = date('Y-m-d H:i:s');

    // Reset Onboarding Status
    $stmt = $conn->prepare("
        UPDATE users
        SET onboarding_completed = 1,
            created_via_onboarding = 1,
            pending_since = ?
        WHERE id = ?
    ");
    $stmt->bind_param('si', $now, $userId);

    if (!$stmt->execute()) {
        throw new Exception('Fehler beim Update: ' . $stmt->error);
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Onboarding-Status für Eckart Hagen erfolgreich zurückgesetzt',
        'user' => [
            'id' => $userId,
            'name' => $user['display_name'],
            'homeLocation' => $user['home_location'],
            'previousStatus' => [
                'onboarding_completed' => $user['onboarding_completed'],
                'created_via_onboarding' => $user['created_via_onboarding'],
                'pending_since' => $user['pending_since']
            ],
            'newStatus' => [
                'onboarding_completed' => 1,
                'created_via_onboarding' => 1,
                'pending_since' => $now
            ]
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
