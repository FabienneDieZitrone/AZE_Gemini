<?php
/**
 * Titel: Hilfsfunktionen für die API
 * Version: 7.0 (FINAL & CORRECTED - Korrekter Cookie-Pfad)
 * Autor: MP-IT
 * Datei: /api/auth_helpers.php
 * Beschreibung: Implementiert die finale, korrekte Session-Konfiguration mittels session_set_cookie_params, um den Cookie-Pfad explizit auf '/' zu setzen.
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
        'lifetime' => 0, // Session-Cookie, läuft ab, wenn der Browser geschlossen wird.
        'path' => '/',   // WICHTIG: Setzt den Pfad auf die Wurzel der Domain.
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => true,      // Nur über HTTPS senden.
        'httponly' => true,    // Für JavaScript unzugänglich machen.
        'samesite' => 'Lax'    // Moderner Standard, der für OAuth-Redirects geeignet ist.
    ]);
    
    session_start();
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
    
    if (isset($_SESSION['user']) && !empty($_SESSION['user']['oid'])) {
        return $_SESSION['user'];
    } else {
        send_response(401, ['message' => 'Unauthorized: No valid session found.']);
    }
}
?>