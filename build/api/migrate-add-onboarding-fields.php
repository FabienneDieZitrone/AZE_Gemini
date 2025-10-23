<?php
/**
 * Migration: Onboarding-Felder für neue Mitarbeiter
 *
 * Fügt folgende Spalten zur users-Tabelle hinzu:
 * - onboarding_completed: Boolean, ob Onboarding abgeschlossen
 * - home_location: Stamm-/Heimatstandort des Mitarbeiters
 * - created_via_onboarding: Boolean, ob via Onboarding-Prozess erstellt
 * - pending_since: Timestamp, seit wann Stammdaten-Eingabe pending
 */

define('API_GUARD', true);
require_once __DIR__ . '/DatabaseConnection.php';

// Don't use error-handler.php for migrations - it interferes with output
header('Content-Type: text/plain; charset=utf-8');

try {
    $conn = DatabaseConnection::getInstance()->getConnection();

    echo "=== Migration: Onboarding-Felder hinzufügen ===\n\n";

    // Prüfe ob Spalten bereits existieren
    $tableInfo = $conn->query("DESCRIBE users");
    $existingColumns = [];
    while ($row = $tableInfo->fetch_assoc()) {
        $existingColumns[] = $row['Field'];
    }

    $changes = [];

    // 1. onboarding_completed
    if (!in_array('onboarding_completed', $existingColumns)) {
        $conn->query("ALTER TABLE users ADD COLUMN onboarding_completed TINYINT(1) DEFAULT 0");
        $changes[] = "✓ onboarding_completed hinzugefügt";
        echo "✓ Spalte 'onboarding_completed' hinzugefügt\n";
    } else {
        echo "- Spalte 'onboarding_completed' existiert bereits\n";
    }

    // 2. home_location
    if (!in_array('home_location', $existingColumns)) {
        $conn->query("ALTER TABLE users ADD COLUMN home_location VARCHAR(255) NULL");
        $changes[] = "✓ home_location hinzugefügt";
        echo "✓ Spalte 'home_location' hinzugefügt\n";
    } else {
        echo "- Spalte 'home_location' existiert bereits\n";
    }

    // 3. created_via_onboarding
    if (!in_array('created_via_onboarding', $existingColumns)) {
        $conn->query("ALTER TABLE users ADD COLUMN created_via_onboarding TINYINT(1) DEFAULT 0");
        $changes[] = "✓ created_via_onboarding hinzugefügt";
        echo "✓ Spalte 'created_via_onboarding' hinzugefügt\n";
    } else {
        echo "- Spalte 'created_via_onboarding' existiert bereits\n";
    }

    // 4. pending_since
    if (!in_array('pending_since', $existingColumns)) {
        $conn->query("ALTER TABLE users ADD COLUMN pending_since DATETIME NULL");
        $changes[] = "✓ pending_since hinzugefügt";
        echo "✓ Spalte 'pending_since' hinzugefügt\n";
    } else {
        echo "- Spalte 'pending_since' existiert bereits\n";
    }

    // Setze für existierende User: onboarding_completed = 1
    $conn->query("UPDATE users SET onboarding_completed = 1 WHERE onboarding_completed = 0");
    echo "✓ Bestehende User als 'onboarding_completed' markiert\n";

    echo "\n=== Migration erfolgreich abgeschlossen ===\n";

    // Verifiziere die Änderungen
    echo "\n=== Verifikation ===\n";
    $tableInfo = $conn->query("DESCRIBE users");
    $newColumns = ['onboarding_completed', 'home_location', 'created_via_onboarding', 'pending_since'];

    while ($row = $tableInfo->fetch_assoc()) {
        if (in_array($row['Field'], $newColumns)) {
            echo "✓ {$row['Field']}: {$row['Type']} (Default: {$row['Default']})\n";
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Migration erfolgreich abgeschlossen',
        'changes' => $changes,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
?>
