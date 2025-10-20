<?php
// Lightweight wrapper with early diagnostics to avoid fatals during includes

// CRITICAL: Session name MUST be set first (consistent with login.php)
session_name('AZE_SESSION');

define('API_GUARD', true);

// Ultra-early ping (no includes)
if (isset($_GET['ping'])) {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'ping' => 'ok',
    'file' => __FILE__,
    'dir' => __DIR__,
    'php' => PHP_VERSION,
    'ts' => date('c')
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  exit;
}

// Lightweight diagnostics that do not require full endpoint bootstrap
if ((isset($_GET['diag']) || (isset($_GET['action']) && $_GET['action'] === 'diag'))) {
  header('Content-Type: application/json; charset=utf-8');
  $out = [ 'endpoint' => 'time-entries.php?diag=1', 'php' => PHP_VERSION, 'file' => __FILE__, 'dir' => __DIR__ ];
  try {
    require_once __DIR__ . '/DatabaseConnection.php';
    $conn = DatabaseConnection::getInstance()->getConnection();
    $out['db_connected'] = @$conn->ping();
    $cols = [];
    if ($res = $conn->query('SHOW COLUMNS FROM time_entries')) {
      while ($r = $res->fetch_assoc()) { $cols[$r['Field']] = $r['Type']; }
      $res->close();
    }
    $out['columns'] = $cols;
    $has = function($n) use ($cols){ return array_key_exists($n,$cols); };
    $out['resolved'] = [
      'start' => $has('start_time') ? 'start_time' : ($has('start') ? 'start' : null),
      'stop'  => $has('stop_time') ? 'stop_time' : ($has('end_time') ? 'end_time' : null),
      'status'=> $has('status') ? 'status' : null,
    ];
    if ($st = $conn->prepare('SELECT COUNT(*) FROM time_entries')) { $st->execute(); $st->bind_result($c); if ($st->fetch()) { $out['count'] = (int)$c; } $st->close(); }
  } catch (Throwable $e) {
    $out['diag_error'] = $e->getMessage();
  }
  echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  exit;
}

// Delegate to implementation
require_once __DIR__ . '/time-entries.impl.php';
