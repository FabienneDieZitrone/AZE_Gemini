<?php
/**
 * Fix Onboarding Status
 * Setzt onboarding_completed = 1 für alle existierenden User mit Azure OID
 */

define('API_GUARD', true);
require_once __DIR__ . '/DatabaseConnection.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $conn = DatabaseConnection::getInstance()->getConnection();

    echo "=== Onboarding-Status korrigieren ===\n\n";

    // Setze onboarding_completed = 1 für alle User MIT Azure OID (echte User)
    $update = $conn->prepare("
        UPDATE users
        SET onboarding_completed = 1
        WHERE (azure_oid IS NOT NULL AND azure_oid != '' AND azure_oid NOT LIKE 'test-%')
        AND onboarding_completed = 0
    ");

    if ($update->execute()) {
        $affected = $conn->affected_rows;
        echo "✓ Onboarding-Status aktualisiert für $affected User\n";

        if ($affected > 0) {
            // Zeige welche User betroffen waren
            $check = $conn->query("
                SELECT id, username, display_name, role
                FROM users
                WHERE azure_oid IS NOT NULL
                AND azure_oid != ''
                AND azure_oid NOT LIKE 'test-%'
                AND onboarding_completed = 1
            ");

            echo "\nBetroffene User:\n";
            while ($user = $check->fetch_assoc()) {
                echo "  - ID {$user['id']}: {$user['display_name']} ({$user['username']}) - Rolle: {$user['role']}\n";
            }
        }
    } else {
        echo "✗ Fehler beim Update: " . $conn->error . "\n";
    }
    $update->close();

    echo "\n✅ Fertig! Sie können sich jetzt neu einloggen.\n";

} catch (Exception $e) {
    http_response_code(500);
    echo "FEHLER: " . $e->getMessage() . "\n";
}
?>
