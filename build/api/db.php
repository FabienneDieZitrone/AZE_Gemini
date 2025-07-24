<?php
/**
 * Titel: Sichere Datenbankverbindung mit Environment Variables
 * Version: 2.0
 * Letzte Aktualisierung: 2025-07-24
 * Autor: Claude Code (Security Update)
 * Status: Secure Configuration
 * Datei: /api/db.php
 * Beschreibung: Stellt eine zentrale Datenbankverbindung her mit sicherer Konfiguration.
 *               SICHERHEIT: Credentials werden aus Environment Variables geladen!
 */

// Lade sichere Konfiguration
require_once __DIR__ . '/../config.php';

// KRITISCHE PRÜFUNG: Ist die MySQLi-Erweiterung überhaupt vorhanden?
if (!function_exists('mysqli_init') && !extension_loaded('mysqli')) {
    http_response_code(500);
    header('Content-Type: application/json');
    $error_message = 'Server-Konfigurationsfehler: Die erforderliche PHP-Erweiterung "mysqli" ist nicht aktiviert. Bitte kontaktieren Sie Ihren Hosting-Anbieter, um diese Erweiterung zu aktivieren.';
    error_log($error_message);
    echo json_encode(['message' => $error_message]);
    exit();
}

// --- Sichere Datenbank-Zugangsdaten aus Environment Variables ---
$config = Config::load();
$servername = Config::get('db.host');
$username = Config::get('db.username');
$password = Config::get('db.password');
$dbname = Config::get('db.name');

// Prüfe ob alle erforderlichen Konfigurationswerte vorhanden sind
if (empty($servername) || empty($username) || empty($dbname)) {
    http_response_code(500);
    header('Content-Type: application/json');
    $error_message = 'Konfigurationsfehler: Datenbank-Credentials sind nicht vollständig konfiguriert. Bitte prüfen Sie die .env Datei oder Environment Variables.';
    error_log($error_message);
    echo json_encode(['message' => $error_message]);
    exit();
}

// Erstellt eine neue mysqli-Verbindung
$conn = new mysqli($servername, $username, $password, $dbname);

// Überprüft die Verbindung
if ($conn->connect_error) {
    // Schreibt den Fehler in ein Log, anstatt ihn auszugeben
    error_log("Connection failed: " . $conn->connect_error);
    
    // Sendet eine detailliertere Fehlermeldung an den Client
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Interne Serverfehler: Datenbankverbindung konnte nicht hergestellt werden.', 'error_details' => $conn->connect_error]);
    exit();
}

// Setzt den Zeichensatz auf UTF-8, um Kompatibilitätsprobleme zu vermeiden
if (!$conn->set_charset("utf8mb4")) {
     error_log("Error loading character set utf8mb4: " . $conn->error);
}

// Die Variable $conn ist nun in allen Skripten verfügbar, die diese Datei einbinden.