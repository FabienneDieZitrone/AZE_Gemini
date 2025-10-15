<?php
/**
 * Cleanup hashed frontend assets in /assets
 *
 * Behavior:
 * - Reads /index.html to determine the currently referenced bundles (index-*.js, index.es-*.js, index-*.css)
 * - Deletes old index-*.js, index.es-*.js, index-*.css files not referenced in index.html
 * - Dry-run by default (GET dry=1). Use POST do=1 to actually delete.
 * Security:
 * - Requires authenticated Admin role. For POST, CSRF check applies (same-origin fallback allowed).
 */
define('API_GUARD', true);
require_once __DIR__ . '/security-middleware.php';
require_once __DIR__ . '/auth_helpers.php';

initialize_api();
initSecurityMiddleware();

header('Content-Type: application/json; charset=utf-8');

// Auth
$user = verify_session_and_get_user();
$role = $user['role'] ?? '';
if (!in_array($role, ['Admin'], true)) {
  http_response_code(403);
  echo json_encode(['message' => 'Nur Admins dürfen diesen Endpunkt aufrufen.']);
  exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$doDelete = false;
$dryRun = true;

if ($method === 'POST') {
  // Minimal CSRF handling: reuse same-origin fallback from other endpoints
  $host = $_SERVER['HTTP_HOST'] ?? '';
  $refHost = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
  $sameOrigin = ($refHost === $host) || empty($refHost);
  // Basic guard: require do=1 and same-origin
  $payload = json_decode(file_get_contents('php://input'), true) ?: [];
  $doDelete = (isset($payload['do']) && (string)$payload['do'] === '1') || (isset($_POST['do']) && $_POST['do'] === '1');
  $dryRun = !$doDelete;
  if ($doDelete && !$sameOrigin) {
    http_response_code(403);
    echo json_encode(['message' => 'CSRF check failed (same-origin required for destructive action).']);
    exit;
  }
}

$webroot = dirname(__DIR__);
$assetsDir = $webroot . '/assets';
$indexFile = $webroot . '/index.html';

if (!is_readable($indexFile) || !is_dir($assetsDir)) {
  http_response_code(500);
  echo json_encode(['message' => 'Strukturfehler: index.html oder /assets fehlt.']);
  exit;
}

$index = file_get_contents($indexFile);
// Extract current references
$current = [
  'js' => null,
  'es' => null,
  'css' => null,
];
if (preg_match('#src="/assets/(index-[^"]+\.js)"#', $index, $m)) { $current['js'] = $m[1]; }
if (preg_match('#(src|href)="/assets/(index\.es-[^"]+\.js)"#', $index, $m)) { $current['es'] = $m[2]; }
if (preg_match('#href="/assets/(index-[^"]+\.css)"#', $index, $m)) { $current['css'] = $m[1]; }

$entries = scandir($assetsDir) ?: [];
$toDelete = [];
foreach ($entries as $f) {
  if ($f === '.' || $f === '..') continue;
  if (preg_match('#^index-.*\.js$#', $f)) {
    if ($current['js'] && $f === $current['js']) continue;
    $toDelete[] = $f;
  } elseif (preg_match('#^index\.es-.*\.js$#', $f)) {
    if ($current['es'] && $f === $current['es']) continue;
    $toDelete[] = $f;
  } elseif (preg_match('#^index-.*\.css$#', $f)) {
    if ($current['css'] && $f === $current['css']) continue;
    $toDelete[] = $f;
  }
}

$deleted = [];
if (!$dryRun) {
  foreach ($toDelete as $f) {
    $full = $assetsDir . '/' . $f;
    if (is_file($full)) {
      @unlink($full);
      $deleted[] = $f;
    }
  }
}

echo json_encode([
  'dryRun' => $dryRun,
  'current' => $current,
  'candidates' => $toDelete,
  'deleted' => $deleted,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
