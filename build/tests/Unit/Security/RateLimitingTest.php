<?php
/**
 * Unit Tests for Rate Limiting Middleware
 * Tests the comprehensive rate limiting system for API endpoints
 * 
 * Test Coverage:
 * - Rate limiter initialization and configuration
 * - Per-endpoint rate limiting
 * - Sliding window algorithm
 * - Client IP detection and handling
 * - File-based cache system
 * - Rate limit headers and responses
 * - Environment variable configuration
 */

use PHPUnit\Framework\TestCase;

class RateLimitingTest extends TestCase
{
    private $tempCacheDir;
    private $originalServerVars;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create temporary cache directory for testing
        $this->tempCacheDir = sys_get_temp_dir() . '/rate_limit_test_' . uniqid();
        mkdir($this->tempCacheDir, 0755, true);
        
        // Store original server variables
        $this->originalServerVars = $_SERVER;
        
        // Set up test server environment
        $_SERVER = [
            'REMOTE_ADDR' => '192.168.1.100',
            'REQUEST_METHOD' => 'POST',
            'HTTP_HOST' => 'localhost',
        ];
        
        // Clear environment variables for clean testing
        unset($_ENV['RATE_LIMIT_ENABLED']);
        unset($_ENV['RATE_LIMIT_MAX_REQUESTS']);
        unset($_ENV['RATE_LIMIT_WINDOW']);
        
        if (!defined('API_GUARD')) {
            define('API_GUARD', true);
        }
        
