<?php
/**
 * Fix Onboarding Status (VERBESSERT)
 * Setzt onboarding_completed = 1 UND created_via_onboarding = 0
 * für alle existierenden User mit Azure OID
 */

define('API_GUARD', true);
require_once __DIR__ . '/DatabaseConnection.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $conn = DatabaseConnection::getInstance()->getConnection();

    echo "=== Onboarding-Status korrigieren (VERBESSERT) ===\n\n";

    // Zeige aktuellen Status ALLER User
    echo "Aktueller Status aller User:\n";
    $checkAll = $conn->query("
        SELECT id, username, display_name, role, azure_oid, onboarding_completed, created_via_onboarding
        FROM users
        ORDER BY id
    ");

    while ($user = $checkAll->fetch_assoc()) {
        $oid = $user['azure_oid'] ? substr($user['azure_oid'], 0, 20) . '...' : 'NULL';
        echo "  ID {$user['id']}: {$user['display_name']} | Azure OID: $oid | ";
        echo "onboarding_completed: {$user['onboarding_completed']} | ";
        echo "created_via_onboarding: {$user['created_via_onboarding']}\n";
    }

    echo "\n";

    // Setze onboarding_completed = 1 UND created_via_onboarding = 0
    // für alle User MIT Azure OID (echte User)
    $update = $conn->prepare("
        UPDATE users
        SET onboarding_completed = 1,
            created_via_onboarding = 0,
            pending_since = NULL
        WHERE (azure_oid IS NOT NULL AND azure_oid != '' AND azure_oid NOT LIKE 'test-%')
    ");

    if ($update->execute()) {
        $affected = $conn->affected_rows;
        echo "✓ Onboarding-Status aktualisiert für $affected User\n";

        // Zeige welche User betroffen waren
        $check = $conn->query("
            SELECT id, username, display_name, role, onboarding_completed, created_via_onboarding
            FROM users
            WHERE azure_oid IS NOT NULL
            AND azure_oid != ''
            AND azure_oid NOT LIKE 'test-%'
        ");

        echo "\nBetroffene User (nach Update):\n";
        while ($user = $check->fetch_assoc()) {
            echo "  - ID {$user['id']}: {$user['display_name']} ({$user['username']}) - Rolle: {$user['role']}\n";
            echo "    onboarding_completed: {$user['onboarding_completed']}, created_via_onboarding: {$user['created_via_onboarding']}\n";
        }
    } else {
        echo "✗ Fehler beim Update: " . $conn->error . "\n";
    }
    $update->close();

    echo "\n✅ Fertig!\n";
    echo "\n🚨 WICHTIG: Bitte AUSLOGGEN und NEU EINLOGGEN!\n";
    echo "URL zum Ausloggen: https://aze.mikropartner.de/api/auth-logout.php\n";

} catch (Exception $e) {
    http_response_code(500);
    echo "FEHLER: " . $e->getMessage() . "\n";
}
?>
