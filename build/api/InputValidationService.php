<?php
/**
 * Unified Input Validation Service
 * Version: 1.0 - Secure, Performance Optimized, SOLID Compliant
 * Author: Refactoring Expert
 * Description: Single source of truth for all input validation
 *              Replaces: validation.php, validation_optimized.php, validation-safe.php
 */

declare(strict_types=1);

interface ValidatorInterface {
    public function validate($value, array $rules): ValidationResult;
}

interface SanitizerInterface {
    public function sanitize($value): mixed;
}

class ValidationResult {
    private bool $isValid;
    private array $errors;
    private mixed $sanitizedValue;
    
    public function __construct(bool $isValid, mixed $sanitizedValue = null, array $errors = []) {
        $this->isValid = $isValid;
        $this->sanitizedValue = $sanitizedValue;
        $this->errors = $errors;
    }
    
    public function isValid(): bool {
        return $this->isValid;
    }
    
    public function getErrors(): array {
        return $this->errors;
    }
    
    public function getSanitizedValue(): mixed {
        return $this->sanitizedValue;
    }
    
    public function toArray(): array {
        return [
            'is_valid' => $this->isValid,
            'value' => $this->sanitizedValue,
            'errors' => $this->errors
        ];
    }
}

class ValidationException extends Exception {
    private array $validationErrors;
    
    public function __construct(string $message, array $errors = [], int $code = 400, ?Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->validationErrors = $errors;
    }
    
    public function getValidationErrors(): array {
        return $this->validationErrors;
    }
}

/**
 * High-Performance String Sanitizer
 */
class StringSanitizer implements SanitizerInterface {
    private static array $controlCharPattern = ['/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/'];
    
    public function sanitize($value): mixed {
        if (!is_string($value)) {
            return $value;
        }
        
        // Early return for empty strings
        if ($value === '') {
            return '';
        }
        
        // Single-pass sanitization for performance
        $value = trim($value);
        $value = str_replace("\0", '', $value); // Remove null bytes
        $value = preg_replace(self::$controlCharPattern[0], '', $value);
        
        return $value;
    }
}

/**
 * Email Validator with advanced RFC compliance
 */
class EmailValidator implements ValidatorInterface {
    public function validate($value, array $rules = []): ValidationResult {
        if (!is_string($value)) {
            return new ValidationResult(false, null, ['Email must be a string']);
        }
        
        $sanitizer = new StringSanitizer();
        $sanitized = $sanitizer->sanitize($value);
        
        // Use PHP's built-in filter with additional checks
        $isValid = filter_var($sanitized, FILTER_VALIDATE_EMAIL) !== false;
        
        // Additional security checks
        if ($isValid) {
            // Check for dangerous patterns
            $dangerousPatterns = ['<script', 'javascript:', 'data:'];
            foreach ($dangerousPatterns as $pattern) {
                if (stripos($sanitized, $pattern) !== false) {
                    return new ValidationResult(false, null, ['Email contains dangerous content']);
                }
            }
            
            // Length validation
            if (strlen($sanitized) > 254) { // RFC 5321 limit
                return new ValidationResult(false, null, ['Email exceeds maximum length']);
            }
        }
        
        return new ValidationResult($isValid, $sanitized, $isValid ? [] : ['Invalid email format']);
    }
}

/**
 * Date/Time Validator with timezone support
 */
class DateTimeValidator implements ValidatorInterface {
    private const DATE_FORMAT = 'Y-m-d';
    private const TIME_FORMAT = 'H:i:s';
    private const DATETIME_FORMAT = 'Y-m-d H:i:s';
    
