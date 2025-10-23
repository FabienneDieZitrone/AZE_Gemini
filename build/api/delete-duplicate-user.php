<?php
/**
 * Delete Duplicate User ID 7
 * Sicheres Löschen des identifizierten Duplikats
 */

define('API_GUARD', true);
require_once __DIR__ . '/DatabaseConnection.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $conn = DatabaseConnection::getInstance()->getConnection();

    echo "=== Duplikat-User löschen ===\n\n";

    // ID 7 ist das identifizierte Duplikat
    $duplicateId = 7;

    // Noch einmal verifizieren dass es wirklich das Duplikat ist
    $verify = $conn->prepare("SELECT id, username, display_name, role, azure_oid FROM users WHERE id = ?");
    $verify->bind_param('i', $duplicateId);
    $verify->execute();
    $verify->bind_result($id, $username, $displayName, $role, $azureOid);

    if (!$verify->fetch()) {
        echo "✗ User ID $duplicateId nicht gefunden.\n";
        $verify->close();
        exit;
    }

    echo "User gefunden:\n";
    echo "  ID: $id\n";
    echo "  Username: $username\n";
    echo "  Name: $displayName\n";
    echo "  Role: $role\n";
    echo "  Azure OID: " . ($azureOid ? $azureOid : "NULL") . "\n\n";

    $verify->close();

    // Sicherheits-Checks
    if ($azureOid !== null && $azureOid !== '') {
        echo "⚠️  FEHLER: Dieser User hat eine Azure OID!\n";
        echo "Dies ist KEIN Duplikat, sondern ein echter User.\n";
        echo "Abbruch aus Sicherheitsgründen.\n";
        exit;
    }

    // Prüfe ob MasterData existiert
    $mdCheck = $conn->prepare("SELECT COUNT(*) FROM master_data WHERE user_id = ?");
    $mdCheck->bind_param('i', $duplicateId);
    $mdCheck->execute();
    $mdCheck->bind_result($mdCount);
    $mdCheck->fetch();
    $mdCheck->close();

    if ($mdCount > 0) {
        echo "⚠️  WARNUNG: Dieser User hat MasterData-Einträge!\n";
        echo "Möglicherweise KEIN Duplikat. Bitte manuell prüfen.\n";
        echo "Abbruch aus Sicherheitsgründen.\n";
        exit;
    }

    // Prüfe ob TimeEntries existieren
    $teCheck = $conn->prepare("SELECT COUNT(*) FROM time_entries WHERE user_id = ?");
    $teCheck->bind_param('i', $duplicateId);
    $teCheck->execute();
    $teCheck->bind_result($teCount);
    $teCheck->fetch();
    $teCheck->close();

    if ($teCount > 0) {
        echo "⚠️  WARNUNG: Dieser User hat TimeEntries!\n";
        echo "Möglicherweise KEIN Duplikat. Bitte manuell prüfen.\n";
        echo "Abbruch aus Sicherheitsgründen.\n";
        exit;
    }

    // Alle Checks bestanden - sicher zu löschen
    echo "✓ Sicherheits-Checks bestanden:\n";
    echo "  - Keine Azure OID\n";
    echo "  - Keine MasterData\n";
    echo "  - Keine TimeEntries\n\n";

    echo "Lösche User ID $duplicateId...\n";

    $delete = $conn->prepare("DELETE FROM users WHERE id = ?");
    $delete->bind_param('i', $duplicateId);

    if ($delete->execute()) {
        echo "✓ User erfolgreich gelöscht!\n";
        echo "Betroffene Zeilen: " . $conn->affected_rows . "\n";
    } else {
        echo "✗ Fehler beim Löschen: " . $conn->error . "\n";
    }
    $delete->close();

    echo "\n=== Verifizierung ===\n";

    // Prüfe ob noch Duplikate existieren
    $checkDupes = $conn->query("
        SELECT display_name, COUNT(*) as count
        FROM users
        WHERE display_name = 'Günter Allert'
        GROUP BY display_name
    ");

    if ($checkDupes && $checkDupes->num_rows > 0) {
        $row = $checkDupes->fetch_assoc();
        if ($row['count'] == 1) {
            echo "✓ Keine Duplikate mehr vorhanden!\n";
        } else {
            echo "⚠️  Es existieren noch {$row['count']} Einträge für 'Günter Allert'\n";
        }
    }

    echo "\n✅ Fertig!\n";

} catch (Exception $e) {
    http_response_code(500);
    echo "FEHLER: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
?>
