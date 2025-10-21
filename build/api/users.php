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
require_once __DIR__ . '/debug-helpers.php';

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

hlog('PATCH /api/users.php', [
    'userId' => $userId,
    'newRole' => $newRole,
    'sessionUser' => $sessionUser['name'] ?? 'unknown'
], 'info');

if ($userId <= 0 || $newRole === '') {
    hlog('Validation failed', 'Invalid userId or newRole', 'error');
    send_response(400, ['message' => 'Ungültige Parameter']);
}

$allowedRoles = ['Admin','Bereichsleiter','Standortleiter','Mitarbeiter','Honorarkraft'];
if (!in_array($newRole, $allowedRoles, true)) { send_response(400, ['message' => 'Ungültige Rolle']); }

$actorRole = getUserRole($conn, $sessionUser);

hlog('Permission check', [
    'actorRole' => $actorRole,
    'targetUserId' => $userId,
    'newRole' => $newRole
], 'info');

// Only Admin/Bereichsleiter/Standortleiter may update other users
if (!in_array($actorRole, ['Admin','Bereichsleiter','Standortleiter'], true)) {
  hlog('Permission denied', 'Actor role not allowed: ' . $actorRole, 'error');
  send_response(403, ['message' => 'Keine Berechtigung']);
}

// Optionally prevent privilege escalation by non-admins to Admin role
if ($actorRole !== 'Admin' && $newRole === 'Admin') {
  hlog('Permission denied', 'Only Admin can assign Admin role', 'error');
  send_response(403, ['message' => 'Nur Administratoren dürfen Admin-Rollen vergeben']);
}

// Prepare UPDATE statement
$st = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
if (!$st) {
    error_log("users.php: Failed to prepare UPDATE statement");
    send_response(500, ['message' => 'Database error (prepare)']);
}

$st->bind_param('si', $newRole, $userId);
if (!$st->execute()) {
    $e = $st->error;
    error_log("users.php: Failed to execute UPDATE - Error: $e");
    $st->close();
    send_response(500, ['message' => 'Database error (execute)', 'error' => $e]);
}

// Check if any rows were actually updated
$affectedRows = $st->affected_rows;
$st->close();

hlog('UPDATE executed', [
    'affected_rows' => $affectedRows,
    'userId' => $userId,
    'newRole' => $newRole
], $affectedRows > 0 ? 'success' : 'warning');

if ($affectedRows === 0) {
    // Either user doesn't exist, or role is already set to this value
    // Verify which case it is
    $userExists = false;
    $currentRole = null;
    if ($checkStmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1")) {
        $checkStmt->bind_param('i', $userId);
        if ($checkStmt->execute()) {
            $checkStmt->bind_result($currentRole);
            $userExists = $checkStmt->fetch();
        }
        $checkStmt->close();
    }

    if (!$userExists) {
        hlog('User not found', "User ID $userId does not exist", 'error');
        error_log("users.php: User ID $userId not found");
        send_response(404, ['message' => "Benutzer mit ID $userId nicht gefunden"]);
    } else if ($currentRole === $newRole) {
        hlog('Role unchanged', "User ID $userId already has role $newRole", 'info');
        error_log("users.php: User ID $userId already has role $newRole");
        send_response(200, ['success' => true, 'message' => 'Rolle war bereits gesetzt', 'unchanged' => true]);
    } else {
        // This shouldn't happen - UPDATE should have worked
        hlog('Unexpected state', [
            'userId' => $userId,
            'currentRole' => $currentRole,
            'newRole' => $newRole,
            'message' => 'User exists but UPDATE did not work'
        ], 'error');
        error_log("users.php: Unexpected state - user exists but UPDATE didn't work. Current role: $currentRole, New role: $newRole");
        send_response(500, ['message' => 'Update fehlgeschlagen (unerwarteter Fehler)']);
    }
} else {
    // Success - verify the update actually worked
    $verifiedRole = null;
    if ($verifyStmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1")) {
        $verifyStmt->bind_param('i', $userId);
        if ($verifyStmt->execute()) {
            $verifyStmt->bind_result($verifiedRole);
            $verifyStmt->fetch();
        }
        $verifyStmt->close();
    }

    if ($verifiedRole === $newRole) {
        hlog('Role update successful', [
            'userId' => $userId,
            'newRole' => $verifiedRole,
            'verified' => true
        ], 'success');
        error_log("users.php: Successfully updated user ID $userId to role $newRole");
        send_response(200, ['success' => true, 'newRole' => $verifiedRole]);
    } else {
        hlog('Verification failed', [
            'userId' => $userId,
            'expected' => $newRole,
            'actual' => $verifiedRole,
            'message' => 'UPDATE reported success but verification failed'
        ], 'error');
        error_log("users.php: UPDATE reported success but verification failed. Expected: $newRole, Got: $verifiedRole");
        send_response(500, ['message' => 'Update scheinbar erfolgreich, aber Verifikation fehlgeschlagen']);
    }
}