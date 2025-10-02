<?php
header('Content-Type: application/json; charset=utf-8');
$out = [ 'status' => 'PHP works', 'time' => date('Y-m-d H:i:s'), 'file' => __FILE__, 'dir' => __DIR__ ];
if (function_exists('opcache_get_status')) {
  $st = @opcache_get_status(false);
  $out['opcache_enabled'] = isset($st['opcache_enabled']) ? $st['opcache_enabled'] : null;
}
if (function_exists('opcache_reset')) {
  $ok = @opcache_reset();
  $out['opcache_reset'] = $ok ? 'ok' : 'failed';
} else {
  $out['opcache_reset'] = 'unavailable';
}
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>

