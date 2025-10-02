<?php
header('Content-Type: application/json; charset=utf-8');
$out = [ 'endpoint' => 'opcache-reset', 'file' => __FILE__, 'dir' => __DIR__, 'php' => PHP_VERSION ];
if (function_exists('opcache_get_status')) {
  $out['opcache_status_before'] = @opcache_get_status(false);
}
if (function_exists('opcache_reset')) {
  $ok = @opcache_reset();
  $out['opcache_reset'] = $ok ? 'ok' : 'failed';
} else {
  $out['opcache_reset'] = 'unavailable';
}
if (function_exists('opcache_get_status')) {
  $out['opcache_status_after'] = @opcache_get_status(false);
}
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>

