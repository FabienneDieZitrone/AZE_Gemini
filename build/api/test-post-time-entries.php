<?php
/**
 * Test-Script für POST Request an time-entries.php
 * Simuliert einen echten POST Request mit allen notwendigen Daten
 */

// Fehlerberichterstattung
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session starten für Test
session_start();

// Test-User in Session setzen (simuliert angemeldeten Benutzer)
$_SESSION['user'] = [
    'id' => 1,
    'oid' => 'test-oid-12345',
    'name' => 'Test User',
    'username' => 'testuser@example.com'
];
$_SESSION['last_activity'] = time();
$_SESSION['created'] = time();

echo "=== POST Request Test für time-entries.php ===\n\n";

// Test 1: Direkter Include-Test
echo "Test 1: Checking includes...\n";
$includes = ['error-handler.php', 'security-headers.php', 'db-init.php', 'auth_helpers.php', 'validation.php'];
foreach ($includes as $inc) {
    if (file_exists(__DIR__ . '/' . $inc)) {
        echo "✓ $inc exists\n";
    } else {
        echo "✗ $inc MISSING!\n";
    }
}

// Test 2: Test-POST-Daten
$test_data = [
    'userId' => 1,
    'username' => 'Test User',
    'date' => date('Y-m-d'),
    'startTime' => '09:00:00',
    'stopTime' => '17:00:00',
    'location' => 'Office',
    'role' => 'Mitarbeiter',
    'updatedBy' => 'Test User'
];

echo "\nTest 2: POST Data:\n";
echo json_encode($test_data, JSON_PRETTY_PRINT) . "\n";

// Test 3: Simuliere POST Request
echo "\nTest 3: Simulating POST request...\n";

// Setze Request-Methode
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_ORIGIN'] = 'https://aze.mikropartner.de';
$_SERVER['CONTENT_TYPE'] = 'application/json';

// Erstelle temporäre Input-Datei für php://input
$temp_file = tempnam(sys_get_temp_dir(), 'post_data');
file_put_contents($temp_file, json_encode($test_data));

// Test 4: Lade time-entries.php mit Error Handling
echo "\nTest 4: Loading time-entries.php...\n";

// Output buffering um die Response zu erfassen
ob_start();

try {
    // Override php://input für den Test
    stream_wrapper_unregister("php");
    stream_wrapper_register("php", "MockPhpStream");
    MockPhpStream::$data = json_encode($test_data);
    
    // Include die API
    require __DIR__ . '/time-entries.php';
    
    $output = ob_get_contents();
    
} catch (Exception $e) {
    echo "Exception caught: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "Fatal Error caught: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
} finally {
    ob_end_clean();
    // Restore original wrapper
    stream_wrapper_restore("php");
}

// Test 5: Zeige Response
echo "\nTest 5: Response Output:\n";
echo $output . "\n";

// Test 6: Check Response Headers
$headers = headers_list();
echo "\nTest 6: Response Headers:\n";
foreach ($headers as $header) {
    echo $header . "\n";
}

// Test 7: Direkter DB-Test
echo "\nTest 7: Direct Database Test:\n";
try {
    require_once __DIR__ . '/db-init.php';
    if (isset($conn)) {
        echo "✓ Database connection established\n";
        $result = $conn->query("SELECT VERSION() as version");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "✓ MySQL Version: " . $row['version'] . "\n";
        }
    } else {
        echo "✗ No database connection\n";
    }
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
}

// Clean up
unlink($temp_file);

/**
 * Mock Stream Wrapper für php://input
 */
class MockPhpStream {
    public static $data = '';
    private $position = 0;
    
    public function stream_open($path, $mode, $options, &$opened_path) {
        return true;
    }
    
    public function stream_read($count) {
        $ret = substr(self::$data, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }
    
    public function stream_eof() {
        return $this->position >= strlen(self::$data);
    }
    
    public function stream_stat() {
        return ['size' => strlen(self::$data)];
    }
    
    public function stream_tell() {
        return $this->position;
    }
}
?>