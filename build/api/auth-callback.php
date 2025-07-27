<?php
/**
 * Titel: OAuth Callback-Endpunkt
 * Version: 1.4 (Finale Korrektur)
 * Letzte Aktualisierung: 24.07.2025
 * Autor: MP-IT
 * Datei: /api/auth-callback.php
 * Beschreibung: Verarbeitet den Callback von Azure AD, erstellt eine Benutzersession und regeneriert die Session-ID vor dem Redirect für maximale Stabilität und Sicherheit.
 */

// CRITICAL FIX: Force browser session BEFORE any requires
ini_set('session.cookie_lifetime', 0);
session_set_cookie_params([
    'lifetime' => 0,  // Browser session only
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'] ?? '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Bindet den OAuth-Client und die sichere Session-Funktion ein.
require_once __DIR__ . '/auth-oauth-client.php';
require_once __DIR__ . '/validation.php';

// Startet die Session mit den sicheren, zentral definierten Einstellungen,
// um auf den gespeicherten 'state' zugreifen zu können.
start_secure_session();

// Validate and sanitize GET parameters
$allowed_params = ['state', 'error', 'error_description', 'code'];
$get_params = InputValidator::validateGetParams($allowed_params);

// --- CSRF-Schutz: 'state' Parameter vergleichen ---
if (empty($get_params['state']) || !isset($_SESSION['oauth2state']) || !hash_equals($_SESSION['oauth2state'], $get_params['state'])) {
    unset($_SESSION['oauth2state']);
    http_response_code(400);
    error_log("Invalid state parameter. SESSION: " . ($_SESSION['oauth2state'] ?? 'not set') . " GET: " . ($get_params['state'] ?? 'not set'));
    echo 'Invalid state parameter. CSRF attack detected.';
    exit();
}

// --- Fehlerprüfung vom Auth-Server ---
if (isset($get_params['error'])) {
    http_response_code(500);
    echo 'Error from authentication server: ' . htmlspecialchars($get_params['error']) . ' - ' . htmlspecialchars($get_params['error_description'] ?? 'No description');
    exit();
}

// --- Code gegen Token tauschen ---
if (isset($get_params['code'])) {
    try {
        $tokens = getTokensFromCode($get_params['code']);
        
        // Den ID-Token dekodieren, um Benutzerinformationen zu erhalten
        $id_token_parts = explode('.', $tokens['id_token']);
        $id_token_payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $id_token_parts[1])), true);
        
        // Wichtige Benutzerinformationen in der Session speichern
        $_SESSION['user'] = [
            'oid' => $id_token_payload['oid'],
            'name' => $id_token_payload['name'],
            'username' => $id_token_payload['preferred_username']
        ];
        
        // Tokens sicher in der Session speichern (für spätere API-Aufrufe, Refresh, etc.)
        $_SESSION['auth_tokens'] = $tokens;
        
        // SECURITY FIX: Session-Timestamps für Timeout-Kontrolle setzen
        $_SESSION['created'] = time();
        $_SESSION['last_activity'] = time();
        
        // State-Variable nach erfolgreicher Verwendung löschen
        unset($_SESSION['oauth2state']);
        
        // WICHTIGE KORREKTUR: Regeneriert die Session-ID nach der erfolgreichen Authentifizierung.
        // Verhindert Session-Fixation-Angriffe und stellt sicher, dass ein neuer, sauberer Cookie-Header gesendet wird.
        session_regenerate_id(true);

        // WICHTIG: Session-Daten explizit auf die Festplatte schreiben, BEVOR der Redirect erfolgt.
        // Verhindert Race Conditions.
        session_write_close();
        
        // Benutzer zur Hauptanwendung zurückleiten
        header('Location: /');
        exit(); // Stellt sicher, dass das Skript hier endet und keinen weiteren Inhalt ausgibt.

    } catch (Exception $e) {
        http_response_code(500);
        error_log('Token exchange failed: ' . $e->getMessage());
        echo 'Failed to exchange authorization code for tokens. Please try again.';
        exit();
    }
} else {
    http_response_code(400);
    echo 'Authorization code not found in callback.';
    exit();
}
?>