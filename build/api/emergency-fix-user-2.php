<?php
/**
 * EMERGENCY FIX für User ID 2
 * Setzt onboarding_completed = 1 SOFORT ohne Onboarding-Prozess
 */

define('API_GUARD', true);
require_once __DIR__ . '/DatabaseConnection.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $conn = DatabaseConnection::getInstance()->getConnection();

    echo "=== EMERGENCY FIX für User ID 2 ===\n\n";

    // Direkt für User ID 2
    $userId = 2;

    // Zeige aktuellen Status
    $check = $conn->prepare("SELECT id, display_name, onboarding_completed, created_via_onboarding, pending_since FROM users WHERE id = ?");
    $check->bind_param('i', $userId);
    $check->execute();
    $check->bind_result($id, $name, $onboardingCompleted, $createdViaOnboarding, $pendingSince);

    if ($check->fetch()) {
        echo "User VORHER:\n";
        echo "  ID: $id\n";
        echo "  Name: $name\n";
        echo "  onboarding_completed: $onboardingCompleted\n";
        echo "  created_via_onboarding: $createdViaOnboarding\n";
        echo "  pending_since: " . ($pendingSince ?: 'NULL') . "\n\n";
    }
    $check->close();

    // Setze alle Flags korrekt
    $update = $conn->prepare("
        UPDATE users
        SET onboarding_completed = 1,
            created_via_onboarding = 0,
            pending_since = NULL
        WHERE id = ?
    ");
    $update->bind_param('i', $userId);

    if ($update->execute()) {
        echo "✓ User aktualisiert!\n\n";

        // Zeige neuen Status
        $check2 = $conn->prepare("SELECT id, display_name, onboarding_completed, created_via_onboarding, pending_since FROM users WHERE id = ?");
        $check2->bind_param('i', $userId);
        $check2->execute();
        $check2->bind_result($id2, $name2, $onboardingCompleted2, $createdViaOnboarding2, $pendingSince2);

        if ($check2->fetch()) {
            echo "User NACHHER:\n";
            echo "  ID: $id2\n";
            echo "  Name: $name2\n";
            echo "  onboarding_completed: $onboardingCompleted2\n";
            echo "  created_via_onboarding: $createdViaOnboarding2\n";
            echo "  pending_since: " . ($pendingSince2 ?: 'NULL') . "\n\n";
        }
        $check2->close();
    }
    $update->close();

    echo "✅ FERTIG!\n\n";
    echo "🚨 JETZT BITTE:\n";
    echo "1. Ausloggen: https://aze.mikropartner.de/api/auth-logout.php\n";
    echo "2. Neu einloggen\n";
    echo "3. Normale Hauptseite sollte erscheinen!\n";

} catch (Exception $e) {
    http_response_code(500);
    echo "FEHLER: " . $e->getMessage() . "\n";
}
?>
