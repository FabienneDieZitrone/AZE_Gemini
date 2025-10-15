<?php
// Simple migration runner for SQL files in api/migrations
// Security: requires APP_ENV!=production or a valid ?key=... matching MIGRATION_KEY env

declare(strict_types=1);

@header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/config.php';

$appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production');
$allow = $appEnv !== 'production';
$providedKey = $_GET['key'] ?? $_POST['key'] ?? '';
$requiredKey = getenv('MIGRATION_KEY') ?: ($_ENV['MIGRATION_KEY'] ?? '');
if (!$allow && (!$requiredKey || !hash_equals($requiredKey, (string)$providedKey))) {
    http_response_code(403);
    echo "Forbidden: invalid or missing key\n";
    exit;
}

// Connect to DB
$host = Config::get('db.host', 'localhost');
$user = Config::get('db.username', '');
$pass = Config::get('db.password', '');
$name = Config::get('db.name', '');

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($host, $user, $pass, $name);
if ($conn->connect_error) {
    http_response_code(500);
    echo "DB connect error: {$conn->connect_error}\n";
    exit;
}
$conn->set_charset('utf8mb4');

$migrationsDir = __DIR__ . '/api/migrations';
$files = glob($migrationsDir . '/*.sql');
sort($files);

$dry = isset($_GET['dry']) || isset($_POST['dry']);
echo "Running migrations in: {$migrationsDir}\n";
foreach ($files as $file) {
    $base = basename($file);
    echo ($dry ? '[DRY] ' : '') . "Applying: {$base}\n";
    if ($dry) { continue; }
    $sql = file_get_contents($file);
    if ($sql === false) { echo "  ! Failed to read file\n"; continue; }
    // Split by delimiter ; while ignoring semicolons in strings isn't trivial; run as a single multi_query
    if (!$conn->multi_query($sql)) {
        echo "  ! Query error: {$conn->error}\n";
        // flush rest
        while ($conn->more_results() && $conn->next_result()) {}
        continue;
    }
    do {
        if ($res = $conn->store_result()) { $res->free(); }
    } while ($conn->more_results() && $conn->next_result());
    echo "  ✓ Done\n";
}

echo "Completed.\n";

