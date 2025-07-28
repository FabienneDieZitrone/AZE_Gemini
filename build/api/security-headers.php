<?php
/**
 * Security Headers for AZE_Gemini
 * 
 * Sets security headers to protect against common vulnerabilities
 * Should be included at the beginning of each API endpoint
 */

/**
 * Set security headers
 */
function setSecurityHeaders() {
    // Prevent clickjacking
    header('X-Frame-Options: DENY');
    
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Enable XSS protection in older browsers
    header('X-XSS-Protection: 1; mode=block');
    
    // Force HTTPS
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    
    // Content Security Policy
    $csp = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://login.microsoftonline.com",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data: https:",
        "font-src 'self' data:",
        "connect-src 'self' https://login.microsoftonline.com https://graph.microsoft.com",
        "frame-src 'none'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'"
    ];
    header('Content-Security-Policy: ' . implode('; ', $csp));
    
    // Referrer Policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Permissions Policy (formerly Feature Policy)
    $permissions = [
        'geolocation=()',
        'microphone=()',
        'camera=()',
        'payment=()',
        'usb=()',
        'magnetometer=()',
        'accelerometer=()',
        'gyroscope=()'
    ];
    header('Permissions-Policy: ' . implode(', ', $permissions));
    
    // Remove PHP version header
    header_remove('X-Powered-By');
    
    // Set secure cookie parameters if session is active
    if (session_status() === PHP_SESSION_ACTIVE) {
        $cookieParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => $cookieParams['lifetime'],
            'path' => $cookieParams['path'],
            'domain' => $cookieParams['domain'],
            'secure' => true, // Require HTTPS
            'httponly' => true, // Prevent JavaScript access
            'samesite' => 'Strict' // CSRF protection
        ]);
    }
}

/**
 * Set CORS headers for API endpoints
 */
function setCorsHeaders($allowedOrigin = null) {
    // Get request origin
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    // Define allowed origins
    $allowedOrigins = [
        'https://aze.mikropartner.de',
        'http://localhost:5173', // Dev environment
        'http://localhost:3000'  // Alternative dev port
    ];
    
    // Add custom allowed origin if provided
    if ($allowedOrigin) {
        $allowedOrigins[] = $allowedOrigin;
    }
    
    // Check if origin is allowed
    if (in_array($origin, $allowedOrigins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
    }
    
    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400'); // 24 hours
        http_response_code(204);
        exit;
    }
}

/**
 * Validate request method
 */
function validateRequestMethod($allowedMethods) {
    if (!is_array($allowedMethods)) {
        $allowedMethods = [$allowedMethods];
    }
    
    if (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
        http_response_code(405);
        header('Allow: ' . implode(', ', $allowedMethods));
        exit(json_encode([
            'success' => false,
            'error' => 'Method not allowed'
        ]));
    }
}

/**
 * Rate limiting (basic implementation)
 */
function checkRateLimit($identifier, $maxRequests = 60, $timeWindow = 60) {
    $cacheDir = __DIR__ . '/../cache/rate-limit';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $cacheFile = $cacheDir . '/' . md5($identifier) . '.json';
    $now = time();
    
    // Read existing data
    $data = [];
    if (file_exists($cacheFile)) {
        $content = file_get_contents($cacheFile);
        $data = json_decode($content, true) ?? [];
    }
    
    // Clean old entries
    $data = array_filter($data, function($timestamp) use ($now, $timeWindow) {
        return ($now - $timestamp) < $timeWindow;
    });
    
    // Check limit
    if (count($data) >= $maxRequests) {
        http_response_code(429);
        header('Retry-After: ' . $timeWindow);
        exit(json_encode([
            'success' => false,
            'error' => 'Rate limit exceeded'
        ]));
    }
    
    // Add current request
    $data[] = $now;
    file_put_contents($cacheFile, json_encode($data));
}

/**
 * Initialize security for API endpoint
 */
function initializeSecurity($requireAuth = true) {
    // Set security headers
    setSecurityHeaders();
    
    // Set CORS headers
    setCorsHeaders();
    
    // Start session if not started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check authentication if required
    if ($requireAuth && empty($_SESSION['user_id'])) {
        http_response_code(401);
        exit(json_encode([
            'success' => false,
            'error' => 'Authentication required'
        ]));
    }
    
    // Basic rate limiting by IP
    $clientIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? 
                $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 
                $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    checkRateLimit('ip:' . $clientIp, 300, 60); // 300 requests per minute
    
    // User-specific rate limiting if authenticated
    if (!empty($_SESSION['user_id'])) {
        checkRateLimit('user:' . $_SESSION['user_id'], 100, 60); // 100 requests per minute per user
    }
}