<?php
// Enable 'create' approval type in live schema (idempotent)
define('API_GUARD', true);
@header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/DatabaseConnection.php';

$db = DatabaseConnection::getInstance();
$conn = $db->getConnection();

$result = [
  'ok' => false,
  'changed' => false,
  'details' => []
];

try {
  // Detect if 'type' enum contains 'create'
  $hasCreate = false;
  if ($res = $conn->query("SHOW COLUMNS FROM approval_requests LIKE 'type'")) {
    if ($row = $res->fetch_assoc()) {
      $type = strtolower($row['Type'] ?? '');
      $hasCreate = (strpos($type, "'create'") !== false);
    }
    $res->close();
  }
  $result['details']['hasCreate'] = $hasCreate;

  if (!$hasCreate) {
    // Apply minimal migration (add 'create' to enum)
    $sql = "ALTER TABLE approval_requests MODIFY COLUMN type ENUM('edit','delete','create') NOT NULL";
    if (!$conn->query($sql)) {
      throw new Exception('Migration failed (enum): ' . $conn->error);
    }
    $result['changed'] = true;
  }

  // Ensure entry_id is nullable (needed for create requests)
  $entryIdNullable = false;
  if ($res = $conn->query("SHOW COLUMNS FROM approval_requests LIKE 'entry_id'")) {
    if ($row = $res->fetch_assoc()) {
      $entryIdNullable = (strtolower($row['Null'] ?? '') === 'yes');
    }
    $res->close();
  }
  $result['details']['entryIdNullable'] = $entryIdNullable;

  if (!$entryIdNullable) {
    $sql = "ALTER TABLE approval_requests MODIFY COLUMN entry_id INT NULL";
    if (!$conn->query($sql)) {
      throw new Exception('Migration failed (entry_id nullable): ' . $conn->error);
    }
    $result['changed'] = true;
  }

  // Verify
  $verify = false;
  if ($res = $conn->query("SHOW COLUMNS FROM approval_requests LIKE 'type'")) {
    if ($row = $res->fetch_assoc()) {
      $type = strtolower($row['Type'] ?? '');
      $verify = (strpos($type, "'create'") !== false);
    }
    $res->close();
  }
  // Re-verify entry_id nullability
  $entryIdNullable2 = false;
  if ($res = $conn->query("SHOW COLUMNS FROM approval_requests LIKE 'entry_id'")) {
    if ($row = $res->fetch_assoc()) {
      $entryIdNullable2 = (strtolower($row['Null'] ?? '') === 'yes');
    }
    $res->close();
  }
  $verifyAll = $verify && $entryIdNullable2;
  $result['details']['verified'] = $verifyAll;
  $result['ok'] = $verifyAll;
  http_response_code($verifyAll ? 200 : 500);
  echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  $result['error'] = $e->getMessage();
  echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

?>
