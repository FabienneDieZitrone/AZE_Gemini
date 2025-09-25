<?php
/**
 * Unified Authentication Service
 * Version: 1.0 - Clean Architecture & SOLID Principles
 * Author: Refactoring Expert
 * Description: Single source of truth for authentication functionality
 *              Replaces: auth_helpers.php, auth_helpers_refactored.php
 */

declare(strict_types=1);

require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/structured-logger.php';
require_once __DIR__ . '/InputValidationService.php';

interface SessionManagerInterface {
    public function startSecureSession(): void;
    public function validateSession(): bool;
    public function destroySession(): void;
    public function regenerateId(): void;
    public function getUser(): ?array;
    public function setUser(array $userData): void;
    public function isAuthenticated(): bool;
}

interface AuthenticationInterface {
    public function authenticate(array $credentials): AuthenticationResult;
    public function verifySession(): AuthenticationResult;
    public function logout(): void;
}

interface CORSHandlerInterface {
    public function handleCORS(): void;
    public function isAllowedOrigin(string $origin): bool;
}

class AuthenticationResult {
    private bool $success;
    private ?array $user;
    private array $errors;
    private array $metadata;
    
    public function __construct(bool $success, ?array $user = null, array $errors = [], array $metadata = []) {
        $this->success = $success;
        $this->user = $user;
        $this->errors = $errors;
        $this->metadata = $metadata;
    }
    
    public function isSuccessful(): bool {
        return $this->success;
    }
    
    public function getUser(): ?array {
        return $this->user;
    }
    
    public function getErrors(): array {
        return $this->errors;
    }
    
    public function getMetadata(): array {
        return $this->metadata;
    }
    
    public function toArray(): array {
        return [
            'success' => $this->success,
            'user' => $this->user,
            'errors' => $this->errors,
            'metadata' => $this->metadata
        ];
    }
}

/**
 * Secure Session Manager following security best practices
 */
class SecureSessionManager implements SessionManagerInterface {
    private static ?self $instance = null;
    private StructuredLogger $logger;
    private array $config;
    private bool $isStarted = false;
    
    private function __construct() {
        $this->logger = new StructuredLogger();
        $this->config = $this->getSessionConfig();
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function getSessionConfig(): array {
        return [
            'name' => 'AZE_SESSION',
            'lifetime' => 0, // Browser session
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'] ?? '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
            'absolute_timeout' => SECONDS_PER_DAY, // 24 hours
            'inactivity_timeout' => SECONDS_PER_HOUR, // 1 hour
            'regeneration_interval' => 1800 // 30 minutes
        ];
    }
    
    public function startSecureSession(): void {
        if ($this->isStarted || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        
        // Set session name
        session_name($this->config['name']);
        
        // Configure secure session parameters
        session_set_cookie_params([
            'lifetime' => $this->config['lifetime'],
            'path' => $this->config['path'],
            'domain' => $this->config['domain'],
            'secure' => $this->config['secure'],
            'httponly' => $this->config['httponly'],
            'samesite' => $this->config['samesite']
        ]);
        
        session_start();
        $this->isStarted = true;
        
        $this->initializeSessionTimestamps();
        
        $this->logger->debug('Secure session started', [
            'session_id' => session_id(),
            'session_name' => session_name()
        ]);
    }
    
    private function initializeSessionTimestamps(): void {
        $currentTime = time();
        
        if (!isset($_SESSION['created_at'])) {
            $_SESSION['created_at'] = $currentTime;
        }
        
        $_SESSION['last_activity'] = $currentTime;
        
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = $currentTime;
        }
    }
    
    public function validateSession(): bool {
        if (!$this->isSessionTimestampValid()) {
            $this->destroySession();
            return false;
        }
        
        $this->updateActivity();
        $this->handleSessionRegeneration();
        
        return true;
    }
    
    private function isSessionTimestampValid(): bool {
        $currentTime = time();
        
        // Check absolute timeout
        if (isset($_SESSION['created_at'])) {
            if ($currentTime - $_SESSION['created_at'] > $this->config['absolute_timeout']) {
                $this->logger->warning('Session absolute timeout exceeded');
                return false;
            }
        } else {
            $this->logger->warning('Session missing creation timestamp');
            return false;
        }
        
        // Check inactivity timeout
        if (isset($_SESSION['last_activity'])) {
            if ($currentTime - $_SESSION['last_activity'] > $this->config['inactivity_timeout']) {
                $this->logger->warning('Session inactivity timeout exceeded');
                return false;
            }
        } else {
            $this->logger->warning('Session missing activity timestamp');
            return false;
        }
        
        return true;
    }
    
    private function updateActivity(): void {
        $_SESSION['last_activity'] = time();
    }
    
    private function handleSessionRegeneration(): void {
        $currentTime = time();
        
        if ($currentTime - $_SESSION['last_regeneration'] > $this->config['regeneration_interval']) {
            $this->regenerateId();
        }
    }
    
    public function regenerateId(): void {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
        $this->logger->info('Session ID regenerated for security');
    }
    
    public function destroySession(): void {
        // Clear session variables
        $_SESSION = [];
        
        // Delete session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), 
                '', 
                time() - 42000,
                $params["path"], 
                $params["domain"],
                $params["secure"], 
                $params["httponly"]
            );
        }
        
