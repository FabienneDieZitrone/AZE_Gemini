<?php
header('Content-Type: application/json; charset=utf-8');
$out = ['endpoint'=>'time-entries-diagnose2','file'=>__FILE__,'dir'=>__DIR__,'php'=>PHP_VERSION];
try {
  require_once __DIR__ . '/db.php';
  $out['db_connected'] = isset($conn) && $conn instanceof mysqli && @$conn->ping();
  if ($out['db_connected']) {
    $cols = [];
    if ($res = $conn->query("SHOW COLUMNS FROM time_entries")) {
      while ($r = $res->fetch_assoc()) { $cols[$r['Field']] = $r['Type']; }
      $res->close();
    }
    $out['time_entries_columns'] = $cols;
    // Resolve dynamic names
    $has = function($n) use ($cols){ return array_key_exists($n,$cols); };
    $startCol = $has('start_time') ? 'start_time' : ($has('start') ? 'start' : null);
    $stopCol  = $has('stop_time')  ? 'stop_time'  : ($has('end_time')? 'end_time': null);
    $statusCol= $has('status') ? 'status' : null;
    $out['resolved'] = ['start'=>$startCol,'stop'=>$stopCol,'status'=>$statusCol];
    $out['ok'] = true;
  }
} catch (Throwable $e) { $out['exception'] = $e->getMessage(); }
echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
?>

