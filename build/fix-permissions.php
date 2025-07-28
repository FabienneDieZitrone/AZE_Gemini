<?php
/**
 * Fix Directory Permissions
 * 
 * This script creates and sets permissions for required directories
 * Upload this to /aze/ and access it once via browser
 */

header('Content-Type: text/plain');

echo "Directory Permission Fixer\n";
echo "==========================\n\n";

$baseDir = dirname(__FILE__);
$directories = [
    'logs' => 0755,
    'data' => 0755,
    'cache' => 0755,
    'cache/rate-limit' => 0755
];

foreach ($directories as $dir => $perms) {
    $fullPath = $baseDir . '/' . $dir;
    
    // Create directory if it doesn't exist
    if (!is_dir($fullPath)) {
        if (@mkdir($fullPath, $perms, true)) {
            echo "✓ Created: $dir\n";
        } else {
            echo "✗ Failed to create: $dir\n";
        }
    } else {
        echo "• Exists: $dir\n";
    }
    
    // Try to set permissions
    if (@chmod($fullPath, $perms)) {
        echo "✓ Permissions set to " . decoct($perms) . ": $dir\n";
    } else {
        echo "✗ Cannot change permissions: $dir\n";
    }
    
    // Test writability
    $testFile = $fullPath . '/test_' . time() . '.txt';
    if (@file_put_contents($testFile, 'test')) {
        echo "✓ Writable: $dir\n";
        @unlink($testFile);
    } else {
        echo "✗ Not writable: $dir\n";
    }
    
    echo "\n";
}

// Security: Delete this script after use
echo "\n⚠️  IMPORTANT: Delete this script after use!\n";
echo "Access this URL once: https://aze.mikropartner.de/fix-permissions.php\n";

// Optional: Self-destruct after execution
// unlink(__FILE__);