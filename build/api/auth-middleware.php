<?php
/**
 * Titel: Zentrales Autorisierungs-Middleware
 * Version: 1.0
 * Datum: 2025-08-05
 * Autor: Security Update
 * Beschreibung: Zentrale Middleware für rollenbasierte Zugriffskontrolle (RBAC)
 */

require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/db.php';

/**
 * Definiert die Berechtigungsmatrix für alle API-Endpoints
 * Format: 'endpoint' => ['erlaubte_rollen']
 */
const ENDPOINT_PERMISSIONS = [
    // Öffentliche Endpoints (keine Rollenprüfung)
    'auth-status.php' => null,
    'login.php' => null,
    'auth-callback.php' => null,
    'auth-logout.php' => null,
    'csrf-protection.php' => null,
    
    // Benutzer-Management (nur Admin)
    'users.php' => [
        'GET' => ['Admin', 'Bereichsleiter', 'Standortleiter', 'Mitarbeiter', 'Honorarkraft'],
        'PATCH' => ['Admin'] // Nur Admin kann Rollen ändern
    ],
    
    // Zeiterfassung (rollenbasierter Zugriff)
    'time-entries.php' => [
        'GET' => ['Admin', 'Bereichsleiter', 'Standortleiter', 'Mitarbeiter', 'Honorarkraft'],
        'POST' => ['Admin', 'Bereichsleiter', 'Standortleiter', 'Mitarbeiter', 'Honorarkraft'],
        'PUT' => ['Admin', 'Bereichsleiter', 'Standortleiter', 'Mitarbeiter', 'Honorarkraft'],
        'DELETE' => ['Admin', 'Bereichsleiter', 'Standortleiter']
    ],
    
    // Genehmigungen (rollenbasiert)
    'approvals.php' => [
        'GET' => ['Admin', 'Bereichsleiter', 'Standortleiter', 'Mitarbeiter', 'Honorarkraft'],
        'POST' => ['Admin', 'Bereichsleiter', 'Standortleiter'],
        'PUT' => ['Admin', 'Bereichsleiter', 'Standortleiter']
    ],
    
    // Stammdaten (Admin und Bereichsleiter)
    'masterdata.php' => [
        'GET' => ['Admin', 'Bereichsleiter', 'Standortleiter'],
        'PUT' => ['Admin', 'Bereichsleiter']
    ],
    
    // Globale Einstellungen (nur Admin)
    'settings.php' => [
        'GET' => ['Admin', 'Bereichsleiter', 'Standortleiter', 'Mitarbeiter', 'Honorarkraft'],
        'PUT' => ['Admin']
    ],
    
    // Historie (alle können lesen)
    'history.php' => [
        'GET' => ['Admin', 'Bereichsleiter', 'Standortleiter', 'Mitarbeiter', 'Honorarkraft']
    ],
    
    // Logs (nur Admin)
    'logs.php' => [
        'GET' => ['Admin'],
        'POST' => ['Admin', 'Bereichsleiter', 'Standortleiter', 'Mitarbeiter', 'Honorarkraft'] // Alle können Fehler loggen
    ],
    
    // MFA (alle authentifizierten Benutzer)
    'mfa/setup.php' => ['Admin', 'Bereichsleiter', 'Standortleiter', 'Mitarbeiter', 'Honorarkraft'],
    'mfa/verify.php' => ['Admin', 'Bereichsleiter', 'Standortleiter', 'Mitarbeiter', 'Honorarkraft']
];

/**
 * Prüft, ob der aktuelle Benutzer berechtigt ist, auf den Endpoint zuzugreifen
 * 
 * @param array $user Der Benutzer aus der Session
 * @param string $endpoint Der aufgerufene Endpoint
 * @param string $method Die HTTP-Methode
 * @return bool True wenn berechtigt, sonst false
 */
