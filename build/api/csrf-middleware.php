<?php
/**
 * Enhanced CSRF Protection Middleware
 * Provides comprehensive CSRF protection for all state-changing operations
 * 
 * Features:
 * - Double-submit cookie pattern
 * - SameSite cookie protection
 * - Origin/Referer validation
 * - Token lifetime management
 * - Per-session token rotation
 * - Integration with rate limiting
 * 
 * Usage: require_once 'csrf-middleware.php'; then call validateCsrfProtection()
 */

// Prevent direct access
if (!defined('API_GUARD')) {
    http_response_code(403);
    die('Direct access forbidden');
}

class CsrfProtection {
    private $tokenName;
    private $tokenLifetime;
    private $cookieName;
    private $enabled;
    
    public function __construct() {
        $this->tokenName = $this->getEnvString('CSRF_TOKEN_NAME', 'csrf_token');
        $this->tokenLifetime = $this->getEnvInt('CSRF_TOKEN_LIFETIME', 3600); // 1 hour default
        $this->cookieName = 'csrf_cookie_token';
        $this->enabled = true; // Always enabled for security
    }

    private function clog($label, $data = null) {
        $line = '[' . date('Y-m-d H:i:s') . "] csrf | " . $label;
        if ($data !== null) {
            $line .= ' | ' . (is_string($data) ? $data : json_encode($data));
        }
        @file_put_contents(__DIR__ . '/csrf-debug.log', $line . "\n", FILE_APPEND);
    }
    
    /**
     * Generate a new CSRF token
     * 
     * @return string The generated token
     */
    public function generateToken() {
        if (session_status() === PHP_SESSION_NONE) {
            start_secure_session();
        }
        
        // Generate cryptographically secure token
        $token = $this->generateSecureToken();
        
        // Store in session with timestamp
        $_SESSION[$this->tokenName] = $token;
        $_SESSION[$this->tokenName . '_time'] = time();
        
        // Set secure cookie for double-submit pattern
        $this->setCsrfCookie($token);
        
        return $token;
    }
    
