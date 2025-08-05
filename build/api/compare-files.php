<?php
// Compare file properties
header('Content-Type: text/plain');

$files = [
    'health.php' => __DIR__ . '/health.php',
    'login.php' => __DIR__ . '/login.php',
    'simple-test.php' => __DIR__ . '/simple-test.php',
    'debug-500.php' => __DIR__ . '/debug-500.php'
];

echo "File Comparison Report\n";
echo "======================\n\n";

foreach ($files as $name => $path) {
    echo "File: $name\n";
    echo "Path: $path\n";
    
    if (file_exists($path)) {
        $stat = stat($path);
        echo "Exists: Yes\n";
        echo "Size: " . $stat['size'] . " bytes\n";
        echo "Permissions: " . substr(sprintf('%o', fileperms($path)), -4) . "\n";
        echo "Owner: " . $stat['uid'] . "\n";
        echo "Group: " . $stat['gid'] . "\n";
        echo "Modified: " . date('Y-m-d H:i:s', $stat['mtime']) . "\n";
        
        // Check first few bytes for BOM or encoding issues
        $fp = fopen($path, 'r');
        $firstBytes = fread($fp, 10);
        fclose($fp);
        
        echo "First bytes (hex): ";
        for ($i = 0; $i < strlen($firstBytes); $i++) {
            echo sprintf("%02X ", ord($firstBytes[$i]));
        }
        echo "\n";
        
        // Check for BOM
        $bom = substr($firstBytes, 0, 3);
        if ($bom === "\xEF\xBB\xBF") {
            echo "UTF-8 BOM: DETECTED!\n";
        } else {
            echo "UTF-8 BOM: Not present\n";
        }
        
    } else {
        echo "Exists: No\n";
    }
    
    echo "\n";
}

// Check directory permissions
echo "Directory Permissions:\n";
echo "API dir (" . __DIR__ . "): " . substr(sprintf('%o', fileperms(__DIR__)), -4) . "\n";
echo "Parent dir (" . dirname(__DIR__) . "): " . substr(sprintf('%o', fileperms(dirname(__DIR__))), -4) . "\n";
?>