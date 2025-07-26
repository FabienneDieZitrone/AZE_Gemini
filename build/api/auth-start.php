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

// CRITICAL FIX: Session leeren statt zerstören (für State-Parameter)
start_secure_session();
// Nur User-Daten löschen, nicht die gesamte Session
unset($_SESSION['user']);
unset($_SESSION['auth_tokens']);
unset($_SESSION['last_activity']);
unset($_SESSION['created']);

// Holt die Authorisierungs-URL vom OAuth-Client-Helfer.
// Diese Funktion startet jetzt intern eine neue, sichere Session und speichert den 'state'.
$authUrl = getAuthorizationUrl();

// Leitet den Browser des Benutzers zur Microsoft Anmeldeseite weiter.
header('Location: ' . $authUrl);
exit(); // WICHTIG: Stellt sicher, dass das Skript hier endet und keinen weiteren Inhalt ausgibt.
?>