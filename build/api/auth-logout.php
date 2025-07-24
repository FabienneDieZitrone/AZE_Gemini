<?php
/**
 * Titel: Logout-Endpunkt
 * Version: 1.2 (Sauberes Beenden)
 * Letzte Aktualisierung: 11.11.2024
 * Autor: MP-IT
 * Datei: /api/auth-logout.php
 * Beschreibung: Zerstört die Benutzersession, leitet zur Startseite um und beendet das Skript sauber.
 */

require_once __DIR__ . '/auth_helpers.php';

// Startet die Session mit den sicheren Einstellungen, um sie korrekt ansprechen zu können.
start_secure_session();

// Alle Session-Variablen löschen
$_SESSION = [];

// Das Session-Cookie explizit löschen
// `session_get_cookie_params()` holt sich die korrekten, sicheren Parameter von `start_secure_session()`
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Session auf dem Server zerstören
session_destroy();

// Leite den Benutzer zur Startseite der Anwendung weiter.
// Der Browser wird die Seite ohne das Session-Cookie neu laden, was den Benutzer effektiv ausloggt.
header('Location: /');
exit(); // WICHTIG: Stellt sicher, dass das Skript hier endet und keinen weiteren Inhalt ausgibt.
?>