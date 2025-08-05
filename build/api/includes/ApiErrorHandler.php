<?php
/**
 * Standardized API Error Handler for AZE_Gemini
 * 
 * Provides consistent error responses across all API endpoints
 * Compatible with frontend ErrorMessageService
 */

namespace AzeGemini\Api;

class ApiErrorHandler {
    
    // Error codes matching frontend ErrorMessageService
    const ERROR_CODES = [
        'AUTH_FAILED' => 'AUTH_FAILED',
        'AUTH_EXPIRED' => 'AUTH_EXPIRED',
        'NETWORK_ERROR' => 'NETWORK_ERROR',
        'TIMEOUT' => 'TIMEOUT',
        'VALIDATION_REQUIRED' => 'VALIDATION_REQUIRED',
        'VALIDATION_FORMAT' => 'VALIDATION_FORMAT',
        'VALIDATION_HOURS' => 'VALIDATION_HOURS',
        'VALIDATION_DATE_FUTURE' => 'VALIDATION_DATE_FUTURE',
        'VALIDATION_DATE_OLD' => 'VALIDATION_DATE_OLD',
        'TIMER_ALREADY_RUNNING' => 'TIMER_ALREADY_RUNNING',
        'TIMER_NOT_RUNNING' => 'TIMER_NOT_RUNNING',
        'PERMISSION_DENIED' => 'PERMISSION_DENIED',
        'NOT_FOUND' => 'NOT_FOUND',
        'DATABASE_ERROR' => 'DATABASE_ERROR',
        'INTERNAL_ERROR' => 'INTERNAL_ERROR'
    ];
    
    /**
     * Send standardized error response
     */
    public static function sendError($code, $message, $field = null, $statusCode = 400) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'timestamp' => date('c')
            ]
        ];
        
        // Add field information for validation errors
        if ($field) {
            $response['error']['field'] = $field;
        }
        
        // Log error for monitoring
        self::logError($code, $message, $field);
        
        echo json_encode($response);
        exit;
    }
    
    /**
     * Send validation error with field context
     */
    public static function sendValidationError($field, $message, $code = null) {
        $errorCode = $code ?: self::ERROR_CODES['VALIDATION_FORMAT'];
        self::sendError($errorCode, $message, $field, 400);
    }
    
    /**
     * Send authentication error
     */
    public static function sendAuthError($message = 'Authentifizierung erforderlich') {
        self::sendError(
            self::ERROR_CODES['AUTH_EXPIRED'],
            $message,
            null,
            401
        );
    }
    
    /**
     * Send permission denied error
     */
    public static function sendPermissionError($message = 'Keine Berechtigung für diese Aktion') {
        self::sendError(
            self::ERROR_CODES['PERMISSION_DENIED'],
            $message,
            null,
            403
        );
    }
    
    /**
     * Send not found error
     */
    public static function sendNotFoundError($message = 'Ressource nicht gefunden') {
        self::sendError(
            self::ERROR_CODES['NOT_FOUND'],
            $message,
            null,
            404
        );
    }
    
    /**
     * Send database error
     */
    public static function sendDatabaseError($message = 'Datenbankfehler aufgetreten') {
        // Hide technical details in production
        if (!defined('DEBUG') || !DEBUG) {
            $message = 'Ein Datenbankfehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
        }
        
        self::sendError(
            self::ERROR_CODES['DATABASE_ERROR'],
            $message,
            null,
            500
        );
    }
    
    /**
     * Send timer-specific errors
     */
    public static function sendTimerError($isRunning) {
        if ($isRunning) {
            self::sendError(
                self::ERROR_CODES['TIMER_ALREADY_RUNNING'],
                'Die Zeiterfassung läuft bereits',
                null,
                409
            );
        } else {
            self::sendError(
                self::ERROR_CODES['TIMER_NOT_RUNNING'],
                'Es läuft keine Zeiterfassung',
                null,
                409
            );
        }
    }
    
    /**
     * Send success response
     */
    public static function sendSuccess($data = null, $message = null) {
        header('Content-Type: application/json');
        
        $response = [
            'success' => true,
            'timestamp' => date('c')
        ];
        
        if ($message) {
            $response['message'] = $message;
        }
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response);
        exit;
    }
    
    /**
     * Validate required fields
     */
    public static function validateRequired($data, $requiredFields) {
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                self::sendValidationError(
                    $field,
                    ucfirst($field) . ' ist erforderlich',
                    self::ERROR_CODES['VALIDATION_REQUIRED']
                );
            }
        }
    }
    
    /**
     * Validate hours input
     */
    public static function validateHours($hours, $field = 'hours') {
        if (!is_numeric($hours)) {
            self::sendValidationError(
                $field,
                'Stunden müssen numerisch sein',
                self::ERROR_CODES['VALIDATION_HOURS']
            );
        }
        
        $hoursFloat = floatval($hours);
        
        if ($hoursFloat < 0.25) {
            self::sendValidationError(
                $field,
                'Mindestens 15 Minuten (0,25 Stunden) müssen erfasst werden',
                self::ERROR_CODES['VALIDATION_HOURS']
            );
        }
        
        if ($hoursFloat > 24) {
            self::sendValidationError(
                $field,
                'Maximal 24 Stunden pro Tag erlaubt',
                self::ERROR_CODES['VALIDATION_HOURS']
            );
        }
        
        // Check if hours are in 15-minute increments
        $minutes = $hoursFloat * 60;
        if ($minutes % 15 !== 0) {
            self::sendValidationError(
                $field,
                'Zeit muss in 15-Minuten-Schritten erfasst werden',
                self::ERROR_CODES['VALIDATION_HOURS']
            );
        }
    }
    
    /**
     * Validate date input
     */
    public static function validateDate($date, $field = 'date') {
        $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
        
        if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
            self::sendValidationError(
                $field,
                'Ungültiges Datumsformat. Format: YYYY-MM-DD',
                self::ERROR_CODES['VALIDATION_FORMAT']
            );
        }
        
        $today = new \DateTime();
        $today->setTime(23, 59, 59);
        
        if ($dateObj > $today) {
            self::sendValidationError(
                $field,
                'Zeiteinträge für zukünftige Daten sind nicht erlaubt',
                self::ERROR_CODES['VALIDATION_DATE_FUTURE']
            );
        }
        
        $cutoff = new \DateTime();
        $cutoff->modify('-30 days');
        $cutoff->setTime(0, 0, 0);
        
        if ($dateObj < $cutoff) {
            self::sendValidationError(
                $field,
                'Zeiteinträge älter als 30 Tage können nicht mehr bearbeitet werden',
                self::ERROR_CODES['VALIDATION_DATE_OLD']
            );
        }
    }
    
    /**
     * Log errors for monitoring
     */
    private static function logError($code, $message, $field = null) {
        $logEntry = [
            'timestamp' => date('c'),
            'code' => $code,
            'message' => $message,
            'field' => $field,
            'user_id' => $_SESSION['user_id'] ?? 'anonymous',
            'endpoint' => $_SERVER['REQUEST_URI'],
            'method' => $_SERVER['REQUEST_METHOD'],
            'ip' => $_SERVER['REMOTE_ADDR']
        ];
        
        error_log(json_encode($logEntry));
    }
}

// Set up global exception handler
set_exception_handler(function($exception) {
    ApiErrorHandler::sendError(
        ApiErrorHandler::ERROR_CODES['INTERNAL_ERROR'],
        'Ein unerwarteter Fehler ist aufgetreten',
        null,
        500
    );
});
?>