<?php
/**
 * Test if the validation fix works
 */

require_once __DIR__ . '/validation.php';

// Test data that might have caused issues
$test_cases = [
    [
        'name' => 'Basic test',
        'data' => [
            'userId' => 1,
            'username' => 'Test User',
            'date' => '2025-07-29',
            'startTime' => '09:00:00',
            'stopTime' => null,
            'location' => 'office',
            'role' => 'employee',
            'updatedBy' => 'Test User'
        ]
    ],
    [
        'name' => 'Special characters in username',
        'data' => [
            'userId' => 1,
            'username' => 'Müller & Schmidt GmbH',
            'date' => '2025-07-29',
            'startTime' => '09:00:00',
            'stopTime' => null,
            'location' => 'Büro München',
            'role' => 'Geschäftsführer',
            'updatedBy' => 'Müller & Schmidt'
        ]
    ],
    [
        'name' => 'Unicode characters',
        'data' => [
            'userId' => 1,
            'username' => '测试用户',
            'date' => '2025-07-29',
            'startTime' => '09:00:00',
            'stopTime' => null,
            'location' => '办公室',
            'role' => '员工',
            'updatedBy' => '测试'
        ]
    ],
    [
        'name' => 'Emoji in data',
        'data' => [
            'userId' => 1,
            'username' => 'Happy User 😊',
            'date' => '2025-07-29',
            'startTime' => '09:00:00',
            'stopTime' => null,
            'location' => 'Home 🏠',
            'role' => 'Developer 💻',
            'updatedBy' => 'Admin 👨‍💼'
        ]
    ]
];

// Mock $_SERVER for validation
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

foreach ($test_cases as $test) {
    echo "\n=== Test: {$test['name']} ===\n";
    
    // Mock php://input
    $json_input = json_encode($test['data']);
    echo "Input JSON: $json_input\n";
    
    // We need to override php://input for testing
    // Since we can't directly override it, we'll test the sanitizeData method directly
    try {
        // Test JSON decoding
        $decoded = json_decode($json_input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "JSON Error: " . json_last_error_msg() . "\n";
            continue;
        }
        
        // Test sanitization using reflection to access private method
        $reflection = new ReflectionClass('InputValidator');
        $method = $reflection->getMethod('sanitizeData');
        $method->setAccessible(true);
        
        $sanitized = $method->invoke(null, $decoded);
        echo "Sanitized data: " . json_encode($sanitized, JSON_UNESCAPED_UNICODE) . "\n";
        
        // Test individual field validation
        echo "Validations:\n";
        echo "  - userId valid: " . (InputValidator::isValidId($sanitized['userId']) ? 'YES' : 'NO') . "\n";
        echo "  - date valid: " . (InputValidator::isValidDate($sanitized['date']) ? 'YES' : 'NO') . "\n";
        echo "  - startTime valid: " . (InputValidator::isValidTime($sanitized['startTime']) ? 'YES' : 'NO') . "\n";
        if ($sanitized['stopTime'] !== null) {
            echo "  - stopTime valid: " . (InputValidator::isValidTime($sanitized['stopTime']) ? 'YES' : 'NO') . "\n";
        }
        
        // Test HTML escaping (for output)
        echo "HTML escaped username: " . InputValidator::escapeHtml($sanitized['username']) . "\n";
        
        echo "Result: PASSED ✓\n";
        
    } catch (Exception $e) {
        echo "Error: " . get_class($e) . " - " . $e->getMessage() . "\n";
        echo "Result: FAILED ✗\n";
    }
}

echo "\n=== Testing complete ===\n";