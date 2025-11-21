<?php
/**
 * View PHP Error Log (last 100 lines)
 */
header('Content-Type: text/plain; charset=utf-8');

// Try different possible error log locations
$possibleLogs = [
    '/var/log/php_errors.log',
    '/var/log/php/error.log',
    '/var/log/apache2/error.log',
    ini_get('error_log'),
    '/tmp/php_errors.log'
];

echo "=== PHP ERROR LOG VIEWER ===\n\n";
echo "PHP error_log setting: " . ini_get('error_log') . "\n\n";

foreach ($possibleLogs as $logFile) {
    if (!$logFile || !file_exists($logFile)) continue;

    echo "=== LOG FILE: $logFile ===\n";
    echo "Last modified: " . date('Y-m-d H:i:s', filemtime($logFile)) . "\n\n";

    // Get last 100 lines
    $lines = file($logFile);
    $lastLines = array_slice($lines, -100);

    // Filter for users.php related errors
    $filtered = array_filter($lastLines, function($line) {
        return stripos($line, 'users.php') !== false ||
               stripos($line, 'PATCH REQUEST') !== false ||
               stripos($line, 'DatabaseConnection') !== false;
    });

    if (count($filtered) > 0) {
        echo "Filtered lines (users.php related):\n";
        echo implode('', $filtered);
    } else {
        echo "Last 20 lines:\n";
        echo implode('', array_slice($lastLines, -20));
    }

    echo "\n" . str_repeat("=", 80) . "\n\n";
    break; // Only show first found log
}

if (!isset($logFile) || !file_exists($logFile)) {
    echo "❌ No error log found\n";
    echo "Checked locations:\n";
    foreach ($possibleLogs as $log) {
        echo "  - $log " . (file_exists($log) ? "EXISTS" : "NOT FOUND") . "\n";
    }
}
?>
