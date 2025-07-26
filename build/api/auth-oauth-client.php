<?php
/**
 * Titel: PHP OAuth2 Client für Azure AD
 * Version: 1.1 (Nutzt sichere Sessions)
 * Letzte Aktualisierung: 11.11.2024
 * Autor: MP-IT
 * Datei: /api/auth-oauth-client.php
 * Beschreibung: Stellt Funktionen für den serverseitigen OAuth2-Flow mit Azure AD bereit. Nutzt jetzt die zentrale, sichere Session-Konfiguration.
 */

// Bindet die zentralen Hilfsfunktionen, inkl. der sicheren Session-Konfiguration, ein.
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/../config.php';

// --- SICHERE KONFIGURATION ---
// Lade Client Secret aus sicherer Konfiguration
$config = Config::load();
$clientSecret = Config::get('oauth.client_secret');

// Fallback für direkte .env Parsing falls Config nicht funktioniert
if (empty($clientSecret)) {
    $clientSecret = $_ENV['OAUTH_CLIENT_SECRET'] ?? null;
}

// SICHERHEITSPRÜFUNG: Keine unsicheren Fallbacks!
if (empty($clientSecret) || $clientSecret === 'your_azure_client_secret_here') {
    error_log('CRITICAL: OAuth Client Secret not configured or using placeholder');
    http_response_code(500);
    die('OAuth configuration error. Please configure OAUTH_CLIENT_SECRET in environment variables.');
}

define('OAUTH_CLIENT_ID', '737740ef-8ab9-44eb-8570-5e3027ddf207');
define('OAUTH_CLIENT_SECRET', $clientSecret);
define('OAUTH_TENANT_ID', '86b2012b-0a6b-4cdc-8f20-fb952f438319');
define('OAUTH_REDIRECT_URI', 'https://aze.mikropartner.de/api/auth-callback.php'); // Muss exakt mit der URI in Azure übereinstimmen

define('OAUTH_AUTHORITY', 'https://login.microsoftonline.com/' . OAUTH_TENANT_ID);
define('OAUTH_AUTHORIZE_ENDPOINT', OAUTH_AUTHORITY . '/oauth2/v2.0/authorize');
define('OAUTH_TOKEN_ENDPOINT', OAUTH_AUTHORITY . '/oauth2/v2.0/token');

// Scopes, die von der Anwendung angefordert werden. 'offline_access' ist für Refresh-Tokens.
define('OAUTH_SCOPES', 'openid profile email User.Read offline_access api://737740ef-8ab9-44eb-8570-5e3027ddf207/access_as_user');

/**
 * Erstellt die Authorisierungs-URL und speichert den state in der Session.
 * @return string Die URL, zu der der Benutzer umgeleitet werden soll.
 */
function getAuthorizationUrl() {
    // Startet die Session mit den sicheren, zentral definierten Einstellungen.
    start_secure_session();

    // Erstellt einen zufälligen, kryptographisch sicheren 'state'-Parameter für CSRF-Schutz.
    $state = bin2hex(random_bytes(32));
    $_SESSION['oauth2state'] = $state;

    $queryParams = http_build_query([
        'client_id' => OAUTH_CLIENT_ID,
        'response_type' => 'code',
        'redirect_uri' => OAUTH_REDIRECT_URI,
        'response_mode' => 'query',
        'scope' => OAUTH_SCOPES,
        'state' => $state
    ]);

    return OAUTH_AUTHORIZE_ENDPOINT . '?' . $queryParams;
}

/**
 * Tauscht einen Authorisierungscode gegen Access- und Refresh-Tokens.
 * @param string $authCode Der von Azure AD erhaltene Authorisierungscode.
 * @return array Die Tokens vom Auth-Server.
 * @throws Exception Wenn der Token-Austausch fehlschlägt.
 */
function getTokensFromCode($authCode) {
    $ch = curl_init(OAUTH_TOKEN_ENDPOINT);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'client_id' => OAUTH_CLIENT_ID,
        'scope' => OAUTH_SCOPES,
        'code' => $authCode,
        'redirect_uri' => OAUTH_REDIRECT_URI,
        'grant_type' => 'authorization_code',
        'client_secret' => OAUTH_CLIENT_SECRET
    ]));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL Error: " . $error_msg);
    }
    
    curl_close($ch);

    if ($httpCode >= 400) {
        throw new Exception("Token endpoint returned status {$httpCode}: " . $response);
    }
    
    $tokens = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($tokens['access_token'])) {
        throw new Exception("Failed to decode token response or access_token is missing.");
    }
    
    return $tokens;
}
?>