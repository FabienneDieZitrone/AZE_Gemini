<?php
/**
 * Reset Onboarding Status für Eckart Hagen
 *
 * Setzt den Onboarding-Status zurück, sodass Eckart Hagen wieder als
 * "pending for masterdata completion" angezeigt wird.
 */

define('API_GUARD', true);
require_once __DIR__ . '/DatabaseConnection.php';

try {
    $conn = DatabaseConnection::getInstance()->getConnection();

    // 1. Finde Eckart Hagen
    echo "=== Suche Eckart Hagen ===\n";
    $stmt = $conn->prepare("
        SELECT id, username, display_name, home_location, role,
               onboarding_completed, created_via_onboarding, pending_since
        FROM users
        WHERE display_name LIKE '%Eckart%' OR display_name LIKE '%Hagen%'
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo "❌ Kein User mit Namen 'Eckart' oder 'Hagen' gefunden!\n";
        exit(1);
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    echo "✅ User gefunden:\n";
    echo "   ID: {$user['id']}\n";
    echo "   Name: {$user['display_name']}\n";
    echo "   Home Location: {$user['home_location']}\n";
    echo "   Role: {$user['role']}\n";
    echo "   Onboarding Completed: {$user['onboarding_completed']}\n";
    echo "   Created via Onboarding: {$user['created_via_onboarding']}\n";
    echo "   Pending Since: {$user['pending_since']}\n";
    echo "\n";

    // 2. Reset Onboarding Status
    echo "=== Setze Onboarding-Status zurück ===\n";

    $now = date('Y-m-d H:i:s');
    $userId = $user['id'];

    $stmt = $conn->prepare("
        UPDATE users
        SET onboarding_completed = 1,
            created_via_onboarding = 1,
            pending_since = ?
        WHERE id = ?
    ");
    $stmt->bind_param('si', $now, $userId);

    if ($stmt->execute()) {
        echo "✅ Onboarding-Status erfolgreich zurückgesetzt!\n";
        echo "   - onboarding_completed = 1\n";
        echo "   - created_via_onboarding = 1\n";
        echo "   - pending_since = $now\n";
        echo "\n";
        echo "✅ Eckart Hagen wird jetzt als 'Pending Onboarding User' angezeigt.\n";
    } else {
        echo "❌ Fehler beim Update: " . $stmt->error . "\n";
        exit(1);
    }
    $stmt->close();

    // 3. Prüfe ob Masterdata existiert
    echo "\n=== Prüfe Masterdata ===\n";
    $stmt = $conn->prepare("SELECT user_id, weekly_hours, workdays, locations FROM master_data WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $md = $result->fetch_assoc();
        echo "✅ Masterdata existiert:\n";
        echo "   Weekly Hours: {$md['weekly_hours']}\n";
        echo "   Workdays: {$md['workdays']}\n";
        echo "   Locations: {$md['locations']}\n";
    } else {
        echo "⚠️  Keine Masterdata gefunden - sollte existieren!\n";
    }
    $stmt->close();

    echo "\n=== FERTIG ===\n";

} catch (Exception $e) {
    echo "❌ Fehler: " . $e->getMessage() . "\n";
    exit(1);
}
?>
