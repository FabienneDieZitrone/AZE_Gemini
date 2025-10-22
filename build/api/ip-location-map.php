<?php
define('API_GUARD', true);
require_once __DIR__ . '/error-handler.php';
require_once __DIR__ . '/security-middleware.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/csrf-middleware.php';
require_once __DIR__ . '/DatabaseConnection.php';

initialize_api();
initSecurityMiddleware();

header('Content-Type: application/json; charset=utf-8');

// Storage path under cache (writable on host)
$storageDir = realpath(__DIR__ . '/../cache') ?: (__DIR__ . '/../cache');
$storageFile = $storageDir . '/ip-location-map.json';

// Ensure file exists
if (!is_dir($storageDir)) { @mkdir($storageDir, 0755, true); }
if (!file_exists($storageFile)) { @file_put_contents($storageFile, json_encode(['entries'=>[]], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)); }

// Load current map
$raw = @file_get_contents($storageFile);
$map = json_decode($raw, true);
if (!is_array($map)) { $map = ['entries'=>[]]; }
if (!isset($map['entries']) || !is_array($map['entries'])) { $map['entries'] = []; }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET','PUT','OPTIONS'], true)) {
  http_response_code(405);
  header('Allow: GET, PUT, OPTIONS');
  echo json_encode(['error'=>'Method not allowed']);
  exit;
}

if ($method === 'GET') {
  echo json_encode($map, JSON_UNESCAPED_UNICODE);
  exit;
}

if ($method === 'PUT') {
  // Only Admin, Bereichsleiter, and Standortleiter may modify
  $sessionUser = verify_session_and_get_user();
  $role = $sessionUser['role'] ?? '';
  if (!in_array($role, ['Admin', 'Bereichsleiter', 'Standortleiter'], true)) {
    http_response_code(403);
    echo json_encode(['message'=>'Nur Admins, Bereichsleiter und Standortleiter dürfen die IP-Standort-Zuordnung ändern.']);
    exit;
  }
  // CSRF
  if (!function_exists('validateCsrfToken') || !validateCsrfToken()) {
    // Accept same-origin fallback
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $refHost = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
    if ($refHost !== $host) {
      http_response_code(403);
      echo json_encode(['message'=>'CSRF validation failed']);
      exit;
    }
  }
  $body = file_get_contents('php://input');
  $data = json_decode($body, true);
  if (!is_array($data) || !isset($data['entries']) || !is_array($data['entries'])) {
    http_response_code(400);
    echo json_encode(['message'=>'Ungültiges Format. Erwartet: { entries: [{ prefix, location }] }']);
    exit;
  }
  // Validate entries
  $san = [];
  foreach ($data['entries'] as $e) {
    $prefix = trim((string)($e['prefix'] ?? ''));
    $loc = trim((string)($e['location'] ?? ''));
    if ($prefix === '' || $loc === '') continue;
    // allow patterns like 10.49.1. or 192.168.0.
    if (!preg_match('/^\d{1,3}(?:\.\d{1,3}){1,3}\.?$/', $prefix)) continue;
    $san[] = ['prefix'=>$prefix, 'location'=>$loc];
  }
  $save = ['entries'=>$san];
  if (@file_put_contents($storageFile, json_encode($save, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)) === false) {
    http_response_code(500);
    echo json_encode(['message'=>'Speichern fehlgeschlagen']);
    exit;
  }
  // Persistiere: Mische alle Locations in global_settings.locations ein (alphabetisch, einzigartig)
  try {
    $conn = DatabaseConnection::getInstance()->getConnection();
    if ($conn instanceof mysqli && @$conn->ping()) {
      $locs = [];
      if ($res = $conn->query('SELECT locations FROM global_settings WHERE id = 1')) {
        if ($row = $res->fetch_assoc()) {
          $arr = json_decode($row['locations'], true);
          if (is_array($arr)) $locs = $arr;
        }
        $res->close();
      }
      $set = array_fill_keys($locs, true);
      foreach ($san as $e) {
        $lname = trim((string)$e['location']);
        if ($lname !== '' && !isset($set[$lname])) { $locs[] = $lname; $set[$lname] = true; }
      }
      // Sortiere alphabetisch, case-insensitive
      usort($locs, function($a,$b){ return strcasecmp($a,$b); });
      if ($up = $conn->prepare('UPDATE global_settings SET locations = ? WHERE id = 1')) {
        $json = json_encode($locs, JSON_UNESCAPED_UNICODE);
        $up->bind_param('s', $json);
        @$up->execute();
        $up->close();
      }
    }
  } catch (Throwable $e) { /* ignore persist errors */ }
  echo json_encode(['success'=>true, 'count'=>count($san), 'locationsCount'=>isset($locs)?count($locs):null]);
  exit;
}

http_response_code(405);
echo json_encode(['message'=>'Method not allowed']);
?>
