<?php
/**
 * Rate Limiting Middleware
 * Provides comprehensive rate limiting for API endpoints
 * 
 * Features:
 * - Per-IP rate limiting
 * - Per-endpoint rate limiting  
 * - Configurable via environment variables
 * - File-based storage for production compatibility
 * - Proper HTTP 429 responses
 * - Sliding window algorithm
 * 
 * Usage: require_once 'rate-limiting.php'; then call checkRateLimit($endpoint)
 */

// Prevent direct access
if (!defined('API_GUARD')) {
    http_response_code(403);
    die('Direct access forbidden');
}

class RateLimiter {
    private $cacheDir;
    private $enabled;
    private $defaultMaxRequests;
    private $defaultWindow;
    
    // Per-endpoint limits (requests per minute)
    private $endpointLimits = [
        'auth' => ['requests' => 10, 'window' => 60],        // Login attempts
        'login' => ['requests' => 10, 'window' => 60],       // Login attempts  
        'logout' => ['requests' => 20, 'window' => 60],      // Logout attempts
        'mfa' => ['requests' => 15, 'window' => 60],         // MFA operations
        'csrf' => ['requests' => 50, 'window' => 60],        // CSRF token requests
        'users' => ['requests' => 100, 'window' => 60],      // User operations
        'time-entries' => ['requests' => 200, 'window' => 60], // Time tracking
        'approvals' => ['requests' => 50, 'window' => 60],   // Approval operations
        'masterdata' => ['requests' => 50, 'window' => 60],  // Master data
        'settings' => ['requests' => 30, 'window' => 60],    // Settings changes
        'logs' => ['requests' => 20, 'window' => 60],        // Log access
        'monitoring' => ['requests' => 30, 'window' => 60],  // Monitoring
        'history' => ['requests' => 50, 'window' => 60],     // Change history
    ];
    
    public function __construct() {
        $this->cacheDir = __DIR__ . '/../cache/rate-limit/';
        $this->enabled = $this->getEnvBool('RATE_LIMIT_ENABLED', true);
        $this->defaultMaxRequests = $this->getEnvInt('RATE_LIMIT_MAX_REQUESTS', 100);
        $this->defaultWindow = $this->getEnvInt('RATE_LIMIT_WINDOW', 60);
        
        // Ensure cache directory exists
        $this->ensureCacheDirectory();
    }
    
    /**
     * Check if request should be rate limited
     * 
     * @param string $endpoint The API endpoint name
     * @return bool True if allowed, False if rate limited
     */
    public function checkRateLimit($endpoint = 'default') {
        if (!$this->enabled) {
            return true;
        }
        
        $clientIP = $this->getClientIP();
        $currentTime = time();
        
        // Check both global IP limit and endpoint-specific limit
        $globalAllowed = $this->checkLimit($clientIP, 'global', $currentTime);
        $endpointAllowed = $this->checkLimit($clientIP, $endpoint, $currentTime);
        
        return $globalAllowed && $endpointAllowed;
    }
    
    /**
     * Get rate limit info for current client
     * 
     * @param string $endpoint The API endpoint name
     * @return array Rate limit information
     */
    public function getRateLimitInfo($endpoint = 'default') {
        if (!$this->enabled) {
            return [
                'limit' => 'unlimited',
                'remaining' => 'unlimited',
                'reset' => 0
            ];
        }
        
        $clientIP = $this->getClientIP();
        $currentTime = time();
        
        // Get endpoint-specific limits
        $limits = $this->getEndpointLimits($endpoint);
        $key = $this->getCacheKey($clientIP, $endpoint);
        $data = $this->readCacheFile($key);
        
        if (!$data) {
            return [
                'limit' => $limits['requests'],
                'remaining' => $limits['requests'],
                'reset' => $currentTime + $limits['window']
            ];
        }
        
        // Clean old entries
        $data = $this->cleanOldEntries($data, $currentTime, $limits['window']);
        
        return [
            'limit' => $limits['requests'],
            'remaining' => max(0, $limits['requests'] - count($data)),
            'reset' => $currentTime + $limits['window']
        ];
    }
    
