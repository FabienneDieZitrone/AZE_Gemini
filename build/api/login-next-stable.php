<?php
/**
 * Login Next Stable
 * - Stabiler Ersatz für login.php (keine 500-Risiken aus Boot-Ketten)
 * - Setzt Security-/CORS-Header direkt und nutzt robuste Session-/CSRF-Checks
 * - Liefert vollständige Initialdaten wie vom Frontend erwartet
 */

define('API_GUARD', true);
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Security- und CORS-Header setzen (ohne initializeSecurity-Kette)
require_once __DIR__ . '/security-headers.php';
setSecurityHeaders();
setCorsHeaders();
header('Content-Type: application/json; charset=UTF-8');

// OPTIONS früh beenden
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// Nur POST zulassen
validateRequestMethod('POST');

// Session sicher starten
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

// CSRF validieren (optional, fallback erlaubt Same-Origin)
require_once __DIR__ . '/csrf-middleware.php';
$csrfOk = validateCsrfToken();
if (!$csrfOk) {
  $host = $_SERVER['HTTP_HOST'] ?? '';
  $refHost = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
  $sameOrigin = ($refHost === $host) || empty($refHost);
  if (!$sameOrigin) {
    http_response_code(403);
    echo json_encode(['error'=>'CSRF_TOKEN_INVALID','message'=>'Invalid or missing CSRF token.']);
    exit;
  }
}

// Auth aus Session prüfen
require_once __DIR__ . '/auth_helpers.php';
if (!isset($_SESSION['user']) || empty($_SESSION['user']['oid'])) {
  http_response_code(401);
  echo json_encode(['success'=>false,'message'=>'Not authenticated']);
  exit;
}

$sessionUser = $_SESSION['user'];

// DB-Verbindung (robust via Config)
require_once __DIR__ . '/DatabaseConnection.php';
$db = DatabaseConnection::getInstance();
$conn = $db->getConnection();
$conn->begin_transaction();