        // Destroy session
        session_destroy();
        $this->isStarted = false;
        
        $this->logger->info('Session completely destroyed');
    }
    
    public function getUser(): ?array {
        return $_SESSION['user'] ?? null;
    }
    
    public function setUser(array $userData): void {
        $_SESSION['user'] = $userData;
        $this->logger->debug('User data stored in session', ['user_id' => $userData['id'] ?? 'unknown']);
    }
    
    public function isAuthenticated(): bool {
        return isset($_SESSION['user']) && !empty($_SESSION['user']['oid']);
    }
    
    public function setMFAVerified(bool $verified = true): void {
        $_SESSION['mfa_verified'] = $verified;
        $_SESSION['mfa_verified_at'] = time();
    }
    
    public function isMFAVerified(): bool {
        if (!isset($_SESSION['mfa_verified']) || $_SESSION['mfa_verified'] !== true) {
            return false;
        }
        
        // Check MFA session timeout
        $verifiedAt = $_SESSION['mfa_verified_at'] ?? 0;
        $mfaTimeout = 3600; // 1 hour MFA validity
        
        if (time() - $verifiedAt >= $mfaTimeout) {
            unset($_SESSION['mfa_verified'], $_SESSION['mfa_verified_at']);
            return false;
        }
        
        return true;
    }
}

/**
 * CORS Handler for secure cross-origin requests
 */
class CORSHandler implements CORSHandlerInterface {
    private static array $allowedOrigins = [
        'https://aze.mikropartner.de',
        'https://aze.mikropartner.de:443'
    ];
    
    private StructuredLogger $logger;
    
    public function __construct() {
        $this->logger = new StructuredLogger();
    }
    
    public function handleCORS(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->sendCORSHeaders();
            $this->logger->debug('CORS preflight request handled');
            exit(0);
        } else {
            $this->sendCORSHeaders();
        }
    }
    
    public function isAllowedOrigin(string $origin): bool {
        return in_array($origin, self::$allowedOrigins, true);
    }
    
    private function sendCORSHeaders(): void {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if ($this->isAllowedOrigin($origin)) {
            header("Access-Control-Allow-Origin: " . $origin);
        } else if (!empty($origin)) {
            $this->logger->warning('CORS request from unauthorized origin', ['origin' => $origin]);
            // Don't send CORS headers for unauthorized origins
            return;
        }
        
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
        header("Access-Control-Max-Age: " . SECONDS_PER_HOUR);
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");
    }
}

/**
 * Main Authentication Service
 */
class AuthenticationService implements AuthenticationInterface {
    private SessionManagerInterface $sessionManager;
    private CORSHandlerInterface $corsHandler;
    private StructuredLogger $logger;
    private InputValidationService $validator;
    