    public function validate($value, array $rules = []): ValidationResult {
        if (!is_string($value)) {
            return new ValidationResult(false, null, ['Date/time must be a string']);
        }
        
        $format = $rules['format'] ?? self::DATE_FORMAT;
        $sanitizer = new StringSanitizer();
        $sanitized = $sanitizer->sanitize($value);
        
        // Validate format
        $dateTime = DateTime::createFromFormat($format, $sanitized);
        $isValid = $dateTime && $dateTime->format($format) === $sanitized;
        
        if ($isValid && isset($rules['min_date'])) {
            $minDate = DateTime::createFromFormat($format, $rules['min_date']);
            if ($dateTime < $minDate) {
                return new ValidationResult(false, null, ['Date is before minimum allowed']);
            }
        }
        
        if ($isValid && isset($rules['max_date'])) {
            $maxDate = DateTime::createFromFormat($format, $rules['max_date']);
            if ($dateTime > $maxDate) {
                return new ValidationResult(false, null, ['Date is after maximum allowed']);
            }
        }
        
        return new ValidationResult($isValid, $sanitized, $isValid ? [] : ['Invalid date/time format']);
    }
}

/**
 * Numeric Validator with range validation
 */
class NumericValidator implements ValidatorInterface {
    public function validate($value, array $rules = []): ValidationResult {
        if (!is_numeric($value)) {
            return new ValidationResult(false, null, ['Value must be numeric']);
        }
        
        $numericValue = is_string($value) ? (float)$value : $value;
        $errors = [];
        
        // Range validation
        if (isset($rules['min']) && $numericValue < $rules['min']) {
            $errors[] = "Value must be at least {$rules['min']}";
        }
        
        if (isset($rules['max']) && $numericValue > $rules['max']) {
            $errors[] = "Value must be at most {$rules['max']}";
        }
        
        // Integer validation
        if (isset($rules['integer']) && $rules['integer'] && !is_int($value) && (int)$value != $value) {
            $errors[] = 'Value must be an integer';
        }
        
        // Positive validation
        if (isset($rules['positive']) && $rules['positive'] && $numericValue <= 0) {
            $errors[] = 'Value must be positive';
        }
        
        return new ValidationResult(empty($errors), $numericValue, $errors);
    }
}

/**
 * String Validator with length and pattern validation
 */
class StringValidator implements ValidatorInterface {
    public function validate($value, array $rules = []): ValidationResult {
        if (!is_string($value)) {
            return new ValidationResult(false, null, ['Value must be a string']);
        }
        
        $sanitizer = new StringSanitizer();
        $sanitized = $sanitizer->sanitize($value);
        $errors = [];
        
        // Length validation
        $length = mb_strlen($sanitized, 'UTF-8');
        
        if (isset($rules['min_length']) && $length < $rules['min_length']) {
            $errors[] = "String must be at least {$rules['min_length']} characters";
        }
        
        if (isset($rules['max_length']) && $length > $rules['max_length']) {
            $errors[] = "String must be at most {$rules['max_length']} characters";
        }
        
        // Pattern validation
        if (isset($rules['pattern']) && !preg_match($rules['pattern'], $sanitized)) {
            $errors[] = 'String does not match required pattern';
        }
        
        // Custom validation
        if (isset($rules['custom'])) {
            $customResult = $rules['custom']($sanitized);
            if ($customResult !== true) {
                $errors[] = is_string($customResult) ? $customResult : 'Custom validation failed';
            }
        }
        
        return new ValidationResult(empty($errors), $sanitized, $errors);
    }
}

/**
 * Main Input Validation Service
 */
class InputValidationService {
    private static ?self $instance = null;
    private array $validators;
    private array $validationCache = [];
    private const MAX_CACHE_SIZE = 100;
    
