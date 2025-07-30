<?php
/**
 * Input Validation Library - FIXED VERSION
 * Removes problematic htmlspecialchars that causes 500 errors
 */

class InputValidator {
    
    /**
     * Validate and sanitize JSON input from php://input
     * @param array $required_fields Array of required field names
     * @param array $optional_fields Array of optional field names with default values
     * @return array Validated data or throws exception
     */
    public static function validateJsonInput($required_fields = [], $optional_fields = []) {
        $raw_input = file_get_contents('php://input');
        
        if (empty($raw_input)) {
            throw new InvalidArgumentException('Empty request body');
        }
        
        $data = json_decode($raw_input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }
        
        // Check required fields
        foreach ($required_fields as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }
        
        // Add optional fields with defaults
        foreach ($optional_fields as $field => $default) {
            if (!isset($data[$field])) {
                $data[$field] = $default;
            }
        }
        
        return self::sanitizeData($data);
    }
    
    /**
     * Validate $_GET parameters
     * @param array $allowed_params Array of allowed parameter names
     * @return array Validated parameters
     */
    public static function validateGetParams($allowed_params = []) {
        $validated = [];
        
        foreach ($_GET as $key => $value) {
            if (!in_array($key, $allowed_params)) {
                continue; // Skip unexpected parameters
            }
            $validated[$key] = self::sanitizeString($value);
        }
        
        return $validated;
    }
    
    /**
     * Sanitize data recursively
     * @param mixed $data Data to sanitize
     * @return mixed Sanitized data
     */
    private static function sanitizeData($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeData'], $data);
        }
        
        if (is_string($data)) {
            return self::sanitizeString($data);
        }
        
        return $data; // Numbers, booleans, null, etc.
    }
    
    /**
     * Sanitize string input - FIXED VERSION
     * @param string $input Input string
     * @return string Sanitized string
     */
    private static function sanitizeString($input) {
        // Handle non-string input gracefully
        if (!is_string($input)) {
            return $input;
        }
        
        // Trim whitespace
        $input = trim($input);
        
        // Remove null bytes (potential for SQL injection)
        $input = str_replace("\0", '', $input);
        
        // Remove control characters except tab, newline, carriage return
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        
        // SECURITY FIX: Re-enable XSS protection
        // While output encoding is important, input sanitization provides defense in depth
        // This prevents stored XSS attacks where malicious scripts are saved to the database
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $input;
    }
    
    /**
     * Validate date format (YYYY-MM-DD)
     * @param string $date Date string
     * @return bool True if valid
     */
    public static function isValidDate($date) {
        if (!is_string($date)) return false;
        
        $format = 'Y-m-d';
        $dateTime = DateTime::createFromFormat($format, $date);
        return $dateTime && $dateTime->format($format) === $date;
    }
    
    /**
     * Validate time format (HH:MM:SS)
     * @param string $time Time string
     * @return bool True if valid
     */
    public static function isValidTime($time) {
        if (!is_string($time)) return false;
        
        return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $time);
    }
    
    /**
     * Validate integer ID
     * @param mixed $id ID value
     * @return bool True if valid positive integer
     */
    public static function isValidId($id) {
        return is_numeric($id) && intval($id) > 0;
    }
    
    /**
     * Validate email format
     * @param string $email Email string
     * @return bool True if valid
     */
    public static function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate username (alphanumeric + underscore + dot + space)
     * @param string $username Username string
     * @return bool True if valid
     */
    public static function isValidUsername($username) {
        // Allow spaces for names from Azure AD (e.g. "Max Mustermann")
        return preg_match('/^[a-zA-Z0-9._\- ]+$/', $username) && strlen($username) >= 2 && strlen($username) <= 50;
    }
    
    /**
     * Escape string for safe HTML output
     * Use this when outputting user data to prevent XSS
     * @param string $string String to escape
     * @return string Escaped string
     */
    public static function escapeHtml($string) {
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Escape string for safe SQL use (when not using prepared statements)
     * @param mysqli $conn Database connection
     * @param string $string String to escape
     * @return string Escaped string
     */
    public static function escapeSql($conn, $string) {
        return $conn->real_escape_string($string);
    }
}

/**
 * Validation Exception for input validation errors
 */
class ValidationException extends Exception {
    public function __construct($message, $code = 400, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}