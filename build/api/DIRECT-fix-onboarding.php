<?php
/**
 * DIRECT FIX - Onboarding Status mit direktem mysqli commit
 */

define('API_GUARD', true);

// Direct mysqli connection (bypass DatabaseConnection class)
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: '';
$pass = getenv('DB_PASS') ?: '';
$name = getenv('DB_NAME') ?: '';

header('Content-Type: text/plain; charset=utf-8');

try {
    $conn = new mysqli($host, $user, $pass, $name);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');

    echo "=== DIRECT FIX mit mysqli commit ===\n\n";

    // VORHER
    echo "VORHER:\n";
    $before = $conn->query("SELECT id, display_name, onboarding_completed FROM users ORDER BY id");
    while ($u = $before->fetch_assoc()) {
        echo "ID {$u['id']}: {$u['display_name']} | onb: {$u['onboarding_completed']}\n";
    }
    echo "\n";

    // UPDATE mit echten Azure OIDs
    $sql = "
        UPDATE users
        SET onboarding_completed = 1,
            created_via_onboarding = 0,
            pending_since = NULL
        WHERE azure_oid IS NOT NULL
          AND azure_oid != ''
          AND azure_oid NOT LIKE 'test-%'
    ";

    if ($conn->query($sql)) {
        echo "✓ UPDATE erfolgreich ({$conn->affected_rows} Zeilen)\n";

        // EXPLICIT COMMIT
        if ($conn->commit()) {
            echo "✓ COMMIT erfolgreich\n\n";
        } else {
            echo "❌ COMMIT FAILED: " . $conn->error . "\n\n";
        }
    } else {
        echo "❌ UPDATE FAILED: " . $conn->error . "\n";
    }

    // NACHHER
    echo "NACHHER:\n";
    $after = $conn->query("SELECT id, display_name, onboarding_completed FROM users ORDER BY id");
    while ($u = $after->fetch_assoc()) {
        echo "ID {$u['id']}: {$u['display_name']} | onb: {$u['onboarding_completed']}\n";
    }

    echo "\n✅ FERTIG!\n";

    $conn->close();

} catch (Exception $e) {
    echo "❌ FEHLER: " . $e->getMessage() . "\n";
}
?>
