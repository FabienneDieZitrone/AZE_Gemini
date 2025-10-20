<?php
/**
 * Time Entries API
 * Supports actions: start, stop, check_running
 *
 * FIX (2025-10-20): session_name() is set by time-entries.php (entry point)
 * FIX (2025-10-20): Removed closing PHP tag to prevent output before send_response()
 */

declare(strict_types=1);

// API_GUARD is already defined by time-entries.php (entry point)
// Only define if not already set (for standalone testing)
if (!defined('API_GUARD')) {
    define('API_GUARD', true);
}

require_once __DIR__ . '/security-middleware.php';
require_once __DIR__ . '/error-handler.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/csrf-middleware.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/InputValidationService.php';

// Frühdiagnose: Schreibe Fatals dieses Endpunkts immer in test.html
if (!function_exists('te_register_shutdown')) {
    function te_register_shutdown() {
        register_shutdown_function(function () {
            $err = error_get_last();
            if ($err) {
                $f = __DIR__ . '/test.html';
                $ts = date('Y-m-d H:i:s');
                $out = "<hr><b>[$ts] time-entries.php | fatal</b><br>" . htmlspecialchars(json_encode($err));
                @file_put_contents($f, $out . "\n", FILE_APPEND);
            }
        });
    }
    te_register_shutdown();
}

// Bootstrap-Log
$__te_boot = function($label){
    $f = __DIR__ . '/test.html';
    $ts = date('Y-m-d H:i:s');
    @file_put_contents($f, "<hr><b>[$ts] time-entries.php | $label</b><br>\n", FILE_APPEND);
};
$__te_boot('bootstrap');

// Hilfsfunktion global definiert: späte Definition innerhalb anderer Funktionen kann zu Fatal führen
if (!function_exists('resolveColumn')) {
    function resolveColumn(mysqli $conn, array $candidates): ?string {
        $names = [];
        if ($res = $conn->query("SHOW COLUMNS FROM time_entries")) {
            while ($r = $res->fetch_assoc()) { $names[strtolower($r['Field'])] = true; }
            $res->close();
        }
        foreach ($candidates as $c) { if (!empty($names[strtolower($c)])) return $c; }
        return null;
    }
}

initialize_api();
initSecurityMiddleware();

// lightweight debug logger to /api/debug-time-entries.log
function tlog($label, $data = null) {
    if ((getenv('APP_ENV') === 'production') && (!filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOLEAN))) { return; }
    $line = '[' . date('Y-m-d H:i:s') . "] time-entries.php | " . $label;
    if ($data !== null) {
        $line .= ' | ' . (is_string($data) ? $data : json_encode($data));
    }
    // Use absolute path relative to this script
    $logFile = __DIR__ . '/debug-time-entries.log';
    @file_put_contents($logFile, $line . "\n", FILE_APPEND);
}

// TEMP: HTML log to /api/test.html so you can view via browser
function hlog($title, $data = null) {
    if ((getenv('APP_ENV') === 'production') && (!filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOLEAN))) { return; }
    $f = __DIR__ . '/test.html';
    $ts = date('Y-m-d H:i:s');
    $out = "<hr><b>[$ts] $title</b><br>";
    if ($data !== null) {
        $payload = is_string($data) ? $data : json_encode($data);
        $out .= htmlspecialchars($payload);
    }
    @file_put_contents($f, $out . "\n", FILE_APPEND);
}

