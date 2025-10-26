<?php
/**
 * Pending Onboarding Users API
 *
 * Gibt alle neuen Mitarbeiter zurück, die Onboarding abgeschlossen haben
 * aber noch auf Stammdaten-Eingabe warten
 *
 * Nur für Standortleiter des jeweiligen Heimat-Standorts
 */

define('API_GUARD', true);
require_once __DIR__ . '/error-handler.php';
require_once __DIR__ . '/security-middleware.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/DatabaseConnection.php';

initialize_api();
initSecurityMiddleware();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Session und User prüfen
    $sessionUser = verify_session_and_get_user();
    $userId = $sessionUser['id'] ?? null;
    $userRole = $sessionUser['role'] ?? '';

    if (!$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Nicht authentifiziert']);
        exit;
    }

    // Nur Standortleiter, Bereichsleiter und Admins dürfen das sehen
    if (!in_array($userRole, ['Standortleiter', 'Bereichsleiter', 'Admin'], true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
        exit;
    }

    $conn = DatabaseConnection::getInstance()->getConnection();

    // Security Fix 2025-10-26: Use assigned locations from master_data
    // Bereichsleiter und Standortleiter sehen nur Users deren home_location in ihren assigned locations liegt
    $assignedLocations = [];
    if ($userRole === 'Bereichsleiter' || $userRole === 'Standortleiter') {
        $assignedLocations = get_user_assigned_locations($conn, $userId);
    }

    // Hole alle pending Onboarding-Users
    // FIX 2025-10-26: onboarding_completed = 1 (nicht 0!), da onboarding-complete.php dies auf 1 setzt
    $query = "
        SELECT u.id, u.display_name, u.home_location, u.pending_since, u.created_via_onboarding
        FROM users u
        WHERE u.onboarding_completed = 1
        AND u.created_via_onboarding = 1
        AND u.pending_since IS NOT NULL
    ";

    // Special Case 2025-10-26: IT Abteilung → nur Admin darf diese Users sehen
    // Bereichsleiter und Standortleiter sehen nur Users deren home_location in ihren assigned locations liegt
    if ($userRole === 'Bereichsleiter' || $userRole === 'Standortleiter') {
        // IT Abteilung darf nicht von Bereichsleiter/Standortleiter gesehen werden
        $query .= " AND u.home_location != 'IT Abteilung'";

        if (empty($assignedLocations)) {
            // Bereichsleiter/Standortleiter ohne zugeordnete Standorte sieht keine pending Users
            $query .= " AND 1=0"; // Never matches
            $stmt = $conn->prepare($query);
        } else {
            // Filter by home_location matching assigned locations
            $placeholders = implode(',', array_fill(0, count($assignedLocations), '?'));
            $query .= " AND u.home_location IN ($placeholders)";
            $stmt = $conn->prepare($query);
            $types = str_repeat('s', count($assignedLocations));
            $stmt->bind_param($types, ...$assignedLocations);
        }
    } else {
        // Admins sehen alle (inklusive IT Abteilung)
        $stmt = $conn->prepare($query);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $pendingUsers = [];
    while ($row = $result->fetch_assoc()) {
        // Berechne wie lange pending
        $pendingSince = new DateTime($row['pending_since']);
        $now = new DateTime();
        $interval = $now->diff($pendingSince);

        $daysPending = $interval->days;
        $hoursPending = $interval->h + ($interval->days * 24);

        $pendingDuration = '';
        if ($daysPending > 0) {
            $pendingDuration = $daysPending . ' Tag' . ($daysPending > 1 ? 'e' : '');
        } else {
            $pendingDuration = $hoursPending . ' Stunde' . ($hoursPending > 1 ? 'n' : '');
        }

        $pendingUsers[] = [
            'id' => (int)$row['id'],
            'name' => $row['display_name'],
            'homeLocation' => $row['home_location'],
            'pendingSince' => $row['pending_since'],
            'pendingDuration' => $pendingDuration,
            'daysPending' => $daysPending
        ];
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'pendingUsers' => $pendingUsers,
        'count' => count($pendingUsers),
        'userRole' => $userRole,
        'assignedLocations' => $assignedLocations
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
