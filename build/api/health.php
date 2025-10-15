<?php
/**
 * Health Check Endpoint for AZE_Gemini
 * Vereinheitlicht: Security via security-middleware + DatabaseConnection
 */

define('API_GUARD', true);

require_once __DIR__ . '/security-middleware.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/DatabaseConnection.php';
require_once __DIR__ . '/structured-logger.php';

initialize_api();
initSecurityMiddleware();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false,'error'=>'Method not allowed']);
    exit;
}

$health = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'checks' => []
];

// DB
try {
    $mysqli = DatabaseConnection::getInstance()->getConnection();
    $result = $mysqli->query('SELECT 1');
    if ($result) {
        $health['checks']['database'] = [ 'status' => 'healthy', 'message' => 'Database connection successful' ];
        $result->close();
    } else {
        throw new Exception('Database query failed');
    }
} catch (Throwable $e) {
    $health['status'] = 'unhealthy';
    $health['checks']['database'] = [ 'status' => 'unhealthy', 'message' => 'Database connection failed', 'error' => $e->getMessage() ];
}

// Session
try {
    start_secure_session();
    if (session_status() === PHP_SESSION_ACTIVE) {
        $health['checks']['session'] = [ 'status' => 'healthy', 'message' => 'Session active' ];
    } else {
        throw new Exception('Session not active');
    }
} catch (Throwable $e) {
    $health['status'] = 'unhealthy';
    $health['checks']['session'] = [ 'status' => 'unhealthy', 'message' => 'Session check failed', 'error' => $e->getMessage() ];
}

// Filesystem
try {
    $logDir = __DIR__ . '/../logs';
    if (is_writable($logDir) || (!is_dir($logDir) && is_writable(dirname($logDir)))) {
        $health['checks']['filesystem'] = [ 'status' => 'healthy', 'message' => 'Log directory writable' ];
    } else {
        throw new Exception('Log directory not writable');
    }
} catch (Throwable $e) {
    $health['status'] = 'unhealthy';
    $health['checks']['filesystem'] = [ 'status' => 'unhealthy', 'message' => 'Filesystem check failed', 'error' => $e->getMessage() ];
}

// PHP extensions
$requiredExtensions = ['mysqli','json','session','openssl'];
$missingExtensions = array_values(array_filter($requiredExtensions, fn($ext)=>!extension_loaded($ext)));
if (empty($missingExtensions)) {
    $health['checks']['php_extensions'] = [ 'status' => 'healthy', 'message' => 'All required extensions loaded' ];
} else {
    $health['status'] = 'unhealthy';
    $health['checks']['php_extensions'] = [ 'status' => 'unhealthy', 'message' => 'Missing extensions', 'missing' => $missingExtensions ];
}

// Memory
$memoryUsage = memory_get_usage(true);
$memoryLimit = ini_get('memory_limit');
$health['checks']['memory'] = [ 'status' => 'healthy', 'current' => $memoryUsage, 'limit' => $memoryLimit ];

// Disk space (optional)
try {
    $freeSpace = @disk_free_space(__DIR__);
    $totalSpace = @disk_total_space(__DIR__);
    if ($freeSpace !== false && $totalSpace !== false && $totalSpace > 0) {
        $usedPercentage = round((($totalSpace - $freeSpace) / $totalSpace) * 100, 2);
        $health['checks']['disk_space'] = [ 'status' => ($usedPercentage < 90 ? 'healthy' : 'warning'), 'used_percentage' => $usedPercentage ];
    }
} catch (Throwable $e) { /* optional */ }

http_response_code($health['status'] === 'healthy' ? 200 : 503);
logInfo('Health check performed', ['status' => $health['status']]);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

?>

