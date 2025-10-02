<?php
header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? $_POST['action'] ?? null;
$now = date('c');
$meta = [
  'stub' => true,
  'timestamp' => $now,
  'file' => __FILE__,
  'dir' => __DIR__,
];
if ($action === 'check_running') {
  echo json_encode(['hasRunningTimer' => false, 'meta' => $meta]);
  exit;
}
if ($action === 'start') {
  echo json_encode(['id' => 999999, 'meta' => $meta]);
  exit;
}
if ($action === 'stop') {
  echo json_encode(['success' => true, 'meta' => $meta]);
  exit;
}
echo json_encode(['ok' => true, 'meta' => $meta]);
?>

