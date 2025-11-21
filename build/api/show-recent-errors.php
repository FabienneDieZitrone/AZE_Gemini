<?php
/**
 * Show Recent PHP Errors - Alternative Log Viewer
 */
header('Content-Type: text/html; charset=utf-8');

echo "<h1>Recent PHP Errors</h1>";
echo "<pre style='background: #000; color: #0f0; padding: 20px; font-size: 12px;'>";

// Get error log location
$errorLog = ini_get('error_log');
echo "Error log location: $errorLog\n";
echo str_repeat("=", 80) . "\n\n";

// Try to read the log
if ($errorLog && file_exists($errorLog)) {
    $lines = file($errorLog);
    $recentLines = array_slice($lines, -200); // Last 200 lines

    echo "Last 200 lines from error log:\n\n";

    foreach ($recentLines as $line) {
        // Highlight important lines
        if (stripos($line, 'users.php') !== false) {
            echo "<span style='color: yellow; font-weight: bold;'>$line</span>";
        } elseif (stripos($line, 'error') !== false || stripos($line, 'fatal') !== false) {
            echo "<span style='color: red;'>$line</span>";
        } elseif (stripos($line, 'PATCH REQUEST') !== false) {
            echo "<span style='color: cyan; font-weight: bold;'>$line</span>";
        } else {
            echo htmlspecialchars($line);
        }
    }
} else {
    echo "❌ Error log not found or not readable\n";
    echo "Trying alternative locations...\n\n";

    $alternatives = [
        '/var/log/php_errors.log',
        '/var/log/apache2/error.log',
        '/tmp/php_errors.log',
        dirname(__FILE__) . '/error.log'
    ];

    foreach ($alternatives as $alt) {
        if (file_exists($alt)) {
            echo "✓ Found: $alt\n";
            $lines = file($alt);
            $recentLines = array_slice($lines, -50);
            echo "\nLast 50 lines:\n";
            echo implode('', $recentLines);
            break;
        } else {
            echo "✗ Not found: $alt\n";
        }
    }
}

echo "</pre>";

// Also show PHP info about error logging
echo "<h2>PHP Error Configuration</h2>";
echo "<pre>";
echo "display_errors: " . ini_get('display_errors') . "\n";
echo "log_errors: " . ini_get('log_errors') . "\n";
echo "error_reporting: " . ini_get('error_reporting') . "\n";
echo "error_log: " . ini_get('error_log') . "\n";
echo "</pre>";
?>
