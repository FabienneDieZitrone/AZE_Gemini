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
    // Apply minimal migration (no FK/NULL changes)
    $sql = "ALTER TABLE approval_requests MODIFY COLUMN type ENUM('edit','delete','create') NOT NULL";
    if (!$conn->query($sql)) {
      throw new Exception('Migration failed: ' . $conn->error);
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
  $result['details']['verified'] = $verify;
  $result['ok'] = $verify;
  http_response_code($verify ? 200 : 500);
  echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  $result['error'] = $e->getMessage();
  echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

?>