function checkEndpointPermission($user, $endpoint, $method) {
    // Extrahiere nur den Dateinamen aus dem Pfad
    $endpoint_file = basename($endpoint);
    
    // Prüfe ob Endpoint in der Berechtigungsmatrix existiert
    if (!isset(ENDPOINT_PERMISSIONS[$endpoint_file])) {
        // Unbekannter Endpoint - Zugriff verweigern (Whitelist-Ansatz)
        error_log("Authorization: Unknown endpoint accessed: $endpoint_file");
        return false;
    }
    
    $permissions = ENDPOINT_PERMISSIONS[$endpoint_file];
    
    // Null bedeutet öffentlicher Endpoint
    if ($permissions === null) {
        return true;
    }
    
    // Array bedeutet methodenbasierte Berechtigungen
    if (is_array($permissions) && isset($permissions[$method])) {
        $allowed_roles = $permissions[$method];
    } elseif (is_array($permissions) && !isset($permissions[$method])) {
        // Methode nicht definiert - Zugriff verweigern
        error_log("Authorization: Method $method not allowed for endpoint $endpoint_file");
        return false;
    } else {
        // Einfaches Array von Rollen
        $allowed_roles = $permissions;
    }
    
    // Prüfe ob Benutzerrolle berechtigt ist
    if (!isset($user['role'])) {
        error_log("Authorization: User has no role defined");
        return false;
    }
    
    if (!in_array($user['role'], $allowed_roles)) {
        error_log("Authorization: User role '{$user['role']}' not allowed for $method $endpoint_file");
        return false;
    }
    
    return true;
}

/**
 * Hauptfunktion zur Autorisierungsprüfung
 * Sollte am Anfang jedes geschützten Endpoints aufgerufen werden
 * 
 * @return array Der autorisierte Benutzer aus der Datenbank
 */
function authorize_request() {
    // Hole Benutzer aus Session
    $session_user = verify_session_and_get_user();
    
    // Hole aktuelle Endpoint und Methode
    $endpoint = $_SERVER['SCRIPT_NAME'];
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Hole vollständige Benutzerdaten aus Datenbank (mit aktueller Rolle)
    global $conn;
    $stmt = $conn->prepare("SELECT id, username, display_name, role, azure_oid FROM users WHERE azure_oid = ?");
    if (!$stmt) {
        error_log("Authorization: Database prepare failed: " . $conn->error);
        send_response(500, ['message' => 'Internal server error']);
    }
    
    $stmt->bind_param("s", $session_user['oid']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        error_log("Authorization: User not found in database for OID: " . $session_user['oid']);
        send_response(401, ['message' => 'User not found']);
    }
    
    $db_user = $result->fetch_assoc();
    $stmt->close();
    
    // Aktualisiere Session mit aktueller Rolle aus DB
    $_SESSION['user']['role'] = $db_user['role'];
    $_SESSION['user']['id'] = $db_user['id'];
    $_SESSION['user']['username'] = $db_user['username'];
    
    // Prüfe Endpoint-Berechtigung
    if (!checkEndpointPermission($db_user, $endpoint, $method)) {
        send_response(403, [
            'message' => 'Forbidden: You do not have permission to access this resource',
            'required_role' => 'Contact administrator for access',
            'your_role' => $db_user['role']
        ]);
    }
    
    // Log erfolgreiche Autorisierung
    error_log("Authorization: User '{$db_user['username']}' ({$db_user['role']}) authorized for $method $endpoint");
    
    return $db_user;
}

/**
 * Hilfsfunktion: Prüft ob Benutzer eine bestimmte Rolle hat
 * 
 * @param array $user Der Benutzer
 * @param string $required_role Die erforderliche Rolle
 * @return bool
 */
function userHasRole($user, $required_role) {
    return isset($user['role']) && $user['role'] === $required_role;
}

/**
 * Hilfsfunktion: Prüft ob Benutzer eine der angegebenen Rollen hat
 * 
 * @param array $user Der Benutzer
 * @param array $allowed_roles Array erlaubter Rollen
 * @return bool
 */
function userHasAnyRole($user, $allowed_roles) {
    return isset($user['role']) && in_array($user['role'], $allowed_roles);
}

/**
 * Hilfsfunktion: Prüft ob Benutzer Admin ist
 * 
 * @param array $user Der Benutzer
 * @return bool
 */
function userIsAdmin($user) {
    return userHasRole($user, 'Admin');
}

/**
 * Hilfsfunktion: Prüft ob Benutzer mindestens Standortleiter ist
 * 
 * @param array $user Der Benutzer
 * @return bool
 */
function userIsAtLeastStandortleiter($user) {
    return userHasAnyRole($user, ['Admin', 'Bereichsleiter', 'Standortleiter']);
}