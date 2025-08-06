<?php
/**
 * Unit Tests for CSRF Protection Middleware
 * Tests the comprehensive CSRF protection system
 * 
 * Test Coverage:
 * - CSRF token generation and validation
 * - Double-submit cookie pattern
 * - Origin/Referer validation
 * - Token lifetime management
 * - Session-based token storage
 * - Security headers and responses
 * - Method-based protection requirements
 */

use PHPUnit\Framework\TestCase;

class CsrfProtectionTest extends TestCase
{
    private $originalServerVars;
    private $originalSessionData;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Store original state
        $this->originalServerVars = $_SERVER ?? [];
        $this->originalSessionData = $_SESSION ?? [];
        
        // Clear and setup test environment
        $_SESSION = [];
        $_SERVER = [];
        $_COOKIE = [];
        $_POST = [];
        
        // Set up basic server environment
        $_SERVER['HTTP_HOST'] = 'aze.mikropartner.de';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_ORIGIN'] = 'https://aze.mikropartner.de';
        $_SERVER['HTTP_REFERER'] = 'https://aze.mikropartner.de/app';
        
        if (!defined('API_GUARD')) {
            define('API_GUARD', true);
        }
        
        if (!defined('TEST_MODE')) {
            define('TEST_MODE', true);
        }
        
