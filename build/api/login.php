<?php
/**
 * Login Endpoint (stabile, erweiterte Variante)
 * - Liefert vollständige Initialdaten (Users, MasterData, TimeEntries, Approvals, History, GlobalSettings)
 * - Robuste Fehlerbehandlung: einzelne Query-Fehler führen NICHT zu 500, sondern zu leeren Teillisten
 * - Vereinheitlichte Security: nutzt security-middleware anstatt security-headers
 */

// CRITICAL: Start output buffering IMMEDIATELY to prevent any output before headers
ob_start();

// CRITICAL: Set session name BEFORE ANY OTHER CODE (even before require)
session_name('AZE_SESSION');

define('API_GUARD', true);

require_once __DIR__ . '/security-middleware.php';
require_once __DIR__ . '/structured-logger.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/DatabaseConnection.php';

// Einheitlicher Security-Bootstrap (CORS, Security Headers, OPTIONS)
initialize_api();
initSecurityMiddleware();

// Lightweight diagnostics (GET ?diag=1): no sensitive data, helps verify live wiring
// Accept diag via GET ?diag=1 or ?action=diag, or via POST X-Diag: 1 / JSON { diag: 1 }
$__method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$__is_diag = false;
if ($__method === 'GET' && (isset($_GET['diag']) || (isset($_GET['action']) && $_GET['action'] === 'diag'))) {
    $__is_diag = true;
} else if ($__method === 'POST') {
    $hdrs = function_exists('getallheaders') ? array_change_key_case(getallheaders(), CASE_LOWER) : [];
    if (!empty($hdrs['x-diag']) && (string)$hdrs['x-diag'] === '1') { $__is_diag = true; }
    if (!$__is_diag) {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $jd = json_decode($raw, true);
            if (is_array($jd) && (!empty($jd['diag']) || (isset($jd['action']) && $jd['action'] === 'diag'))) {
                $__is_diag = true;
            }
        }
    }
}
if ($__is_diag) {
    header('Content-Type: application/json; charset=utf-8');
    $out = [
        'endpoint' => 'login.php?diag=1',
        'php' => PHP_VERSION,
        'app_env' => getenv('APP_ENV') ?: 'unknown',
        'session' => [
            'active' => session_status() === PHP_SESSION_ACTIVE,
            'has_user' => isset($_SESSION['user']),
        ],
        'db' => [ 'connected' => false ],
        'counts' => [ 'users' => null, 'time_entries' => null, 'approval_requests' => null ],
        'global_settings' => [ 'exists' => false, 'locations_count' => null ],
    ];
    try {
        $db = DatabaseConnection::getInstance()->getConnection();
        $out['db']['connected'] = @$db->ping();
        // Counts
        if ($st = $db->prepare('SELECT COUNT(*) FROM users')) { $st->execute(); $st->bind_result($c); if ($st->fetch()) { $out['counts']['users'] = (int)$c; } $st->close(); }
        if ($st = $db->prepare('SELECT COUNT(*) FROM time_entries')) { $st->execute(); $st->bind_result($c); if ($st->fetch()) { $out['counts']['time_entries'] = (int)$c; } $st->close(); }
        if ($st = $db->prepare('SELECT COUNT(*) FROM approval_requests')) { $st->execute(); $st->bind_result($c); if ($st->fetch()) { $out['counts']['approval_requests'] = (int)$c; } $st->close(); }
        // Global settings
        if ($st = $db->prepare('SHOW TABLES LIKE "global_settings"')) { $st->execute(); $res = $st->get_result(); $exists = $res && $res->num_rows > 0; $out['global_settings']['exists'] = $exists; $st->close();
            if ($exists) {
                if ($gs = $db->prepare('SELECT locations FROM global_settings WHERE id = 1')) { $gs->execute(); $gs->bind_result($locs); if ($gs->fetch()) { $arr = json_decode((string)$locs, true) ?: []; $out['global_settings']['locations_count'] = is_array($arr) ? count($arr) : 0; } $gs->close(); }
            }
        }
    } catch (Throwable $e) {
        $out['diag_error'] = $e->getMessage();
    }
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Erlaube ausschließlich POST für den Login-Payload
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

// Wrapper-Funktionen für frühere db-wrapper-Kompatibilität
if (!function_exists('initDB')) {
    function initDB() {
        return DatabaseConnection::getInstance()->getConnection(); // returns mysqli
    }
}
if (!function_exists('executeQuery')) {
    function executeQuery($sql, $types = '', $params = []) {
        // Liefert ein ausgeführtes mysqli_stmt zurück (kompatibel zum bisherigen Code)
        return DatabaseConnection::getInstance()->prepareAndExecute($sql, $types, $params);
    }
}

try {
    // Session already started at line 10 with AZE_SESSION name
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['oid'])) {
        http_response_code(401);
        echo json_encode(['message' => 'Unauthorized: No valid session found.']);
        exit();
    }

    $user_session = $_SESSION['user'];
    $azure_oid = $user_session['oid'];
    $username = trim($user_session['username'] ?? '');
    $display_name = $user_session['name'] ?? '';

    // DB initialisieren (robust)
    try {
        $db = initDB(); // mysqli
    } catch (Throwable $e) {
        // Fallback: direkte Verbindung aus .env/Config (extreme Edge-Case)
        if (!class_exists('Config')) { @include_once __DIR__ . '/../config.php'; }
        $host = (class_exists('Config') ? Config::get('db.host') : ($_ENV['DB_HOST'] ?? ''));
        $user = (class_exists('Config') ? (Config::get('db.username') ?: Config::get('db.user')) : ($_ENV['DB_USERNAME'] ?? $_ENV['DB_USER'] ?? ''));
        $pass = (class_exists('Config') ? (Config::get('db.password') ?: Config::get('db.pass')) : ($_ENV['DB_PASSWORD'] ?? $_ENV['DB_PASS'] ?? ''));
        $name = (class_exists('Config') ? Config::get('db.name') : ($_ENV['DB_NAME'] ?? ''));
        $db = @new mysqli($host, $user, $pass, $name);
        if ($db && !$db->connect_error) {
            $db->set_charset('utf8mb4');
        } else {
            logError('db_init_failed', ['msg' => $e->getMessage(), 'host' => $host]);
            http_response_code(500);
            echo json_encode(['message' => 'Login failed']);
            exit();
        }
    }

    // Benutzer laden oder anlegen (robust, mit Fallback ohne 500)
    $current_user_id = null;
    $user_role = 'Mitarbeiter';
    $user_display_name = $display_name;
    try {
        $stmt = executeQuery("SELECT id, username, display_name, role FROM users WHERE azure_oid = ?", 's', [$azure_oid]);
        $res = $stmt->get_result();
        if ($res && ($row = $res->fetch_assoc())) {
            $current_user_id = (int)$row['id'];
            $user_role = $row['role'] ?: 'Mitarbeiter';
            $user_display_name = $row['display_name'] ?: $display_name;
        } else {
            $ins = executeQuery("INSERT INTO users (username, display_name, role, azure_oid, created_at) VALUES (?, ?, 'Mitarbeiter', ?, NOW())", 'sss', [$username, $display_name, $azure_oid]);
            $current_user_id = $db->insert_id;
            if (method_exists($ins, 'close')) { $ins->close(); }
        }
        if (method_exists($stmt, 'close')) { $stmt->close(); }
    } catch (Throwable $e) {
        logError('user_sync_failed_executeQuery', ['msg' => $e->getMessage()]);
        try {
            $sel = $db->prepare("SELECT id, username, display_name, role FROM users WHERE azure_oid = ?");
            if (!$sel) { throw new Exception('mysqli_prepare(select) failed: ' . $db->error); }
            $sel->bind_param('s', $azure_oid);
            $sel->execute();
            $res = $sel->get_result();
            if ($res && ($row = $res->fetch_assoc())) {
                $current_user_id = (int)$row['id'];
                $user_role = $row['role'] ?: 'Mitarbeiter';
                $user_display_name = $row['display_name'] ?: $display_name;
            } else {
                $ins = $db->prepare("INSERT INTO users (username, display_name, role, azure_oid, created_at) VALUES (?, ?, 'Mitarbeiter', ?, NOW())");
                if (!$ins) { throw new Exception('mysqli_prepare(insert) failed: ' . $db->error); }
                $ins->bind_param('sss', $username, $display_name, $azure_oid);
                $ins->execute();
                $current_user_id = (int)$db->insert_id;
                $ins->close();
            }
            $sel->close();
        } catch (Throwable $e2) {
            // Minimaler Fallback statt 500
            logError('user_sync_hard_failed', ['msg' => $e2->getMessage()]);
            $current_user_id = 0;
            $user_role = 'Mitarbeiter';
            $user_display_name = $display_name;
        }
    }

    $response = [
        'currentUser' => [
            'id' => $current_user_id,
            'name' => $user_display_name,
            'role' => $user_role,
            'azureOid' => $azure_oid
        ],
        'users' => [],
        'masterData' => new stdClass(),
        'timeEntries' => [],
        'approvalRequests' => [],
        'history' => [],
        // Default-Werte; werden unten aus DB überschrieben, falls vorhanden
        'globalSettings' => [
            'overtimeThreshold' => 8.0,
            'changeReasons' => ['Vergessen einzustempeln','Vergessen auszustempeln','Dienstgang','Arzttermin','Technische Störung','Sonstige'],
            'locations' => ['Zentrale Berlin','Standort Hamburg','Standort Köln']
        ]
    ];

    // Benutzerliste (direkte mysqli-Nutzung, stabil)
    try {
        $sql = "SELECT id, display_name AS name, role, azure_oid AS azureOid FROM users";
        $s = $db->prepare($sql);
        if ($s && $s->execute()) {
            $r = $s->get_result();
            $response['users'] = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
            $s->close();
        } else {
            throw new Exception($db->error ?: ($s ? $s->error : 'prepare failed'));
        }
    } catch (Throwable $e) { logError('load_users_failed', ['msg' => $e->getMessage()]); }

    // Masterdaten
    try {
        $sql = "SELECT user_id, weekly_hours, workdays, can_work_from_home, flexible_workdays, daily_hours FROM master_data";
        $s = $db->prepare($sql);
        if ($s && $s->execute()) {
            $r = $s->get_result();
            $md = [];
            while ($row = $r->fetch_assoc()) {
                $md[(int)$row['user_id']] = [
                    'weeklyHours' => (float)$row['weekly_hours'],
                    'workdays' => json_decode($row['workdays'], true) ?: [],
                    'canWorkFromHome' => (bool)$row['can_work_from_home'],
                    'flexibleWorkdays' => (bool)($row['flexible_workdays'] ?? 0),
                    'dailyHours' => json_decode($row['daily_hours'] ?? 'null', true) ?: null,
                ];
            }
            $response['masterData'] = $md;
            $s->close();
        } else {
            throw new Exception($db->error ?: ($s ? $s->error : 'prepare failed'));
        }
    } catch (Throwable $e) { logError('load_masterdata_failed', ['msg' => $e->getMessage()]); }

    // Globale Einstellungen (falls in DB vorhanden)
    try {
        $sql = "SELECT overtime_threshold, change_reasons, locations FROM global_settings WHERE id = 1";
        $s = $db->prepare($sql);
        if ($s && $s->execute()) {
            $s->bind_result($thr, $reasons, $locations);
            if ($s->fetch()) {
                $response['globalSettings'] = [
                    'overtimeThreshold' => (float)$thr,
                    'changeReasons' => json_decode((string)$reasons, true) ?: $response['globalSettings']['changeReasons'],
                    'locations' => json_decode((string)$locations, true) ?: $response['globalSettings']['locations']
                ];
            }
            $s->close();
        } else {
            throw new Exception($db->error ?: ($s ? $s->error : 'prepare failed'));
        }
    } catch (Throwable $e) { logError('load_global_settings_failed', ['msg' => $e->getMessage()]); }

    // Zeiteinträge
    try {
        $sql = "SELECT id, user_id AS userId, username, date, start_time AS startTime, stop_time AS stopTime, location, role, created_at AS createdAt, updated_by AS updatedBy, updated_at AS updatedAt FROM time_entries ORDER BY date DESC, start_time DESC";
        $s = $db->prepare($sql);
        if ($s && $s->execute()) {
            $r = $s->get_result();
            $response['timeEntries'] = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
            $s->close();
        } else {
            throw new Exception($db->error ?: ($s ? $s->error : 'prepare failed'));
        }
    } catch (Throwable $e) { logError('load_time_entries_failed', ['msg' => $e->getMessage()]); }

    // Pending Genehmigungen (rollenbasiert), robustes ORDER BY ohne created_at
    try {
        // Spalten in approval_requests ermitteln
        $cols = [];
        if ($res = $db->query("SHOW COLUMNS FROM approval_requests")) {
            while ($rr = $res->fetch_assoc()) { $cols[strtolower($rr['Field'])] = true; }
            $res->close();
        }
        $hasRequestedAt = !empty($cols['requested_at']);
        $orderBy = $hasRequestedAt ? 'ORDER BY requested_at DESC' : 'ORDER BY id DESC';

        $role = $user_role;
        if ($role === 'Honorarkraft' || $role === 'Mitarbeiter') {
            $sql = "SELECT id, type, original_entry_data, new_data, reason_data, requested_by, status FROM approval_requests WHERE (status IS NULL OR LOWER(status)='pending') AND requested_by = ? $orderBy";
            $s = $db->prepare($sql);
            $s->bind_param('s', $username);
            $s->execute();
        } else if ($role === 'Standortleiter') {
            $loc = $response['currentUser']['location'] ?? '';
            // Versuche COALESCE(new_data.location, original.location), sonst Fallback nur original
            $sql1 = "SELECT id, type, original_entry_data, new_data, reason_data, requested_by, status FROM approval_requests WHERE (status IS NULL OR LOWER(status)='pending') AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(new_data, '$.location')), JSON_UNQUOTE(JSON_EXTRACT(original_entry_data, '$.location'))) = ? $orderBy";
            $s = $db->prepare($sql1);
            if (!$s) {
                logError('load_approvals_role_fallback', ['msg' => $db->error]);
                $sql2 = "SELECT id, type, original_entry_data, new_data, reason_data, requested_by, status FROM approval_requests WHERE (status IS NULL OR LOWER(status)='pending') AND JSON_EXTRACT(original_entry_data, '$.location') = ? $orderBy";
                $s = $db->prepare($sql2);
            }
            $s->bind_param('s', $loc);
            $s->execute();
        } else {
            $sql = "SELECT id, type, original_entry_data, new_data, reason_data, requested_by, status FROM approval_requests WHERE (status IS NULL OR LOWER(status)='pending') $orderBy";
            $s = $db->prepare($sql);
            $s->execute();
        }
        $r = $s->get_result();
        $rows = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
        $s->close();
        $response['approvalRequests'] = array_map(function($req){
            $orig = json_decode($req['original_entry_data'] ?? '[]', true) ?: [];
            return [
                'id' => $req['id'],
                'type' => $req['type'],
                'entry' => [
                    'id' => (int)($orig['id'] ?? 0),
                    'userId' => (int)($orig['user_id'] ?? 0),
                    'username' => $orig['username'] ?? '',
                    'date' => $orig['date'] ?? '',
                    'startTime' => $orig['start_time'] ?? '',
                    'stopTime' => $orig['stop_time'] ?? '',
                    'location' => $orig['location'] ?? '',
                    'role' => $orig['role'] ?? 'Mitarbeiter',
                    'createdAt' => $orig['created_at'] ?? '',
                    'updatedBy' => $orig['updated_by'] ?? '',
                    'updatedAt' => $orig['updated_at'] ?? '',
                ],
                'newData' => json_decode($req['new_data'] ?? 'null', true),
                'reasonData' => json_decode($req['reason_data'] ?? 'null', true),
                'requestedBy' => $req['requested_by'],
                'status' => 'pending',
            ];
        }, $rows);
    } catch (Throwable $e) { logError('load_approvals_failed', ['msg' => $e->getMessage()]); }

    // Historie – robustes ORDER BY ohne created_at
    try {
        $cols2 = [];
        if ($res2 = $db->query("SHOW COLUMNS FROM approval_requests")) {
            while ($rr2 = $res2->fetch_assoc()) { $cols2[strtolower($rr2['Field'])] = true; }
            $res2->close();
        }
        $hasResolvedAt = !empty($cols2['resolved_at']);
        $orderByHist = $hasResolvedAt ? 'ORDER BY resolved_at DESC' : 'ORDER BY id DESC';

        if ($user_role === 'Honorarkraft' || $user_role === 'Mitarbeiter') {
            $sql = "SELECT * FROM approval_requests WHERE status IS NOT NULL AND LOWER(status) != 'pending' AND requested_by = ? $orderByHist";
            $s = $db->prepare($sql);
            $s->bind_param('s', $username);
            $s->execute();
        } else if ($user_role === 'Standortleiter') {
            $loc = $response['currentUser']['location'] ?? '';
            $sql1 = "SELECT * FROM approval_requests WHERE status IS NOT NULL AND LOWER(status) != 'pending' AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(new_data, '$.location')), JSON_UNQUOTE(JSON_EXTRACT(original_entry_data, '$.location'))) = ? $orderByHist";
            $s = $db->prepare($sql1);
            if (!$s) {
                logError('load_history_role_fallback', ['msg' => $db->error]);
                $sql2 = "SELECT * FROM approval_requests WHERE status IS NOT NULL AND LOWER(status) != 'pending' AND JSON_EXTRACT(original_entry_data, '$.location') = ? $orderByHist";
                $s = $db->prepare($sql2);
            }
            $s->bind_param('s', $loc);
            $s->execute();
        } else {
            $sql = "SELECT * FROM approval_requests WHERE status IS NOT NULL AND LOWER(status) != 'pending' $orderByHist";
            $s = $db->prepare($sql);
            $s->execute();
        }
        $r = $s->get_result();
        $hist = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
        $s->close();
        $response['history'] = array_map(function($row){
            $orig = json_decode($row['original_entry_data'] ?? '[]', true) ?: [];
            return [
                'id' => $row['id'],
                'type' => $row['type'],
                'entry' => [
                    'id' => (int)($orig['id'] ?? 0),
                    'userId' => (int)($orig['user_id'] ?? 0),
                    'username' => $orig['username'] ?? '',
                    'date' => $orig['date'] ?? '',
                    'startTime' => $orig['start_time'] ?? '',
                    'stopTime' => $orig['stop_time'] ?? '',
                    'location' => $orig['location'] ?? '',
                    'role' => $orig['role'] ?? 'Mitarbeiter',
                    'createdAt' => $orig['created_at'] ?? '',
                    'updatedBy' => $orig['updated_by'] ?? '',
                    'updatedAt' => $orig['updated_at'] ?? '',
                ],
                'newData' => json_decode($row['new_data'] ?? 'null', true),
                'reasonData' => json_decode($row['reason_data'] ?? 'null', true),
                'requestedBy' => $row['requested_by'],
                'finalStatus' => $row['status'],
                'resolvedAt' => $row['resolved_at'],
                'resolvedBy' => $row['resolved_by'],
            ];
        }, $hist);
    } catch (Throwable $e) { logError('load_history_failed', ['msg' => $e->getMessage()]); }

    echo json_encode($response);
    exit();

} catch (Throwable $e) {
    logError('Login error', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    http_response_code(500);
    echo json_encode(['message' => 'Login failed']);
}

?>
