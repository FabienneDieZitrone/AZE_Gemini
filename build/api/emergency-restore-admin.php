<?php
/**
 * EMERGENCY - Restore Admin Role
 */
define('API_GUARD', true);

require_once __DIR__ . '/DatabaseConnection.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $db = DatabaseConnection::getInstance();
    $conn = $db->getConnection();

    echo "=== EMERGENCY ADMIN RESTORE ===\n\n";

    // Show all users and their roles
    echo "CURRENT USER ROLES:\n";
    $result = $conn->query("SELECT id, username, display_name, role, azure_oid FROM users ORDER BY id");
    while ($user = $result->fetch_assoc()) {
        echo sprintf("ID %d: %s (%s) | Role: %s | Azure OID: %s\n",
            $user['id'],
            $user['display_name'],
            $user['username'],
            $user['role'],
            substr($user['azure_oid'] ?? 'none', 0, 20)
        );
    }

    echo "\n=== RESTORING ADMIN ROLES ===\n";

    // Restore Günter Allert (ID 1) to Admin
    $stmt = $conn->prepare("UPDATE users SET role = 'Admin' WHERE id = 1");
    if ($stmt && $stmt->execute()) {
        echo "✓ User ID 1 (Günter Allert) restored to Admin\n";
        echo "  Affected rows: " . $stmt->affected_rows . "\n";
        $stmt->close();
    } else {
        echo "❌ Failed to restore User ID 1\n";
    }

    // Also restore User ID 2 (Fabienne) if exists
    $check = $conn->query("SELECT id, display_name FROM users WHERE id = 2");
    if ($check && $check->num_rows > 0) {
        $user = $check->fetch_assoc();
        $stmt = $conn->prepare("UPDATE users SET role = 'Admin' WHERE id = 2");
        if ($stmt && $stmt->execute()) {
            echo "✓ User ID 2 (" . $user['display_name'] . ") restored to Admin\n";
            echo "  Affected rows: " . $stmt->affected_rows . "\n";
            $stmt->close();
        }
    }

    echo "\n=== VERIFICATION ===\n";
    $result = $conn->query("SELECT id, display_name, role FROM users WHERE id IN (1, 2) ORDER BY id");
    while ($user = $result->fetch_assoc()) {
        echo sprintf("ID %d: %s | Role: %s %s\n",
            $user['id'],
            $user['display_name'],
            $user['role'],
            $user['role'] === 'Admin' ? '✓' : '❌'
        );
    }

    echo "\n✅ ADMIN RESTORE COMPLETED!\n";
    echo "\nPlease logout and login again to refresh your session.\n";

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}
?>
