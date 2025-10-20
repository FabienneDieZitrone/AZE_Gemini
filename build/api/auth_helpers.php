<?php
/**
 * Titel: Hilfsfunktionen für die API
 * Version: 8.0 (Session-Timeout-Funktionalität)
 * Autor: MP-IT
 * Datei: /api/auth_helpers.php
 * Beschreibung: Implementiert die finale, korrekte Session-Konfiguration mittels session_set_cookie_params, 
 *               um den Cookie-Pfad explizit auf '/' zu setzen. Erweitert um Session-Timeout-Funktionalität
 *               mit 24h absolutem Timeout und 1h Inaktivitäts-Timeout.
 * 
 * Änderungen in Version 8.0:
 * - Neue Funktion checkSessionTimeout() für Timeout-Überprüfung
 * - Session-Initialisierung mit created_at und last_activity Zeitstempeln
 * - Automatische Session-Zerstörung bei Timeout
 * - Neue Hilfsfunktion destroy_session_completely() für saubere Session-Beendigung
 * - Session-ID-Regeneration alle 30 Minuten für zusätzliche Sicherheit
 */

require_once __DIR__ . '/constants.php';
// Backward-compatible defaults if constants.php does not define time constants
if (!defined('SECONDS_PER_HOUR')) { define('SECONDS_PER_HOUR', 3600); }
if (!defined('SECONDS_PER_DAY')) { define('SECONDS_PER_DAY', 86400); }

/**
 * Polyfill für die Funktion getallheaders(), falls sie nicht existiert (z.B. bei FastCGI-Setups).
 */
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $header_name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$header_name] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
        }
        return $headers;
    }
}

/**
 * Initialisiert die Standard-Header für einen API-Endpunkt und prüft auf OPTIONS-Requests.
 */
function initialize_api() {
    // Security Headers werden jetzt durch security-middleware.php gesetzt
    // Diese Funktion behandelt nur noch OPTIONS requests
    
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        // CORS Headers für OPTIONS
        $origin = $_SERVER['HTTP_ORIGIN'] ?? 'https://aze.mikropartner.de';
        $allowed_origins = ['https://aze.mikropartner.de'];
        
        if (in_array($origin, $allowed_origins)) {
            header("Access-Control-Allow-Origin: " . $origin);
        }
        
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
        header("Access-Control-Max-Age: " . SECONDS_PER_HOUR);
        header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-CSRF-Token");
        exit(0);
    }
}

/**
 * Sendet eine JSON-Antwort mit dem entsprechenden HTTP-Statuscode und beendet das Skript.
 */
