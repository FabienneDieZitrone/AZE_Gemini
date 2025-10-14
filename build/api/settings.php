<?php
/**
 * Settings API
 * GET:    Liefert globale Einstellungen
 * PUT:    Aktualisiert globale Einstellungen (Admin only)
 *
 * Response-Shape entspricht dem Frontend-Typ `GlobalSettings`:
 * { overtimeThreshold: number, changeReasons: string[], locations: string[] }
 */

define('API_GUARD', true);

require_once __DIR__ . '/security-middleware.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/csrf-middleware.php';
require_once __DIR__ . '/DatabaseConnection.php';

initialize_api();
initSecurityMiddleware();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET','PUT'], true)) {
  http_response_code(405);
  header('Allow: GET, PUT');
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['success' => false, 'error' => 'Method not allowed']);
  exit;
}

header('Content-Type: application/json; charset=utf-8');

$db = DatabaseConnection::getInstance();
$conn = $db->getConnection();

function gs_fetch(mysqli $conn): array {
  $defaults = [
    'overtimeThreshold' => 8.0,
    'changeReasons' => [
      'Vergessen einzustempeln','Vergessen auszustempeln','Dienstgang','Arzttermin','Technische Störung','Sonstige'
    ],
    'locations' => ['Zentrale Berlin','Standort Hamburg','Standort Köln']
  ];
  if ($st = $conn->prepare("SELECT overtime_threshold, change_reasons, locations FROM global_settings WHERE id = 1 LIMIT 1")) {
    if ($st->execute()) {
      $st->bind_result($thr, $reasons, $locations);
      if ($st->fetch()) {
        $st->close();
        return [
          'overtimeThreshold' => (float)$thr,
          'changeReasons' => json_decode((string)$reasons, true) ?: $defaults['changeReasons'],
          'locations' => json_decode((string)$locations, true) ?: $defaults['locations']
        ];
      }
    }
    $st->close();
  }
  return $defaults;
}

function gs_resolve_role(mysqli $conn, array $sessionUser): string {
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

if ($method === 'GET') {
  echo json_encode(gs_fetch($conn), JSON_UNESCAPED_UNICODE);
  exit;
}

// PUT: Admin-only, CSRF required (mit Same-Origin-Fallback wie andere Endpoints)
$csrfOk = function_exists('validateCsrfToken') ? validateCsrfToken() : true;
if (!$csrfOk) {
  $host = $_SERVER['HTTP_HOST'] ?? '';
  $refHost = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
  $sameOrigin = ($refHost === $host) || empty($refHost);
  if (!$sameOrigin) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF_TOKEN_INVALID', 'message' => 'Invalid or missing CSRF token.']);
    exit;
  }
}

$sessionUser = verify_session_and_get_user();
$role = gs_resolve_role($conn, $sessionUser);
if ($role !== 'Admin') {
  http_response_code(403);
  echo json_encode(['message' => 'Nur Administratoren dürfen globale Einstellungen ändern.']);
  exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

$overtimeThreshold = isset($data['overtimeThreshold']) ? (float)$data['overtimeThreshold'] : null;
$changeReasons = isset($data['changeReasons']) && is_array($data['changeReasons']) ? array_values(array_filter($data['changeReasons'], 'strlen')) : null;
$locations = isset($data['locations']) && is_array($data['locations']) ? array_values(array_filter($data['locations'], 'strlen')) : null;

if ($overtimeThreshold === null || $changeReasons === null || $locations === null) {
  http_response_code(400);
  echo json_encode(['message' => 'Ungültige Parameter']);
  exit;
}

// Begrenzte Plausibilitätsprüfungen
if ($overtimeThreshold < 0 || $overtimeThreshold > 24) {
  http_response_code(400);
  echo json_encode(['message' => 'overtimeThreshold außerhalb des gültigen Bereichs']);
  exit;
}

$reasonsJson = json_encode($changeReasons, JSON_UNESCAPED_UNICODE);
$locationsJson = json_encode($locations, JSON_UNESCAPED_UNICODE);

// Upsert id=1
$conn->begin_transaction();
try {
  $exists = false;
  if ($st = $conn->prepare("SELECT 1 FROM global_settings WHERE id = 1 LIMIT 1")) {
    if ($st->execute()) { $st->store_result(); $exists = $st->num_rows > 0; }
    $st->close();
  }

  if ($exists) {
    $st = $conn->prepare("UPDATE global_settings SET overtime_threshold = ?, change_reasons = ?, locations = ? WHERE id = 1");
    if (!$st) { throw new RuntimeException('Prepare failed'); }
    $st->bind_param('dss', $overtimeThreshold, $reasonsJson, $locationsJson);
    if (!$st->execute()) { throw new RuntimeException('Execute failed: '.$st->error); }
    $st->close();
  } else {
    $st = $conn->prepare("INSERT INTO global_settings (id, overtime_threshold, change_reasons, locations) VALUES (1, ?, ?, ?)");
    if (!$st) { throw new RuntimeException('Prepare failed'); }
    $st->bind_param('dss', $overtimeThreshold, $reasonsJson, $locationsJson);
    if (!$st->execute()) { throw new RuntimeException('Execute failed: '.$st->error); }
    $st->close();
  }

  $conn->commit();
  echo json_encode(['success' => true] + gs_fetch($conn), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  echo json_encode(['message' => 'DATABASE_ERROR', 'error' => $e->getMessage()]);
}

?>

