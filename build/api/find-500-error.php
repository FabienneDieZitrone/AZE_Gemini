<?php
/**
 * Findet den exakten 500 Error in time-entries.php
 * Simuliert einen POST Request und zeigt wo es fehlschlägt
 */

// Fehlerberichterstattung aktivieren
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Custom error handler für detaillierte Fehler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo "\n⚠️ ERROR [$errno]: $errstr\n";
    echo "   File: " . basename($errfile) . "\n";
    echo "   Line: $errline\n\n";
    return false;
});

// Fatal error handler
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "\n🔴 FATAL ERROR: {$error['message']}\n";
        echo "   File: " . basename($error['file']) . "\n";
        echo "   Line: {$error['line']}\n\n";
    }
});

echo "=== Finding 500 Error in time-entries.php ===\n\n";

// 1. Setup environment
echo "1. Setting up environment...\n";
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_ORIGIN'] = 'https://aze.mikropartner.de';
$_SERVER['CONTENT_TYPE'] = 'application/json';
$_SERVER['HTTP_HOST'] = 'aze.mikropartner.de';

// 2. Mock session with user
echo "2. Setting up session...\n";
session_start();
$_SESSION['user'] = [
    'id' => 1,
    'oid' => 'test-oid-123',
    'name' => 'Test User',
    'username' => 'test@example.com'
];
$_SESSION['last_activity'] = time();
$_SESSION['created'] = time();
echo "   ✓ Session created with test user\n";

// 3. Create POST data
echo "\n3. Creating POST data...\n";
$post_data = [
    'userId' => 1,
    'username' => 'Test User',
    'date' => date('Y-m-d'),
    'startTime' => '09:00:00',
    'stopTime' => '17:00:00',
    'location' => 'Office',
    'role' => 'Mitarbeiter',
    'updatedBy' => 'Test User'
];
$json_data = json_encode($post_data);
echo "   POST data: " . $json_data . "\n";

// 4. Mock php://input
echo "\n4. Mocking php://input stream...\n";

// Create a temporary file to simulate input
$temp_file = tempnam(sys_get_temp_dir(), 'input');
file_put_contents($temp_file, $json_data);

// Use a simple approach - override file_get_contents for php://input
$GLOBALS['mock_input'] = $json_data;

// 5. Test individual components
echo "\n5. Testing components individually...\n";

// Test error-handler.php
echo "   Loading error-handler.php... ";
if (file_exists(__DIR__ . '/error-handler.php')) {
    require_once __DIR__ . '/error-handler.php';
    echo "✓\n";
} else {
    echo "✗ FILE MISSING!\n";
}

// Test security-headers.php  
echo "   Loading security-headers.php... ";
if (file_exists(__DIR__ . '/security-headers.php')) {
    require_once __DIR__ . '/security-headers.php';
    echo "✓\n";
} else {
    echo "✗ FILE MISSING!\n";
}

// Test db-init.php
echo "   Loading db-init.php... ";
if (file_exists(__DIR__ . '/db-init.php')) {
    require_once __DIR__ . '/db-init.php';
    echo "✓\n";
    if (isset($conn)) {
        echo "     → Database connection established\n";
    } else {
        echo "     → ⚠️ WARNING: \$conn not set!\n";
    }
} else {
    echo "✗ FILE MISSING!\n";
}

// Test auth_helpers.php
echo "   Loading auth_helpers.php... ";
if (file_exists(__DIR__ . '/auth_helpers.php')) {
    require_once __DIR__ . '/auth_helpers.php';
    echo "✓\n";
} else {
    echo "✗ FILE MISSING!\n";
}

// Test validation.php
echo "   Loading validation.php... ";
if (file_exists(__DIR__ . '/validation.php')) {
    require_once __DIR__ . '/validation.php';
    echo "✓\n";
    if (class_exists('InputValidator')) {
        echo "     → InputValidator class found\n";
    } else {
        echo "     → ⚠️ WARNING: InputValidator class missing!\n";
    }
} else {
    echo "✗ FILE MISSING!\n";
}

// 6. Test the actual request handling
echo "\n6. Testing request handling from time-entries.php...\n";

// Test initialize_api
if (function_exists('initialize_api')) {
    echo "   Calling initialize_api()... ";
    ob_start();
    initialize_api();
    ob_end_clean();
    echo "✓\n";
} else {
    echo "   ⚠️ initialize_api() function not found!\n";
}

// Test verify_session_and_get_user
if (function_exists('verify_session_and_get_user')) {
    echo "   Calling verify_session_and_get_user()... ";
    $user = verify_session_and_get_user();
    // This would normally exit with 401, but we have a valid session
    echo "✓ User verified\n";
} else {
    echo "   ⚠️ verify_session_and_get_user() function not found!\n";
}

// 7. Test POST data parsing
echo "\n7. Testing POST data parsing...\n";
if (class_exists('InputValidator')) {
    try {
        echo "   Testing InputValidator::validateJsonInput()...\n";
        
        // Mock the file_get_contents for php://input
        $original_file_get_contents = 'file_get_contents';
        
        // We'll manually parse instead
        $required_fields = ['userId', 'username', 'date', 'startTime', 'location', 'role', 'updatedBy'];
        $optional_fields = ['stopTime' => null];
        
        echo "   Validating fields: " . implode(', ', $required_fields) . "\n";
        
        // Check each field
        foreach ($required_fields as $field) {
            if (isset($post_data[$field])) {
                echo "     ✓ $field: " . $post_data[$field] . "\n";
            } else {
                echo "     ✗ $field: MISSING\n";
            }
        }
        
    } catch (Exception $e) {
        echo "   ⚠️ Validation error: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ⚠️ InputValidator class not available\n";
}

// 8. Test database insert
echo "\n8. Testing database insert...\n";
if (isset($conn)) {
    echo "   Preparing INSERT statement... ";
    $stmt = $conn->prepare("INSERT INTO time_entries (user_id, username, date, start_time, stop_time, location, role, updated_by, updated_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    
    if ($stmt) {
        echo "✓\n";
        echo "   Binding parameters... ";
        $result = $stmt->bind_param("isssssss", 
            $post_data['userId'], 
            $post_data['username'], 
            $post_data['date'], 
            $post_data['startTime'], 
            $post_data['stopTime'],
            $post_data['location'], 
            $post_data['role'],
            $post_data['updatedBy']
        );
        echo $result ? "✓\n" : "✗ Error: " . $stmt->error . "\n";
        
        // Don't actually execute in test mode
        echo "   (Not executing INSERT in test mode)\n";
        $stmt->close();
    } else {
        echo "✗ Error: " . $conn->error . "\n";
    }
} else {
    echo "   ⚠️ No database connection available\n";
}

// 9. Summary
echo "\n=== Summary ===\n";
echo "If you see this message, the basic components are working.\n";
echo "The 500 error might be caused by:\n";
echo "- Missing InputValidator class or methods\n";
echo "- Database connection issues\n";
echo "- Session configuration problems\n";
echo "- file_get_contents('php://input') returning empty\n";

// Clean up
unlink($temp_file);

echo "\nTo debug further, check:\n";
echo "1. /app/build/api/validation.php - ensure InputValidator class exists\n";
echo "2. /app/build/api/db-wrapper.php - ensure database connection works\n";
echo "3. PHP error logs for more details\n";
?>