// Resolve authoritative user id from session (prefer Azure OID)
function resolveUserId(mysqli $conn, array $sessionUser): ?int {
    // Fast path: trust session numeric id if present
    if (isset($sessionUser['id']) && is_numeric($sessionUser['id'])) {
        $sid = (int)$sessionUser['id'];
        if ($sid > 0) { return $sid; }
    }
    $oid = $sessionUser['azure_oid'] ?? ($sessionUser['oid'] ?? null);
    $username = $sessionUser['username'] ?? ($sessionUser['name'] ?? null);
    // Try Azure OID first
    if (!empty($oid)) {
        if ($stmt = $conn->prepare("SELECT id FROM users WHERE azure_oid = ? LIMIT 1")) {
            $stmt->bind_param('s', $oid);
            if ($stmt->execute()) { $stmt->bind_result($rid); if ($stmt->fetch()) { $stmt->close(); return (int)$rid; } }
            $stmt->close();
        }
    }
    // Fallback by username/email
    if (!empty($username)) {
        if ($stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1")) {
            $stmt->bind_param('s', $username);
            if ($stmt->execute()) { $stmt->bind_result($rid); if ($stmt->fetch()) { $stmt->close(); return (int)$rid; } }
            $stmt->close();
        }
    }
    return null;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? null;
hlog('incoming', ['method'=>$method,'action'=>$action,'uri'=>($_SERVER['REQUEST_URI']??'')]);

// Ultra-early ping/diag (before any guards)
if (isset($_GET['ping'])) {
    header('Content-Type: application/json; charset=utf-8');
    hlog('ping');
    echo json_encode([
        'ping' => 'ok',
        'file' => __FILE__,
        'dir' => __DIR__,
        'php' => PHP_VERSION,
        'ts' => date('c')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if (isset($_GET['diag'])) {
    header('Content-Type: application/json; charset=utf-8');
    $out = [
        'diag' => true,
        'file' => __FILE__,
        'dir' => __DIR__,
        'php' => PHP_VERSION,
    ];
    // Collect time_entries columns
    $cols = [];
    if (isset($conn) && $conn instanceof mysqli && @$conn->ping()) {
        hlog('diag-db-ok');
        if ($res = $conn->query("SHOW COLUMNS FROM time_entries")) {
            while ($r = $res->fetch_assoc()) { $cols[$r['Field']] = $r['Type']; }
            $res->close();
        }
        $out['time_entries_columns'] = $cols;
        // Resolve candidate columns
        $has = function($n) use ($cols){ return array_key_exists($n,$cols); };
        $startCol = $has('start_time') ? 'start_time' : ($has('start') ? 'start' : null);
        $stopCol  = $has('stop_time')  ? 'stop_time'  : ($has('end_time')? 'end_time': null);
        $statusCol= $has('status') ? 'status' : null;
        $out['resolved'] = ['start'=>$startCol,'stop'=>$stopCol,'status'=>$statusCol];
        $whereStop = $statusCol ? " AND $statusCol='running'" : ($stopCol ? " AND $stopCol IS NULL" : '');
        $today = date('Y-m-d');
        $out['sql_exist_preview'] = "SELECT id FROM time_entries WHERE user_id = ? AND date = ?$whereStop LIMIT 1";
        if ($startCol) {
            $selStop = $stopCol ? ", $stopCol AS stopTime" : ", NULL AS stopTime";
            $out['sql_check_preview'] = "SELECT id, user_id, username, date, $startCol AS startTime$selStop FROM time_entries WHERE user_id = ? AND date = ?$whereStop ORDER BY $startCol DESC LIMIT 1";
        }
    } else {
        $out['db'] = 'not connected';
    }
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}
tlog('incoming', ['method' => $method, 'action' => $action]);

if (!$action) {
    send_response(400, ['message' => 'Missing action parameter']);
}

// Early diagnose path (no session/CSRF) to verify live code & schema quickly
if ($action === 'diagnose-early' || isset($_GET['diag'])) {
    header('Content-Type: application/json; charset=utf-8');
    $out = [
        'endpoint' => 'time-entries.php?diag=1',
        'file' => __FILE__,
        'dir' => __DIR__,
        'php' => PHP_VERSION,
    ];
    // Columns of time_entries
    $cols = [];
    if ($res = $conn->query("SHOW COLUMNS FROM time_entries")) {
        while ($r = $res->fetch_assoc()) { $cols[$r['Field']] = $r['Type']; }
        $res->close();
    }
    $out['time_entries_columns'] = $cols;
    // Resolve dynamic columns
    $has = function($n) use ($cols){ return array_key_exists($n,$cols); };
    $startCol = $has('start_time') ? 'start_time' : ($has('start') ? 'start' : null);
    $stopCol  = $has('stop_time')  ? 'stop_time'  : ($has('end_time')? 'end_time': null);
    $statusCol= $has('status') ? 'status' : null;
    $out['resolved'] = ['start'=>$startCol,'stop'=>$stopCol,'status'=>$statusCol];
    echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    exit;
}

// Auth: must have a valid session
$sessionUser = verify_session_and_get_user();
tlog('sessionUser', isset($sessionUser['username']) ? $sessionUser['username'] : 'none');
hlog('sessionUser', $sessionUser);

// CSRF for state-changing operations
if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    $csrfOk = validateCsrfToken();
    if (!$csrfOk) {
        // As a controlled fallback: allow same-origin with valid session, log bypass
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $refHost = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
        $sameOrigin = ($refHost === $host) || empty($refHost);
        if ($sameOrigin && !empty($sessionUser)) {
            tlog('csrf_bypass_same_origin', ['user' => $sessionUser['username'] ?? 'unknown']);
        } else {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => 'CSRF_TOKEN_INVALID',
                'message' => 'Invalid or missing CSRF token. Please refresh the page and try again.'
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
    }
}

$conn = $conn; // mysqli from db.php
$validator = InputValidationService::getInstance();

try {
    switch ($action) {
        case 'diagnose':
            handleDiagnose($conn, $sessionUser);
            break;
        case 'check_running':
            handleCheckRunning($conn, $sessionUser, $validator);
            break;
        case 'start':
            if ($method !== 'POST') {
                send_response(405, ['message' => 'Method Not Allowed']);
            }
            handleStart($conn, $sessionUser, $validator);
            break;
        case 'stop':
            if ($method !== 'POST') {
                send_response(405, ['message' => 'Method Not Allowed']);
            }
            handleStop($conn, $sessionUser, $validator);
            break;
        default:
            send_response(400, ['message' => 'Unknown action']);
    }
} catch (ValidationException $ve) {
    tlog('validation_error', $ve->getValidationErrors());
    hlog('validation_error', $ve->getValidationErrors());
    // Map to structured validation error
    send_response(400, [
        'error' => 'VALIDATION_ERROR',
        'message' => 'Validation failed',
        'details' => $ve->getValidationErrors()
    ]);
} catch (Throwable $e) {
    tlog('fatal_error', ['type' => get_class($e), 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    hlog('fatal_error', ['type' => get_class($e), 'message' => $e->getMessage()]);
    // Structured generic error; details suppressed by default config
    send_response(500, [
        'error' => 'INTERNAL_ERROR',
        'message' => 'Ein interner Fehler ist aufgetreten.'
    ]);
}

function handleDiagnose(mysqli $conn, array $sessionUser): void {
    header('Content-Type: application/json; charset=utf-8');
    $out = [
        'endpoint' => 'time-entries.php?action=diagnose',
        'file' => __FILE__,
        'dir' => __DIR__,
        'php' => PHP_VERSION,
    ];
    $out['db_connected'] = $conn instanceof mysqli && @$conn->ping();

    // Columns
    $cols = [];
    if ($res = $conn->query("SHOW COLUMNS FROM time_entries")) {
        while ($r = $res->fetch_assoc()) { $cols[$r['Field']] = $r['Type']; }
        $res->close();
    }
    $out['time_entries_columns'] = $cols;

    // Resolve username -> user_id
    $username = $sessionUser['username'] ?? ($sessionUser['name'] ?? null);
    $out['username'] = $username;
    $uid = null; $uerr = null;
    if ($stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1")) {
        $stmt->bind_param('s', $username);
        if ($stmt->execute()) { $stmt->bind_result($rid); if ($stmt->fetch()) $uid = (int)$rid; }
        else { $uerr = $stmt->error; }
        $stmt->close();
    } else { $uerr = $conn->error; }
    $out['resolved_user_id'] = $uid;
    if ($uerr) $out['user_lookup_error'] = $uerr;

    // Resolve dynamic cols
    $has = function($n) use ($cols){ return array_key_exists($n,$cols); };
    $startCol = $has('start_time') ? 'start_time' : ($has('start') ? 'start' : null);
    $stopCol  = $has('stop_time')  ? 'stop_time'  : ($has('end_time')? 'end_time': null);
    $statusCol= $has('status') ? 'status' : null;
    $out['resolved'] = ['start'=>$startCol,'stop'=>$stopCol,'status'=>$statusCol];

    $today = date('Y-m-d');
    $whereStop = $statusCol ? " AND $statusCol='running'" : ($stopCol ? " AND $stopCol IS NULL" : '');
    $sqlExist = "SELECT id FROM time_entries WHERE user_id = ? AND date = ?" . $whereStop . " LIMIT 1";
    $out['sql_exist'] = $sqlExist;
    $sqlCheck = $startCol ? ("SELECT id, user_id, username, date, $startCol AS startTime" . ($stopCol? ", $stopCol AS stopTime":" , NULL AS stopTime") .
               " FROM time_entries WHERE user_id = ? AND date = ?" . $whereStop . " ORDER BY $startCol DESC LIMIT 1") : null;
    $out['sql_check'] = $sqlCheck;

    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

function handleCheckRunning(mysqli $conn, array $sessionUser, InputValidationService $validator): void {
    $userId = resolveUserId($conn, $sessionUser);
    if (!$userId) { tlog('check_running_user_not_found', $sessionUser); send_response(404, ['message' => 'User not found']); }
    hlog('check_running_userId', $userId);

    $today = date('Y-m-d');
    // Dynamically resolve columns (stop_time vs end_time, start_time vs start, status)
    // resolveColumn ist nun global definiert
    $stopCol = resolveColumn($conn, ['stop_time','end_time']);
    $statusCol = resolveColumn($conn, ['status']);
    $startCol = resolveColumn($conn, ['start_time','start']);
    $statusCol = resolveColumn($conn, ['status']);
    if (!$startCol) { $startCol = 'start_time'; }
    $whereStop = '';
    if ($statusCol) {
        $whereStop = " AND $statusCol='running'";
    } elseif ($stopCol) {
        $whereStop = " AND $stopCol IS NULL";
    }
    // Backtick identifiers to avoid reserved word conflicts (e.g., `date`)
    $table = "`time_entries`";
    $idCol = "`id`";
    $uidCol = "`user_id`";
    $userCol = "`username`";
    $dateCol = "`date`";
    $startSel = "`" . $conn->real_escape_string($startCol) . "` AS startTime";
    $stopSel = $stopCol ? (" , `" . $conn->real_escape_string($stopCol) . "` AS stopTime") : ", NULL AS stopTime";
    $whereStopSql = $whereStop;
    if ($statusCol) { $whereStopSql = " AND `" . $conn->real_escape_string($statusCol) . "`='running'"; }
    elseif ($stopCol) { $whereStopSql = " AND `" . $conn->real_escape_string($stopCol) . "` IS NULL"; }
    $sql = "SELECT $idCol, $uidCol, $userCol, $dateCol, $startSel" . $stopSel .
           " FROM $table WHERE $uidCol = ? AND $dateCol = ?" . $whereStopSql .
           " ORDER BY `" . $conn->real_escape_string($startCol) . "` DESC LIMIT 1";
    hlog('check_running_sql', $sql);
    $stmt = $conn->prepare($sql);
    if (!$stmt) { tlog('check_running_prepare_failed', $conn->error . ' | sql=' . $sql); hlog('check_running_prepare_failed', $conn->error); send_response(500, ['message' => 'Database error']); }
    $stmt->bind_param('is', $userId, $today);
    if (!$stmt->execute()) { tlog('check_running_execute_failed', $stmt->error); hlog('check_running_execute_failed', $stmt->error); send_response(500, ['message' => 'Database error']); }
    $stmt->bind_result($rid, $ruserId, $rusername, $rdate, $rstart, $rstop);
    $row = null;
    if ($stmt->fetch()) {
        $row = [
            'id' => $rid,
            'userId' => $ruserId,
            'username' => $rusername,
            'date' => $rdate,
            'startTime' => $rstart,
            'stopTime' => $rstop
        ];
    }
    $stmt->close();

    if ($row) {
        send_response(200, [
            'hasRunningTimer' => true,
            'runningTimer' => $row
        ]);
    } else {
        send_response(200, [
            'hasRunningTimer' => false
        ]);
    }
}

function handleStart(mysqli $conn, array $sessionUser, InputValidationService $validator): void {
    tlog('start_begin');
    hlog('start_begin');
    $payload = $validator->validateJsonInput([
        // userId is resolved from session for integrity
        'date' => ['type' => 'datetime', 'required' => true, 'format' => 'Y-m-d'],
        'startTime' => ['type' => 'datetime', 'required' => true, 'format' => 'H:i:s'],
        'createdBy' => ['type' => 'string', 'required' => true, 'min_length' => 1, 'max_length' => 200],
        // csrf_token may be included but is validated by middleware
    ]);

    // Sanitize Username sicher über ValidationService (StringSanitizer nicht erforderlich)
    $username = $validator->sanitizeString($sessionUser['username'] ?? ($sessionUser['name'] ?? ''));
    $userId = resolveUserId($conn, $sessionUser);
    if (!$userId) { tlog('start_user_not_found', $sessionUser); send_response(404, ['message' => 'User not found']); }
    tlog('start_user_resolved', $userId);
    hlog('start_user_resolved', $userId);

    // Get user role from users table (needed for time_entries.role field)
    $userRole = 'employee'; // Default fallback
    if ($stmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1")) {
        $stmt->bind_param('i', $userId);
        if ($stmt->execute()) {
            $stmt->bind_result($roleResult);
            if ($stmt->fetch()) {
                $userRole = $roleResult;
            }
        }
        $stmt->close();
    }

    // Prevent multiple running timers
    $stopCol = resolveColumn($conn, ['stop_time','end_time']);
    $statusCol = resolveColumn($conn, ['status']);
    $whereStop = $statusCol ? " AND `status`='running'" : ($stopCol ? (" AND `".$conn->real_escape_string($stopCol)."` IS NULL") : '');
    $sqlExist = "SELECT `id` FROM `time_entries` WHERE `user_id` = ? AND `date` = ?" . $whereStop . " LIMIT 1";
    hlog('start_exist_sql', $sqlExist);
    $stmt = $conn->prepare($sqlExist);
    if (!$stmt) { tlog('start_prepare_exists_failed', $conn->error . ' | sql=' . $sqlExist); hlog('start_prepare_exists_failed', $conn->error); send_response(500, ['message' => 'Database error']); }
    $stmt->bind_param('is', $userId, $payload['date']);
    if (!$stmt->execute()) { tlog('start_execute_exists_failed', $stmt->error); hlog('start_execute_exists_failed', $stmt->error); send_response(500, ['message' => 'Database error']); }
    $stmt->store_result();
    $existing = $stmt->num_rows > 0;
    $stmt->free_result();
    $stmt->close();
    tlog('start_exists_checked', ['existing' => $existing]);
    if ($existing) {
        send_response(409, [
            'error' => 'TIMER_ALREADY_RUNNING',
            'message' => 'Die Zeiterfassung läuft bereits.'
        ]);
    }

    // Insert new entry (dynamic columns to match live schema)
    $conn->begin_transaction();
    try {
        // Detect available columns
        $cols = [];
        if ($res = $conn->query("SHOW COLUMNS FROM time_entries")) {
            while ($r = $res->fetch_assoc()) { $cols[strtolower($r['Field'])] = true; }
            $res->close();
        }
        tlog('start_cols', array_keys($cols));
        $sessionLoc = $sessionUser['location'] ?? 'Home Office';
        tlog('start_location_session', $sessionLoc);
        // Build insert columns/params based on available columns
        $fields = ['`user_id`', '`date`', '`start_time`'];
        $placeholders = ['?', '?', '?'];
        $types = 'iss';
        $values = [$userId, $payload['date'], $payload['startTime']];
        if (!empty($cols['username'])) { $fields[] = '`username`'; $placeholders[] = '?'; $types .= 's'; $values[] = $username; }
        if (!empty($cols['updated_by'])) { $fields[] = '`updated_by`'; $placeholders[] = '?'; $types .= 's'; $values[] = $payload['createdBy']; }
        if (!empty($cols['status'])) { $fields[] = '`status`'; $placeholders[] = '?'; $types .= 's'; $values[] = 'running'; }
        // Set location if column exists (use session-detected location or Home Office)
        if (!empty($cols['location'])) {
            $loc = $sessionUser['location'] ?? 'Home Office';
            $fields[] = '`location`';
            $placeholders[] = '?';
            $types .= 's';
            $values[] = $loc;
        }
        // Set role if column exists (fetched from users table)
        if (!empty($cols['role'])) {
            $fields[] = '`role`';
            $placeholders[] = '?';
            $types .= 's';
            $values[] = $userRole;
        }
        if (!empty($cols['created_at'])) { $fields[] = '`created_at`'; $placeholders[] = 'NOW()'; }
        if (!empty($cols['updated_at'])) { $fields[] = '`updated_at`'; $placeholders[] = 'NOW()'; }

        // Build SQL; handle NOW() (no placeholder) positions
        $ph = [];
        $bindIndices = [];
        $vIndex = 0;
        foreach ($placeholders as $i => $p) {
            if ($p === 'NOW()') { $ph[] = 'NOW()'; }
            else { $ph[] = '?'; $bindIndices[] = $i; }
        }
        $sql = "INSERT INTO time_entries (" . implode(',', $fields) . ") VALUES (" . implode(',', $ph) . ")";
        tlog('start_insert_sql', $sql);
        hlog('start_insert_sql', $sql);
        $stmt = $conn->prepare($sql);
        if (!$stmt) { tlog('start_prepare_insert_failed', $conn->error . ' | sql=' . $sql); hlog('start_prepare_insert_failed', $conn->error); throw new RuntimeException('Prepare failed: ' . $conn->error); }

        // Build bind params according to $types and $values mapping
        // We only bind for '?' placeholders, in the order they appeared
        $bindValues = [];
        $bindTypes = '';
        // Build sequence values in the same order as dynamic additions above
        $sequenceValues = [$userId, $payload['date'], $payload['startTime']];
        if (!empty($cols['username'])) { $sequenceValues[] = $username; }
        if (!empty($cols['updated_by'])) { $sequenceValues[] = $payload['createdBy']; }
        if (!empty($cols['status'])) { $sequenceValues[] = 'running'; }
        if (!empty($cols['location'])) { $sequenceValues[] = $sessionUser['location'] ?? 'Home Office'; }
        if (!empty($cols['role'])) { $sequenceValues[] = $userRole; }
        // Types accordingly
        $bindTypes = '';
        foreach ($sequenceValues as $idx => $val) {
            $bindTypes .= is_int($val) ? 'i' : 's';
            $bindValues[] = $val;
        }
        // Bind
        $stmt->bind_param($bindTypes, ...$bindValues);
        if (!$stmt->execute()) { tlog('start_execute_insert_failed', $stmt->error); hlog('start_execute_insert_failed', $stmt->error); throw new RuntimeException('Execute failed: ' . $stmt->error); }
        $stmt->close();
        $newId = $conn->insert_id;
        // Safety fix: ensure location is set (overrides defaults like 'web' if present)
        if (!empty($cols['location'])) {
            $loc = $sessionLoc;
            // Erzwinge Standort (unabhängig vom DB-Default)
            $u = $conn->prepare("UPDATE time_entries SET location = ? WHERE id = ?");
            if ($u) { $u->bind_param('si', $loc, $newId); @$u->execute(); $u->close(); }
        }
        tlog('start_success', $newId);
        hlog('start_success', $newId);
        $conn->commit();
        send_response(200, ['id' => $newId]);
    } catch (Throwable $e) {
        tlog('start_exception', ['type' => get_class($e), 'message' => $e->getMessage()]);
        hlog('start_exception', ['type' => get_class($e), 'message' => $e->getMessage()]);
        $conn->rollback();
        send_response(500, ['error' => 'DATABASE_ERROR', 'message' => 'Ein Datenbankfehler ist aufgetreten.']);
    }
}

function handleStop(mysqli $conn, array $sessionUser, InputValidationService $validator): void {
    $payload = $validator->validateJsonInput([
        'id' => ['type' => 'numeric', 'required' => true, 'integer' => true, 'positive' => true],
        'stopTime' => ['type' => 'datetime', 'required' => true, 'format' => 'H:i:s'],
        'updatedBy' => ['type' => 'string', 'required' => true, 'min_length' => 1, 'max_length' => 200],
    ]);

    $entryId = (int)$payload['id'];
    $updatedBy = $payload['updatedBy'];

    // Load entry
    $stmt = $conn->prepare(
        "SELECT id, date, start_time AS startTime, stop_time AS stopTime FROM time_entries WHERE id = ? LIMIT 1"
    );
    if (!$stmt) {
        tlog('stop_prepare_select_failed', $conn->error);
        send_response(500, ['message' => 'Database error']);
    }
    $stmt->bind_param('i', $entryId);
    if (!$stmt->execute()) { tlog('stop_select_execute_failed', $stmt->error); send_response(500, ['message' => 'Database error']); }
    $stmt->bind_result($sid, $sdate, $sstart, $sstop);
    $row = null;
    if ($stmt->fetch()) { $row = ['id' => $sid, 'date' => $sdate, 'startTime' => $sstart, 'stopTime' => $sstop]; }
    $stmt->close();

    if (!$row) {
        send_response(404, [
            'error' => 'NOT_FOUND',
            'message' => 'Die angeforderten Daten wurden nicht gefunden.'
        ]);
    }
    if (!empty($row['stopTime'])) {
        send_response(409, [
            'error' => 'TIMER_NOT_RUNNING',
            'message' => 'Es läuft keine Zeiterfassung.'
        ]);
    }

    // Validate time ordering (stop > start)
    $startTs = strtotime($row['date'] . ' ' . $row['startTime']);
    $stopTs = strtotime($row['date'] . ' ' . $payload['stopTime']);
    if ($stopTs <= $startTs) {
        send_response(400, [
            'error' => 'VALIDATION_ERROR',
            'message' => 'Stop time must be after start time.'
        ]);
    }

    $conn->begin_transaction();
    try {
        // Detect if status column exists to mark completed
        $cols = [];
        if ($res = $conn->query("SHOW COLUMNS FROM time_entries")) {
            while ($r = $res->fetch_assoc()) { $cols[strtolower($r['Field'])] = true; }
            $res->close();
        }
        $hasStatus = !empty($cols['status']);
        $sql = "UPDATE time_entries SET stop_time = ?, updated_by = ?, updated_at = NOW()" . ($hasStatus? ", status='completed'" : "") . " WHERE id = ? AND stop_time IS NULL";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            tlog('stop_prepare_update_failed', $conn->error);
            throw new RuntimeException('Prepare failed: ' . $conn->error);
        }
        $stmt->bind_param('ssi', $payload['stopTime'], $updatedBy, $entryId);
        if (!$stmt->execute()) {
            tlog('stop_execute_update_failed', $stmt->error);
            throw new RuntimeException('Execute failed: ' . $stmt->error);
        }
        $affected = $stmt->affected_rows;
        $stmt->close();
        // Erzwinge Standort auch beim Stop (sollte bereits gesetzt sein, ist hier idempotent)
        if (!empty($cols['location'])) {
            $loc = $sessionUser['location'] ?? 'Home Office';
            $u = $conn->prepare("UPDATE time_entries SET location = ? WHERE id = ?");
            if ($u) { $u->bind_param('si', $loc, $entryId); @$u->execute(); $u->close(); }
        }
        $conn->commit();

        if ($affected === 0) {
            send_response(409, [
                'error' => 'TIMER_NOT_RUNNING',
                'message' => 'Es läuft keine Zeiterfassung.'
            ]);
        }

        send_response(200, ['success' => true]);
    } catch (Throwable $e) {
        tlog('stop_exception', ['type' => get_class($e), 'message' => $e->getMessage()]);
        $conn->rollback();
        send_response(500, [
            'error' => 'DATABASE_ERROR',
            'message' => 'Ein Datenbankfehler ist aufgetreten.'
        ]);
    }
}
