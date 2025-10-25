<?php
/**
 * SQL FIX - Simple autocommit enabled query
 */

define('API_GUARD', true);
require_once __DIR__ . '/DatabaseConnection.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $conn = DatabaseConnection::getInstance()->getConnection();

    echo "=== SQL FIX - Autocommit ON ===\n\n";

    // ENABLE AUTOCOMMIT
    $conn->autocommit(true);
    echo "✓ Autocommit aktiviert\n\n";

    // VORHER
    echo "VORHER:\n";
    $before = $conn->query("SELECT id, display_name, onboarding_completed FROM users WHERE id <= 10 ORDER BY id");
    while ($u = $before->fetch_assoc()) {
        echo "ID {$u['id']}: {$u['display_name']} | onb: {$u['onboarding_completed']}\n";
    }
    echo "\n";

    // UPDATE
    $sql = "UPDATE users SET onboarding_completed = 1, created_via_onboarding = 0, pending_since = NULL WHERE azure_oid IS NOT NULL AND azure_oid != '' AND azure_oid NOT LIKE 'test-%'";

    if ($conn->query($sql)) {
        echo "✓ UPDATE: {$conn->affected_rows} Zeilen\n\n";
    } else {
        echo "❌ UPDATE ERROR: " . $conn->error . "\n\n";
    }

    // NACHHER
    echo "NACHHER:\n";
    $after = $conn->query("SELECT id, display_name, onboarding_completed FROM users WHERE id <= 10 ORDER BY id");
    while ($u = $after->fetch_assoc()) {
        echo "ID {$u['id']}: {$u['display_name']} | onb: {$u['onboarding_completed']}\n";
    }

    // RESTORE AUTOCOMMIT OFF
    $conn->autocommit(false);

    echo "\n✅ DONE!\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>
