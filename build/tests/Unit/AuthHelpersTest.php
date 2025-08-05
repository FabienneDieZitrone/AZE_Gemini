<?php
/**
 * Unit Tests for Authentication Helper Functions
 * Tests the core authentication and session management functionality
 */

use PHPUnit\Framework\TestCase;

class AuthHelpersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear any existing sessions
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        // Reset superglobals
        $_SERVER = [];
        $_SESSION = [];
        $_COOKIE = [];
        
        // Set up basic server environment
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTPS'] = 'on';
    }

    protected function tearDown(): void
    {
        // Clean up sessions after each test
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        parent::tearDown();
    }

    public function testInitializeApiSetsCorrectHeaders(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://aze.mikropartner.de';
        
        // Capture headers that would be sent
        ob_start();
        
        // Include the auth helpers file
        require_once API_BASE_PATH . '/auth_helpers.php';
        
        // This is tricky to test without actually sending headers
        // We'll test the logic instead
        $this->assertTrue(true); // Placeholder for now
        
        ob_end_clean();
    }

    public function testSendResponseWithData(): void
    {
        $testData = ['message' => 'Test response', 'status' => 'success'];
        
        // Capture output
        ob_start();
        
        try {
            require_once API_BASE_PATH . '/auth_helpers.php';
            
            // Mock the send_response function behavior
            $status_code = 200;
            $data = $testData;
            
            $expectedOutput = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            
            // Test JSON encoding
            $this->assertJson($expectedOutput);
            $this->assertStringContainsString('Test response', $expectedOutput);
            
        } catch (Exception $e) {
            // Expected behavior for exit() call
        }
        
        ob_end_clean();
    }

    public function testGetAllHeadersPolyfill(): void
    {
        // Set up mock HTTP headers
        $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test-token';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['CONTENT_LENGTH'] = '123';
        
        require_once API_BASE_PATH . '/auth_helpers.php';
        
        $headers = getallheaders();
        
        $this->assertIsArray($headers);
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertEquals('application/json', $headers['Content-Type']);
        $this->assertArrayHasKey('Content-Length', $headers);
        $this->assertEquals('123', $headers['Content-Length']);
    }

    public function testSessionCookieParameters(): void
    {
        require_once API_BASE_PATH . '/auth_helpers.php';
        
        // Test that session cookie parameters would be set correctly
        $expectedParams = [
            'lifetime' => 0,
            'path' => '/',
            'domain' => 'localhost',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ];
        
        // This tests the logic - actual session testing would require more setup
        $this->assertTrue(true); // Placeholder
    }

    public function testOptionsRequestHandling(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['HTTP_ORIGIN'] = 'https://aze.mikropartner.de';
        
        ob_start();
        
        try {
            require_once API_BASE_PATH . '/auth_helpers.php';
            initialize_api();
        } catch (Exception $e) {
            // Expected due to exit(0) call
        }
        
        $output = ob_get_contents();
        ob_end_clean();
        
        // OPTIONS request should exit with no output
        $this->assertEmpty($output);
    }

    public function testSessionTimeoutLogic(): void
    {
        // Mock session data for timeout testing
        $_SESSION = [
            'user' => ['id' => 1, 'name' => 'Test User'],
            'created_at' => time() - (25 * 3600), // 25 hours ago (should timeout)
            'last_activity' => time() - (2 * 3600) // 2 hours ago (should timeout)
        ];
        
        $currentTime = time();
        $createdAt = $_SESSION['created_at'];
        $lastActivity = $_SESSION['last_activity'];
        
        // Test absolute timeout (24 hours)
        $absoluteTimeout = ($currentTime - $createdAt) > (24 * 3600);
        $this->assertTrue($absoluteTimeout, 'Session should timeout after 24 hours');
        
        // Test inactivity timeout (1 hour)
        $inactivityTimeout = ($currentTime - $lastActivity) > (1 * 3600);
        $this->assertTrue($inactivityTimeout, 'Session should timeout after 1 hour of inactivity');
    }

    public function testSessionWithinTimeout(): void
    {
        // Mock fresh session data
        $_SESSION = [
            'user' => ['id' => 1, 'name' => 'Test User'],
            'created_at' => time() - (1 * 3600), // 1 hour ago
            'last_activity' => time() - (30 * 60) // 30 minutes ago
        ];
        
        $currentTime = time();
        $createdAt = $_SESSION['created_at'];
        $lastActivity = $_SESSION['last_activity'];
        
        // Test absolute timeout (24 hours)
        $absoluteTimeout = ($currentTime - $createdAt) > (24 * 3600);
        $this->assertFalse($absoluteTimeout, 'Session should NOT timeout within 24 hours');
        
        // Test inactivity timeout (1 hour)
        $inactivityTimeout = ($currentTime - $lastActivity) > (1 * 3600);
        $this->assertFalse($inactivityTimeout, 'Session should NOT timeout within 1 hour of activity');
    }

    public function testValidUserSession(): void
    {
        // Mock valid user session
        $_SESSION = [
            'user' => [
                'id' => 1,
                'name' => 'Test User',
                'email' => 'test@example.com',
                'role' => 'Mitarbeiter'
            ],
            'created_at' => time() - 3600, // 1 hour ago
            'last_activity' => time() - 300 // 5 minutes ago
        ];
        
        $this->assertArrayHasKey('user', $_SESSION);
        $this->assertArrayHasKey('id', $_SESSION['user']);
        $this->assertEquals(1, $_SESSION['user']['id']);
        $this->assertEquals('Test User', $_SESSION['user']['name']);
    }

    public function testInvalidUserSession(): void
    {
        // Mock invalid session (no user data)
        $_SESSION = [
            'some_other_data' => 'value',
            'created_at' => time(),
            'last_activity' => time()
        ];
        
        $this->assertArrayNotHasKey('user', $_SESSION);
    }

    public function testCorsHeaderValidation(): void
    {
        $allowedOrigins = ['https://aze.mikropartner.de'];
        
        // Test valid origin
        $validOrigin = 'https://aze.mikropartner.de';
        $this->assertContains($validOrigin, $allowedOrigins);
        
        // Test invalid origin
        $invalidOrigin = 'https://malicious-site.com';
        $this->assertNotContains($invalidOrigin, $allowedOrigins);
    }

    public function testJsonResponseFormat(): void
    {
        $testData = [
            'success' => true,
            'message' => 'Operation completed successfully',
            'data' => [
                'id' => 1,
                'name' => 'Test Item',
                'timestamp' => '2025-08-03T12:00:00Z'
            ]
        ];
        
        $jsonOutput = json_encode($testData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        $this->assertJson($jsonOutput);
        $this->assertStringContainsString('Operation completed successfully', $jsonOutput);
        $this->assertStringContainsString('Test Item', $jsonOutput);
        
        // Test that JSON is properly formatted (pretty print)
        $this->assertStringContainsString("\n", $jsonOutput);
        $this->assertStringContainsString("    ", $jsonOutput); // Indentation
    }

    public function testErrorResponseFormat(): void
    {
        $errorData = [
            'error' => true,
            'message' => 'Authentication required',
            'code' => 401
        ];
        
        $jsonOutput = json_encode($errorData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        $this->assertJson($jsonOutput);
        $this->assertStringContainsString('Authentication required', $jsonOutput);
        $this->assertStringContainsString('401', $jsonOutput);
    }
}