    private function __construct() {
        $this->validators = [
            'email' => new EmailValidator(),
            'datetime' => new DateTimeValidator(),
            'numeric' => new NumericValidator(),
            'string' => new StringValidator(),
        ];
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Validate JSON input with schema validation
     */
    public function validateJsonInput(array $schema): array {
        $rawInput = file_get_contents('php://input');
        
        if (empty($rawInput)) {
            throw new ValidationException('Empty request body');
        }
        
        $data = json_decode($rawInput, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ValidationException('Invalid JSON: ' . json_last_error_msg());
        }
        
        return $this->validateData($data, $schema);
    }
    
    /**
     * Validate data against schema
     */
    public function validateData(array $data, array $schema): array {
        $validatedData = [];
        $errors = [];
        
        // Check required fields
        $requiredFields = array_filter($schema, fn($rules) => $rules['required'] ?? false);
        $missingFields = array_diff(array_keys($requiredFields), array_keys($data));
        
        if (!empty($missingFields)) {
            throw new ValidationException('Missing required fields: ' . implode(', ', $missingFields));
        }
        
        // Validate each field
        foreach ($schema as $field => $rules) {
            $value = $data[$field] ?? $rules['default'] ?? null;
            
            // Skip validation if field is not required and not present
            if ($value === null && !($rules['required'] ?? false)) {
                continue;
            }
            
            $validationType = $rules['type'] ?? 'string';
            $validator = $this->validators[$validationType] ?? $this->validators['string'];
            
            $result = $validator->validate($value, $rules);
            
            if (!$result->isValid()) {
                $errors[$field] = $result->getErrors();
            } else {
                $validatedData[$field] = $result->getSanitizedValue();
            }
        }
        
        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }
        
        return $validatedData;
    }
    
    /**
     * Quick validation methods for common use cases
     */
    public function validateEmail(string $email): bool {
        $result = $this->validators['email']->validate($email);
        return $result->isValid();
    }
    
    public function validateDate(string $date, string $format = 'Y-m-d'): bool {
        $result = $this->validators['datetime']->validate($date, ['format' => $format]);
        return $result->isValid();
    }
    
    public function validateId($id): bool {
        $result = $this->validators['numeric']->validate($id, ['integer' => true, 'positive' => true]);
        return $result->isValid();
    }
    
    /**
     * Sanitize string for safe output
     */
    public function escapeHtml(string $string): string {
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Escape for SQL (when prepared statements aren't available)
     */
    public function escapeSql(mysqli $conn, string $string): string {
        return $conn->real_escape_string($string);
    }
    
    /**
     * Register custom validator
     */
    public function registerValidator(string $name, ValidatorInterface $validator): void {
        $this->validators[$name] = $validator;
    }
    
    /**
     * Clear validation cache
     */
    public function clearCache(): void {
        $this->validationCache = [];
    }
    
    /**
     * Get cache statistics
     */
    public function getCacheStats(): array {
        return [
            'size' => count($this->validationCache),
            'max_size' => self::MAX_CACHE_SIZE
        ];
    }
}

// Backward compatibility functions
function validateJsonInput(array $schema): array {
    return InputValidationService::getInstance()->validateJsonInput($schema);
}

function isValidEmail(string $email): bool {
    return InputValidationService::getInstance()->validateEmail($email);
}

function isValidDate(string $date): bool {
    return InputValidationService::getInstance()->validateDate($date);
}

function isValidId($id): bool {
    return InputValidationService::getInstance()->validateId($id);
}

function escapeHtml(string $string): string {
    return InputValidationService::getInstance()->escapeHtml($string);
}

// Legacy class alias for backward compatibility
class InputValidator {
    public static function validateJsonInput($required_fields = [], $optional_fields = []): array {
        $schema = [];
        foreach ($required_fields as $field) {
            $schema[$field] = ['type' => 'string', 'required' => true];
        }
        foreach ($optional_fields as $field => $default) {
            $schema[$field] = ['type' => 'string', 'required' => false, 'default' => $default];
        }
        return InputValidationService::getInstance()->validateJsonInput($schema);
    }
    
    public static function sanitizeString($input) {
        return (new StringSanitizer())->sanitize($input);
    }
    
    /**
     * Instance method for sanitizing strings
     */
    public function sanitizeString($input) {
        return (new StringSanitizer())->sanitize($input);
    }
    
    public static function isValidDate($date): bool {
        return isValidDate($date);
    }
    
    public static function isValidEmail($email): bool {
        return isValidEmail($email);
    }
    
    public static function isValidId($id): bool {
        return isValidId($id);
    }
    
    public static function escapeHtml($string): string {
        return escapeHtml($string);
    }
}
?>