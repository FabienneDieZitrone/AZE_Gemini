<?php
/**
 * Central Error Handler for AZE_Gemini
 * 
 * Provides consistent error handling across all API endpoints
 * Implements structured error responses matching frontend AppError format
 */

// Error codes matching frontend
define('ERROR_CODES', [
    'NETWORK_ERROR' => 'NETWORK_ERROR',
    'AUTH_EXPIRED' => 'AUTH_EXPIRED',
    'TIMER_ALREADY_RUNNING' => 'TIMER_ALREADY_RUNNING',
    'TIMER_NOT_RUNNING' => 'TIMER_NOT_RUNNING',
    'VALIDATION_ERROR' => 'VALIDATION_ERROR',
    'DATABASE_ERROR' => 'DATABASE_ERROR',
    'PERMISSION_DENIED' => 'PERMISSION_DENIED',
    'NOT_FOUND' => 'NOT_FOUND',
    'INTERNAL_ERROR' => 'INTERNAL_ERROR'
]);

class AppError extends Exception {
    public $code;
    public $userMessage;
    public $details;
    public $recoveryStrategy;
    
    public function __construct($code, $message, $userMessage = null, $details = null, $recoveryStrategy = null) {
        parent::__construct($message);
        $this->code = $code;
        $this->userMessage = $userMessage ?? $message;
        $this->details = $details;
        $this->recoveryStrategy = $recoveryStrategy;
    }
}

/**
 * Global error handler function
 */
function handleError($error) {
    // Normalize to AppError if needed
    if (!($error instanceof AppError)) {
        $error = new AppError(
            ERROR_CODES['INTERNAL_ERROR'],
            $error->getMessage(),
            'An unexpected error occurred',
            ['originalError' => get_class($error)]
        );
    }
    
    // Log error (if logger available)
    if (function_exists('logError')) {
        logError($error);
    }
    
    // Send error response
    http_response_code(getHttpStatusCode($error->code));
    header('Content-Type: application/json');
    
    $response = [
        'success' => false,
        'error' => [
            'code' => $error->code,
            'message' => $error->userMessage,
            'details' => $error->details,
            'recoveryStrategy' => $error->recoveryStrategy
        ]
    ];
    
    echo json_encode($response);
    exit;
}

/**
 * Map error codes to HTTP status codes
 */
function getHttpStatusCode($errorCode) {
    $mapping = [
        ERROR_CODES['AUTH_EXPIRED'] => 401,
        ERROR_CODES['PERMISSION_DENIED'] => 403,
        ERROR_CODES['NOT_FOUND'] => 404,
        ERROR_CODES['VALIDATION_ERROR'] => 400,
        ERROR_CODES['TIMER_ALREADY_RUNNING'] => 409,
        ERROR_CODES['TIMER_NOT_RUNNING'] => 409,
        ERROR_CODES['DATABASE_ERROR'] => 500,
        ERROR_CODES['INTERNAL_ERROR'] => 500,
        ERROR_CODES['NETWORK_ERROR'] => 503
    ];
    
    return $mapping[$errorCode] ?? 500;
}

/**
 * Set up global error handlers
 */
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function($exception) {
    handleError($exception);
});

// Disable error display in production
if (!isset($_ENV['APP_DEBUG']) || $_ENV['APP_DEBUG'] !== 'true') {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL);
}

/**
 * Helper function to throw validation errors
 */
function throwValidationError($field, $message) {
    throw new AppError(
        ERROR_CODES['VALIDATION_ERROR'],
        "Validation failed for field: $field",
        $message,
        ['field' => $field],
        'correct_input'
    );
}

/**
 * Helper function to throw auth errors
 */
function throwAuthError($message = 'Authentication required') {
    throw new AppError(
        ERROR_CODES['AUTH_EXPIRED'],
        $message,
        'Please log in to continue',
        null,
        'login'
    );
}

/**
 * Helper function to throw database errors
 */
function throwDatabaseError($message, $query = null) {
    $details = null;
    if (isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true' && $query) {
        $details = ['query' => $query];
    }
    
    throw new AppError(
        ERROR_CODES['DATABASE_ERROR'],
        $message,
        'A database error occurred. Please try again later.',
        $details,
        'retry'
    );
}