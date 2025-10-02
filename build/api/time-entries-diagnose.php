<?php
define('API_GUARD', true);
header('Content-Type: application/json; charset=utf-8');

$out = [
  'endpoint' => 'time-entries-diagnose',
  'file' => __FILE__,
  'dir' => __DIR__,
  'php' => PHP_VERSION,
];

function jerr($msg) {
  echo json_encode(['error' => $msg], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
  exit;
}

try {
  require_once __DIR__ . '/db.php';
  require_once __DIR__ . '/auth_helpers.php';
  require_once __DIR__ . '/InputValidationService.php';
  initialize_api();
  $user = verify_session_and_get_user();
  $out['sessionUser'] = $user;

  // DB connection sanity
  $out['db_connected'] = isset($conn) && $conn instanceof mysqli && @$conn->ping();
  if (!$out['db_connected']) jerr('DB not connected');

  // Columns
  $cols = [];
  if ($res = $conn->query("SHOW COLUMNS FROM time_entries")) {
    while ($r = $res->fetch_assoc()) { $cols[strtolower($r['Field'])] = $r['Type']; }
    $res->close();
  }
  $out['time_entries_columns'] = $cols;

  // Resolve username -> user_id
  $username = $user['username'] ?? ($user['name'] ?? null);
  $out['username'] = $username;
  $uid = null;
  if ($stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1")) {
    $stmt->bind_param('s', $username);
    if ($stmt->execute()) {
      $stmt->bind_result($rid);
      if ($stmt->fetch()) $uid = (int)$rid;
    } else {
      $out['user_lookup_error'] = $stmt->error;
    }
    $stmt->close();
  } else {
    $out['user_lookup_prepare_error'] = $conn->error;
  }
  $out['resolved_user_id'] = $uid;

  $today = date('Y-m-d');
  // Resolve dynamic columns
  $has = function($name) use ($cols) { return array_key_exists(strtolower($name), $cols); };
  $startCol = $has('start_time') ? 'start_time' : ($has('start') ? 'start' : null);
  $stopCol = $has('stop_time') ? 'stop_time' : ($has('end_time') ? 'end_time' : null);
  $statusCol = $has('status') ? 'status' : null;
  $out['resolved_columns'] = [ 'start' => $startCol, 'stop' => $stopCol, 'status' => $statusCol ];

  // Build exists SQL
  $whereStop = '';
  if ($statusCol) $whereStop = " AND $statusCol='running'";
  elseif ($stopCol) $whereStop = " AND $stopCol IS NULL";
  $sqlExist = "SELECT id FROM time_entries WHERE user_id = ? AND date = ?" . $whereStop . " LIMIT 1";
  $out['sql_exist'] = $sqlExist;
  
  // Try execute exists if we have uid
  if ($uid && $startCol) {
    $existOk = null; $existErr = null; $existRows = 0;
    if ($stmt = $conn->prepare($sqlExist)) {
      $stmt->bind_param('is', $uid, $today);
      if ($stmt->execute()) {
        $stmt->store_result();
        $existRows = $stmt->num_rows;
        $existOk = true;
      } else { $existOk = false; $existErr = $stmt->error; }
      $stmt->free_result();
      $stmt->close();
    } else { $existOk = false; $existErr = $conn->error; }
    $out['exist_result'] = [ 'ok' => $existOk, 'rows' => $existRows, 'error' => $existErr ];
  }

  // Build check_running SQL
  $selectStop = $stopCol ? ", $stopCol AS stopTime" : ", NULL AS stopTime";
  $checkWhereStop = $whereStop; // same criterion
  $sqlCheck = "SELECT id, user_id, username, date, $startCol AS startTime" . $selectStop .
              " FROM time_entries WHERE user_id = ? AND date = ?" . $checkWhereStop .
              " ORDER BY $startCol DESC LIMIT 1";
  $out['sql_check'] = $sqlCheck;

  // Try execute check
  if ($uid && $startCol) {
    $checkOk = null; $checkErr = null; $row = null;
    if ($stmt = $conn->prepare($sqlCheck)) {
      $stmt->bind_param('is', $uid, $today);
      if ($stmt->execute()) {
        $stmt->bind_result($rid,$ruid,$ruser,$rdate,$rstart,$rstop);
        if ($stmt->fetch()) {
          $row = [ 'id'=>$rid, 'user_id'=>$ruid, 'username'=>$ruser, 'date'=>$rdate, 'startTime'=>$rstart, 'stopTime'=>$rstop ];
        }
        $checkOk = true;
      } else { $checkOk = false; $checkErr = $stmt->error; }
      $stmt->close();
    } else { $checkOk = false; $checkErr = $conn->error; }
    $out['check_result'] = [ 'ok' => $checkOk, 'row' => $row, 'error' => $checkErr ];
  }

  // Build insert preview
  $fields = ['user_id','date',$startCol ?: 'start_time'];
  $place = ['?','?','?'];
  if ($cols['username'] ?? false) { $fields[]='username'; $place[]='?'; }
  if ($cols['updated_by'] ?? false) { $fields[]='updated_by'; $place[]='?'; }
  if ($cols['status'] ?? false) { $fields[]='status'; $place[]='?'; }
  if ($cols['created_at'] ?? false) { $fields[]='created_at'; $place[]='NOW()'; }
  if ($cols['updated_at'] ?? false) { $fields[]='updated_at'; $place[]='NOW()'; }
  $sqlInsert = "INSERT INTO time_entries (".implode(',',$fields).") VALUES (".implode(',',$place).")";
  $out['sql_insert_preview'] = $sqlInsert;

  echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  echo json_encode(['error'=>'diagnose_exception','message'=>$e->getMessage()], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
?>

