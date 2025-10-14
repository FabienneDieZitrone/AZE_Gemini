<?php
/**
 * Master Data API
 * Supports: PUT to create/update per-user master data
 */
define('API_GUARD', true);

require_once __DIR__ . '/security-middleware.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/csrf-middleware.php';
require_once __DIR__ . '/DatabaseConnection.php';
require_once __DIR__ . '/InputValidationService.php';

initialize_api();
initSecurityMiddleware();

// Only allow PUT
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'PUT') {
  http_response_code(405);
  header('Allow: PUT');
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['error'=>'Method not allowed']);
  exit;
}

// Require CSRF for PUT; relax only for same-origin + valid session (consistent with other endpoints)
$csrfOk = function_exists('validateCsrfToken') ? validateCsrfToken() : true;
if (!$csrfOk) {
  $host = $_SERVER['HTTP_HOST'] ?? '';
  $refHost = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
  $sameOrigin = ($refHost === $host) || empty($refHost);
  if (!$sameOrigin) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'CSRF_TOKEN_INVALID', 'message' => 'Invalid or missing CSRF token.']);
    exit;
  }
}

$db = DatabaseConnection::getInstance();
$conn = $db->getConnection();

// Verify session and resolve current user + role
$sessionUser = verify_session_and_get_user();

function resolveRole(mysqli $conn, array $sessionUser): string {
  $role = '';
  $oid = $sessionUser['azure_oid'] ?? ($sessionUser['oid'] ?? null);
  $username = $sessionUser['username'] ?? null;
  if (!empty($oid)) {
    if ($st = $conn->prepare("SELECT role FROM users WHERE azure_oid = ? LIMIT 1")) {
      $st->bind_param('s', $oid);
      if ($st->execute()) { $st->bind_result($r); if ($st->fetch()) { $role = (string)$r; } }
      $st->close();
    }
  }
  if (!$role && $username) {
    if ($st = $conn->prepare("SELECT role FROM users WHERE username = ? LIMIT 1")) {
      $st->bind_param('s', $username);
      if ($st->execute()) { $st->bind_result($r); if ($st->fetch()) { $role = (string)$r; } }
      $st->close();
    }
  }
  return $role ?: 'Mitarbeiter';
}

// Parse JSON body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

$userId = isset($data['userId']) ? (int)$data['userId'] : 0;
if ($userId <= 0) { send_response(400, ['message' => 'userId required']); }

$validator = InputValidationService::getInstance();

$weeklyHours = isset($data['weeklyHours']) ? (float)$data['weeklyHours'] : null;
$workdays = isset($data['workdays']) && is_array($data['workdays']) ? array_values(array_unique(array_filter($data['workdays'], 'strlen'))) : [];
$canWFH = isset($data['canWorkFromHome']) ? (bool)$data['canWorkFromHome'] : false;
$locations = isset($data['locations']) && is_array($data['locations']) ? array_values(array_unique(array_filter($data['locations'], 'strlen'))) : [];
$flexible = isset($data['flexibleWorkdays']) ? (bool)$data['flexibleWorkdays'] : false;
$dailyHours = isset($data['dailyHours']) && is_array($data['dailyHours']) ? $data['dailyHours'] : [];

// RBAC: users can update their own master data; Admin/Bereichsleiter/Standortleiter may update any
$role = resolveRole($conn, $sessionUser);
$allowed = ($sessionUser['id'] ?? null) == $userId || in_array($role, ['Admin','Bereichsleiter','Standortleiter'], true);
if (!$allowed) { send_response(403, ['message' => 'Keine Berechtigung']); }

// Validate locations against global_settings.locations (if present)
$allowedLocations = [];
if ($res = $conn->query("SHOW COLUMNS FROM global_settings LIKE 'locations'")) {
  if ($res->num_rows > 0) {
    $rs = $conn->query("SELECT locations FROM global_settings WHERE id = 1");
    if ($rs) { $row = $rs->fetch_assoc(); $rs->close(); $allowedLocations = json_decode($row['locations'] ?? '[]', true) ?: []; }
  }
  $res->close();
}
if ($locations && $allowedLocations) {
  $locations = array_values(array_intersect($locations, $allowedLocations));
}

// Detect optional columns in master_data
$cols = [];
if ($res = $conn->query("SHOW COLUMNS FROM master_data")) {
  while ($r = $res->fetch_assoc()) { $cols[strtolower($r['Field'])] = true; }
  $res->close();
}
$hasLocations = !empty($cols['locations']);
$hasFlexible = !empty($cols['flexible_workdays']);
$hasDaily = !empty($cols['daily_hours']);

// Upsert master_data
$conn->begin_transaction();
try {
  // Does record exist?
  $exists = false;
  if ($st = $conn->prepare("SELECT 1 FROM master_data WHERE user_id = ? LIMIT 1")) {
    $st->bind_param('i', $userId);
    if ($st->execute()) { $st->store_result(); $exists = $st->num_rows > 0; }
    $st->close();
  }

  $workdaysJson = json_encode($workdays, JSON_UNESCAPED_UNICODE);
  $dailyJson = json_encode($dailyHours, JSON_UNESCAPED_UNICODE);
  $locJson = json_encode($locations, JSON_UNESCAPED_UNICODE);

  if ($exists) {
    $fields = ["weekly_hours = ?", "workdays = ?", "can_work_from_home = ?"];
    $params = [$weeklyHours, $workdaysJson, $canWFH ? 1 : 0];
    $types = 'dsi';
    if ($hasLocations) { $fields[] = "locations = ?"; $params[] = $locJson; $types .= 's'; }
    if ($hasFlexible) { $fields[] = "flexible_workdays = ?"; $params[] = $flexible ? 1 : 0; $types .= 'i'; }
    if ($hasDaily) { $fields[] = "daily_hours = ?"; $params[] = $dailyJson; $types .= 's'; }
    $sql = "UPDATE master_data SET " . implode(', ', $fields) . " WHERE user_id = ?";
    $params[] = $userId; $types .= 'i';
    $st = $conn->prepare($sql);
    if (!$st) { throw new RuntimeException('Prepare failed'); }
    $st->bind_param($types, ...$params);
    if (!$st->execute()) { throw new RuntimeException('Execute failed'); }
    $st->close();
  } else {
    $colsSql = ['user_id','weekly_hours','workdays','can_work_from_home'];
    $valsSql = ['?','?','?','?'];
    $params = [$userId, $weeklyHours, $workdaysJson, $canWFH ? 1 : 0];
    $types = 'idss';
    if ($hasLocations) { $colsSql[] = 'locations'; $valsSql[] = '?'; $params[] = $locJson; $types .= 's'; }
    if ($hasFlexible) { $colsSql[] = 'flexible_workdays'; $valsSql[] = '?'; $params[] = $flexible ? 1 : 0; $types .= 'i'; }
    if ($hasDaily) { $colsSql[] = 'daily_hours'; $valsSql[] = '?'; $params[] = $dailyJson; $types .= 's'; }
    $sql = "INSERT INTO master_data (".implode(',', $colsSql).") VALUES (".implode(',', $valsSql).")";
    $st = $conn->prepare($sql);
    if (!$st) { throw new RuntimeException('Prepare failed'); }
    $st->bind_param($types, ...$params);
    if (!$st->execute()) { throw new RuntimeException('Execute failed'); }
    $st->close();
  }

  $conn->commit();
  send_response(200, ['success' => true]);
} catch (Throwable $e) {
  $conn->rollback();
  send_response(500, ['message' => 'DATABASE_ERROR', 'error' => $e->getMessage()]);
}

?>
