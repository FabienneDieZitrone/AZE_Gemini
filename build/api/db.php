<?php
/**
 * Titel: Datenbankverbindung
 * Version: 1.4
 * Letzte Aktualisierung: 15.11.2024
 * Autor: MP-IT
 * Status: Final & Corrected
 * Datei: /api/db.php
 * Beschreibung: Stellt eine zentrale Datenbankverbindung her. Die Zugangsdaten wurden auf die korrekten Server-Spezifikationen von Host Europe aktualisiert.
 */

// KRITISCHE PRÜFUNG: Ist die MySQLi-Erweiterung überhaupt vorhanden?
if (!function_exists('mysqli_init') && !extension_loaded('mysqli')) {
    http_response_code(500);
    header('Content-Type: application/json');
    $error_message = 'Server-Konfigurationsfehler: Die erforderliche PHP-Erweiterung "mysqli" ist nicht aktiviert. Bitte kontaktieren Sie Ihren Hosting-Anbieter, um diese Erweiterung zu aktivieren.';
    error_log($error_message);
    echo json_encode(['message' => $error_message]);
    exit();
}

// --- Datenbank-Zugangsdaten (korrigiert am 15.11.2024) ---
$servername = "vwp8374.webpack.hosteurope.de";
$username = "db10454681-aze";
$password = "Start.321";
$dbname = "db10454681-aze";

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