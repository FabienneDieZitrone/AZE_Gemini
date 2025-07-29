<?php
/**
 * DEBUG Version of validation.php
 * Logs every step to find the exact error
 */

class InputValidator {
    
    private static $debug_log = [];
    
    public static function getDebugLog() {
        return self::$debug_log;
    }
    
    private static function log($step, $data = null) {
        self::$debug_log[] = [
            'step' => $step,
            'data' => $data,
            'time' => microtime(true),
            'memory' => memory_get_usage()
        ];
    }
    
    /**
     * Validate and sanitize JSON input from php://input
     * @param array $required_fields Array of required field names
     * @param array $optional_fields Array of optional field names with default values
     * @return array Validated data or throws exception
     */
    public static function validateJsonInput($required_fields = [], $optional_fields = []) {
        self::log('validateJsonInput_start', ['required' => $required_fields, 'optional' => $optional_fields]);
        
        // Check if we can read php://input
        $raw_input = file_get_contents('php://input');
        self::log('php_input_read', ['length' => strlen($raw_input), 'first_100' => substr($raw_input, 0, 100)]);
        
        if (empty($raw_input)) {
            self::log('empty_input_error');
            throw new InvalidArgumentException('Empty request body');
        }
        
        // Try to decode JSON
        self::log('json_decode_start');
        $data = json_decode($raw_input, true);
        $json_error = json_last_error();
        self::log('json_decode_result', [
            'error_code' => $json_error,
            'error_msg' => json_last_error_msg(),
            'data_type' => gettype($data),
            'data_keys' => is_array($data) ? array_keys($data) : null
        ]);
        
        if ($json_error !== JSON_ERROR_NONE) {
            self::log('json_error');
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }
        
        // Check required fields
        self::log('check_required_fields_start');
        foreach ($required_fields as $field) {
            self::log('check_field', ['field' => $field, 'exists' => isset($data[$field]), 'value' => $data[$field] ?? null]);
            if (!isset($data[$field])) {
                self::log('missing_field_error', $field);
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }
        
        // Add optional fields with defaults
        self::log('add_optional_fields_start');
        foreach ($optional_fields as $field => $default) {
            if (!isset($data[$field])) {
                self::log('add_optional_field', ['field' => $field, 'default' => $default]);
                $data[$field] = $default;
            }
        }
        
        // Sanitize data
        self::log('sanitize_start', ['data_before' => $data]);
        try {
            $sanitized = self::sanitizeData($data);
            self::log('sanitize_complete', ['data_after' => $sanitized]);
            return $sanitized;
        } catch (Exception $e) {
            self::log('sanitize_error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
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
        self::log('sanitizeData', ['type' => gettype($data), 'value' => $data]);
        
        if (is_array($data)) {
            self::log('sanitizeData_array', ['count' => count($data)]);
            return array_map([self::class, 'sanitizeData'], $data);
        }
        
        if (is_string($data)) {
            self::log('sanitizeData_string', ['length' => strlen($data), 'value' => $data]);
            return self::sanitizeString($data);
        }
        
        self::log('sanitizeData_passthrough', ['type' => gettype($data)]);
        return $data; // Numbers, booleans, etc.
    }
    
    /**
     * Sanitize string input
     * @param string $input Input string
     * @return string Sanitized string
     */
    private static function sanitizeString($input) {
        self::log('sanitizeString_start', ['input' => $input, 'type' => gettype($input)]);
        
        // Check if input is actually a string
        if (!is_string($input)) {
            self::log('sanitizeString_not_string', ['type' => gettype($input)]);
            return $input; // Don't process non-strings
        }
        
        // Trim whitespace
        $input = trim($input);
        self::log('sanitizeString_after_trim', ['value' => $input]);
        
        // Try htmlspecialchars with error checking
        try {
            $sanitized = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            self::log('sanitizeString_after_htmlspecialchars', ['value' => $sanitized]);
        } catch (Exception $e) {
            self::log('sanitizeString_htmlspecialchars_error', ['error' => $e->getMessage()]);
            throw $e;
        }
        
        // Remove null bytes (potential for SQL injection)
        $sanitized = str_replace("\0", '', $sanitized);
        self::log('sanitizeString_complete', ['final' => $sanitized]);
        
        return $sanitized;
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
}

/**
 * Validation Exception for input validation errors
 */
class ValidationException extends Exception {
    public function __construct($message, $code = 400, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}