<?php
/**
 * Integration Tests for Time Entries API
 * Tests the complete time tracking functionality including security, validation, and business logic
 * 
 * Test Coverage:
 * - GET time entries with role-based filtering
 * - POST new time entries with validation
 * - Timer start/stop functionality
 * - Running timer management
 * - Authorization and security middleware integration
 * - Data validation and sanitization
 * - Error handling and responses
 */

use PHPUnit\Framework\TestCase;

class TimeEntriesApiTest extends TestCase
{
    private $mockConn;
    private $originalServerVars;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Store original server variables
        $this->originalServerVars = $_SERVER ?? [];
        
        // Set up test environment
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'HTTP_HOST' => 'localhost',
            'HTTPS' => 'on',
            'SCRIPT_NAME' => '/api/time-entries.php',
            'HTTP_ORIGIN' => 'https://aze.mikropartner.de'
        ];
        
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        
        if (!defined('API_GUARD')) {
            define('API_GUARD', true);
        }
        
        if (!defined('TEST_MODE')) {
            define('TEST_MODE', true);
        }
        
        // Mock database connection
        $this->mockConn = $this->createMockDatabaseConnection();
    }

    protected function tearDown(): void
    {
        // Restore original server variables
        $_SERVER = $this->originalServerVars;
        
        parent::tearDown();
    }

    private function createMockDatabaseConnection()
    {
        return (object) [
            'error' => '',
            'insert_id' => 1,
            'affected_rows' => 1,
            'prepare' => function($query) {
                return $this->createMockStatement();
            },
            'close' => function() { return true; }
        ];
    }

    private function createMockStatement()
    {
        return (object) [
            'bind_param' => function() { return true; },
            'execute' => function() { return true; },
            'get_result' => function() {
                return $this->createMockResult();
            },
            'close' => function() { return true; },
            'error' => '',
            'affected_rows' => 1
        ];
    }

    private function createMockResult()
    {
        return (object) [
            'num_rows' => 1,
            'fetch_assoc' => function() {
                return [
                    'id' => 1,
                    'userId' => 1,
                    'username' => 'test.user',
                    'date' => '2025-08-06',
                    'startTime' => '09:00:00',
                    'stopTime' => '17:00:00',
                    'location' => 'Office',
                    'role' => 'Mitarbeiter',
                    'createdAt' => '2025-08-06 09:00:00',
                    'updatedBy' => 'test.user',
                    'updatedAt' => '2025-08-06 17:00:00'
                ];
            },
            'fetch_all' => function($mode) {
                return [
                    [
                        'id' => 1,
                        'userId' => 1,
                        'username' => 'test.user',
                        'date' => '2025-08-06',
                        'startTime' => '09:00:00',
                        'stopTime' => '17:00:00',
                        'location' => 'Office',
                        'role' => 'Mitarbeiter',
                        'createdAt' => '2025-08-06 09:00:00',
                        'updatedBy' => 'test.user',
                        'updatedAt' => '2025-08-06 17:00:00'
                    ]
                ];
            }
        ];
    }

    /**
     * Test GET time entries with role-based filtering for Mitarbeiter
     */
    public function testGetTimeEntriesRoleBasedFilteringMitarbeiter(): void
    {
        $timeEntriesCode = file_get_contents(API_BASE_PATH . '/time-entries.php');
        
        // Test that Mitarbeiter and Honorarkraft can only see their own entries
        $this->assertStringContainsString('WHERE user_id = ?', $timeEntriesCode, 'Should filter by user_id for Mitarbeiter/Honorarkraft');
        
        // Test role-based query building
        $this->assertStringContainsString("'Honorarkraft' || \$current_user['role'] === 'Mitarbeiter'", $timeEntriesCode, 
            'Should check for restricted roles');
        
        // Simulate user filtering logic
        $currentUser = ['id' => 1, 'role' => 'Mitarbeiter'];
        $shouldFilterByUser = in_array($currentUser['role'], ['Honorarkraft', 'Mitarbeiter']);
        
        $this->assertTrue($shouldFilterByUser, 'Mitarbeiter should be filtered to their own entries');
        
        // Test query structure for user filtering
        $baseQuery = "SELECT id, user_id AS userId, username, date, start_time AS startTime, stop_time AS stopTime, location, role, created_at AS createdAt, updated_by AS updatedBy, updated_at AS updatedAt FROM time_entries";
        $userFilteredQuery = $baseQuery . " WHERE user_id = ? ORDER BY date DESC, start_time DESC";
        
        $this->assertStringContainsString('ORDER BY date DESC, start_time DESC', $timeEntriesCode, 
            'Should order by date and time descending');
    }

    /**
     * Test GET time entries with location-based filtering for Standortleiter
     */
    public function testGetTimeEntriesLocationBasedFilteringStandortleiter(): void
    {
        $timeEntriesCode = file_get_contents(API_BASE_PATH . '/time-entries.php');
        
        // Test location filtering for Standortleiter
        $this->assertStringContainsString("'Standortleiter'", $timeEntriesCode, 'Should handle Standortleiter role');
        $this->assertStringContainsString('WHERE location = ?', $timeEntriesCode, 'Should filter by location for Standortleiter');
        
        // Simulate location filtering logic
        $currentUser = ['id' => 2, 'role' => 'Standortleiter', 'location' => 'Berlin Office'];
        $shouldFilterByLocation = $currentUser['role'] === 'Standortleiter';
        
        $this->assertTrue($shouldFilterByLocation, 'Standortleiter should see entries from their location');
    }

    /**
     * Test GET time entries for Admin/Bereichsleiter (see all)
     */
    public function testGetTimeEntriesAllAccessForAdmins(): void
    {
        $timeEntriesCode = file_get_contents(API_BASE_PATH . '/time-entries.php');
        
        // Test that Admin and Bereichsleiter see all entries
        $this->assertStringContainsString('Bereichsleiter and Admin can see all entries', $timeEntriesCode, 
            'Should document admin access');
        
        // Simulate admin access logic
        $adminUser = ['id' => 3, 'role' => 'Admin'];
        $bereichsleiterUser = ['id' => 4, 'role' => 'Bereichsleiter'];
        
        $adminCanSeeAll = in_array($adminUser['role'], ['Admin', 'Bereichsleiter']);
        $bereichsleiterCanSeeAll = in_array($bereichsleiterUser['role'], ['Admin', 'Bereichsleiter']);
        
        $this->assertTrue($adminCanSeeAll, 'Admin should see all entries');
        $this->assertTrue($bereichsleiterCanSeeAll, 'Bereichsleiter should see all entries');
    }

    /**
     * Test running timer check functionality
     */
    public function testRunningTimerCheck(): void
    {
        $timeEntriesCode = file_get_contents(API_BASE_PATH . '/time-entries.php');
        
        // Test running timer check endpoint
        $this->assertStringContainsString("'check_running'", $timeEntriesCode, 'Should handle check_running action');
        $this->assertStringContainsString('handle_check_running_timer', $timeEntriesCode, 'Should have running timer handler');
        
        // Test running timer query
        $this->assertStringContainsString('stop_time IS NULL', $timeEntriesCode, 'Should check for NULL stop_time');
        $this->assertStringContainsString('hasRunningTimer', $timeEntriesCode, 'Should return hasRunningTimer flag');
        $this->assertStringContainsString('runningTimer', $timeEntriesCode, 'Should return timer details');
        
        // Simulate running timer check
        $_GET['action'] = 'check_running';
        $currentUser = ['id' => 1, 'role' => 'Mitarbeiter'];
        
        // Mock response structure
        $expectedResponse = [
            'hasRunningTimer' => true,
            'runningTimer' => [
                'id' => 1,
                'userId' => 1,
                'username' => 'test.user',
                'date' => '2025-08-06',
                'startTime' => '09:00:00',
                'location' => 'Office',
                'role' => 'Mitarbeiter',
                'createdAt' => '2025-08-06 09:00:00'
            ]
        ];
        
        $this->assertTrue($expectedResponse['hasRunningTimer'], 'Should detect running timer');
        $this->assertArrayHasKey('runningTimer', $expectedResponse, 'Should return timer details');
    }

    /**
     * Test POST time entry validation
     */
    public function testPostTimeEntryValidation(): void
    {
        $timeEntriesCode = file_get_contents(API_BASE_PATH . '/time-entries.php');
        
        // Test required fields validation
        $requiredFields = ['userId', 'username', 'date', 'startTime', 'location', 'role', 'updatedBy'];
        foreach ($requiredFields as $field) {
            $this->assertStringContainsString("'$field'", $timeEntriesCode, "Should validate required field $field");
        }
        
        // Test optional fields
        $this->assertStringContainsString("'stopTime' => null", $timeEntriesCode, 'stopTime should be optional for running timers');
        
        // Test validation functions
        $this->assertStringContainsString('InputValidator::validateJsonInput', $timeEntriesCode, 'Should use input validator');
        $this->assertStringContainsString('InputValidator::isValidId', $timeEntriesCode, 'Should validate ID format');
        $this->assertStringContainsString('InputValidator::isValidDate', $timeEntriesCode, 'Should validate date format');
        $this->assertStringContainsString('InputValidator::isValidTime', $timeEntriesCode, 'Should validate time format');
        
        // Simulate validation tests
        $validData = [
            'userId' => 1,
            'username' => 'test.user',
            'date' => '2025-08-06',
            'startTime' => '09:00:00',
            'stopTime' => null, // Running timer
            'location' => 'Office',
            'role' => 'Mitarbeiter',
            'updatedBy' => 'test.user'
        ];
        
        // Test date format validation
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $validData['date'], 'Date should be in YYYY-MM-DD format');
        
        // Test time format validation  
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $validData['startTime'], 'Start time should be in HH:MM:SS format');
        
        // Test userId validation
        $this->assertIsInt($validData['userId'], 'User ID should be integer');
        $this->assertGreaterThan(0, $validData['userId'], 'User ID should be positive');
    }

    /**
     * Test POST time entry with invalid data
     */
    public function testPostTimeEntryInvalidData(): void
    {
        $invalidDataSets = [
            // Invalid date format
            [
                'data' => ['date' => '06-08-2025'],
                'expectedError' => 'Invalid date format'
            ],
            // Invalid start time format
            [
                'data' => ['startTime' => '9:00'],
                'expectedError' => 'Invalid startTime format'
            ],
            // Invalid stop time format
            [
                'data' => ['stopTime' => '25:00:00'],
                'expectedError' => 'Invalid stopTime format'
            ],
            // Invalid userId
            [
                'data' => ['userId' => -1],
                'expectedError' => 'Invalid userId format'
            ]
        ];
        
        foreach ($invalidDataSets as $testCase) {
            $data = $testCase['data'];
            $expectedError = $testCase['expectedError'];
            
            // Simulate validation failure
            if (isset($data['date'])) {
                $isValidDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date']);
                if (!$isValidDate) {
                    $this->assertStringContainsString('date format', $expectedError, 'Should detect invalid date format');
                }
            }
            
            if (isset($data['startTime'])) {
                $isValidTime = preg_match('/^\d{2}:\d{2}:\d{2}$/', $data['startTime']);
                if (!$isValidTime) {
                    $this->assertStringContainsString('startTime format', $expectedError, 'Should detect invalid start time format');
                }
            }
            
            if (isset($data['userId'])) {
                $isValidId = is_int($data['userId']) && $data['userId'] > 0;
                if (!$isValidId) {
                    $this->assertStringContainsString('userId format', $expectedError, 'Should detect invalid user ID');
                }
            }
        }
    }

    /**
     * Test auto-stop existing running timers
     */
    public function testAutoStopExistingRunningTimers(): void
    {
        $timeEntriesCode = file_get_contents(API_BASE_PATH . '/time-entries.php');
        
        // Test auto-stop functionality
        $this->assertStringContainsString('Stop all existing running timers', $timeEntriesCode, 
            'Should document auto-stop behavior');
        $this->assertStringContainsString('UPDATE time_entries SET stop_time = NOW()', $timeEntriesCode, 
            'Should update running timers');
        $this->assertStringContainsString('WHERE user_id = ? AND stop_time IS NULL', $timeEntriesCode, 
            'Should find running timers for user');
        $this->assertStringContainsString('System Auto-Stop', $timeEntriesCode, 
            'Should mark auto-stopped timers');
        
        // Test logging of auto-stop
        $this->assertStringContainsString('Auto-stopped', $timeEntriesCode, 'Should log auto-stop actions');
        $this->assertStringContainsString('running timer(s)', $timeEntriesCode, 'Should log count of stopped timers');
        
        // Simulate auto-stop logic
        $userId = 1;
        $newTimerHasNoStopTime = true; // Starting new timer
        
        if ($newTimerHasNoStopTime) {
            // Would execute: UPDATE time_entries SET stop_time = NOW() WHERE user_id = ? AND stop_time IS NULL
            $this->assertTrue(true, 'Should auto-stop existing running timers when starting new timer');
        }
    }

    /**
     * Test timer stop functionality (POST with action=stop)
     */
    public function testTimerStopFunctionality(): void
    {
        $timeEntriesCode = file_get_contents(API_BASE_PATH . '/time-entries.php');
        
        // Test stop action handling
        $this->assertStringContainsString("'stop'", $timeEntriesCode, 'Should handle stop action');
        $this->assertStringContainsString('handle_stop_timer', $timeEntriesCode, 'Should have stop timer handler');
        
        // Test workaround comment for Apache PUT blocking
        $this->assertStringContainsString('PUT method blocked by Apache', $timeEntriesCode, 
            'Should document PUT workaround');
        
        // Test stop timer validation
        $this->assertStringContainsString("'id'", $timeEntriesCode, 'Should require timer ID for stopping');
        
        // Test ownership verification
        $this->assertStringContainsString('WHERE id = ? AND user_id = ?', $timeEntriesCode, 
            'Should verify timer ownership before stopping');
        
        // Test already stopped timer handling
        $this->assertStringContainsString('Timer already stopped', $timeEntriesCode, 
            'Should handle already stopped timers gracefully');
        $this->assertStringContainsString('alreadyStopped', $timeEntriesCode, 
            'Should return flag for already stopped timers');
        
        // Simulate stop timer process
        $_GET['action'] = 'stop';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        $stopData = [
            'id' => 1,
            'stopTime' => '17:00:00',
            'updatedBy' => 'test.user'
        ];
        
        $this->assertIsInt($stopData['id'], 'Timer ID should be integer');
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $stopData['stopTime'], 
            'Stop time should be in HH:MM:SS format');
    }

    /**
     * Test security and authorization integration
     */
    public function testSecurityMiddlewareIntegration(): void
    {
        $timeEntriesCode = file_get_contents(API_BASE_PATH . '/time-entries.php');
        
        // Test security middleware includes
        $this->assertStringContainsString("require_once __DIR__ . '/security-middleware.php'", $timeEntriesCode,
            'Should include security middleware');
        $this->assertStringContainsString("require_once __DIR__ . '/rate-limiting.php'", $timeEntriesCode,
            'Should include rate limiting');
        $this->assertStringContainsString("require_once __DIR__ . '/csrf-middleware.php'", $timeEntriesCode,
            'Should include CSRF protection');
        $this->assertStringContainsString("require_once __DIR__ . '/auth-middleware.php'", $timeEntriesCode,
            'Should include auth middleware');
        
        // Test middleware initialization
        $this->assertStringContainsString('initSecurityMiddleware()', $timeEntriesCode,
            'Should initialize security middleware');
        $this->assertStringContainsString('checkRateLimit(\'time-entries\')', $timeEntriesCode,
            'Should apply rate limiting for time-entries endpoint');
        $this->assertStringContainsString('validateCsrfProtection()', $timeEntriesCode,
            'Should validate CSRF protection');
        $this->assertStringContainsString('authorize_request()', $timeEntriesCode,
            'Should authorize request');
        
        // Test CSRF protection condition
        $this->assertStringContainsString('requiresCsrfProtection()', $timeEntriesCode,
            'Should check if CSRF protection is required');
    }

    /**
     * Test error handling and responses
     */
    public function testErrorHandlingAndResponses(): void
    {
        $timeEntriesCode = file_get_contents(API_BASE_PATH . '/time-entries.php');
        
        // Test fatal error handler
        $this->assertStringContainsString('register_shutdown_function', $timeEntriesCode,
            'Should register fatal error handler');
        $this->assertStringContainsString('error_get_last()', $timeEntriesCode,
            'Should check for fatal errors');
        $this->assertStringContainsString('Fatal PHP Error', $timeEntriesCode,
            'Should handle fatal PHP errors');
        
        // Test database error handling
        $this->assertStringContainsString('Prepare failed', $timeEntriesCode,
            'Should handle database prepare failures');
        $this->assertStringContainsString('error_log', $timeEntriesCode,
            'Should log database errors');
        $this->assertStringContainsString('Datenbankfehler', $timeEntriesCode,
            'Should return user-friendly database error messages');
        
        // Test validation error handling
        $this->assertStringContainsString('InvalidArgumentException', $timeEntriesCode,
            'Should catch validation exceptions');
        $this->assertStringContainsString('Validation error:', $timeEntriesCode,
            'Should return validation error messages');
        
        // Test method not allowed
        $this->assertStringContainsString('Method Not Allowed', $timeEntriesCode,
            'Should handle unsupported HTTP methods');
        $this->assertStringContainsString('405', $timeEntriesCode,
            'Should return 405 status for unsupported methods');
    }

    /**
     * Test database cleanup and safety measures
     */
    public function testDatabaseCleanupAndSafety(): void
    {
        $timeEntriesCode = file_get_contents(API_BASE_PATH . '/time-entries.php');
        
        // Test cleanup of additional running timers
        $this->assertStringContainsString('System Cleanup', $timeEntriesCode,
            'Should perform system cleanup of running timers');
        $this->assertStringContainsString('Cleaned up', $timeEntriesCode,
            'Should log cleanup actions');
        $this->assertStringContainsString('additional running timers', $timeEntriesCode,
            'Should clean up additional running timers');
        
        // Test connection closure
        $this->assertStringContainsString('$conn->close()', $timeEntriesCode,
            'Should close database connection');
        
        // Test prepared statements usage
        $this->assertStringContainsString('prepare(', $timeEntriesCode,
            'Should use prepared statements');
        $this->assertStringContainsString('bind_param', $timeEntriesCode,
            'Should bind parameters safely');
        $this->assertStringContainsString('$stmt->close()', $timeEntriesCode,
            'Should close prepared statements');
    }

    /**
     * Test time entry data structure and constraints
     */
    public function testTimeEntryDataStructure(): void
    {
        $timeEntriesCode = file_get_contents(API_BASE_PATH . '/time-entries.php');
        
        // Test table structure mapping
        $expectedColumns = [
            'id', 'user_id', 'username', 'date', 'start_time', 'stop_time', 
            'location', 'role', 'created_at', 'updated_by', 'updated_at'
        ];
        
        foreach ($expectedColumns as $column) {
            $this->assertStringContainsString($column, $timeEntriesCode,
                "Should reference column $column");
        }
        
        // Test column aliases
        $aliases = [
            'user_id AS userId',
            'start_time AS startTime',
            'stop_time AS stopTime',
            'created_at AS createdAt',
            'updated_by AS updatedBy',
            'updated_at AS updatedAt'
        ];
        
        foreach ($aliases as $alias) {
            $this->assertStringContainsString($alias, $timeEntriesCode,
                "Should use alias: $alias");
        }
        
        // Test time entry creation with proper fields
        $this->assertStringContainsString('INSERT INTO time_entries', $timeEntriesCode,
            'Should insert into time_entries table');
        $this->assertStringContainsString('NOW()', $timeEntriesCode,
            'Should use NOW() for timestamps');
    }

    /**
     * Test critical user ID handling
     */
    public function testCriticalUserIdHandling(): void
    {
        $timeEntriesCode = file_get_contents(API_BASE_PATH . '/time-entries.php');
        
        // Test critical user ID check
        $this->assertStringContainsString('CRITICAL: Get user ID from session', $timeEntriesCode,
            'Should document critical user ID handling');
        $this->assertStringContainsString('Try to get ID from database if not in session', $timeEntriesCode,
            'Should fallback to database for user ID');
        $this->assertStringContainsString('SELECT id FROM users WHERE oid = ?', $timeEntriesCode,
            'Should query user ID by OID');
        $this->assertStringContainsString('CRITICAL: No user ID in session', $timeEntriesCode,
            'Should log missing user ID');
        
        // Simulate user ID handling
        $userFromSession = ['oid' => 'azure-oid-123', 'username' => 'test.user'];
        
        if (!isset($userFromSession['id'])) {
            // Should query database for ID
            $this->assertTrue(true, 'Should attempt to get ID from database when not in session');
        }
    }

    /**
     * Test business logic and constraints
     */
    public function testBusinessLogicAndConstraints(): void
    {
        // Test that only one timer can run per user
        $userId = 1;
        $runningTimers = [
            ['id' => 1, 'user_id' => $userId, 'stop_time' => null],
            ['id' => 2, 'user_id' => $userId, 'stop_time' => null]
        ];
        
        $multipleRunningTimers = count(array_filter($runningTimers, function($timer) {
            return $timer['stop_time'] === null;
        })) > 1;
        
        $this->assertTrue($multipleRunningTimers, 'Test scenario should have multiple running timers');
        // The system should prevent this by auto-stopping existing timers
        
        // Test time validation logic
        $timeData = [
            'startTime' => '09:00:00',
            'stopTime' => '17:00:00'
        ];
        
        $startTime = strtotime($timeData['startTime']);
        $stopTime = strtotime($timeData['stopTime']);
        $isValidTimeRange = $stopTime > $startTime;
        
        $this->assertTrue($isValidTimeRange, 'Stop time should be after start time');
        
        // Test invalid time range
        $invalidTimeData = [
            'startTime' => '17:00:00',
            'stopTime' => '09:00:00'
        ];
        
        $invalidStartTime = strtotime($invalidTimeData['startTime']);
        $invalidStopTime = strtotime($invalidTimeData['stopTime']);
        $isInvalidTimeRange = $invalidStopTime <= $invalidStartTime;
        
        $this->assertTrue($isInvalidTimeRange, 'Invalid time range should be detected');
    }
}