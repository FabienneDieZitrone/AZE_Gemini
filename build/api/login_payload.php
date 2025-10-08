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
  if ($res = $mysqli->query("SELECT id, user_id AS userId, username, date, start_time AS startTime, stop_time AS stopTime, location, role, created_at AS createdAt, updated_by AS updatedBy, updated_at AS updatedAt FROM time_entries ORDER BY date DESC, start_time DESC")) {
    $response['timeEntries'] = $res->fetch_all(MYSQLI_ASSOC);
    $res->close();
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