    /**
     * Send HTTP 429 response with rate limit headers
     * 
     * @param string $endpoint The API endpoint name
     */
    public function sendRateLimitResponse($endpoint = 'default') {
        $info = $this->getRateLimitInfo($endpoint);
        
        // Set rate limit headers
        header('X-RateLimit-Limit: ' . $info['limit']);
        header('X-RateLimit-Remaining: ' . $info['remaining']);
        header('X-RateLimit-Reset: ' . $info['reset']);
        header('Retry-After: ' . ($info['reset'] - time()));
        
        // Send 429 response
        http_response_code(429);
        header('Content-Type: application/json; charset=utf-8');
        
        echo json_encode([
            'error' => 'Rate limit exceeded',
            'message' => 'Too many requests. Please try again later.',
            'retry_after' => $info['reset'] - time(),
            'limit' => $info['limit'],
            'remaining' => $info['remaining'],
            'reset' => $info['reset']
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        exit;
    }
    
    /**
     * Check rate limit for specific key and endpoint
     * 
     * @param string $ip Client IP address
     * @param string $endpoint Endpoint name
     * @param int $currentTime Current timestamp
     * @return bool True if allowed
     */
    private function checkLimit($ip, $endpoint, $currentTime) {
        $limits = $this->getEndpointLimits($endpoint);
        $key = $this->getCacheKey($ip, $endpoint);
        
        // Read existing data
        $data = $this->readCacheFile($key);
        if (!$data) {
            $data = [];
        }
        
        // Clean old entries (sliding window)
        $data = $this->cleanOldEntries($data, $currentTime, $limits['window']);
        
        // Check if limit is exceeded
        if (count($data) >= $limits['requests']) {
            return false;
        }
        
        // Add current request
        $data[] = $currentTime;
        
        // Save updated data
        $this->writeCacheFile($key, $data);
        
        return true;
    }
    
    /**
     * Get endpoint-specific limits or default
     * 
     * @param string $endpoint Endpoint name
     * @return array Limits configuration
     */
    private function getEndpointLimits($endpoint) {
        if ($endpoint === 'global') {
            return [
                'requests' => $this->defaultMaxRequests,
                'window' => $this->defaultWindow
            ];
        }
        
        return $this->endpointLimits[$endpoint] ?? [
            'requests' => 50,
            'window' => 60
        ];
    }
    
    /**
     * Clean old entries from data array
     * 
     * @param array $data Request timestamps
     * @param int $currentTime Current timestamp
     * @param int $window Time window in seconds
     * @return array Cleaned data
     */
    private function cleanOldEntries($data, $currentTime, $window) {
        return array_filter($data, function($timestamp) use ($currentTime, $window) {
            return ($currentTime - $timestamp) < $window;
        });
    }
    
    /**
     * Get client IP address
     * 
     * @return string Client IP
     */
    private function getClientIP() {
        // Check for forwarded IP first (load balancer/proxy)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }
        
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * Generate cache key for IP and endpoint
     * 
     * @param string $ip Client IP
     * @param string $endpoint Endpoint name
     * @return string Cache key
     */
    private function getCacheKey($ip, $endpoint) {
        return 'rate_limit_' . hash('sha256', $ip . '_' . $endpoint);
    }
    
    /**
     * Read data from cache file
     * 
     * @param string $key Cache key
     * @return array|null Cached data or null
     */
    private function readCacheFile($key) {
        $filePath = $this->cacheDir . $key . '.json';
        
        if (!file_exists($filePath)) {
            return null;
        }
        
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }
        
        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }
    
    /**
     * Write data to cache file
     * 
     * @param string $key Cache key
     * @param array $data Data to cache
     * @return bool Success status
     */
    private function writeCacheFile($key, $data) {
        $filePath = $this->cacheDir . $key . '.json';
        $content = json_encode($data);
        
        return file_put_contents($filePath, $content, LOCK_EX) !== false;
    }
    
    /**
     * Ensure cache directory exists
     */
    private function ensureCacheDirectory() {
        if (!is_dir($this->cacheDir)) {
            // Unter strengem Error-Handler kann mkdir-Warning als Exception geworfen werden
            // Daher Fehlerausgaben unterdrücken und nur logging nutzen
            if (!@mkdir($this->cacheDir, 0755, true)) {
                error_log("Failed to create rate limit cache directory: " . $this->cacheDir);
                return; // Verhindert Folgeschritte (z. B. .htaccess), falls Ordner nicht existiert
            }
        }
        
        // Create .htaccess to deny web access
        $htaccessPath = $this->cacheDir . '.htaccess';
        if (!file_exists($htaccessPath)) {
            @file_put_contents($htaccessPath, "Order deny,allow\nDeny from all\n");
        }
    }
    
    /**
     * Get environment variable as boolean
     * 
     * @param string $key Environment variable name
     * @param bool $default Default value
     * @return bool
     */
    private function getEnvBool($key, $default = false) {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * Get environment variable as integer
     * 
     * @param string $key Environment variable name
     * @param int $default Default value
     * @return int
     */
    private function getEnvInt($key, $default = 0) {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return (int)$value;
    }
}

// Global rate limiter instance
$rateLimiter = new RateLimiter();

/**
 * Check rate limit for current request
 * 
 * @param string $endpoint API endpoint name
 * @return bool True if allowed, sends 429 response if not
 */
function checkRateLimit($endpoint = 'default') {
    global $rateLimiter;
    
    if (!$rateLimiter->checkRateLimit($endpoint)) {
        $rateLimiter->sendRateLimitResponse($endpoint);
        return false;
    }
    
    return true;
}

/**
 * Get rate limit information for current client
 * 
 * @param string $endpoint API endpoint name
 * @return array Rate limit info
 */
function getRateLimitInfo($endpoint = 'default') {
    global $rateLimiter;
    return $rateLimiter->getRateLimitInfo($endpoint);
}

/**
 * Add rate limit headers to response
 * 
 * @param string $endpoint API endpoint name
 */
function addRateLimitHeaders($endpoint = 'default') {
    $info = getRateLimitInfo($endpoint);
    
    header('X-RateLimit-Limit: ' . $info['limit']);
    header('X-RateLimit-Remaining: ' . $info['remaining']);
    header('X-RateLimit-Reset: ' . $info['reset']);
}
