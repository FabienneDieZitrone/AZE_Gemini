<?php
/**
 * Fix ALL Real Users Onboarding Status
 * Setzt onboarding_completed = 1 für ALLE User MIT echter Azure OID
 */

define('API_GUARD', true);
require_once __DIR__ . '/DatabaseConnection.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $conn = DatabaseConnection::getInstance()->getConnection();

    echo "=== FIX: Alle echten User auf onboarding_completed = 1 setzen ===\n\n";

    // Zeige VORHER-Status
    echo "VORHER - Alle User:\n";
    $before = $conn->query("SELECT id, display_name, azure_oid, onboarding_completed FROM users ORDER BY id");
    while ($user = $before->fetch_assoc()) {
        $oid = $user['azure_oid'];
        $isReal = ($oid !== null && $oid !== '' && !str_starts_with($oid, 'test-'));
        $marker = $isReal ? '🟢 ECHT' : '🔵 TEST';

        echo "$marker ID {$user['id']}: {$user['display_name']} | onboarding_completed: {$user['onboarding_completed']}\n";
    }
    echo "\n";

    // Setze onboarding_completed = 1 für alle echten User (mit echter Azure OID)
    echo "Aktualisiere echte User...\n";
    $update = $conn->prepare("
        UPDATE users
        SET onboarding_completed = 1,
            created_via_onboarding = 0,
            pending_since = NULL
        WHERE azure_oid IS NOT NULL
          AND azure_oid != ''
          AND azure_oid NOT LIKE 'test-%'
    ");

    if ($update->execute()) {
        $affected = $conn->affected_rows;
        echo "✓ $affected User aktualisiert!\n\n";
    } else {
        echo "✗ Fehler: " . $conn->error . "\n\n";
    }
    $update->close();

    // Zeige NACHHER-Status
    echo "NACHHER - Alle User:\n";
    $after = $conn->query("SELECT id, display_name, azure_oid, onboarding_completed FROM users ORDER BY id");
    while ($user = $after->fetch_assoc()) {
        $oid = $user['azure_oid'];
        $isReal = ($oid !== null && $oid !== '' && !str_starts_with($oid, 'test-'));
        $marker = $isReal ? '🟢 ECHT' : '🔵 TEST';

        echo "$marker ID {$user['id']}: {$user['display_name']} | onboarding_completed: {$user['onboarding_completed']}\n";
    }

    echo "\n✅ FERTIG!\n";

} catch (Exception $e) {
    http_response_code(500);
    echo "FEHLER: " . $e->getMessage() . "\n";
}
?>