try {
  // Nutzer anlegen/aktualisieren
  $azure_oid = $sessionUser['oid'];
  $display_name = $sessionUser['name'] ?? $sessionUser['display_name'] ?? 'Unbekannt';
  $email = $sessionUser['email'] ?? $sessionUser['preferred_username'] ?? $sessionUser['username'] ?? '';

  // 1) Auflösen über Azure OID
  $stmt = $conn->prepare('SELECT id, username, display_name, role FROM users WHERE azure_oid = ?');
  if (!$stmt) throw new Exception('Prepare failed: '.$conn->error);
  $stmt->bind_param('s', $azure_oid);
  $stmt->execute();
  $res = $stmt->get_result();
  $user = $res->fetch_assoc();
  $stmt->close();

  // 2) Fallback: Auflösen über E-Mail/Username, dann azure_oid nachtragen
  if (!$user && !empty($email)) {
    $s2 = $conn->prepare('SELECT id, username, display_name, role FROM users WHERE username = ?');
    if (!$s2) throw new Exception('Prepare failed: '.$conn->error);
    $s2->bind_param('s', $email);
    $s2->execute();
    $r2 = $s2->get_result();
    $user = $r2->fetch_assoc();
    $s2->close();
    if ($user) {
      // azure_oid nachtragen, damit künftige Logins stabil auflösen
      $u2 = $conn->prepare('UPDATE users SET azure_oid = ? WHERE id = ?');
      $uidTmp = (int)$user['id'];
      $u2->bind_param('si', $azure_oid, $uidTmp);
      $u2->execute();
      $u2->close();
    }
  }

  if ($user) {
    $user_id = (int)$user['id'];
    if ($user['display_name'] !== $display_name) {
      $u = $conn->prepare('UPDATE users SET display_name = ? WHERE id = ?');
      $u->bind_param('si', $display_name, $user_id);
      $u->execute();
      $u->close();
    }
  } else {
    $ins = $conn->prepare("INSERT INTO users (username, display_name, role, azure_oid) VALUES (?, ?, 'Mitarbeiter', ?)");
    if (!$ins) throw new Exception('Insert prepare failed: '.$conn->error);
    $ins->bind_param('sss', $email, $display_name, $azure_oid);
    $ins->execute();
    $user_id = $conn->insert_id;
    $ins->close();

    $workdays = json_encode(['Mo','Di','Mi','Do','Fr']);
    $m = $conn->prepare('INSERT INTO master_data (user_id, weekly_hours, workdays, can_work_from_home) VALUES (?, 40.00, ?, 0)');
    $m->bind_param('is', $user_id, $workdays);
    $m->execute();
    $m->close();
  }

  // Zeit-Einträge auf den korrekten Nutzer verlinken (falls abweichend)
  if (!empty($user_id)) {
    // 1) Fälle mit username = E-Mail
    if (!empty($email)) {
      if ($fix1 = $conn->prepare('UPDATE time_entries SET user_id = ?, username = ? WHERE username = ? AND (user_id IS NULL OR user_id <> ?)')) {
        $fix1->bind_param('issi', $user_id, $display_name, $email, $user_id);
        $fix1->execute();
        $fix1->close();
      }
    }
    // 2) Fälle mit username = Anzeigename
    if (!empty($display_name)) {
      if ($fix2 = $conn->prepare('UPDATE time_entries SET user_id = ? WHERE username = ? AND (user_id IS NULL OR user_id <> ?)')) {
        $fix2->bind_param('isi', $user_id, $display_name, $user_id);
        $fix2->execute();
        $fix2->close();
      }
    }
  }

  // Antwort vorbereiten
  $response = [
    'currentUser' => [ 'id'=>$user_id, 'name'=>$display_name, 'role'=>($user['role'] ?? 'Mitarbeiter'), 'azureOid'=>$azure_oid ],
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

  // Users
  if ($r = $conn->query('SELECT id, display_name AS name, role, azure_oid AS azureOid FROM users')) {
    $response['users'] = $r->fetch_all(MYSQLI_ASSOC);
    $r->close();
  }

  // MasterData
  if ($r = $conn->query('SELECT user_id, weekly_hours, workdays, can_work_from_home FROM master_data')) {
    $md = [];
    while ($row = $r->fetch_assoc()) {
      $md[(int)$row['user_id']] = [
        'weeklyHours' => (float)$row['weekly_hours'],
        'workdays' => json_decode($row['workdays'], true) ?: ['Mo','Di','Mi','Do','Fr'],
        'canWorkFromHome' => (bool)$row['can_work_from_home'],
      ];
    }
    $response['masterData'] = $md;
    $r->close();
  }

  // TimeEntries
  $sqlTE = "SELECT id, user_id AS userId, username, date, start_time AS startTime, stop_time AS stopTime, location, role, created_at AS createdAt, updated_by AS updatedBy, updated_at AS updatedAt FROM time_entries ORDER BY date DESC, start_time DESC";
  if ($r = $conn->query($sqlTE)) {
    $response['timeEntries'] = $r->fetch_all(MYSQLI_ASSOC);
    $r->close();
  }

  // Global Settings (optional)
  if ($r = $conn->query("SHOW TABLES LIKE 'global_settings'")) {
    if ($r->num_rows > 0) {
      $r->close();
      if ($s = $conn->query('SELECT overtime_threshold, change_reasons, locations FROM global_settings WHERE id = 1')) {
        if ($row = $s->fetch_assoc()) {
          $response['globalSettings'] = [
            'overtimeThreshold' => (float)$row['overtime_threshold'],
            'changeReasons' => json_decode($row['change_reasons'], true) ?: $response['globalSettings']['changeReasons'],
            'locations' => json_decode($row['locations'], true) ?: $response['globalSettings']['locations'],
          ];
        }
        $s->close();
      }
    } else {
      $r->close();
    }
  }

  $conn->commit();
  $_SESSION['user_id'] = $user_id;
  $_SESSION['user_role'] = $user['role'] ?? 'Mitarbeiter';
  
  // Pending approvals laden (Admin/Bereichs-/Standortleiter: alle; Mitarbeiter nur eigene)
  $role = $user['role'] ?? 'Mitarbeiter';
  $approvals = [];
  $sqlAppr = "SELECT id, type, original_entry_data, new_data, reason_data, requested_by, status FROM approval_requests WHERE status = 'pending'";
  if ($role === 'Mitarbeiter' || $role === 'Honorarkraft') {
    $sqlAppr .= " AND requested_by = '" . $conn->real_escape_string($email) . "'";
  }
  if ($a = $conn->query($sqlAppr)) {
    while ($row = $a->fetch_assoc()) {
      $orig = json_decode($row['original_entry_data'] ?? '[]', true) ?: [];
      $newd = json_decode($row['new_data'] ?? '[]', true) ?: [];
      $reas = json_decode($row['reason_data'] ?? '[]', true) ?: [];
      // Entry für Anzeige bauen
      $entry = [];
      if (!empty($orig) && isset($orig['id'])) {
        $entry = [
          'id' => (int)$orig['id'],
          'userId' => (int)($orig['user_id'] ?? $user_id),
          'username' => $orig['username'] ?? $display_name,
          'date' => $orig['date'] ?? $today,
          'startTime' => $orig['start_time'] ?? ($newd['startTime'] ?? '00:00:00'),
          'stopTime' => $orig['stop_time'] ?? ($newd['stopTime'] ?? '00:00:00'),
          'location' => $orig['location'] ?? ($newd['location'] ?? ($response['globalSettings']['locations'][0] ?? '')),
          'role' => $orig['role'] ?? ($newd['role'] ?? ($user['role'] ?? 'Mitarbeiter')),
          'createdAt' => $orig['created_at'] ?? date('c'),
          'updatedBy' => $orig['updated_by'] ?? $row['requested_by'],
          'updatedAt' => $orig['updated_at'] ?? date('c'),
        ];
      } else {
        // 'create' oder fehlende Originaldaten → aus newData bauen
        $entry = [
          'id' => 0,
          'userId' => (int)($newd['userId'] ?? $user_id),
          'username' => $newd['username'] ?? $display_name,
          'date' => $newd['date'] ?? date('Y-m-d'),
          'startTime' => $newd['startTime'] ?? '00:00:00',
          'stopTime' => $newd['stopTime'] ?? '00:00:00',
          'location' => $newd['location'] ?? ($response['globalSettings']['locations'][0] ?? ''),
          'role' => $newd['role'] ?? ($user['role'] ?? 'Mitarbeiter'),
          'createdAt' => date('c'),
          'updatedBy' => $row['requested_by'],
          'updatedAt' => date('c'),
        ];
      }
      $approvals[] = [
        'id' => (string)$row['id'],
        'type' => $row['type'],
        'entry' => $entry,
        'newData' => $newd,
        'reasonData' => $reas,
        'requestedBy' => $row['requested_by'],
        'status' => 'pending',
      ];
    }
    $a->close();
  }
  $response['approvalRequests'] = $approvals;
  echo json_encode($response);
} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Failed to process login']);
}

?>
