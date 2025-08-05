<?php
/**
 * PHPUnit Bootstrap File
 * Sets up test environment and autoloading
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

// Set timezone for consistent date/time testing
date_default_timezone_set('Europe/Berlin');

// Define test constants
define('TEST_MODE', true);
define('TEST_BASE_PATH', __DIR__ . '/..');
define('API_BASE_PATH', TEST_BASE_PATH . '/api');

// Mock environment variables for testing
$_ENV['TEST_ENVIRONMENT'] = 'testing';

// Include necessary API files for testing
require_once API_BASE_PATH . '/constants.php';

// Test database configuration (you might want to use a separate test DB)
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
        'email' => "testuser$id@example.com",
        'role' => $role,
        'created_at' => date('Y-m-d H:i:s')
    ];
}

// Helper function to create test time entry data
function createTestTimeEntry($id = 1, $userId = 1) {
    return [
        'id' => $id,
        'userId' => $userId,
        'date' => date('Y-m-d'),
        'startTime' => '09:00:00',
        'stopTime' => '17:00:00',
        'status' => 'Erfasst',
        'reason' => 'Reguläre Arbeitszeit',
        'reasonData' => json_encode([
            'type' => 'work',
            'location' => 'Büro',
            'projectId' => null,
            'customReason' => null
        ]),
        'created_at' => date('Y-m-d H:i:s')
    ];
}

// Clean up function for tests
function cleanupTestData() {
    // This would clean up any test data created during tests
    // Implementation depends on your database setup
}

// Register cleanup function
register_shutdown_function('cleanupTestData');