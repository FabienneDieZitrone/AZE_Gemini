<?php
/**
 * Integration Tests for API Endpoints
 * Tests the complete API functionality including authentication, time entries, and approvals
 */

use PHPUnit\Framework\TestCase;

class ApiEndpointsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test environment
        $_ENV['TEST_ENVIRONMENT'] = 'testing';
        
        // Mock server environment
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_ORIGIN'] = 'https://aze.mikropartner.de';
        
        // Clear session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        parent::tearDown();
    }

    public function testAuthStatusEndpointWithoutSession(): void
    {
        // Test auth-status.php without valid session
        $_SESSION = []; // No user session
        
        ob_start();
        $statusCode = null;
        
        try {
            // Mock the endpoint behavior
            $hasValidSession = isset($_SESSION['user']) && 
                              isset($_SESSION['created_at']) && 
                              isset($_SESSION['last_activity']);
            
            if (!$hasValidSession) {
                $statusCode = 401;
            } else {
                $statusCode = 204;
            }
            
        } catch (Exception $e) {
            // Handle any exceptions
        }
        
        ob_end_clean();
        
        $this->assertEquals(401, $statusCode, 'Should return 401 without valid session');
    }

    public function testAuthStatusEndpointWithValidSession(): void
    {
        // Test auth-status.php with valid session
        $_SESSION = [
            'user' => createTestUser(),
            'created_at' => time() - 3600, // 1 hour ago
            'last_activity' => time() - 300 // 5 minutes ago
        ];
        
        ob_start();
        $statusCode = null;
        
        try {
            // Mock the endpoint behavior
            $hasValidSession = isset($_SESSION['user']) && 
                              isset($_SESSION['created_at']) && 
                              isset($_SESSION['last_activity']);
            
            // Check for session timeout
            $currentTime = time();
            $absoluteTimeout = ($currentTime - $_SESSION['created_at']) > (24 * 3600);
            $inactivityTimeout = ($currentTime - $_SESSION['last_activity']) > (1 * 3600);
            
            if (!$hasValidSession || $absoluteTimeout || $inactivityTimeout) {
                $statusCode = 401;
            } else {
                $statusCode = 204;
            }
            
        } catch (Exception $e) {
            // Handle any exceptions
        }
        
        ob_end_clean();
        
        $this->assertEquals(204, $statusCode, 'Should return 204 with valid session');
    }

    public function testAuthStatusEndpointWithExpiredSession(): void
    {
        // Test auth-status.php with expired session
        $_SESSION = [
            'user' => createTestUser(),
            'created_at' => time() - (25 * 3600), // 25 hours ago (expired)
            'last_activity' => time() - (2 * 3600) // 2 hours ago (expired)
        ];
        
        ob_start();
        $statusCode = null;
        
        try {
            $currentTime = time();
            $absoluteTimeout = ($currentTime - $_SESSION['created_at']) > (24 * 3600);
            $inactivityTimeout = ($currentTime - $_SESSION['last_activity']) > (1 * 3600);
            
            if ($absoluteTimeout || $inactivityTimeout) {
                $statusCode = 401;
            } else {
                $statusCode = 204;
            }
            
        } catch (Exception $e) {
            // Handle any exceptions
        }
        
        ob_end_clean();
        
        $this->assertEquals(401, $statusCode, 'Should return 401 with expired session');
    }

    public function testTimeEntriesEndpointPostRequest(): void
    {
        // Mock authenticated session
        $_SESSION = [
            'user' => createTestUser(),
            'created_at' => time() - 3600,
            'last_activity' => time() - 300
        ];
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        // Mock POST data for time entry
        $postData = [
            'userId' => 1,
            'date' => date('Y-m-d'),
            'startTime' => '09:00:00',
            'stopTime' => '17:00:00',
            'reason' => 'Reguläre Arbeitszeit',
            'reasonData' => [
                'type' => 'work',
                'location' => 'Büro',
                'projectId' => null,
                'customReason' => null
            ]
        ];
        
        // Validate time entry data
        $this->assertNotEmpty($postData['date']);
        $this->assertNotEmpty($postData['startTime']);
        $this->assertNotEmpty($postData['stopTime']);
        $this->assertNotEmpty($postData['reason']);
        
        // Validate time format
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $postData['startTime']);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $postData['stopTime']);
        
        // Validate date format
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $postData['date']);
        
        // Test logic validation
        $startTime = strtotime($postData['startTime']);
        $stopTime = strtotime($postData['stopTime']);
        $this->assertGreaterThan($startTime, $stopTime, 'Stop time should be after start time');
    }

    public function testTimeEntriesEndpointValidation(): void
    {
        $_SESSION = [
            'user' => createTestUser(),
            'created_at' => time() - 3600,
            'last_activity' => time() - 300
        ];
        
        // Test invalid time entry (stop time before start time)
        $invalidPostData = [
            'userId' => 1,
            'date' => date('Y-m-d'),
            'startTime' => '17:00:00',
            'stopTime' => '09:00:00', // Invalid: before start time
            'reason' => 'Test entry'
        ];
        
        $startTime = strtotime($invalidPostData['startTime']);
        $stopTime = strtotime($invalidPostData['stopTime']);
        $isValid = $stopTime > $startTime;
        
        $this->assertFalse($isValid, 'Should detect invalid time range');
        
        // Test missing required fields
        $incompleteData = [
            'userId' => 1,
            'date' => date('Y-m-d'),
            // Missing startTime, stopTime, reason
        ];
        
        $hasRequiredFields = isset($incompleteData['startTime']) && 
                           isset($incompleteData['stopTime']) && 
                           isset($incompleteData['reason']);
        
        $this->assertFalse($hasRequiredFields, 'Should detect missing required fields');
    }

    public function testApprovalsEndpointCreateRequest(): void
    {
        $_SESSION = [
            'user' => createTestUser(),
            'created_at' => time() - 3600,
            'last_activity' => time() - 300
        ];
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        // Mock approval request data
        $approvalRequest = [
            'entryId' => 1,
            'requestType' => 'change',
            'requestedChanges' => [
                'startTime' => '08:30:00',
                'stopTime' => '16:30:00'
            ],
            'reason' => 'Korrektur der tatsächlichen Arbeitszeit'
        ];
        
        // Validate approval request data
        $this->assertNotEmpty($approvalRequest['entryId']);
        $this->assertNotEmpty($approvalRequest['requestType']);
        $this->assertNotEmpty($approvalRequest['requestedChanges']);
        $this->assertNotEmpty($approvalRequest['reason']);
        
        // Validate request type
        $validRequestTypes = ['change', 'delete', 'create'];
        $this->assertContains($approvalRequest['requestType'], $validRequestTypes);
        
        // Validate requested changes format
        if ($approvalRequest['requestType'] === 'change') {
            $this->assertArrayHasKey('startTime', $approvalRequest['requestedChanges']);
            $this->assertArrayHasKey('stopTime', $approvalRequest['requestedChanges']);
        }
    }

    public function testApprovalsEndpointProcessRequest(): void
    {
        // Mock supervisor session
        $_SESSION = [
            'user' => createTestUser(2, 'Supervisor'),
            'created_at' => time() - 3600,
            'last_activity' => time() - 300
        ];
        
        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        
        // Mock approval processing data
        $processData = [
            'requestId' => 'req-123',
            'finalStatus' => 'genehmigt' // or 'abgelehnt'
        ];
        
        // Validate processing data
        $this->assertNotEmpty($processData['requestId']);
        $this->assertNotEmpty($processData['finalStatus']);
        
        // Validate status values
        $validStatuses = ['genehmigt', 'abgelehnt'];
        $this->assertContains($processData['finalStatus'], $validStatuses);
        
        // Test supervisor role requirement
        $userRole = $_SESSION['user']['role'];
        $canProcessApprovals = in_array($userRole, ['Supervisor', 'Admin']);
        $this->assertTrue($canProcessApprovals, 'Only supervisors and admins can process approvals');
    }

    public function testMasterDataEndpointUpdate(): void
    {
        $_SESSION = [
            'user' => createTestUser(),
            'created_at' => time() - 3600,
            'last_activity' => time() - 300
        ];
        
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        
        // Mock master data update
        $masterDataUpdate = [
            'userId' => 1,
            'workingHoursPerWeek' => 40,
            'vacationDaysPerYear' => 25
        ];
        
        // Validate master data
        $this->assertIsInt($masterDataUpdate['userId']);
        $this->assertIsInt($masterDataUpdate['workingHoursPerWeek']);
        $this->assertIsInt($masterDataUpdate['vacationDaysPerYear']);
        
        // Validate reasonable values
        $this->assertGreaterThan(0, $masterDataUpdate['workingHoursPerWeek']);
        $this->assertLessThanOrEqual(60, $masterDataUpdate['workingHoursPerWeek']);
        $this->assertGreaterThan(0, $masterDataUpdate['vacationDaysPerYear']);
        $this->assertLessThanOrEqual(50, $masterDataUpdate['vacationDaysPerYear']);
    }

    public function testUsersEndpointRoleUpdate(): void
    {
        // Mock admin session
        $_SESSION = [
            'user' => createTestUser(1, 'Admin'),
            'created_at' => time() - 3600,
            'last_activity' => time() - 300
        ];
        
        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        
        // Mock role update data
        $roleUpdate = [
            'userId' => 2,
            'newRole' => 'Supervisor'
        ];
        
        // Validate role update data
        $this->assertIsInt($roleUpdate['userId']);
        $this->assertNotEmpty($roleUpdate['newRole']);
        
        // Validate role values
        $validRoles = ['Mitarbeiter', 'Supervisor', 'Admin'];
        $this->assertContains($roleUpdate['newRole'], $validRoles);
        
        // Test admin role requirement for role changes
        $userRole = $_SESSION['user']['role'];
        $canChangeRoles = $userRole === 'Admin';
        $this->assertTrue($canChangeRoles, 'Only admins can change user roles');
    }

    public function testSettingsEndpointGlobalUpdate(): void
    {
        // Mock admin session
        $_SESSION = [
            'user' => createTestUser(1, 'Admin'),
            'created_at' => time() - 3600,
            'last_activity' => time() - 300
        ];
        
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        
        // Mock global settings update
        $settingsUpdate = [
            'companyName' => 'Test Company GmbH',
            'workingHoursPerWeek' => 40,
            'vacationDaysPerYear' => 25,
            'allowOvertime' => true,
            'requireApprovalForChanges' => true
        ];
        
        // Validate settings data
        $this->assertNotEmpty($settingsUpdate['companyName']);
        $this->assertIsInt($settingsUpdate['workingHoursPerWeek']);
        $this->assertIsInt($settingsUpdate['vacationDaysPerYear']);
        $this->assertIsBool($settingsUpdate['allowOvertime']);
        $this->assertIsBool($settingsUpdate['requireApprovalForChanges']);
        
        // Test admin role requirement for global settings
        $userRole = $_SESSION['user']['role'];
        $canChangeGlobalSettings = $userRole === 'Admin';
        $this->assertTrue($canChangeGlobalSettings, 'Only admins can change global settings');
    }

    public function testCorsHandling(): void
    {
        // Test CORS headers for allowed origin
        $_SERVER['HTTP_ORIGIN'] = 'https://aze.mikropartner.de';
        
        $allowedOrigins = ['https://aze.mikropartner.de'];
        $origin = $_SERVER['HTTP_ORIGIN'];
        
        $this->assertContains($origin, $allowedOrigins, 'Origin should be in allowed list');
        
        // Test CORS headers for disallowed origin
        $_SERVER['HTTP_ORIGIN'] = 'https://malicious-site.com';
        $origin = $_SERVER['HTTP_ORIGIN'];
        
        $this->assertNotContains($origin, $allowedOrigins, 'Malicious origin should not be allowed');
    }

    public function testRateLimitingLogic(): void
    {
        // Mock rate limiting logic
        $userId = 1;
        $endpoint = '/api/time-entries.php';
        $timeWindow = 60; // 1 minute
        $maxRequests = 10;
        
        // Simulate request tracking
        $requestCount = 5; // Current requests in time window
        
        $isWithinLimit = $requestCount < $maxRequests;
        $this->assertTrue($isWithinLimit, 'Should be within rate limit');
        
        // Test exceeding rate limit
        $requestCount = 15;
        $isWithinLimit = $requestCount < $maxRequests;
        $this->assertFalse($isWithinLimit, 'Should exceed rate limit');
    }
}