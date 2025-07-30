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
    // Die Herkunft wird nun dynamisch ermittelt, um lokale Entwicklung und Produktion zu unterstützen.
    $origin = $_SERVER['HTTP_ORIGIN'] ?? 'https://aze.mikropartner.de';
    $allowed_origins = ['https://aze.mikropartner.de']; // Fügen Sie hier bei Bedarf lokale Entwicklungsumgebungen hinzu
    
    if (in_array($origin, $allowed_origins)) {
        header("Access-Control-Allow-Origin: " . $origin);
    }
    
    header("Access-Control-Allow-Credentials: true");
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
    header("Access-Control-Max-Age: 3600");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        exit(0);
    }
}

/**
 * Sendet eine JSON-Antwort mit dem entsprechenden HTTP-Statuscode und beendet das Skript.
 */
function send_response($status_code, $data = null) {
    // Verhindere, dass nach dem Senden der Antwort noch etwas passiert.
    if (headers_sent()) {
        error_log("Attempted to send response, but headers already sent.");
        return;
    }
    http_response_code($status_code);
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
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    
    // FINALE KORREKTUR: Verwende die offizielle PHP-Methode, um die Cookie-Parameter
    // VOR dem Starten der Session zu setzen.
    session_set_cookie_params([
        'lifetime' => 0, // CRITICAL FIX: Browser-Session (läuft ab beim Browser-Schließen)
        'path' => '/',   // WICHTIG: Setzt den Pfad auf die Wurzel der Domain.
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => true,      // Nur über HTTPS senden.
        'httponly' => true,    // Für JavaScript unzugänglich machen.
        'samesite' => 'Lax' // CRITICAL FIX: Lax für OAuth-Redirects (Strict blockiert)
    ]);
    
    session_start();
    
    // Initialisiere Session-Zeitstempel beim ersten Start
    if (!isset($_SESSION['created_at'])) {
        $_SESSION['created_at'] = time();
        $_SESSION['last_activity'] = time();
    }
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
    $absolute_timeout = 86400; // 24 Stunden (24 * 60 * 60)
    $inactivity_timeout = 3600; // 1 Stunde (60 * 60)
    
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
    
    // Überprüfe Session-Timeout mit der neuen Funktion
    if (!checkSessionTimeout()) {
        // Session ist abgelaufen - komplett zerstören
        destroy_session_completely();
        send_response(401, ['message' => 'Unauthorized: Session expired. Please login again.']);
        return;
    }
    
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
?>