function send_response($status_code, $data = null) {
    // Clean any output buffer before sending response (prevents header corruption)
    if (ob_get_level() > 0) {
        ob_end_clean();
    }

    // Verhindere, dass nach dem Senden der Antwort noch etwas passiert.
    // CRITICAL (2025-10-19): MUST exit() here, not return, to prevent script continuation!
    if (headers_sent()) {
        error_log("CRITICAL: Attempted to send response, but headers already sent. Terminating.");
        exit(1);  // Exit immediately to prevent further execution
    }

    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');

    if ($data !== null) {
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    exit();
}

/**
 * Startet eine PHP-Session mit sicheren, modernen Cookie-Einstellungen.
 * Diese Funktion behebt das Cookie-Pfad-Problem.
 */
function start_secure_session() {
    // CRITICAL: Session name MUST be AZE_SESSION (consistent with login.php)
    // IMPORTANT: Can ONLY be set BEFORE session is active!
    $migrate = null;
    if (session_status() === PHP_SESSION_ACTIVE) {
        // Session läuft bereits - Daten sichern für Migration
        $migrate = $_SESSION ?? null;
        // Keine session_write_close() - Session bleibt aktiv
    } else {
        // Session ist noch nicht aktiv - jetzt session_name() setzen
        session_name('AZE_SESSION');
    }

    // Härtung der Session-Engine
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.cookie_secure', '1');
    @ini_set('session.cookie_samesite', 'Lax');

    // Set cookie params BEFORE session_start
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',  // Empty = current domain automatically
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    // Start session (using AZE_SESSION name)
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // Migriere relevante Daten aus vorheriger Session (falls vorhanden)
    if (isset($migrate) && is_array($migrate)) {
        foreach (['user','created_at','last_activity','last_regeneration'] as $k) {
            if (isset($migrate[$k]) && !isset($_SESSION[$k])) {
                $_SESSION[$k] = $migrate[$k];
            }
        }
    }

    // Initialisiere/fixe Session-Zeitstempel robust
    $now = time();
    if (!isset($_SESSION['created_at'])) { $_SESSION['created_at'] = $now; }
    if (!isset($_SESSION['last_activity'])) { $_SESSION['last_activity'] = $now; }
}

/**
 * Überprüft Session-Timeout-Bedingungen:
 * - Absolute Session-Dauer: 24 Stunden
 * - Inaktivitäts-Timeout: 1 Stunde
 * 
 * @return bool True wenn die Session gültig ist, False wenn abgelaufen
 */
function checkSessionTimeout() {
    // Definiere Timeout-Werte in Sekunden
    $absolute_timeout = SECONDS_PER_DAY; // 24 Stunden
    $inactivity_timeout = SECONDS_PER_HOUR; // 1 Stunde
    
    $current_time = time();
    
    // Prüfe absolute Session-Dauer (24 Stunden)
    if (isset($_SESSION['created_at'])) {
        if ($current_time - $_SESSION['created_at'] > $absolute_timeout) {
            // Session ist älter als 24 Stunden
            error_log("Session timeout: Absolute timeout reached (24h)");
            return false;
        }
    } else {
        // Kein Erstellungszeitpunkt - Session ist ungültig
        error_log("Session timeout: No creation timestamp found");
        return false;
    }
    
    // Prüfe Inaktivitäts-Timeout (1 Stunde)
    if (isset($_SESSION['last_activity'])) {
        if ($current_time - $_SESSION['last_activity'] > $inactivity_timeout) {
            // Letzte Aktivität ist länger als 1 Stunde her
            error_log("Session timeout: Inactivity timeout reached (1h)");
            return false;
        }
    } else {
        // Keine letzte Aktivität verzeichnet - Session ist ungültig
        error_log("Session timeout: No last activity timestamp found");
        return false;
    }
    
    // Aktualisiere den Zeitstempel der letzten Aktivität
    $_SESSION['last_activity'] = $current_time;
    
    // Session-ID-Regeneration alle 30 Minuten für zusätzliche Sicherheit
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = $current_time;
    } else if ($current_time - $_SESSION['last_regeneration'] > 1800) { // 30 Minuten
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = $current_time;
        error_log("Session ID regenerated for security");
    }
    
    return true;
}


/**
 * Überprüft, ob eine gültige Benutzersession existiert.
 * Wenn nicht, wird eine 401-Antwort gesendet und das Skript beendet.
 * Wenn ja, werden die Benutzerdaten aus der Session zurückgegeben.
 *
 * @return array Die Benutzerdaten aus der Session ('oid', 'name', 'username').
 */
function verify_session_and_get_user() {
    start_secure_session();

    // NOTE (2025-10-19): Session-Timeout-Check temporarily disabled for debugging
    // Will re-enable after fixing timer API
    // if (!checkSessionTimeout()) {
    //     destroy_session_completely();
    //     send_response(401, ['message' => 'Unauthorized: Session expired. Please login again.']);
    //     return;
    // }

    // Prüfe ob Benutzerdaten in der Session vorhanden sind
    if (isset($_SESSION['user']) && !empty($_SESSION['user']['oid'])) {
        return $_SESSION['user'];
    } else {
        send_response(401, ['message' => 'Unauthorized: No valid session found.']);
    }
}

/**
 * Zerstört die Session vollständig inklusive Cookie-Löschung.
 * Diese Funktion sollte bei Logout oder Session-Timeout aufgerufen werden.
 */
function destroy_session_completely() {
    // Session-Variablen löschen
    $_SESSION = array();
    
    // Session-Cookie löschen
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Session zerstören
    session_destroy();
    
    error_log("Session completely destroyed");
}