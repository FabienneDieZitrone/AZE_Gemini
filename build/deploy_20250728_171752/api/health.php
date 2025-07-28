<?php
/**
 * Health Check Endpoint for AZE_Gemini
 * 
 * Provides system health status for monitoring
 */

require_once __DIR__ . '/security-headers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/structured-logger.php';

// Initialize security (no auth required for health check)
initializeSecurity(false);
validateRequestMethod('GET');

$health = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'checks' => []
];

// Check database connection
try {
    $mysqli = initDB();
    $result = $mysqli->query("SELECT 1");
    if ($result) {
        $health['checks']['database'] = [
            'status' => 'healthy',
            'message' => 'Database connection successful'
        ];
        $result->close();
    } else {
        throw new Exception('Database query failed');
    }
} catch (Exception $e) {
    $health['status'] = 'unhealthy';
    $health['checks']['database'] = [
        'status' => 'unhealthy',
        'message' => 'Database connection failed',
        'error' => $e->getMessage()
    ];
}

// Check session functionality
try {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $health['checks']['session'] = [
            'status' => 'healthy',
            'message' => 'Session functionality working'
        ];
    } else {
        throw new Exception('Session not active');
    }
} catch (Exception $e) {
    $health['status'] = 'unhealthy';
    $health['checks']['session'] = [
        'status' => 'unhealthy',
        'message' => 'Session functionality failed',
        'error' => $e->getMessage()
    ];
}

// Check file system (logs directory)
try {
    $logDir = __DIR__ . '/../logs';
    if (is_writable($logDir) || (!is_dir($logDir) && is_writable(dirname($logDir)))) {
        $health['checks']['filesystem'] = [
            'status' => 'healthy',
            'message' => 'Log directory is writable'
        ];
    } else {
        throw new Exception('Log directory not writable');
    }
} catch (Exception $e) {
    $health['status'] = 'unhealthy';
    $health['checks']['filesystem'] = [
        'status' => 'unhealthy',
        'message' => 'Filesystem check failed',
        'error' => $e->getMessage()
    ];
}

// Check PHP extensions
$requiredExtensions = ['mysqli', 'json', 'session', 'openssl'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (empty($missingExtensions)) {
    $health['checks']['php_extensions'] = [
        'status' => 'healthy',
        'message' => 'All required PHP extensions are loaded'
    ];
} else {
    $health['status'] = 'unhealthy';
    $health['checks']['php_extensions'] = [
        'status' => 'unhealthy',
        'message' => 'Missing PHP extensions',
        'missing' => $missingExtensions
    ];
}

// Memory usage
$memoryUsage = memory_get_usage(true);
$memoryLimit = ini_get('memory_limit');
$health['checks']['memory'] = [
    'status' => 'healthy',
    'current' => formatBytes($memoryUsage),
    'limit' => $memoryLimit
];

// Disk space (if possible)
try {
    $freeSpace = disk_free_space(__DIR__);
    $totalSpace = disk_total_space(__DIR__);
    $usedPercentage = round((($totalSpace - $freeSpace) / $totalSpace) * 100, 2);
    
    $health['checks']['disk_space'] = [
        'status' => $usedPercentage < 90 ? 'healthy' : 'warning',
        'free' => formatBytes($freeSpace),
        'total' => formatBytes($totalSpace),
        'used_percentage' => $usedPercentage
    ];
} catch (Exception $e) {
    // Disk space check is optional
}

// Set appropriate HTTP status code
http_response_code($health['status'] === 'healthy' ? 200 : 503);

// Log health check
logInfo('Health check performed', ['status' => $health['status']]);

// Return JSON response
header('Content-Type: application/json');
echo json_encode($health, JSON_PRETTY_PRINT);

/**
 * Format bytes to human readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}