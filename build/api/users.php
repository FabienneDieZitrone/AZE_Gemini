<?php
/**
 * Users API
 * PATCH: update user role (RBAC enforced)
 */
define('API_GUARD', true);

require_once __DIR__ . '/security-middleware.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/csrf-middleware.php';
require_once __DIR__ . '/DatabaseConnection.php';

initialize_api();
initSecurityMiddleware();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'PATCH') {
  http_response_code(405);
  header('Allow: PATCH');
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['error'=>'Method not allowed']);
  exit;
}

// CSRF validation
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

$sessionUser = verify_session_and_get_user();

function getUserRole(mysqli $conn, array $sessionUser): string {
  $oid = $sessionUser['azure_oid'] ?? ($sessionUser['oid'] ?? null);
  $username = $sessionUser['username'] ?? null;
  $role = '';
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

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

$userId = isset($data['userId']) ? (int)$data['userId'] : 0;
$newRole = isset($data['newRole']) ? (string)$data['newRole'] : '';
if ($userId <= 0 || $newRole === '') { send_response(400, ['message' => 'Ungültige Parameter']); }

$allowedRoles = ['Admin','Bereichsleiter','Standortleiter','Mitarbeiter','Honorarkraft'];
if (!in_array($newRole, $allowedRoles, true)) { send_response(400, ['message' => 'Ungültige Rolle']); }

$actorRole = getUserRole($conn, $sessionUser);
// Only Admin/Bereichsleiter/Standortleiter may update other users
if (!in_array($actorRole, ['Admin','Bereichsleiter','Standortleiter'], true)) {
  send_response(403, ['message' => 'Keine Berechtigung']);
}

// Optionally prevent privilege escalation by non-admins to Admin role
if ($actorRole !== 'Admin' && $newRole === 'Admin') {
  send_response(403, ['message' => 'Nur Administratoren dürfen Admin-Rollen vergeben']);
}

$st = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
if (!$st) { send_response(500, ['message' => 'Database error (prepare)']); }
$st->bind_param('si', $newRole, $userId);
if (!$st->execute()) { $e = $st->error; $st->close(); send_response(500, ['message' => 'Database error (execute)', 'error' => $e]); }
$st->close();

send_response(200, ['success' => true]);

?>