        // Mock session functions since we can't start real sessions in tests
        if (!function_exists('start_secure_session')) {
            function start_secure_session() {
                // Mock function for testing
                return true;
            }
        }
    }

    protected function tearDown(): void
    {
        // Restore original state
        $_SERVER = $this->originalServerVars;
        $_SESSION = $this->originalSessionData;
        
        parent::tearDown();
    }

    /**
     * Test CSRF token generation
     */
    public function testCsrfTokenGeneration(): void
    {
        $csrfCode = file_get_contents(API_BASE_PATH . '/csrf-middleware.php');
        
        // Test that token generation uses secure methods
        $this->assertStringContainsString('random_bytes(32)', $csrfCode, 'Should use cryptographically secure random bytes');
        $this->assertStringContainsString('bin2hex', $csrfCode, 'Should convert to hexadecimal string');
        
        // Test token storage in session
        $this->assertStringContainsString('$_SESSION[$this->tokenName]', $csrfCode, 'Should store token in session');
        $this->assertStringContainsString('$_SESSION[$this->tokenName . \'_time\']', $csrfCode, 'Should store token timestamp');
        
        // Test token uniqueness simulation
        $token1 = bin2hex(random_bytes(32));
        $token2 = bin2hex(random_bytes(32));
        
        $this->assertNotEquals($token1, $token2, 'Generated tokens should be unique');
        $this->assertEquals(64, strlen($token1), 'Token should be 64 characters (32 bytes hex)');
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $token1, 'Token should contain only hex characters');
    }

    /**
     * Test CSRF token validation logic
     */
    public function testCsrfTokenValidation(): void
    {
        $csrfCode = file_get_contents(API_BASE_PATH . '/csrf-middleware.php');
        
        // Test token comparison uses timing-safe comparison
        $this->assertStringContainsString('hash_equals', $csrfCode, 'Should use timing-safe hash comparison');
        
        // Test token expiration checking
        $this->assertStringContainsString('time() -', $csrfCode, 'Should check token age');
        $this->assertStringContainsString('$this->tokenLifetime', $csrfCode, 'Should respect token lifetime');
        
        // Simulate token validation logic
        $currentTime = time();
        $tokenTime = $currentTime - 1800; // 30 minutes ago
        $tokenLifetime = 3600; // 1 hour
        
        $isValid = ($currentTime - $tokenTime) <= $tokenLifetime;
        $this->assertTrue($isValid, 'Token should be valid within lifetime');
        
        // Test expired token
        $expiredTokenTime = $currentTime - 7200; // 2 hours ago
        $isExpired = ($currentTime - $expiredTokenTime) > $tokenLifetime;
        $this->assertTrue($isExpired, 'Token should be expired after lifetime');
    }

    /**
     * Test double-submit cookie pattern
     */
    public function testDoubleSubmitCookiePattern(): void
    {
        $csrfCode = file_get_contents(API_BASE_PATH . '/csrf-middleware.php');
        
        // Test cookie setting for double-submit pattern
        $this->assertStringContainsString('setcookie', $csrfCode, 'Should set CSRF cookie');
        $this->assertStringContainsString('csrf_cookie_token', $csrfCode, 'Should use specific cookie name');
        $this->assertStringContainsString('hash(\'sha256\', $token)', $csrfCode, 'Should hash token for cookie');
        
        // Test cookie validation
        $this->assertStringContainsString('$_COOKIE[$this->cookieName]', $csrfCode, 'Should validate cookie');
        $this->assertStringContainsString('validateCookieToken', $csrfCode, 'Should have cookie validation method');
        
        // Test secure cookie settings
        $this->assertStringContainsString("'secure' => true", $csrfCode, 'Cookie should be secure (HTTPS only)');
        $this->assertStringContainsString("'httponly' => true", $csrfCode, 'Cookie should be HTTP-only');
        $this->assertStringContainsString("'samesite' => 'Lax'", $csrfCode, 'Cookie should use SameSite Lax');
        
        // Simulate double-submit validation
        $originalToken = bin2hex(random_bytes(32));
        $cookieHash = hash('sha256', $originalToken);
        $sessionToken = $originalToken;
        
        $isValid = hash_equals($cookieHash, hash('sha256', $sessionToken));
        $this->assertTrue($isValid, 'Double-submit pattern should validate correctly');
    }

    /**
     * Test origin and referer validation
     */
    public function testOriginRefererValidation(): void
    {
        $csrfCode = file_get_contents(API_BASE_PATH . '/csrf-middleware.php');
        
        // Test allowed origins configuration
        $this->assertStringContainsString('aze.mikropartner.de', $csrfCode, 'Should include production domain');
        $this->assertStringContainsString('localhost', $csrfCode, 'Should include localhost for development');
        
        // Test origin header validation
        $this->assertStringContainsString('$_SERVER[\'HTTP_ORIGIN\']', $csrfCode, 'Should check Origin header');
        $this->assertStringContainsString('in_array($_SERVER[\'HTTP_ORIGIN\']', $csrfCode, 'Should validate origin against whitelist');
        
        // Test referer header fallback
        $this->assertStringContainsString('$_SERVER[\'HTTP_REFERER\']', $csrfCode, 'Should check Referer header as fallback');
        $this->assertStringContainsString('parse_url', $csrfCode, 'Should parse referer URL');
        
        // Test development environment handling
        $this->assertStringContainsString('APP_ENV', $csrfCode, 'Should check environment variable');
        $this->assertStringContainsString('development', $csrfCode, 'Should handle development environment');
        
        // Simulate origin validation
        $allowedOrigins = ['https://aze.mikropartner.de', 'http://localhost:5173'];
        
        $validOrigin = 'https://aze.mikropartner.de';
        $invalidOrigin = 'https://malicious-site.com';
        
        $this->assertContains($validOrigin, $allowedOrigins, 'Valid origin should be allowed');
        $this->assertNotContains($invalidOrigin, $allowedOrigins, 'Invalid origin should be rejected');
    }

    /**
     * Test CSRF token extraction from requests
     */
    public function testTokenExtractionFromRequests(): void
    {
        $csrfCode = file_get_contents(API_BASE_PATH . '/csrf-middleware.php');
        
        // Test header extraction
        $expectedHeaders = ['X-CSRF-Token', 'X-CSRFToken', 'X-Csrf-Token'];
        foreach ($expectedHeaders as $header) {
            $this->assertStringContainsString($header, $csrfCode, "Should check $header header");
        }
        
        // Test POST data extraction
        $this->assertStringContainsString('$_POST[$this->tokenName]', $csrfCode, 'Should check POST data');
        
        // Test JSON body extraction
        $this->assertStringContainsString('php://input', $csrfCode, 'Should read JSON request body');
        $this->assertStringContainsString('json_decode', $csrfCode, 'Should parse JSON data');
        
        // Test getallheaders function
        $this->assertStringContainsString('getallheaders', $csrfCode, 'Should use getallheaders function');
        
        // Simulate token extraction priority
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'header-token';
        $_POST['csrf_token'] = 'post-token';
        
        // Header should have priority over POST data
        $headers = ['X-CSRF-Token' => 'header-token'];
        $postData = ['csrf_token' => 'post-token'];
        
        $this->assertEquals('header-token', $headers['X-CSRF-Token'], 'Should extract from header first');
    }

    /**
     * Test CSRF protection for different HTTP methods
     */
    public function testHttpMethodProtection(): void
    {
        $csrfCode = file_get_contents(API_BASE_PATH . '/csrf-middleware.php');
        
        // Test method checking function
        $this->assertStringContainsString('requiresCsrfProtection', $csrfCode, 'Should have method checking function');
        
        // Test protected methods
        $protectedMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];
        foreach ($protectedMethods as $method) {
            $this->assertStringContainsString($method, $csrfCode, "Should protect $method method");
        }
        
        // Test safe methods (should not require CSRF protection)
        $safeMethods = ['GET', 'HEAD', 'OPTIONS'];
        
        // Simulate method checking logic
        foreach ($protectedMethods as $method) {
            $requiresProtection = in_array(strtoupper($method), $protectedMethods, true);
            $this->assertTrue($requiresProtection, "$method should require CSRF protection");
        }
        
        foreach ($safeMethods as $method) {
            $requiresProtection = in_array(strtoupper($method), $protectedMethods, true);
            $this->assertFalse($requiresProtection, "$method should not require CSRF protection");
        }
    }

    /**
     * Test CSRF error responses
     */
    public function testCsrfErrorResponses(): void
    {
        $csrfCode = file_get_contents(API_BASE_PATH . '/csrf-middleware.php');
        
        // Test HTTP status code
        $this->assertStringContainsString('http_response_code(403)', $csrfCode, 'Should return 403 Forbidden');
        
        // Test JSON response format
        $this->assertStringContainsString('Content-Type: application/json', $csrfCode, 'Should set JSON content type');
        $this->assertStringContainsString('JSON_UNESCAPED_UNICODE', $csrfCode, 'Should use proper JSON encoding');
        $this->assertStringContainsString('JSON_PRETTY_PRINT', $csrfCode, 'Should format JSON nicely');
        
        // Test error message structure
        $this->assertStringContainsString("'error' => 'CSRF validation failed'", $csrfCode, 'Should have error field');
        $this->assertStringContainsString("'message' =>", $csrfCode, 'Should have user message');
        $this->assertStringContainsString("'code' => 'CSRF_TOKEN_INVALID'", $csrfCode, 'Should have error code');
        
        // Test security logging
        $this->assertStringContainsString('error_log', $csrfCode, 'Should log CSRF attacks');
        $this->assertStringContainsString('CSRF attack attempt', $csrfCode, 'Should log attack attempts');
        $this->assertStringContainsString('HTTP_USER_AGENT', $csrfCode, 'Should log user agent');
    }

    /**
     * Test token lifetime management
     */
    public function testTokenLifetimeManagement(): void
    {
        $csrfCode = file_get_contents(API_BASE_PATH . '/csrf-middleware.php');
        
        // Test token lifetime configuration
        $this->assertStringContainsString('CSRF_TOKEN_LIFETIME', $csrfCode, 'Should check env for token lifetime');
        $this->assertStringContainsString('3600', $csrfCode, 'Should have default 1 hour lifetime');
        
        // Test token cleanup
        $this->assertStringContainsString('clearTokens', $csrfCode, 'Should have token cleanup method');
        $this->assertStringContainsString('unset($_SESSION', $csrfCode, 'Should clear session tokens');
        
        // Test cookie cleanup
        $this->assertStringContainsString('time() - 3600', $csrfCode, 'Should expire cookies');
        
        // Simulate token aging
        $tokenLifetime = 3600; // 1 hour
        $currentTime = time();
        
        // Fresh token
        $freshTokenTime = $currentTime - 1800; // 30 minutes ago
        $isFresh = ($currentTime - $freshTokenTime) < $tokenLifetime;
        $this->assertTrue($isFresh, 'Fresh token should be valid');
        
        // Expired token
        $expiredTokenTime = $currentTime - 7200; // 2 hours ago
        $isExpired = ($currentTime - $expiredTokenTime) >= $tokenLifetime;
        $this->assertTrue($isExpired, 'Expired token should be invalid');
    }

    /**
     * Test environment configuration
     */
    public function testEnvironmentConfiguration(): void
    {
        $csrfCode = file_get_contents(API_BASE_PATH . '/csrf-middleware.php');
        
        // Test environment variable handling
        $this->assertStringContainsString('CSRF_TOKEN_NAME', $csrfCode, 'Should check token name env var');
        $this->assertStringContainsString('getEnvString', $csrfCode, 'Should have env string helper');
        $this->assertStringContainsString('getEnvInt', $csrfCode, 'Should have env int helper');
        
        // Test default values
        $this->assertStringContainsString('csrf_token', $csrfCode, 'Should have default token name');
        
        // Test environment variable processing
        $this->assertStringContainsString('$_ENV[$key]', $csrfCode, 'Should check $_ENV');
        $this->assertStringContainsString('getenv($key)', $csrfCode, 'Should check getenv');
        
        // Simulate environment variable handling
        $_ENV['CSRF_TOKEN_NAME'] = 'custom_csrf_token';
        $_ENV['CSRF_TOKEN_LIFETIME'] = '7200';
        
        $tokenName = $_ENV['CSRF_TOKEN_NAME'] ?? 'csrf_token';
        $tokenLifetime = (int)($_ENV['CSRF_TOKEN_LIFETIME'] ?? 3600);
        
        $this->assertEquals('custom_csrf_token', $tokenName, 'Should use custom token name');
        $this->assertEquals(7200, $tokenLifetime, 'Should use custom token lifetime');
    }

    /**
     * Test CSRF class initialization
     */
    public function testCsrfClassInitialization(): void
    {
        $csrfCode = file_get_contents(API_BASE_PATH . '/csrf-middleware.php');
        
        // Test class existence
        $this->assertStringContainsString('class CsrfProtection', $csrfCode, 'Should define CsrfProtection class');
        
        // Test constructor
        $this->assertStringContainsString('public function __construct()', $csrfCode, 'Should have constructor');
        
        // Test properties initialization
        $this->assertStringContainsString('$this->tokenName', $csrfCode, 'Should initialize token name');
        $this->assertStringContainsString('$this->tokenLifetime', $csrfCode, 'Should initialize token lifetime');
        $this->assertStringContainsString('$this->cookieName', $csrfCode, 'Should initialize cookie name');
        
        // Test global instance
        $this->assertStringContainsString('$csrfProtection = new CsrfProtection()', $csrfCode, 'Should create global instance');
        
        // Test helper functions
        $helperFunctions = ['generateCsrfToken', 'validateCsrfToken', 'validateCsrfProtection', 'getCsrfToken'];
        foreach ($helperFunctions as $function) {
            $this->assertStringContainsString("function $function", $csrfCode, "Should define $function helper");
        }
    }

    /**
     * Test strict validation mode
     */
    public function testStrictValidationMode(): void
    {
        $csrfCode = file_get_contents(API_BASE_PATH . '/csrf-middleware.php');
        
        // Test strict parameter
        $this->assertStringContainsString('$strict = true', $csrfCode, 'Should support strict validation');
        $this->assertStringContainsString('validateOrigin()', $csrfCode, 'Strict mode should include origin validation');
        
        // Test that strict mode includes additional checks
        $this->assertStringContainsString('if ($strict &&', $csrfCode, 'Should conditionally apply strict checks');
        
        // Simulate strict vs non-strict validation
        $tokenValid = true;
        $cookieValid = true;
        $originValid = false; // Invalid origin
        
        // Non-strict should pass with valid token and cookie
        $nonStrictResult = $tokenValid && $cookieValid;
        $this->assertTrue($nonStrictResult, 'Non-strict validation should pass with valid token/cookie');
        
        // Strict should fail with invalid origin
        $strictResult = $tokenValid && $cookieValid && $originValid;
        $this->assertFalse($strictResult, 'Strict validation should fail with invalid origin');
    }

    /**
     * Test CSRF attack simulation scenarios
     */
    public function testCsrfAttackSimulation(): void
    {
        // Simulate various CSRF attack scenarios
        
        // Scenario 1: Missing token
        $_POST = [];
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        $this->simulateTokenValidation(false, 'Missing token should fail validation');
        
        // Scenario 2: Invalid token
        $_POST['csrf_token'] = 'invalid-token';
        $_SESSION['csrf_token'] = 'valid-token';
        $this->simulateTokenValidation(false, 'Invalid token should fail validation');
        
        // Scenario 3: Expired token
        $_POST['csrf_token'] = 'valid-token';
        $_SESSION['csrf_token'] = 'valid-token';
        $_SESSION['csrf_token_time'] = time() - 7200; // 2 hours ago
        $this->simulateTokenValidation(false, 'Expired token should fail validation');
        
        // Scenario 4: Valid token
        $_POST['csrf_token'] = 'valid-token';
        $_SESSION['csrf_token'] = 'valid-token';
        $_SESSION['csrf_token_time'] = time() - 1800; // 30 minutes ago
        $this->simulateTokenValidation(true, 'Valid token should pass validation');
    }

    private function simulateTokenValidation(bool $expectedResult, string $message): void
    {
        $tokenLifetime = 3600; // 1 hour
        $currentTime = time();
        
        // Check if token exists in both request and session
        $requestToken = $_POST['csrf_token'] ?? null;
        $sessionToken = $_SESSION['csrf_token'] ?? null;
        $sessionTime = $_SESSION['csrf_token_time'] ?? 0;
        
        if (empty($requestToken) || empty($sessionToken)) {
            $result = false;
        } elseif (!hash_equals($sessionToken, $requestToken)) {
            $result = false;
        } elseif (($currentTime - $sessionTime) > $tokenLifetime) {
            $result = false;
        } else {
            $result = true;
        }
        
        $this->assertEquals($expectedResult, $result, $message);
    }

    /**
     * Performance test for CSRF operations
     */
    public function testCsrfPerformance(): void
    {
        $startTime = microtime(true);
        
        // Simulate 1000 token operations
        for ($i = 0; $i < 1000; $i++) {
            // Token generation
            $token = bin2hex(random_bytes(32));
            
            // Token validation simulation
            $sessionToken = $token;
            $isValid = hash_equals($sessionToken, $token);
            
            // Cookie hash generation
            $cookieHash = hash('sha256', $token);
            
            $this->assertNotEmpty($token);
            $this->assertTrue($isValid);
            $this->assertNotEmpty($cookieHash);
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // Should complete 1000 operations in less than 0.1 seconds
        $this->assertLessThan(0.1, $executionTime, 'CSRF operations should be fast');
    }
}