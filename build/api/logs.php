<?php
/**
 * Titel: API-Endpunkt für Fehlerprotokollierung
 * Version: 1.2
 * Autor: MP-IT
 * Datei: /api/logs.php
 * Beschreibung: Nimmt Fehlerdaten vom Frontend entgegen und schreibt sie in eine Log-Datei. Fügt robustes Fatal-Error-Handling hinzu.
 */

// Robuster Fatal-Error-Handler, um leere Antworten zu verhindern
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        error_log("Fatal error in logs.php: " . print_r($error, true));
        echo json_encode(['message' => 'Fatal PHP Error in logging endpoint.']);
        exit;
    }
});


// SECURITY: Error reporting disabled in production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// Keine Authentifizierung für diesen Endpunkt erforderlich.
require_once __DIR__ . '/validation.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Method Not Allowed']);
    exit();
}

try {
    $required_fields = ['message'];
    $optional_fields = ['level' => 'error', 'context' => [], 'timestamp' => date('Y-m-d H:i:s')];
    $data = InputValidator::validateJsonInput($required_fields, $optional_fields);
    
    // Validate log level
    $allowed_levels = ['error', 'warning', 'info', 'debug'];
    if (!in_array($data['level'], $allowed_levels)) {
        $data['level'] = 'error'; // Default to error if invalid
    }
    
    // Limit message length to prevent log spam
    if (strlen($data['message']) > 1000) {
        $data['message'] = substr($data['message'], 0, 997) . '...';
    }
    
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['message' => 'Validation error: ' . $e->getMessage()]);
    exit();
} catch (Exception $e) {
    error_log('Validation error in logs.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['message' => 'Server error during validation']);
    exit();
}

// Pfad zur Log-Datei im selben Verzeichnis wie das Skript
$log_file = __DIR__ . '/error.log';

// Formatieren des Log-Eintrags
$log_entry = "[" . date('Y-m-d H:i:s') . "]\n";
$log_entry .= "Message: " . ($data['message'] ?? 'N/A') . "\n";
$log_entry .= "Context: " . ($data['context'] ?? 'N/A') . "\n";
$log_entry .= "Stack Trace: " . ($data['stack'] ?? 'N/A') . "\n";
$log_entry .= "--------------------------------------------------\n\n";

// Log-Eintrag in die Datei schreiben (FILE_APPEND fügt hinzu, anstatt zu überschreiben)
if (file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX) === false) {
    // Wenn das Schreiben fehlschlägt, den Server-Fehler loggen
    error_log("Failed to write to log file: " . $log_file);
    http_response_code(500);
    echo json_encode(['message' => 'Internal Server Error: Could not write to log file.']);
} else {
    http_response_code(204); // No Content
}