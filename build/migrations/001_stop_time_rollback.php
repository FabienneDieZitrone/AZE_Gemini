#!/usr/bin/env php
<?php
/**
 * Rollback: stop_time wieder NOT NULL
 * Version: 1.0
 * Datum: 2025-07-29
 * Autor: Claude Code
 * 
 * Dieses Script macht die stop_time Migration rückgängig.
 * ACHTUNG: Alle NULL-Werte werden zu '00:00:00' konvertiert!
 * 
 * Ausführung: php migrations/001_stop_time_rollback.php
 */

// CLI-Check
if (php_sapi_name() !== 'cli') {
    die("Dieses Script kann nur von der Kommandozeile ausgeführt werden.\n");
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Farbige Ausgabe für CLI
class CliColors {
    const RED = "\033[0;31m";
    const GREEN = "\033[0;32m";
    const YELLOW = "\033[1;33m";
    const BLUE = "\033[0;34m";
    const RESET = "\033[0m";
}

function printHeader($text) {
    echo "\n" . CliColors::BLUE . "=== " . $text . " ===" . CliColors::RESET . "\n\n";
}

function printSuccess($text) {
    echo CliColors::GREEN . "✓ " . $text . CliColors::RESET . "\n";
}

function printWarning($text) {
    echo CliColors::YELLOW . "⚠ " . $text . CliColors::RESET . "\n";
}

function printError($text) {
    echo CliColors::RED . "✗ " . $text . CliColors::RESET . "\n";
}

function printInfo($text) {
    echo "  " . $text . "\n";
}

// Start des Rollbacks
printHeader("stop_time Rollback - NOT NULL wiederherstellen");
printWarning("ACHTUNG: Dieses Script macht die NULL-Migration rückgängig!");
echo "Folgende Änderungen werden durchgeführt:\n";
echo "1. Alle NULL-Werte in stop_time werden zu '00:00:00' konvertiert\n";
echo "2. Die Spalte wird wieder auf NOT NULL gesetzt\n\n";

// Sicherheitsabfrage
echo "Sind Sie SICHER, dass Sie fortfahren möchten? (ja/nein): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
if (trim($line) != 'ja') {
    printWarning("Rollback abgebrochen.");
    exit(0);
}
fclose($handle);

// Lade Konfiguration
require_once __DIR__ . '/../config.php';
$config = Config::load();

// Datenbankverbindung
$servername = Config::get('db.host');
$username = Config::get('db.username');
$password = Config::get('db.password');
$dbname = Config::get('db.name');

printHeader("Verbinde mit Datenbank");
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    printError("Verbindung fehlgeschlagen: " . $conn->connect_error);
    exit(1);
}

$conn->set_charset("utf8mb4");
printSuccess("Datenbankverbindung hergestellt");

// Analysiere Daten
printHeader("Analysiere aktuelle Daten");

$result = $conn->query("SELECT COUNT(*) as null_count FROM time_entries WHERE stop_time IS NULL");
$row = $result->fetch_assoc();
$nullCount = $row['null_count'];
printInfo("Einträge mit stop_time = NULL: " . $nullCount);

if ($nullCount > 0) {
    printWarning("$nullCount Einträge werden zu '00:00:00' konvertiert!");
}

// Rollback durchführen
printHeader("Führe Rollback durch");

// Transaktion starten
$conn->begin_transaction();

try {
    // Schritt 1: Update NULL zu '00:00:00'
    printInfo("Konvertiere NULL Werte zu '00:00:00'...");
    $updateQuery = "UPDATE time_entries SET stop_time = '00:00:00' WHERE stop_time IS NULL";
    
    if (!$conn->query($updateQuery)) {
        throw new Exception("Fehler beim Update: " . $conn->error);
    }
    
    $affectedRows = $conn->affected_rows;
    printSuccess("$affectedRows Einträge wurden zu '00:00:00' konvertiert");
    
    // Schritt 2: Alter Table - stop_time NOT NULL
    printInfo("Ändere Spaltenstruktur zurück zu NOT NULL...");
    $alterQuery = "ALTER TABLE time_entries MODIFY COLUMN stop_time TIME NOT NULL DEFAULT '00:00:00'";
    
    if (!$conn->query($alterQuery)) {
        throw new Exception("Fehler beim Ändern der Spalte: " . $conn->error);
    }
    printSuccess("Spalte stop_time ist wieder NOT NULL");
    
    // Commit
    $conn->commit();
    printSuccess("Rollback erfolgreich abgeschlossen!");
    
} catch (Exception $e) {
    // Rollback
    $conn->rollback();
    printError("Rollback fehlgeschlagen: " . $e->getMessage());
    exit(1);
}

// Verifizierung
printHeader("Verifiziere Rollback");

// Prüfe Tabellenstruktur
$result = $conn->query("SHOW CREATE TABLE time_entries");
$row = $result->fetch_assoc();
$createTable = $row['Create Table'];

if (strpos($createTable, "`stop_time` time NOT NULL") !== false) {
    printSuccess("Tabellenstruktur korrekt wiederhergestellt");
} else {
    printError("Tabellenstruktur nicht wie erwartet!");
}

// Prüfe Daten
$result = $conn->query("SELECT COUNT(*) as null_count FROM time_entries WHERE stop_time IS NULL");
$row = $result->fetch_assoc();
$remainingNulls = $row['null_count'];

if ($remainingNulls > 0) {
    printError("Es gibt noch NULL-Einträge! Das sollte nicht sein.");
} else {
    printSuccess("Keine NULL-Einträge mehr vorhanden");
}

// Abschlussbericht
printHeader("Rollback-Report");
echo "Rollback abgeschlossen am: " . date('Y-m-d H:i:s') . "\n";
echo "Konvertierte Einträge: " . $affectedRows . "\n";
echo "Status: " . CliColors::GREEN . "ERFOLGREICH" . CliColors::RESET . "\n";

$conn->close();

echo "\n" . CliColors::YELLOW . "Rollback abgeschlossen. Die Datenbank ist im ursprünglichen Zustand." . CliColors::RESET . "\n\n";