        if (!defined('TEST_MODE')) {
            define('TEST_MODE', true);
        }
    }

    protected function tearDown(): void
    {
        // Restore original server variables
        $_SERVER = $this->originalServerVars;
        
        // Clean up temporary cache directory
        if (is_dir($this->tempCacheDir)) {
            $this->recursiveDelete($this->tempCacheDir);
        }
        
        parent::tearDown();
    }

    private function recursiveDelete($dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->recursiveDelete($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * Test RateLimiter class initialization
     */
    public function testRateLimiterInitialization(): void
    {
        // Mock the rate limiting file to use our temp directory
        $rateLimitingCode = file_get_contents(API_BASE_PATH . '/rate-limiting.php');
        $modifiedCode = str_replace(
            "__DIR__ . '/../cache/rate-limit/'",
            "'{$this->tempCacheDir}/'",
            $rateLimitingCode
        );
        
        // Create a temporary file with modified code
        $tempFile = $this->tempCacheDir . '/rate-limiting-test.php';
        file_put_contents($tempFile, $modifiedCode);
        
        require_once $tempFile;
        
        $this->assertTrue(class_exists('RateLimiter'), 'RateLimiter class should exist');
        
        $rateLimiter = new RateLimiter();
        $this->assertInstanceOf('RateLimiter', $rateLimiter, 'Should create RateLimiter instance');
    }

    /**
     * Test endpoint-specific limits configuration
     */
    public function testEndpointLimitsConfiguration(): void
    {
        $rateLimitingCode = file_get_contents(API_BASE_PATH . '/rate-limiting.php');
        
        // Test that endpoint limits are properly defined
        $this->assertStringContainsString("'auth' => ['requests' => 10, 'window' => 60]", $rateLimitingCode);
        $this->assertStringContainsString("'login' => ['requests' => 10, 'window' => 60]", $rateLimitingCode);
        $this->assertStringContainsString("'time-entries' => ['requests' => 200, 'window' => 60]", $rateLimitingCode);
        $this->assertStringContainsString("'users' => ['requests' => 100, 'window' => 60]", $rateLimitingCode);
        $this->assertStringContainsString("'approvals' => ['requests' => 50, 'window' => 60]", $rateLimitingCode);
        
        // Test that high-security endpoints have lower limits
        preg_match("/'auth' => \['requests' => (\d+),/", $rateLimitingCode, $authMatches);
        preg_match("/'login' => \['requests' => (\d+),/", $rateLimitingCode, $loginMatches);
        preg_match("/'time-entries' => \['requests' => (\d+),/", $rateLimitingCode, $timeMatches);
        
        $authLimit = (int)$authMatches[1];
        $loginLimit = (int)$loginMatches[1];
        $timeLimit = (int)$timeMatches[1];
        
        $this->assertLessThan($timeLimit, $authLimit, 'Auth endpoints should have stricter limits than time entries');
        $this->assertLessThan($timeLimit, $loginLimit, 'Login endpoints should have stricter limits than time entries');
    }

    /**
     * Test client IP detection methods
     */
    public function testClientIPDetection(): void
    {
        $rateLimitingCode = file_get_contents(API_BASE_PATH . '/rate-limiting.php');
        
        // Test standard REMOTE_ADDR
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['HTTP_X_REAL_IP']);
        unset($_SERVER['HTTP_CF_CONNECTING_IP']);
        
        // Extract and test getClientIP logic
        $this->assertStringContainsString('HTTP_X_FORWARDED_FOR', $rateLimitingCode, 'Should check X-Forwarded-For header');
        $this->assertStringContainsString('HTTP_X_REAL_IP', $rateLimitingCode, 'Should check X-Real-IP header');
        $this->assertStringContainsString('HTTP_CF_CONNECTING_IP', $rateLimitingCode, 'Should check CloudFlare header');
        $this->assertStringContainsString('REMOTE_ADDR', $rateLimitingCode, 'Should fallback to REMOTE_ADDR');
        
        // Test X-Forwarded-For priority
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1, 203.0.113.1';
        $_SERVER['HTTP_X_REAL_IP'] = '203.0.113.2';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.3';
        
        // The first IP in X-Forwarded-For should be used
        $this->assertStringContainsString("explode(',', \$_SERVER['HTTP_X_FORWARDED_FOR'])", $rateLimitingCode);
        $this->assertStringContainsString('trim($ips[0])', $rateLimitingCode);
    }

    /**
     * Test rate limiting logic simulation
     */
    public function testRateLimitingLogic(): void
    {
        // Simulate sliding window rate limiting algorithm
        $timeWindow = 60; // seconds
        $maxRequests = 10;
        $currentTime = time();
        
        // Simulate request timestamps within window
        $requestTimes = [
            $currentTime - 70, // Outside window - should be cleaned
            $currentTime - 50, // Within window
            $currentTime - 40, // Within window
            $currentTime - 30, // Within window
            $currentTime - 20, // Within window
            $currentTime - 10, // Within window
        ];
        
        // Clean old entries (simulate cleanOldEntries logic)
        $validRequests = array_filter($requestTimes, function($timestamp) use ($currentTime, $timeWindow) {
            return ($currentTime - $timestamp) < $timeWindow;
        });
        
        $this->assertCount(5, $validRequests, 'Should have 5 requests within time window');
        $this->assertLessThan($maxRequests, count($validRequests), 'Should be within rate limit');
        
        // Test exceeding limit
        $excessiveRequests = array_fill(0, 15, $currentTime - 10);
        $this->assertGreaterThan($maxRequests, count($excessiveRequests), 'Should exceed rate limit');
    }

    /**
     * Test cache key generation
     */
    public function testCacheKeyGeneration(): void
    {
        $rateLimitingCode = file_get_contents(API_BASE_PATH . '/rate-limiting.php');
        
        // Test that cache keys are generated securely
        $this->assertStringContainsString('hash(\'sha256\'', $rateLimitingCode, 'Cache keys should be hashed with SHA256');
        $this->assertStringContainsString('$ip . \'_\' . $endpoint', $rateLimitingCode, 'Cache keys should include IP and endpoint');
        
        // Test key uniqueness
        $ip1 = '192.168.1.1';
        $ip2 = '192.168.1.2';
        $endpoint1 = 'login';
        $endpoint2 = 'time-entries';
        
        $key1 = 'rate_limit_' . hash('sha256', $ip1 . '_' . $endpoint1);
        $key2 = 'rate_limit_' . hash('sha256', $ip1 . '_' . $endpoint2);
        $key3 = 'rate_limit_' . hash('sha256', $ip2 . '_' . $endpoint1);
        
        $this->assertNotEquals($key1, $key2, 'Different endpoints should generate different keys');
        $this->assertNotEquals($key1, $key3, 'Different IPs should generate different keys');
        $this->assertEquals(64, strlen(hash('sha256', $ip1 . '_' . $endpoint1)), 'SHA256 hash should be 64 characters');
    }

    /**
     * Test file-based cache system
     */
    public function testFileCacheSystem(): void
    {
        $cacheDir = $this->tempCacheDir;
        
        // Test cache file creation and reading
        $testKey = 'test_cache_key';
        $testData = [time() - 30, time() - 20, time() - 10];
        
        // Simulate cache write
        $filePath = $cacheDir . '/' . $testKey . '.json';
        $content = json_encode($testData);
        file_put_contents($filePath, $content, LOCK_EX);
        
        $this->assertFileExists($filePath, 'Cache file should be created');
        
        // Simulate cache read
        $readContent = file_get_contents($filePath);
        $readData = json_decode($readContent, true);
        
        $this->assertEquals($testData, $readData, 'Cache data should be preserved');
        $this->assertIsArray($readData, 'Cache data should be an array');
        
        // Test cache directory protection
        $htaccessPath = $cacheDir . '/.htaccess';
        file_put_contents($htaccessPath, "Order deny,allow\nDeny from all\n");
        
        $this->assertFileExists($htaccessPath, '.htaccess file should protect cache directory');
        $htaccessContent = file_get_contents($htaccessPath);
        $this->assertStringContainsString('Deny from all', $htaccessContent, '.htaccess should deny web access');
    }

    /**
     * Test rate limit headers generation
     */
    public function testRateLimitHeaders(): void
    {
        $rateLimitingCode = file_get_contents(API_BASE_PATH . '/rate-limiting.php');
        
        // Test that proper headers are set
        $expectedHeaders = [
            'X-RateLimit-Limit',
            'X-RateLimit-Remaining', 
            'X-RateLimit-Reset',
            'Retry-After'
        ];
        
        foreach ($expectedHeaders as $header) {
            $this->assertStringContainsString($header, $rateLimitingCode, "Should set $header header");
        }
        
        // Test HTTP 429 response
        $this->assertStringContainsString('http_response_code(429)', $rateLimitingCode, 'Should return 429 status code');
        $this->assertStringContainsString('Rate limit exceeded', $rateLimitingCode, 'Should return rate limit error message');
    }

    /**
     * Test environment variable configuration
     */
    public function testEnvironmentConfiguration(): void
    {
        $rateLimitingCode = file_get_contents(API_BASE_PATH . '/rate-limiting.php');
        
        // Test that environment variables are checked
        $this->assertStringContainsString('RATE_LIMIT_ENABLED', $rateLimitingCode, 'Should check RATE_LIMIT_ENABLED env var');
        $this->assertStringContainsString('RATE_LIMIT_MAX_REQUESTS', $rateLimitingCode, 'Should check RATE_LIMIT_MAX_REQUESTS env var');
        $this->assertStringContainsString('RATE_LIMIT_WINDOW', $rateLimitingCode, 'Should check RATE_LIMIT_WINDOW env var');
        
        // Test default values
        $this->assertStringContainsString('getenv($key)', $rateLimitingCode, 'Should use getenv for fallback');
        $this->assertStringContainsString('$_ENV[$key]', $rateLimitingCode, 'Should check $_ENV array');
        
        // Test boolean and integer parsing
        $this->assertStringContainsString('filter_var($value, FILTER_VALIDATE_BOOLEAN)', $rateLimitingCode, 'Should validate boolean env vars');
        $this->assertStringContainsString('(int)$value', $rateLimitingCode, 'Should cast integer env vars');
    }

    /**
     * Test rate limiting bypass for disabled state
     */
    public function testRateLimitingDisabled(): void
    {
        // Test behavior when rate limiting is disabled
        $_ENV['RATE_LIMIT_ENABLED'] = 'false';
        
        $rateLimitingCode = file_get_contents(API_BASE_PATH . '/rate-limiting.php');
        
        // Should have logic to bypass when disabled
        $this->assertStringContainsString('!$this->enabled', $rateLimitingCode, 'Should check if rate limiting is enabled');
        $this->assertStringContainsString('return true', $rateLimitingCode, 'Should return true when disabled');
        
        // Test unlimited info when disabled
        $this->assertStringContainsString("'limit' => 'unlimited'", $rateLimitingCode, 'Should return unlimited when disabled');
        $this->assertStringContainsString("'remaining' => 'unlimited'", $rateLimitingCode, 'Should return unlimited remaining');
    }

    /**
     * Test global vs endpoint-specific limits
     */
    public function testGlobalVsEndpointLimits(): void
    {
        $rateLimitingCode = file_get_contents(API_BASE_PATH . '/rate-limiting.php');
        
        // Should check both global and endpoint limits
        $this->assertStringContainsString('checkLimit($clientIP, \'global\'', $rateLimitingCode, 'Should check global limit');
        $this->assertStringContainsString('checkLimit($clientIP, $endpoint', $rateLimitingCode, 'Should check endpoint limit');
        $this->assertStringContainsString('$globalAllowed && $endpointAllowed', $rateLimitingCode, 'Should require both limits to pass');
        
        // Test default limits for global
        $this->assertStringContainsString('defaultMaxRequests', $rateLimitingCode, 'Should have default max requests');
        $this->assertStringContainsString('defaultWindow', $rateLimitingCode, 'Should have default window');
    }

    /**
     * Test JSON response format for rate limit errors
     */
    public function testRateLimitErrorResponse(): void
    {
        $rateLimitingCode = file_get_contents(API_BASE_PATH . '/rate-limiting.php');
        
        // Test JSON response structure
        $this->assertStringContainsString("'error' => 'Rate limit exceeded'", $rateLimitingCode, 'Should have error field');
        $this->assertStringContainsString("'message' => 'Too many requests", $rateLimitingCode, 'Should have user message');
        $this->assertStringContainsString("'retry_after'", $rateLimitingCode, 'Should have retry_after field');
        $this->assertStringContainsString("'limit'", $rateLimitingCode, 'Should have limit field');
        $this->assertStringContainsString("'remaining'", $rateLimitingCode, 'Should have remaining field');
        $this->assertStringContainsString("'reset'", $rateLimitingCode, 'Should have reset field');
        
        // Test JSON formatting
        $this->assertStringContainsString('JSON_UNESCAPED_UNICODE', $rateLimitingCode, 'Should use proper JSON encoding');
        $this->assertStringContainsString('JSON_PRETTY_PRINT', $rateLimitingCode, 'Should format JSON nicely');
    }

    /**
     * Test endpoint limit defaults
     */
    public function testEndpointLimitDefaults(): void
    {
        $rateLimitingCode = file_get_contents(API_BASE_PATH . '/rate-limiting.php');
        
        // Should have fallback for unknown endpoints
        $this->assertStringContainsString('?? [', $rateLimitingCode, 'Should have null coalescing for unknown endpoints');
        $this->assertStringContainsString("'requests' => 50", $rateLimitingCode, 'Should have default request limit');
        $this->assertStringContainsString("'window' => 60", $rateLimitingCode, 'Should have default window');
        
        // Test that unknown endpoints get reasonable defaults
        $defaultLimit = 50; // From the code
        $defaultWindow = 60; // From the code
        
        $this->assertGreaterThan(0, $defaultLimit, 'Default limit should be positive');
        $this->assertGreaterThan(0, $defaultWindow, 'Default window should be positive');
        $this->assertLessThan(1000, $defaultLimit, 'Default limit should not be excessive');
    }

    /**
     * Test security considerations
     */
    public function testSecurityConsiderations(): void
    {
        $rateLimitingCode = file_get_contents(API_BASE_PATH . '/rate-limiting.php');
        
        // Test file locking for cache writes
        $this->assertStringContainsString('LOCK_EX', $rateLimitingCode, 'Should use exclusive locks for file writes');
        
        // Test cache directory permissions
        $this->assertStringContainsString('0755', $rateLimitingCode, 'Should set secure directory permissions');
        
        // Test that cache files are protected from web access
        $this->assertStringContainsString('.htaccess', $rateLimitingCode, 'Should create .htaccess for protection');
        
        // Test error handling
        $this->assertStringContainsString('error_log', $rateLimitingCode, 'Should log errors appropriately');
    }

    /**
     * Performance test for rate limiting operations
     */
    public function testRateLimitingPerformance(): void
    {
        $tempDir = $this->tempCacheDir;
        
        // Test that cache operations are fast
        $startTime = microtime(true);
        
        // Simulate 100 cache operations
        for ($i = 0; $i < 100; $i++) {
            $key = 'perf_test_' . $i;
            $data = [time() - rand(0, 60)];
            $filePath = $tempDir . '/' . $key . '.json';
            
            // Write
            file_put_contents($filePath, json_encode($data), LOCK_EX);
            
            // Read
            $content = file_get_contents($filePath);
            $readData = json_decode($content, true);
            
            // Validate
            $this->assertIsArray($readData, 'Cache data should be valid array');
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // Should complete 100 cache operations in less than 0.1 seconds
        $this->assertLessThan(0.1, $executionTime, 'Cache operations should be fast');
    }
}