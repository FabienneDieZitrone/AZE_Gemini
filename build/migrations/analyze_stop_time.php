#!/usr/bin/env php
<?php
/**
 * Analyse: stop_time Status
 * Version: 1.0
 * Datum: 2025-07-29
 * Autor: Claude Code
 * 
 * Dieses Script analysiert den aktuellen Status der stop_time Spalte
 * ohne Änderungen vorzunehmen.
 * 
 * Ausführung: php migrations/analyze_stop_time.php
 */

// CLI-Check
if (php_sapi_name() !== 'cli') {
    die("Dieses Script kann nur von der Kommandozeile ausgeführt werden.\n");
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Farbige Ausgabe
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

function printInfo($text) {
    echo "  " . $text . "\n";
}

// Lade Konfiguration
require_once __DIR__ . '/../config.php';
$config = Config::load();

// Datenbankverbindung
$conn = new mysqli(
    Config::get('db.host'),
    Config::get('db.username'),
    Config::get('db.password'),
    Config::get('db.name')
);

if ($conn->connect_error) {
    die(CliColors::RED . "Verbindung fehlgeschlagen: " . $conn->connect_error . CliColors::RESET . "\n");
}

$conn->set_charset("utf8mb4");

printHeader("stop_time Analyse");

// 1. Tabellenstruktur
printHeader("Tabellenstruktur");
$result = $conn->query("SHOW CREATE TABLE time_entries");
$row = $result->fetch_assoc();
$createTable = $row['Create Table'];

// Extrahiere stop_time Definition
if (preg_match('/`stop_time`\s+([^,]+)/', $createTable, $matches)) {
    printInfo("stop_time Definition: " . $matches[1]);
    
    if (strpos($matches[1], 'NOT NULL') !== false) {
        printWarning("stop_time ist aktuell NOT NULL");
    } else {
        printSuccess("stop_time erlaubt NULL");
    }
}

// 2. Datenanalyse
printHeader("Datenanalyse");

// Gesamtanzahl
$result = $conn->query("SELECT COUNT(*) as total FROM time_entries");
$row = $result->fetch_assoc();
printInfo("Gesamtanzahl Einträge: " . $row['total']);

// NULL-Einträge
$result = $conn->query("SELECT COUNT(*) as null_count FROM time_entries WHERE stop_time IS NULL");
$row = $result->fetch_assoc();
printInfo("Einträge mit stop_time = NULL: " . $row['null_count']);

// 00:00:00-Einträge
$result = $conn->query("SELECT COUNT(*) as zero_count FROM time_entries WHERE stop_time = '00:00:00'");
$row = $result->fetch_assoc();
printInfo("Einträge mit stop_time = '00:00:00': " . $row['zero_count']);

// Heute gestartete Timer mit 00:00:00
$result = $conn->query("
    SELECT COUNT(*) as today_running 
    FROM time_entries 
    WHERE stop_time = '00:00:00' 
    AND DATE(start_time) = CURDATE()
");
$row = $result->fetch_assoc();
printInfo("Heute gestartete Timer mit stop_time = '00:00:00': " . $row['today_running']);

// 3. Detailanalyse
printHeader("Detailanalyse der letzten 20 Einträge");

$query = "
    SELECT 
        id,
        user_id,
        start_time,
        stop_time,
        CASE 
            WHEN stop_time IS NULL THEN 'NULL'
            WHEN stop_time = '00:00:00' THEN '00:00:00 (läuft)'
            ELSE stop_time
        END as stop_display,
        TIMESTAMPDIFF(MINUTE, start_time, 
            CASE 
                WHEN stop_time IS NULL OR stop_time = '00:00:00' 
                THEN NOW() 
                ELSE CONCAT(DATE(start_time), ' ', stop_time)
            END
        ) as duration_minutes
    FROM time_entries 
    ORDER BY id DESC 
    LIMIT 20
";

$result = $conn->query($query);

if ($result->num_rows > 0) {
    printf("%-8s %-12s %-20s %-20s %-15s\n", "ID", "User", "Start", "Stop", "Dauer (Min)");
    echo str_repeat("-", 80) . "\n";
    
    while ($row = $result->fetch_assoc()) {
        $color = ($row['stop_time'] == '00:00:00') ? CliColors::YELLOW : "";
        $reset = ($row['stop_time'] == '00:00:00') ? CliColors::RESET : "";
        
        printf("%s%-8s %-12s %-20s %-20s %-15s%s\n", 
            $color,
            $row['id'], 
            $row['user_id'], 
            $row['start_time'],
            $row['stop_display'],
            $row['duration_minutes'],
            $reset
        );
    }
}

// 4. Empfehlungen
printHeader("Empfehlungen");

$result = $conn->query("SELECT COUNT(*) as zero_count FROM time_entries WHERE stop_time = '00:00:00'");
$row = $result->fetch_assoc();
$zeroCount = $row['zero_count'];

if ($zeroCount > 0) {
    printWarning("Es gibt $zeroCount Einträge mit stop_time = '00:00:00'");
    echo "\nEmpfohlene Aktion:\n";
    echo "1. Führen Sie die Migration aus: " . CliColors::GREEN . "php migrations/001_stop_time_nullable.php" . CliColors::RESET . "\n";
    echo "2. Dies konvertiert alle '00:00:00' zu NULL und erlaubt NULL-Werte\n";
    echo "3. Laufende Timer werden dann mit stop_time = NULL gespeichert\n";
} else {
    printSuccess("Keine problematischen '00:00:00' Einträge gefunden");
}

$conn->close();

echo "\n";