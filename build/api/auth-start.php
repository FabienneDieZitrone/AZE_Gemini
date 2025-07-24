<?php
/**
 * Titel: OAuth Start-Endpunkt
 * Version: 1.2 (Sauberes Beenden)
 * Letzte Aktualisierung: 11.11.2024
 * Autor: MP-IT
 * Datei: /api/auth-start.php
 * Beschreibung: Leitet den serverseitigen OAuth2 Authorization Code Flow ein und beendet das Skript sauber.
 */
require_once __DIR__ . '/auth-oauth-client.php';

// Zerstört eine eventuell bestehende alte Session, um einen sauberen
// Neuanfang für den OAuth-Flow zu gewährleisten und "state"-Konflikte zu vermeiden.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Holt die Authorisierungs-URL vom OAuth-Client-Helfer.
// Diese Funktion startet jetzt intern eine neue, sichere Session und speichert den 'state'.
$authUrl = getAuthorizationUrl();

// Leitet den Browser des Benutzers zur Microsoft Anmeldeseite weiter.
header('Location: ' . $authUrl);
exit(); // WICHTIG: Stellt sicher, dass das Skript hier endet und keinen weiteren Inhalt ausgibt.
?>