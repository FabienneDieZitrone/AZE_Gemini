<?php
/**
 * Titel: Auth Status Check Endpunkt
 * Version: 1.0
 * Letzte Aktualisierung: 10.11.2024
 * Autor: MP-IT
 * Datei: /api/auth-status.php
 * Beschreibung: Überprüft die Session und gibt 204 bei Erfolg oder 401 bei Fehler zurück.
 *              Wird von der Frontend-App beim Start aufgerufen, um den Login-Status zu ermitteln.
 */

// CRITICAL FIX: Force browser session (no auto-login in private windows)
ini_set('session.cookie_lifetime', 0);
session_set_cookie_params([
    'lifetime' => 0,  // Browser session only
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'] ?? '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Define API guard constant
define('API_GUARD', true);

require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/security-middleware.php';

initialize_api();

// Apply security headers
initSecurityMiddleware();

// Diese Funktion erledigt die gesamte Arbeit: Sie startet die Session, prüft auf einen
// gültigen 'user'-Eintrag und sendet automatisch eine 401-Antwort, falls keiner gefunden wird.
verify_session_and_get_user();

// Wenn das Skript bis hierhin gelangt, ist die Session gültig.
// Wir senden eine 204 No Content Antwort, da der Aufrufer nur den HTTP-Statuscode benötigt.
send_response(204);
?>
