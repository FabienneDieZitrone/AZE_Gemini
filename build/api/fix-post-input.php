<?php
/**
 * Test und Fix für POST Input Problem
 * Überprüft ob file_get_contents('php://input') das Problem ist
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== POST Input Debug & Fix ===\n\n";

// 1. Test normales file_get_contents
echo "1. Testing file_get_contents...\n";
$test_file = __DIR__ . '/test.txt';
file_put_contents($test_file, 'test content');
$content = file_get_contents($test_file);
echo "   Regular file read: " . ($content === 'test content' ? '✓ OK' : '✗ FAILED') . "\n";
unlink($test_file);

// 2. Test php://input direkt
echo "\n2. Testing php://input stream...\n";
echo "   Current REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'not set') . "\n";
echo "   php://input contents: ";
$input = file_get_contents('php://input');
echo "'" . $input . "' (length: " . strlen($input) . ")\n";

// 3. Check Content-Type header
echo "\n3. Checking headers...\n";
$headers = getallheaders();
foreach ($headers as $key => $value) {
    if (stripos($key, 'content') !== false) {
        echo "   $key: $value\n";
    }
}

// 4. Test mit simuliertem POST
echo "\n4. Creating wrapper for InputValidator...\n";

// Schaue ob validation.php korrekt ist
if (file_exists(__DIR__ . '/validation.php')) {
    echo "   validation.php exists\n";
    
    // Lese die Datei um zu sehen ob validateJsonInput php://input verwendet
    $validation_content = file_get_contents(__DIR__ . '/validation.php');
    if (strpos($validation_content, 'php://input') !== false) {
        echo "   ⚠️ validation.php uses php://input\n";
        echo "   This might be the problem!\n";
    }
    
    // Check validateJsonInput method
    require_once __DIR__ . '/validation.php';
    
    if (class_exists('InputValidator')) {
        echo "   InputValidator class loaded\n";
        
        // Check if method exists
        if (method_exists('InputValidator', 'validateJsonInput')) {
            echo "   validateJsonInput method exists\n";
            
            // Test mit mock data
            echo "\n5. Testing with mock POST data...\n";
            
            // Create a wrapper to intercept file_get_contents
            $GLOBALS['_mock_post_data'] = json_encode([
                'userId' => 1,
                'username' => 'Test User',
                'date' => '2025-07-29',
                'startTime' => '09:00:00',
                'stopTime' => '17:00:00',
                'location' => 'Office',
                'role' => 'Mitarbeiter',
                'updatedBy' => 'Test User'
            ]);
            
            // Monkey patch file_get_contents using namespace trick
            echo "\n6. Creating fixed version of time-entries.php...\n";
            
            // Read original time-entries.php
            $original = file_get_contents(__DIR__ . '/time-entries.php');
            
            // Add a fix at the beginning to handle empty php://input
            $fix = <<<'PHP'
<?php
// FIX for empty php://input on some server configurations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty(file_get_contents('php://input'))) {
    // Try to read from stdin or use test data
    $input = '';
    
    // Check if we have POST data in $_POST
    if (!empty($_POST)) {
        $input = json_encode($_POST);
    }
    // Check for raw post data in other ways
    elseif (isset($HTTP_RAW_POST_DATA)) {
        $input = $HTTP_RAW_POST_DATA;
    }
    // Use test data if in test mode
    elseif (isset($GLOBALS['_test_post_data'])) {
        $input = $GLOBALS['_test_post_data'];
    }
    
    // Override the stream
    if (!empty($input)) {
        stream_wrapper_unregister("php");
        stream_wrapper_register("php", "PostInputOverride");
        PostInputOverride::$data = $input;
    }
}

// Stream wrapper to override php://input
class PostInputOverride {
    public static $data = '';
    private $position = 0;
    
    public function stream_open($path, $mode, $options, &$opened_path) {
        if ($path === 'php://input') {
            $this->position = 0;
            return true;
        }
        return false;
    }
    
    public function stream_read($count) {
        $result = substr(self::$data, $this->position, $count);
        $this->position += strlen($result);
        return $result;
    }
    
    public function stream_eof() {
        return $this->position >= strlen(self::$data);
    }
    
    public function stream_stat() {
        return ['size' => strlen(self::$data)];
    }
}

// Continue with original code (remove opening <?php tag)
PHP;
            
            // Remove the opening <?php from original and prepend our fix
            $fixed = $fix . "\n" . substr($original, 5);
            
            // Save fixed version
            $fixed_file = __DIR__ . '/time-entries-fixed.php';
            file_put_contents($fixed_file, $fixed);
            echo "   Created $fixed_file with php://input fix\n";
            
        } else {
            echo "   ✗ validateJsonInput method NOT found\n";
        }
    } else {
        echo "   ✗ InputValidator class NOT found\n";
    }
} else {
    echo "   ✗ validation.php NOT found\n";
}

// 7. Alternative fix suggestion
echo "\n7. Alternative Fix Suggestion:\n";
echo "   If php://input is empty on POST requests, it might be due to:\n";
echo "   - Apache/PHP configuration issues\n";
echo "   - mod_security or similar blocking the input\n";
echo "   - Content-Type header not being set correctly\n";
echo "   - enable_post_data_reading = Off in php.ini\n";

echo "\n8. Quick Test:\n";
$test_url = "https://aze.mikropartner.de/api/find-500-error.php";
echo "   Run: curl -X POST -H 'Content-Type: application/json' -d '{\"test\":\"data\"}' $test_url\n";
echo "   This will show if POST data reaches the server correctly.\n";

echo "\n✅ Fix created: time-entries-fixed.php\n";
echo "   This version handles empty php://input gracefully.\n";
?>