<?php
/**
 * Find Duplicate Users
 * Identifiziert doppelte Benutzer-Einträge in der Datenbank
 */

define('API_GUARD', true);
require_once __DIR__ . '/DatabaseConnection.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $conn = DatabaseConnection::getInstance()->getConnection();

    echo "=== Suche nach doppelten Benutzern ===\n\n";

    // Suche nach doppelten display_name
    $duplicateNames = $conn->query("
        SELECT display_name, COUNT(*) as count
        FROM users
        GROUP BY display_name
        HAVING COUNT(*) > 1
        ORDER BY count DESC
    ");

    if ($duplicateNames && $duplicateNames->num_rows > 0) {
        echo "Doppelte Namen gefunden:\n";
        while ($row = $duplicateNames->fetch_assoc()) {
            echo "  - '{$row['display_name']}': {$row['count']}x vorhanden\n";

            // Details zu jedem Eintrag
            $details = $conn->prepare("
                SELECT id, username, display_name, role, azure_oid, created_at, onboarding_completed
                FROM users
                WHERE display_name = ?
                ORDER BY id
            ");
            $details->bind_param('s', $row['display_name']);
            $details->execute();
            $result = $details->get_result();

            while ($user = $result->fetch_assoc()) {
                echo "    ID: {$user['id']}, Username: {$user['username']}, Role: {$user['role']}, ";
                echo "Azure OID: " . ($user['azure_oid'] ? substr($user['azure_oid'], 0, 20) . '...' : 'NULL') . ", ";
                echo "Created: {$user['created_at']}, Onboarding: {$user['onboarding_completed']}\n";

                // Prüfe ob MasterData existiert
                $md = $conn->prepare("SELECT COUNT(*) FROM master_data WHERE user_id = ?");
                $md->bind_param('i', $user['id']);
                $md->execute();
                $md->bind_result($mdCount);
                $md->fetch();
                $md->close();

                echo "      MasterData: " . ($mdCount > 0 ? "✓ Vorhanden" : "✗ Fehlt") . "\n";

                // Prüfe ob TimeEntries existieren
                $te = $conn->prepare("SELECT COUNT(*) FROM time_entries WHERE user_id = ?");
                $te->bind_param('i', $user['id']);
                $te->execute();
                $te->bind_result($teCount);
                $te->fetch();
                $te->close();

                echo "      TimeEntries: $teCount Einträge\n";
            }
            $details->close();
            echo "\n";
        }
    } else {
        echo "✓ Keine doppelten Namen gefunden.\n";
    }

    // Suche nach Benutzern ohne Azure OID
    echo "\n=== Benutzer ohne Azure OID ===\n";
    $noAzureOid = $conn->query("
        SELECT id, username, display_name, role
        FROM users
        WHERE azure_oid IS NULL OR azure_oid = ''
        ORDER BY id
    ");

    if ($noAzureOid && $noAzureOid->num_rows > 0) {
        echo "Gefunden: {$noAzureOid->num_rows} Benutzer ohne Azure OID\n";
        while ($user = $noAzureOid->fetch_assoc()) {
            echo "  - ID: {$user['id']}, Name: {$user['display_name']}, Username: {$user['username']}, Role: {$user['role']}\n";
        }

        echo "\n⚠️  Diese Benutzer könnten Duplikate sein, wenn später ein User mit Azure OID erstellt wurde!\n";
    } else {
        echo "✓ Alle Benutzer haben eine Azure OID.\n";
    }

    echo "\n=== Empfohlene Aktionen ===\n";
    echo "1. Prüfe welcher Eintrag der korrekte ist (meist der mit Azure OID und MasterData)\n";
    echo "2. Erstelle Backup der Datenbank BEVOR du Änderungen vornimmst!\n";
    echo "3. Lösche falsche Duplikate mit:\n";
    echo "   DELETE FROM users WHERE id = <FALSCHE_ID>;\n";
    echo "\n";

} catch (Exception $e) {
    http_response_code(500);
    echo "FEHLER: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
?>
