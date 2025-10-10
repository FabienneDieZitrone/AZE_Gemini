<?php
/**
 * Login Lite Endpoint (with approvalRequests injection)
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$origin = $_SERVER['HTTP_ORIGIN'] ?? 'https://aze.mikropartner.de';
$allowed_origs = ['https://aze.mikropartner.de'];
if (in_array($origin, $allowed_origs, true)) {
  header('Access-Control-Allow-Origin: ' . $origin);
  header('Access-Control-Allow-Credentials: true');
}
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  header('Access-Control-Allow-Methods: POST, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type');
  http_response_code(204);
  exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Method not allowed']);
  exit;
}

if (session_status() === PHP_SESSION_NONE) {
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}
if (!isset($_SESSION['user']) || empty($_SESSION['user']['oid'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Not authenticated']);
  exit;
}
$sessionUser = $_SESSION['user'];

try {
  $rootConfig = __DIR__ . '/../config.php';
  require_once $rootConfig;
  $db_host = Config::get('db.host');
  $db_name = Config::get('db.name');
  $db_user = Config::get('db.username') ?: (Config::get('db.user'));
  $db_pass = Config::get('db.password');
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Configuration error']);
  exit;
}

$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_error) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Database connection error']);
  exit;
}
$mysqli->set_charset('utf8mb4');

  $mysqli->begin_transaction();
  try {
  $azure_oid = $sessionUser['oid'];
  $display_name = $sessionUser['name'] ?? $sessionUser['display_name'] ?? 'Unknown User';
  $email = $sessionUser['email'] ?? $sessionUser['preferred_username'] ?? $sessionUser['username'] ?? '';

  $stmt = $mysqli->prepare('SELECT id, username, display_name, role FROM users WHERE azure_oid = ?');
  $stmt->bind_param('s', $azure_oid);
  $stmt->execute();
  $user = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($user) {
    $user_id = (int)$user['id'];
    if (($user['display_name'] ?? '') !== $display_name) {
      $u = $mysqli->prepare('UPDATE users SET display_name = ? WHERE id = ?');
      $u->bind_param('si', $display_name, $user_id);
      $u->execute();
      $u->close();
    }
  } else {
    $ins = $mysqli->prepare("INSERT INTO users (username, display_name, role, azure_oid) VALUES (?, ?, 'Mitarbeiter', ?)");
    $ins->bind_param('sss', $email, $display_name, $azure_oid);
    $ins->execute();
    $user_id = $mysqli->insert_id;
    $ins->close();
    $workdays = json_encode(['Mo','Di','Mi','Do','Fr']);
    $m = $mysqli->prepare('INSERT INTO master_data (user_id, weekly_hours, workdays, can_work_from_home) VALUES (?, 40.00, ?, 0)');
    $m->bind_param('is', $user_id, $workdays);
    $m->execute();
    $m->close();
  }

  // Client-IP ermitteln
  $clientIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''));
  if (strpos($clientIp, ',') !== false) { $clientIp = trim(explode(',', $clientIp)[0]); }
  // IP→Standort Map (Prefix-Match) – Default-Hardcode
  $ipMap = [
    '10.63.91.' => 'PIN',
    '10.63.98.' => 'BER FRI 5.OG',
    '10.96.91.' => 'BER STO 7.OG',
    '10.84.91.' => 'BER GRU 4.OG',
    '10.155.91.' => 'BRE HER',
    '10.49.1.' => 'BER GRU',
    '10.49.2.' => 'HAN GEO',
    '10.49.3.' => 'BER BOS 6.OG',
    '10.49.4.' => 'BER COL',
    '10.49.5.' => 'NEU FAB',
    '10.49.6.' => 'BER LYN',
    '10.49.7.' => 'BER BOS 3.OG',
    '10.49.8.' => 'HAM SPA 4.OG',
    '10.49.9.' => 'HAM NOR BRE FOE',
    '10.49.10.' => 'ITZ',
    '10.49.11.' => 'BRE MAR 2.OG',
    '10.49.12.' => 'LAU',
    '10.49.13.' => 'HAL',
    '10.49.14.' => 'BRE HER',
    '10.49.15.' => 'BRE TEE',
    '10.49.16.' => 'SEN',
    '192.168.179.' => 'SEN',
    '10.49.17.' => 'MAG RE  MUE2 (B)',
    '10.49.18.' => 'COT',
    '10.49.19.' => 'HAM SPA 8.OG',
    '10.49.20.' => 'BRE PRU',
    '10.49.21.' => 'ELM',
    '10.49.22.' => 'BRE KRA',
    '10.49.23.' => 'HAM SPA 2.OG B',
    '10.49.24.' => 'HAM SPA 4.OG B',
    '10.49.25.' => 'HAM SPA 8.OG B',
    '10.49.26.' => 'MUE (A)',
    '10.49.27.' => 'BER PIC',
    '10.49.28.' => 'BRE HOL',
    '10.49.29.' => 'BRE LOU',
    '192.168.0.'  => 'BRE KRA',
    '10.49.35.' => 'BER GRU 1.OG',
    '10.49.36.' => 'GLU',
    '10.49.50.' => 'BER GRU 7.OG',
    '10.49.55.' => 'HAN BOE',
    '10.49.71.' => 'BRE DEI',
    '10.49.91.' => 'ELM',
    '10.49.113.' => 'BRE MAR 5.OG',
    '10.49.115.' => 'BER BAD',
    '10.49.163.' => 'HAM SPA 2. OG',
  ];
  // Optional: Overrides aus cache/ip-location-map.json (vom Admin pflegbar)
  $overrideFile = __DIR__ . '/../cache/ip-location-map.json';
  if (is_readable($overrideFile)) {
    $ovr = json_decode(@file_get_contents($overrideFile), true);
    if (isset($ovr['entries']) && is_array($ovr['entries'])) {
      // Admin-Overrides sollen Vorrang haben: vorn einfügen
      $ovrMap = [];
      foreach ($ovr['entries'] as $e) {
        $p = (string)($e['prefix'] ?? ''); $l = (string)($e['location'] ?? '');
        if ($p !== '' && $l !== '') { $ovrMap[$p] = $l; }
      }
      $ipMap = array_merge($ovrMap, $ipMap);
    }
  }

  $detectedLocation = '';
  foreach ($ipMap as $prefix => $locName) {
    if ($clientIp && strpos($clientIp, $prefix) === 0) { $detectedLocation = $locName; break; }
  }
  // Default: Home Office, wenn keine Zuordnung gefunden wurde
  if ($detectedLocation === '') { $detectedLocation = 'Home Office'; }
  // in Session für serverseitige Filter (z. B. Standortleiter) bereitstellen
  $_SESSION['user']['location'] = $detectedLocation;

  $response = [
    'success' => true,
    'currentUser' => [
      'id' => $user_id,
      'name' => $display_name,
      'role' => $user['role'] ?? 'Mitarbeiter',
      'azureOid' => $azure_oid,
    ],
    'users' => [],
    'masterData' => new stdClass(),
    'timeEntries' => [],
    'approvalRequests' => [],
    'history' => [],
    'globalSettings' => [
      'overtimeThreshold' => 8.0,
      'changeReasons' => ['Vergessen','Fehler','Nachträglich'],
      'locations' => ['Zentrale Berlin']
    ],
    // Neuer Schlüssel für die UI (optional): erkannter Standort
    'currentLocation' => $detectedLocation,
  ];

  if ($res = $mysqli->query('SELECT id, display_name AS name, role, azure_oid AS azureOid FROM users')) {
    $response['users'] = $res->fetch_all(MYSQLI_ASSOC);
    $res->close();
  }
  if ($res = $mysqli->query("SELECT user_id, weekly_hours, workdays, can_work_from_home FROM master_data")) {
    $md = [];
    while ($row = $res->fetch_assoc()) {
      $md[(int)$row['user_id']] = [
        'weeklyHours' => (float)$row['weekly_hours'],
        'workdays' => json_decode($row['workdays'], true) ?: ['Mo','Di','Mi','Do','Fr'],
        'canWorkFromHome' => (bool)$row['can_work_from_home'],
      ];
    }
    $response['masterData'] = $md;
    $res->close();
  }
  // Lade Zeiteinträge; für den aktuellen Benutzer wird 'Web'/leere location visuell auf den erkannten Standort gemappt
  if ($stmt = $mysqli->prepare("SELECT id, user_id AS userId, username, date, start_time AS startTime, stop_time AS stopTime,
    CASE WHEN (LOWER(COALESCE(location,'')) IN ('web','') AND user_id = ?) THEN ? ELSE location END AS location,
    role, created_at AS createdAt, updated_by AS updatedBy, updated_at AS updatedAt
    FROM time_entries ORDER BY date DESC, start_time DESC")) {
    $stmt->bind_param('is', $user_id, $detectedLocation);
    if ($stmt->execute()) {
      $res = $stmt->get_result();
      $response['timeEntries'] = $res->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
  }

  // Globale Einstellungen (aus DB), dabei sicherstellen: "Home Office" ist in der Standortliste enthalten
  if ($s = $mysqli->query('SELECT overtime_threshold, change_reasons, locations FROM global_settings WHERE id = 1')) {
    if ($row = $s->fetch_assoc()) {
      $ot = isset($row['overtime_threshold']) ? (float)$row['overtime_threshold'] : $response['globalSettings']['overtimeThreshold'];
      $cr = json_decode($row['change_reasons'], true);
      if (!is_array($cr) || empty($cr)) { $cr = $response['globalSettings']['changeReasons']; }
      $locs = json_decode($row['locations'], true);
      if (!is_array($locs) || empty($locs)) { $locs = $response['globalSettings']['locations']; }
      // "Home Office" hinzufügen, falls nicht vorhanden
      if (!in_array('Home Office', $locs, true)) { $locs[] = 'Home Office'; }
      $response['globalSettings'] = [
        'overtimeThreshold' => $ot,
        'changeReasons' => $cr,
        'locations' => $locs,
      ];
    } else {
      // Fallback: nur Home Office ergänzen
      if (!in_array('Home Office', $response['globalSettings']['locations'], true)) {
        $response['globalSettings']['locations'][] = 'Home Office';
      }
    }
    $s->close();
  } else {
    // Fallback ohne DB: Home Office ergänzen
    if (!in_array('Home Office', $response['globalSettings']['locations'], true)) {
      $response['globalSettings']['locations'][] = 'Home Office';
    }
  }

  // Sicherstellen: Alle Standorte aus der IP→Standort‑Zuordnung sind auch in der Stammliste vorhanden
  if (is_array($response['globalSettings']['locations'])) {
    $locSet = array_fill_keys($response['globalSettings']['locations'], true);
    foreach ($ipMap as $p => $lname) {
      $lname = trim((string)$lname);
      if ($lname !== '' && !isset($locSet[$lname])) {
        $response['globalSettings']['locations'][] = $lname;
        $locSet[$lname] = true;
      }
    }
    // Sortiere alphabetisch (case-insensitive)
    $locationsSorted = $response['globalSettings']['locations'];
    usort($locationsSorted, function($a,$b){ return strcasecmp($a,$b); });
    $response['globalSettings']['locations'] = array_values(array_unique($locationsSorted));
    // Persistiere in DB (global_settings.locations)
    try {
      if ($up = $mysqli->prepare('UPDATE global_settings SET locations = ? WHERE id = 1')) {
        $json = json_encode($response['globalSettings']['locations'], JSON_UNESCAPED_UNICODE);
        $up->bind_param('s', $json);
        @$up->execute();
        $up->close();
      }
    } catch (Throwable $e) { /* ignore persist errors */ }
  }

  // Inject approvalRequests (rollenbasiert)
  try {
    $role = $response['currentUser']['role'] ?? 'Mitarbeiter';
    $items = [];
    $pendingWhere = "(status IS NULL OR TRIM(LOWER(status))='pending' OR status='')";
    if (in_array($role, ['Honorarkraft','Mitarbeiter'])) {
      $sql = "SELECT id, type, original_entry_data, new_data, reason_data, requested_by, status FROM approval_requests WHERE " . $pendingWhere . " AND requested_by = ? ORDER BY id DESC";
      if ($st = $mysqli->prepare($sql)) { $st->bind_param('s', $email); if ($st->execute()) { $rs=$st->get_result(); while ($row=$rs->fetch_assoc()) { $items[]=$row; } } $st->close(); }
    } else {
      // Admin/Leitung: nur tatsächlich pending Anträge beim Login-Payload
      $sql = "SELECT id, type, original_entry_data, new_data, reason_data, requested_by, status FROM approval_requests WHERE " . $pendingWhere . " ORDER BY id DESC";
      if ($st = $mysqli->prepare($sql)) { if ($st->execute()) { $rs=$st->get_result(); while ($row=$rs->fetch_assoc()) { $items[]=$row; } } $st->close(); }
    }
    $mapped = [];
    foreach ($items as $req) {
      $orig = json_decode($req['original_entry_data'] ?? '[]', true) ?: [];
      $mapped[] = [
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
        'status' => $req['status'] ?: 'pending',
      ];
    }
    $response['approvalRequests'] = $mapped;
  } catch (Throwable $e) { }

  $mysqli->commit();
  $_SESSION['user_id'] = $user_id;
  $_SESSION['user_role'] = $response['currentUser']['role'];
  echo json_encode($response);
} catch (Throwable $e) {
  $mysqli->rollback();
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Failed to process login']);
}
$mysqli->close();
?>
