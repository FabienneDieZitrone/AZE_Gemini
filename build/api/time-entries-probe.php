<?php
define('API_GUARD', true);
header('Content-Type: application/json; charset=utf-8');

$out = [];
$out['probe'] = 'time-entries-probe';
$out['file'] = __FILE__;
$out['dir'] = __DIR__;
$out['cwd'] = getcwd();
$out['php'] = PHP_VERSION;

// Try DB
try {
    require_once __DIR__ . '/db.php';
    $out['db_connected'] = isset($conn) && $conn instanceof mysqli && $conn->ping();
    if ($out['db_connected']) {
        $cols = [];
        if ($res = $conn->query("SHOW COLUMNS FROM time_entries")) {
            while ($r = $res->fetch_assoc()) { $cols[] = $r['Field'] . ':' . $r['Type']; }
            $res->close();
        }
        $out['time_entries_columns'] = $cols;
    }
} catch (Throwable $e) {
    $out['db_error'] = $e->getMessage();
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>