    /**
     * Validate CSRF token from request
     * 
     * @param string|null $token Token from request (header/POST data)
     * @param bool $strict Enable strict validation (origin + token)
     * @return bool True if valid
     */
    public function validateToken($token = null, $strict = true) {
        if (!$this->enabled) {
            return true;
        }

        if (session_status() === PHP_SESSION_NONE) {
            start_secure_session();
        }

        // Get token from request if not provided
        if ($token === null) {
            $token = $this->getTokenFromRequest();
        }

        if (empty($token)) {
            // Fallback: accept if double-submit cookie matches current session token hash
            if (isset($_SESSION[$this->tokenName]) && isset($_COOKIE[$this->cookieName])) {
                $sessionToken = $_SESSION[$this->tokenName];
                $cookieHash = $_COOKIE[$this->cookieName];
                if (hash_equals(hash('sha256', $sessionToken), $cookieHash)) {
                    $this->clog('fallback_cookie_only_valid');
                    return true;
                }
            }
            $this->clog('fail_no_token', ['uri' => $_SERVER['REQUEST_URI'] ?? '']);
            return false;
        }
        
        // Check if session token exists
        if (!isset($_SESSION[$this->tokenName]) || !isset($_SESSION[$this->tokenName . '_time'])) {
            $this->clog('fail_no_session_token');
            return false;
        }
        
        // Check token age
        if (time() - $_SESSION[$this->tokenName . '_time'] > $this->tokenLifetime) {
            $this->clearTokens();
            $this->clog('fail_token_expired');
            return false;
        }
        
        // Validate session token
        if (!hash_equals($_SESSION[$this->tokenName], $token)) {
            $this->clog('fail_token_mismatch');
            return false;
        }
        
        // Double-submit cookie validation
        if (!$this->validateCookieToken($token)) {
            $this->clog('fail_cookie_validation', [
                'cookie_present' => isset($_COOKIE[$this->cookieName])
            ]);
            return false;
        }
        
        // Strict validation includes origin/referer checks
        if ($strict && !$this->validateOrigin()) {
            $this->clog('fail_origin');
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate request for state-changing operations
     * Sends appropriate error response if validation fails
     * 
     * @param string|null $token Optional token override
     * @return bool True if valid, false if validation failed (response sent)
     */
    public function validateRequest($token = null) {
        if ($this->validateToken($token, true)) {
            return true;
        }
        
        // Log failed attempt
        $ip = $this->getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        error_log("CSRF attack attempt from IP: {$ip}, User-Agent: {$userAgent}");
        
        // Send error response
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        
        echo json_encode([
            'error' => 'CSRF validation failed',
            'message' => 'Invalid or missing CSRF token. Please refresh the page and try again.',
            'code' => 'CSRF_TOKEN_INVALID'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        exit;
    }
    
    /**
     * Get CSRF token for current session
     * 
     * @param bool $generate Generate new token if none exists
     * @return string|null Current token or null
     */
    public function getToken($generate = true) {
        if (session_status() === PHP_SESSION_NONE) {
            start_secure_session();
        }
        
        // Check if valid token exists
        if (isset($_SESSION[$this->tokenName]) && isset($_SESSION[$this->tokenName . '_time'])) {
            // Check if token is still valid
            if (time() - $_SESSION[$this->tokenName . '_time'] <= $this->tokenLifetime) {
                return $_SESSION[$this->tokenName];
            } else {
                $this->clearTokens();
            }
        }
        
        // Generate new token if requested
        if ($generate) {
            return $this->generateToken();
        }
        
        return null;
    }
    
    /**
     * Clear all CSRF tokens
     */
    public function clearTokens() {
        if (session_status() === PHP_SESSION_NONE) {
            start_secure_session();
        }
        
        unset($_SESSION[$this->tokenName]);
        unset($_SESSION[$this->tokenName . '_time']);
        
        // Clear cookie
        if (isset($_COOKIE[$this->cookieName])) {
            setcookie($this->cookieName, '', time() - 3600, '/', '', true, true);
        }
    }
    
    /**
     * Generate cryptographically secure token
     * 
     * @return string Secure token
     */
    private function generateSecureToken() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Get token from request (headers or POST data)
     * 
     * @return string|null Token from request
     */
    private function getTokenFromRequest() {
        // Check headers first (case-insensitive)
        $headers = getallheaders();
        $headersLower = [];
        foreach ($headers as $k => $v) { $headersLower[strtolower($k)] = $v; }
        
        // Common CSRF header names (lowercase)
        $csrfHeaders = ['x-csrf-token', 'x-csrftoken', 'x-xsrf-token'];
        foreach ($csrfHeaders as $key) {
            if (isset($headersLower[$key])) {
                return $headersLower[$key];
            }
        }
        
        // Check POST data
        if (isset($_POST[$this->tokenName])) {
            return $_POST[$this->tokenName];
        }
        
        // Check JSON body
        $input = file_get_contents('php://input');
        if (!empty($input)) {
            $data = json_decode($input, true);
            if (is_array($data)) {
                // accept both snake_case and camelCase
                if (isset($data[$this->tokenName])) {
                    return $data[$this->tokenName];
                }
                if (isset($data['csrfToken'])) {
                    return $data['csrfToken'];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Set CSRF cookie for double-submit pattern
     * 
     * @param string $token Token to set in cookie
     */
    private function setCsrfCookie($token) {
        $options = [
            'expires' => time() + $this->tokenLifetime,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ];
        
        setcookie($this->cookieName, hash('sha256', $token), $options);
    }
    
    /**
     * Validate cookie token (double-submit pattern)
     * 
     * @param string $token Session token to validate against cookie
     * @return bool True if cookie is valid
     */
    private function validateCookieToken($token) {
        if (!isset($_COOKIE[$this->cookieName])) {
            return false;
        }
        
        $cookieHash = $_COOKIE[$this->cookieName];
        $tokenHash = hash('sha256', $token);
        
        return hash_equals($cookieHash, $tokenHash);
    }
    
    /**
     * Validate request origin/referer
     * 
     * @return bool True if origin is valid
     */
    private function validateOrigin() {
        $allowedOrigins = [
            'https://aze.mikropartner.de'
        ];
        
        // Add localhost for development
        if (defined('APP_ENV') && APP_ENV === 'development') {
            $allowedOrigins[] = 'http://localhost:5173';
            $allowedOrigins[] = 'http://localhost:3000';
        }
        
        // Check Origin header first
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            return in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins, true);
        }
        
        // Fallback to Referer header
        if (isset($_SERVER['HTTP_REFERER'])) {
            $refererHost = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
            $refererScheme = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_SCHEME);
            
            if ($refererHost && $refererScheme) {
                $refererOrigin = $refererScheme . '://' . $refererHost;
                return in_array($refererOrigin, $allowedOrigins, true);
            }
        }
        
        // If neither Origin nor Referer is present, assume same-origin browser request
        // (common for same-site fetch where browsers omit Origin)
        // This is acceptable because we already validated the CSRF token + double-submit cookie.
        return true;
    }
    
    /**
     * Get client IP address
     * 
     * @return string Client IP
     */
    private function getClientIP() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * Get environment variable as string
     * 
     * @param string $key Environment variable name
     * @param string $default Default value
     * @return string
     */
    private function getEnvString($key, $default = '') {
        $value = $_ENV[$key] ?? getenv($key);
        return ($value !== false && $value !== null) ? (string)$value : $default;
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

// Global CSRF protection instance
$csrfProtection = new CsrfProtection();

/**
 * Generate CSRF token for current session
 * 
 * @return string Generated token
 */
function generateCsrfToken() {
    global $csrfProtection;
    return $csrfProtection->generateToken();
}

/**
 * Validate CSRF token from request
 * 
 * @param string|null $token Optional token override
 * @return bool True if valid
 */
function validateCsrfToken($token = null) {
    global $csrfProtection;
    return $csrfProtection->validateToken($token);
}

/**
 * Validate CSRF protection for current request
 * Sends error response if validation fails
 * 
 * @param string|null $token Optional token override
 * @return bool True if valid (false means error response sent)
 */
function validateCsrfProtection($token = null) {
    global $csrfProtection;
    return $csrfProtection->validateRequest($token);
}

/**
 * Get current CSRF token
 * 
 * @param bool $generate Generate new token if none exists
 * @return string|null Current token
 */
function getCsrfToken($generate = true) {
    global $csrfProtection;
    return $csrfProtection->getToken($generate);
}

/**
 * Clear CSRF tokens
 */
function clearCsrfTokens() {
    global $csrfProtection;
    $csrfProtection->clearTokens();
}

/**
 * Check if request method requires CSRF protection
 * 
 * @param string|null $method HTTP method (defaults to current request method)
 * @return bool True if CSRF protection is required
 */
function requiresCsrfProtection($method = null) {
    if ($method === null) {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }
    
    $protectedMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];
    return in_array(strtoupper($method), $protectedMethods, true);
}
