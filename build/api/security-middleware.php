<?php
/**
 * Security Middleware
 * Provides centralized security headers for all API endpoints
 * 
 * Usage: require_once 'security-middleware.php';
 */

// Prevent direct access
if (!defined('API_GUARD')) {
    http_response_code(403);
    die('Direct access forbidden');
}

/**
 * Apply security headers to the response
 * Should be called at the beginning of each API endpoint
 */
function applySecurityHeaders() {
    // Prevent clickjacking attacks
    header('X-Frame-Options: DENY');
    
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Enable XSS protection in older browsers
    header('X-XSS-Protection: 1; mode=block');
    
    // Strict Transport Security (HSTS)
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    
    // Content Security Policy (API-friendly, an die App angepasst)
    // APIs liefern JSON; keine Skriptausführung erforderlich.
    // connect-src erlaubt Same-Origin API Requests; Azure Endpunkte optional freigegeben.
    $cspParts = [
        "default-src 'none'",
        "script-src 'none'",
        "connect-src 'self' https://login.microsoftonline.com https://graph.microsoft.com",
        "img-src 'self' data:",
        "style-src 'self' 'unsafe-inline'",
        "font-src 'self'",
        "frame-ancestors 'none'",
        "base-uri 'self'",
        "form-action 'self'"
    ];
    header('Content-Security-Policy: ' . implode('; ', $cspParts));
    
    // Referrer Policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Permissions Policy (formerly Feature Policy)
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    
    // Set secure JSON content type for API responses
    header('Content-Type: application/json; charset=utf-8');
    
    // Cache control for API responses
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
    header('Expires: 0');
}

/**
 * Apply CORS headers for API endpoints
 * @param array $allowedOrigins List of allowed origins
 */
function applyCorsHeaders($allowedOrigins = []) {
    // Default allowed origins
    if (empty($allowedOrigins)) {
        $allowedOrigins = [
            'https://aze.mikropartner.de'
        ];
        
        // Only allow localhost in development
        if (defined('APP_ENV') && APP_ENV === 'development') {
            $allowedOrigins[] = 'http://localhost:5173';
            $allowedOrigins[] = 'http://localhost:3000';
        }
    }
    
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    if (in_array($origin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400'); // 24 hours
    }
    
    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/**
 * Initialize security middleware
 * Call this at the beginning of every API endpoint
 */
function initSecurityMiddleware() {
    applySecurityHeaders();
    applyCorsHeaders();
}

// Export for use in other files
return [
    'applySecurityHeaders' => 'applySecurityHeaders',
    'applyCorsHeaders' => 'applyCorsHeaders',
    'initSecurityMiddleware' => 'initSecurityMiddleware'
];
