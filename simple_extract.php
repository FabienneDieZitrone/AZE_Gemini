<?php
// Simple extraction script
error_reporting(E_ALL);
ini_set('display_errors', 1);

$archive = 'aze-test-complete.tar.gz';

if (!file_exists($archive)) {
    die("Archive file not found: $archive");
}

// Try to extract using PharData
try {
    $phar = new PharData($archive);
    $phar->extractTo('.');
    echo "Extraction successful using PharData\n";
    
    // Clean up
    unlink($archive);
    echo "Archive removed\n";
} catch (Exception $e) {
    echo "PharData failed: " . $e->getMessage() . "\n";
    
    // Try using shell command
    $cmd = "tar -xzf $archive 2>&1";
    $output = shell_exec($cmd);
    if ($output === null) {
        die("tar command failed\n");
    }
    echo "Extraction successful using tar\n";
    echo "Output: $output\n";
    
    // Clean up
    unlink($archive);
    echo "Archive removed\n";
}

echo "Done!\n";
?>