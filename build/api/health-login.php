<?php
/**
 * Enhanced Health Check for Login Dependencies
 * Specifically checks all requirements for login.php to work
 */

header('Content-Type: application/json');

$health = [
    'endpoint' => 'login.php health check',
    'status' => 'healthy',
    'timestamp' => date('c'),
    'checks' => []
];

// 1. Check config.php and environment variables
try {
    require_once __DIR__ . '/../config.php';
    
    $dbHost = Config::get('db.host');
    $dbUser = Config::get('db.username');
    $dbPass = Config::get('db.password');
    $dbName = Config::get('db.name');
    
    $configStatus = [
        'status' => 'healthy',
        'details' => [
            'host_configured' => !empty($dbHost),
            'username_configured' => !empty($dbUser),
            'password_configured' => !empty($dbPass),
            'name_configured' => !empty($dbName)
        ]
    ];
    
    if (empty($dbUser) || empty($dbPass) || empty($dbName)) {
        $configStatus['status'] = 'unhealthy';
        $configStatus['message'] = 'Missing database configuration';
        $health['status'] = 'unhealthy';
    }
    
    $health['checks']['configuration'] = $configStatus;
    
} catch (Exception $e) {
    $health['status'] = 'unhealthy';
    $health['checks']['configuration'] = [
        'status' => 'unhealthy',
        'error' => $e->getMessage()
    ];
}

// 2. Check all required files
$requiredFiles = [
    'security-headers.php',
    'error-handler.php',
    'structured-logger.php',
    'security-middleware.php',
    'db-init.php',
    'db-wrapper.php',
    'db.php',
    'auth_helpers.php',
    'validation.php'
];

$missingFiles = [];
foreach ($requiredFiles as $file) {
    if (!file_exists(__DIR__ . '/' . $file)) {
        $missingFiles[] = $file;
    }
}

if (empty($missingFiles)) {
    $health['checks']['required_files'] = [
        'status' => 'healthy',
        'message' => 'All required files present'
    ];
} else {
    $health['status'] = 'unhealthy';
    $health['checks']['required_files'] = [
        'status' => 'unhealthy',
        'missing' => $missingFiles
    ];
}

// 3. Test database connection
if (!empty($dbHost) && !empty($dbUser) && !empty($dbName)) {
    try {
        $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
        if ($conn->connect_error) {
            throw new Exception($conn->connect_error);
        }
        
        // Test a query
        $result = $conn->query("SELECT 1 as test");
        if (!$result) {
            throw new Exception("Query failed");
        }
        
        // Check required tables
        $requiredTables = ['users', 'master_data', 'time_entries', 'approval_requests', 'global_settings'];
        $result = $conn->query("SHOW TABLES");
        $existingTables = [];
        while ($row = $result->fetch_array()) {
            $existingTables[] = $row[0];
        }
        
        $missingTables = array_diff($requiredTables, $existingTables);
        
        $dbStatus = [
            'status' => 'healthy',
            'details' => [
                'connection' => 'successful',
                'server_version' => $conn->server_info,
                'charset' => $conn->character_set_name()
            ]
        ];
        
        if (!empty($missingTables)) {
            $dbStatus['status'] = 'unhealthy';
            $dbStatus['missing_tables'] = $missingTables;
            $health['status'] = 'unhealthy';
        }
        
        $health['checks']['database'] = $dbStatus;
        $conn->close();
        
    } catch (Exception $e) {
        $health['status'] = 'unhealthy';
        $health['checks']['database'] = [
            'status' => 'unhealthy',
            'error' => $e->getMessage()
        ];
    }
} else {
    $health['checks']['database'] = [
        'status' => 'skipped',
        'message' => 'No database configuration'
    ];
}

// 4. Check session configuration
$sessionCheck = [
    'status' => 'healthy',
    'details' => [
        'save_path' => ini_get('session.save_path') ?: '/tmp',
        'save_path_writable' => is_writable(ini_get('session.save_path') ?: '/tmp'),
        'cookie_secure' => ini_get('session.cookie_secure'),
        'cookie_httponly' => ini_get('session.cookie_httponly')
    ]
];

if (!$sessionCheck['details']['save_path_writable']) {
    $sessionCheck['status'] = 'unhealthy';
    $sessionCheck['message'] = 'Session save path not writable';
    $health['status'] = 'unhealthy';
}

$health['checks']['session'] = $sessionCheck;

// 5. Check PHP extensions
$requiredExtensions = ['mysqli', 'json', 'session'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (empty($missingExtensions)) {
    $health['checks']['php_extensions'] = [
        'status' => 'healthy',
        'loaded' => $requiredExtensions
    ];
} else {
    $health['status'] = 'unhealthy';
    $health['checks']['php_extensions'] = [
        'status' => 'unhealthy',
        'missing' => $missingExtensions
    ];
}

// 6. Check OAuth configuration
$oauthSecret = Config::get('oauth.client_secret');
$health['checks']['oauth'] = [
    'status' => !empty($oauthSecret) ? 'healthy' : 'warning',
    'configured' => !empty($oauthSecret)
];

// 7. Directory permissions
$directories = [
    '../logs' => 'Log directory',
    '../cache' => 'Cache directory',
    '../cache/rate-limit' => 'Rate limit cache'
];

$dirStatus = ['status' => 'healthy', 'details' => []];
foreach ($directories as $dir => $name) {
    $fullPath = __DIR__ . '/' . $dir;
    $exists = is_dir($fullPath);
    $writable = $exists ? is_writable($fullPath) : is_writable(dirname($fullPath));
    
    $dirStatus['details'][$name] = [
        'exists' => $exists,
        'writable' => $writable
    ];
    
    if (!$writable) {
        $dirStatus['status'] = 'warning';
    }
}
$health['checks']['directories'] = $dirStatus;

// Set HTTP status code
http_response_code($health['status'] === 'healthy' ? 200 : 503);

// Output result
echo json_encode($health, JSON_PRETTY_PRINT);
?>