    public function __construct(
        ?SessionManagerInterface $sessionManager = null,
        ?CORSHandlerInterface $corsHandler = null
    ) {
        $this->sessionManager = $sessionManager ?? SecureSessionManager::getInstance();
        $this->corsHandler = $corsHandler ?? new CORSHandler();
        $this->logger = new StructuredLogger();
        $this->validator = InputValidationService::getInstance();
    }
    
    public function authenticate(array $credentials): AuthenticationResult {
        try {
            // Validate input
            $validatedCredentials = $this->validator->validateData($credentials, [
                'username' => ['type' => 'email', 'required' => true],
                'password' => ['type' => 'string', 'required' => true, 'min_length' => 1]
            ]);
            
            // This would integrate with your OAuth/Azure AD flow
            // For now, we assume session-based authentication from Azure
            $user = $this->sessionManager->getUser();
            
            if (!$user) {
                return new AuthenticationResult(false, null, ['Invalid credentials or session expired']);
            }
            
            $this->logger->info('User authentication successful', [
                'user_id' => $user['id'] ?? 'unknown'
            ]);
            
            return new AuthenticationResult(true, $user);
            
        } catch (ValidationException $e) {
            $this->logger->warning('Authentication validation failed', [
                'errors' => $e->getValidationErrors()
            ]);
            
            return new AuthenticationResult(false, null, $e->getValidationErrors());
        } catch (Exception $e) {
            $this->logger->error('Authentication error', [
                'error' => $e->getMessage()
            ]);
            
            return new AuthenticationResult(false, null, ['Authentication failed']);
        }
    }
    
    public function verifySession(): AuthenticationResult {
        $this->sessionManager->startSecureSession();
        
        if (!$this->sessionManager->validateSession()) {
            return new AuthenticationResult(false, null, ['Session expired']);
        }
        
        if (!$this->sessionManager->isAuthenticated()) {
            return new AuthenticationResult(false, null, ['Not authenticated']);
        }
        
        $user = $this->sessionManager->getUser();
        return new AuthenticationResult(true, $user);
    }
    
    public function logout(): void {
        $user = $this->sessionManager->getUser();
        $this->sessionManager->destroySession();
        
        $this->logger->info('User logged out', [
            'user_id' => $user['id'] ?? 'unknown'
        ]);
    }
    
    public function handleCORS(): void {
        $this->corsHandler->handleCORS();
    }
    
    public function sendResponse(int $statusCode, $data = null): void {
        if (headers_sent()) {
            $this->logger->error('Attempted to send response after headers sent');
            return;
        }
        
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        if ($data !== null) {
            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        
        exit();
    }
    
    public function sendUnauthorizedResponse(string $message): void {
        $this->logger->warning('Unauthorized access attempt', ['message' => $message]);
        $this->sendResponse(401, [
            'message' => "Unauthorized: {$message}. Please login again."
        ]);
    }
    
    /**
     * Initialize API with security measures
     */
    public function initializeAPI(): void {
        $this->handleCORS();
        
        // Additional security initialization can go here
        $this->logger->debug('API initialized with security measures');
    }
    
    /**
     * Get session manager instance
     */
    public function getSessionManager(): SessionManagerInterface {
        return $this->sessionManager;
    }
}

// Global helper functions for backward compatibility
function initialize_api(): void {
    (new AuthenticationService())->initializeAPI();
}

function send_response(int $statusCode, $data = null): void {
    (new AuthenticationService())->sendResponse($statusCode, $data);
}

function start_secure_session(): void {
    SecureSessionManager::getInstance()->startSecureSession();
}

function verify_session_and_get_user(): array {
    $auth = new AuthenticationService();
    $result = $auth->verifySession();
    
    if (!$result->isSuccessful()) {
        $auth->sendUnauthorizedResponse(implode(', ', $result->getErrors()));
    }
    
    return $result->getUser();
}

function destroy_session_completely(): void {
    SecureSessionManager::getInstance()->destroySession();
}

// Polyfill for getallheaders() if not available
if (!function_exists('getallheaders')) {
    function getallheaders(): array {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$headerName] = $value;
            }
        }
        
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }
        
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
        }
        
        return $headers;
    }
}
?>