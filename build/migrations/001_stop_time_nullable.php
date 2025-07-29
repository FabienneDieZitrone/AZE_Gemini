#!/usr/bin/env php
<?php
/**
 * Migration: stop_time NULL erlauben
 * Version: 1.0
 * Datum: 2025-07-29
 * Autor: Claude Code
 * 
 * Diese Migration ändert die stop_time Spalte von NOT NULL zu NULL
 * und konvertiert alle '00:00:00' Werte zu NULL.
 * 
 * Ausführung: php migrations/001_stop_time_nullable.php
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

// Start der Migration
printHeader("stop_time Migration - NULL Support");
echo "Dieses Script führt folgende Änderungen durch:\n";
echo "1. Ändert die stop_time Spalte von NOT NULL zu NULL\n";
echo "2. Konvertiert alle '00:00:00' Werte zu NULL\n";
echo "3. Erstellt einen detaillierten Report\n\n";

// Sicherheitsabfrage
echo "Möchten Sie fortfahren? (ja/nein): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
if (trim($line) != 'ja') {
    printWarning("Migration abgebrochen.");
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
printInfo("Host: " . $servername);
printInfo("Datenbank: " . $dbname);
printInfo("Benutzer: " . $username);

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    printError("Verbindung fehlgeschlagen: " . $conn->connect_error);
    exit(1);
}

$conn->set_charset("utf8mb4");
printSuccess("Datenbankverbindung hergestellt");

// Backup-Empfehlung
printHeader("WICHTIG: Backup-Empfehlung");
printWarning("Bitte erstellen Sie VOR der Migration ein Backup der Datenbank!");
echo "Empfohlener Befehl:\n";
echo CliColors::YELLOW . "mysqldump -h$servername -u$username -p $dbname > backup_" . date('Y-m-d_H-i-s') . ".sql" . CliColors::RESET . "\n\n";
echo "Haben Sie ein Backup erstellt? (ja/nein): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
if (trim($line) != 'ja') {
    printError("Migration abgebrochen. Bitte erstellen Sie zuerst ein Backup!");
    exit(0);
}
fclose($handle);

// Analysiere aktuelle Struktur
printHeader("Analysiere aktuelle Tabellenstruktur");

$result = $conn->query("SHOW CREATE TABLE time_entries");
if (!$result) {
    printError("Fehler beim Abrufen der Tabellenstruktur: " . $conn->error);
    exit(1);
}

$row = $result->fetch_assoc();
$createTable = $row['Create Table'];
printInfo("Aktuelle Struktur gefunden");

// Prüfe ob stop_time bereits NULL erlaubt
if (strpos($createTable, "`stop_time` time DEFAULT NULL") !== false) {
    printWarning("stop_time erlaubt bereits NULL. Migration nicht erforderlich.");
    exit(0);
}

// Analysiere Daten
printHeader("Analysiere Daten");

// Gesamtanzahl Einträge
$result = $conn->query("SELECT COUNT(*) as total FROM time_entries");
$row = $result->fetch_assoc();
$totalEntries = $row['total'];
printInfo("Gesamtanzahl Einträge: " . $totalEntries);

// Anzahl '00:00:00' Einträge
$result = $conn->query("SELECT COUNT(*) as zero_count FROM time_entries WHERE stop_time = '00:00:00'");
$row = $result->fetch_assoc();
$zeroCount = $row['zero_count'];
printInfo("Einträge mit stop_time = '00:00:00': " . $zeroCount);

// Anzahl laufender Timer (stop_time = '00:00:00' UND start_time = heute)
$result = $conn->query("SELECT COUNT(*) as running FROM time_entries WHERE stop_time = '00:00:00' AND DATE(start_time) = CURDATE()");
$row = $result->fetch_assoc();
$runningTimers = $row['running'];
printInfo("Vermutlich laufende Timer (heute gestartet): " . $runningTimers);

// Detaillierte Analyse der betroffenen Einträge
printHeader("Detailanalyse der betroffenen Einträge");

$query = "SELECT id, user_id, start_time, stop_time, DATE(start_time) as date 
          FROM time_entries 
          WHERE stop_time = '00:00:00' 
          ORDER BY start_time DESC 
          LIMIT 10";

$result = $conn->query($query);
if ($result->num_rows > 0) {
    echo "\nLetzte 10 Einträge mit stop_time = '00:00:00':\n";
    printf("%-10s %-15s %-20s %-20s\n", "ID", "User ID", "Start Time", "Datum");
    echo str_repeat("-", 70) . "\n";
    
    while ($row = $result->fetch_assoc()) {
        printf("%-10s %-15s %-20s %-20s\n", 
            $row['id'], 
            $row['user_id'], 
            $row['start_time'],
            $row['date']
        );
    }
}

// Migration durchführen
printHeader("Führe Migration durch");

// Transaktion starten
$conn->begin_transaction();

try {
    // Schritt 1: Alter Table - stop_time NULL erlauben
    printInfo("Ändere Spaltenstruktur...");
    $alterQuery = "ALTER TABLE time_entries MODIFY COLUMN stop_time TIME NULL DEFAULT NULL";
    
    if (!$conn->query($alterQuery)) {
        throw new Exception("Fehler beim Ändern der Spalte: " . $conn->error);
    }
    printSuccess("Spalte stop_time erlaubt jetzt NULL");
    
    // Schritt 2: Update '00:00:00' zu NULL
    printInfo("Konvertiere '00:00:00' Werte zu NULL...");
    $updateQuery = "UPDATE time_entries SET stop_time = NULL WHERE stop_time = '00:00:00'";
    
    if (!$conn->query($updateQuery)) {
        throw new Exception("Fehler beim Update: " . $conn->error);
    }
    
    $affectedRows = $conn->affected_rows;
    printSuccess("$affectedRows Einträge wurden zu NULL konvertiert");
    
    // Commit
    $conn->commit();
    printSuccess("Migration erfolgreich abgeschlossen!");
    
} catch (Exception $e) {
    // Rollback
    $conn->rollback();
    printError("Migration fehlgeschlagen: " . $e->getMessage());
    exit(1);
}

// Verifizierung
printHeader("Verifiziere Migration");

// Prüfe Tabellenstruktur
$result = $conn->query("SHOW CREATE TABLE time_entries");
$row = $result->fetch_assoc();
$createTable = $row['Create Table'];

if (strpos($createTable, "`stop_time` time DEFAULT NULL") !== false) {
    printSuccess("Tabellenstruktur korrekt geändert");
} else {
    printError("Tabellenstruktur nicht wie erwartet!");
}

// Prüfe Daten
$result = $conn->query("SELECT COUNT(*) as null_count FROM time_entries WHERE stop_time IS NULL");
$row = $result->fetch_assoc();
$nullCount = $row['null_count'];

$result = $conn->query("SELECT COUNT(*) as zero_count FROM time_entries WHERE stop_time = '00:00:00'");
$row = $result->fetch_assoc();
$remainingZeros = $row['zero_count'];

printInfo("Einträge mit stop_time = NULL: " . $nullCount);
printInfo("Verbleibende '00:00:00' Einträge: " . $remainingZeros);

if ($remainingZeros > 0) {
    printWarning("Es gibt noch '00:00:00' Einträge! Das sollte nicht sein.");
}

// Abschlussbericht
printHeader("Migrations-Report");
echo "Migration abgeschlossen am: " . date('Y-m-d H:i:s') . "\n";
echo "Betroffene Einträge: " . $affectedRows . "\n";
echo "Status: " . CliColors::GREEN . "ERFOLGREICH" . CliColors::RESET . "\n";

// Empfehlungen
printHeader("Nächste Schritte");
echo "1. Testen Sie die Anwendung gründlich\n";
echo "2. Prüfen Sie ob laufende Timer korrekt funktionieren\n";
echo "3. Aktualisieren Sie die API-Endpunkte für die neue NULL-Logik\n";
echo "4. Dokumentieren Sie die Änderung\n";

$conn->close();

echo "\n" . CliColors::GREEN . "Migration erfolgreich abgeschlossen!" . CliColors::RESET . "\n\n";