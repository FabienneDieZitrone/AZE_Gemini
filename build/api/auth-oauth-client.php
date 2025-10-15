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

// Load config.php and ensure .env is loaded
$configLoaded = false;
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
    $configLoaded = class_exists('Config');
}

// If Config class failed to load or .env wasn't loaded, load .env directly
if (!$configLoaded || empty($_ENV['OAUTH_CLIENT_ID'])) {
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Remove quotes if present
            if (preg_match('/^"(.*)"$/', $value, $matches) || preg_match("/^'(.*)'$/", $value, $matches)) {
                $value = $matches[1];
            }
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
            }
        }
    }
}

// --- SICHERE KONFIGURATION ---
// Lade Client Secret aus sicherer Konfiguration
if ($configLoaded) {
    $config = Config::load();
    $clientSecret = Config::get('oauth.client_secret');
} else {
    $clientSecret = $_ENV['OAUTH_CLIENT_SECRET'] ?? null;
}

// Additional fallback for direct .env parsing
if (empty($clientSecret)) {
    $clientSecret = $_ENV['OAUTH_CLIENT_SECRET'] ?? null;
}

// SICHERHEITSPRÜFUNG: Keine unsicheren Fallbacks!
if (empty($clientSecret) || $clientSecret === 'your_azure_client_secret_here') {
    error_log('CRITICAL: OAuth Client Secret not configured or using placeholder');
    http_response_code(500);
    die('OAuth configuration error. Please configure OAUTH_CLIENT_SECRET in environment variables.');
}

// SECURITY FIX: Load OAuth configuration from environment variables with proper fallbacks
if ($configLoaded) {
    $oauthClientId = Config::get('oauth.client_id') ?: ($_ENV['OAUTH_CLIENT_ID'] ?? null);
    $oauthTenantId = Config::get('oauth.tenant_id') ?: ($_ENV['OAUTH_TENANT_ID'] ?? null);
    $oauthRedirectUri = Config::get('oauth.redirect_uri') ?: ($_ENV['OAUTH_REDIRECT_URI'] ?? 'https://aze.mikropartner.de/api/auth-callback.php');
} else {
    $oauthClientId = $_ENV['OAUTH_CLIENT_ID'] ?? null;
    $oauthTenantId = $_ENV['OAUTH_TENANT_ID'] ?? null;
    $oauthRedirectUri = $_ENV['OAUTH_REDIRECT_URI'] ?? 'https://aze.mikropartner.de/api/auth-callback.php';
}

// Validate OAuth configuration
if (empty($oauthClientId) || empty($oauthTenantId)) {
    error_log('CRITICAL: OAuth Client ID or Tenant ID not configured');
    error_log('DEBUG: OAUTH_CLIENT_ID = ' . var_export($oauthClientId, true));
    error_log('DEBUG: OAUTH_TENANT_ID = ' . var_export($oauthTenantId, true));
    error_log('DEBUG: Config loaded = ' . var_export($configLoaded, true));
    error_log('DEBUG: ENV keys = ' . implode(', ', array_keys($_ENV)));
    http_response_code(500);
    die('OAuth configuration error. Please configure OAUTH_CLIENT_ID and OAUTH_TENANT_ID in environment variables.');
}

define('OAUTH_CLIENT_ID', $oauthClientId);
define('OAUTH_CLIENT_SECRET', $clientSecret);
define('OAUTH_TENANT_ID', $oauthTenantId);
define('OAUTH_REDIRECT_URI', $oauthRedirectUri); // Muss exakt mit der URI in Azure übereinstimmen

define('OAUTH_AUTHORITY', 'https://login.microsoftonline.com/' . OAUTH_TENANT_ID);
define('OAUTH_AUTHORIZE_ENDPOINT', OAUTH_AUTHORITY . '/oauth2/v2.0/authorize');
define('OAUTH_TOKEN_ENDPOINT', OAUTH_AUTHORITY . '/oauth2/v2.0/token');

// Scopes, die von der Anwendung angefordert werden. 'offline_access' ist für Refresh-Tokens.
// API-Scope dynamisch bestimmen: bevorzugt aus Konfiguration/ENV, ansonsten Fallback auf api://{client-id}
if ($configLoaded) {
    $apiAppIdUri = Config::get('oauth.api_app_id_uri') ?: ($_ENV['OAUTH_API_APP_ID_URI'] ?? null);
} else {
    $apiAppIdUri = $_ENV['OAUTH_API_APP_ID_URI'] ?? null;
}
if (!$apiAppIdUri) {
    $apiAppIdUri = 'api://' . OAUTH_CLIENT_ID;
}
define('OAUTH_SCOPES', 'openid profile email User.Read offline_access ' . $apiAppIdUri . '/access_as_user');

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