<?php
/**
 * Fix All Users Onboarding - WITH EXPLICIT COMMIT
 */

define('API_GUARD', true);
require_once __DIR__ . '/DatabaseConnection.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $db = DatabaseConnection::getInstance();
    $conn = $db->getConnection();

    echo "=== FIX mit explizitem COMMIT ===\n\n";

    // VORHER
    echo "VORHER:\n";
    $before = $conn->query("SELECT id, display_name, azure_oid, onboarding_completed FROM users ORDER BY id");
    while ($user = $before->fetch_assoc()) {
        $oid = $user['azure_oid'];
        $isReal = ($oid !== null && $oid !== '' && !str_starts_with($oid, 'test-'));
        $marker = $isReal ? '🟢' : '🔵';
        echo "$marker ID {$user['id']}: {$user['display_name']} | onb_compl: {$user['onboarding_completed']}\n";
    }
    echo "\n";

    // BEGIN TRANSACTION
    $db->beginTransaction();
    echo "✓ Transaction started\n";

    // UPDATE
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
        echo "✓ $affected User aktualisiert\n";
    } else {
        throw new Exception("Update failed: " . $conn->error);
    }
    $update->close();

    // COMMIT
    $db->commit();
    echo "✓ Transaction committed\n\n";

    // NACHHER
    echo "NACHHER:\n";
    $after = $conn->query("SELECT id, display_name, azure_oid, onboarding_completed FROM users ORDER BY id");
    while ($user = $after->fetch_assoc()) {
        $oid = $user['azure_oid'];
        $isReal = ($oid !== null && $oid !== '' && !str_starts_with($oid, 'test-'));
        $marker = $isReal ? '🟢' : '🔵';
        echo "$marker ID {$user['id']}: {$user['display_name']} | onb_compl: {$user['onboarding_completed']}\n";
    }

    echo "\n✅ FERTIG mit COMMIT!\n";

} catch (Exception $e) {
    if (isset($db)) {
        $db->rollback();
    }
    http_response_code(500);
    echo "❌ FEHLER: " . $e->getMessage() . "\n";
}
?>
