<?php
/**
 * Simple extraction script for test environment
 */

$archive = 'aze-test-complete.tar.gz';
$testDir = '/www/aze-test/';

// Check if archive exists
if (!file_exists($archive)) {
    die("Error: Archive $archive not found\n");
}

echo "Extracting $archive...\n";

// Extract using system command
$cmd = "tar -xzf $archive -C $testDir --strip-components=1 2>&1";
$output = shell_exec($cmd);

if ($output) {
    echo "Extraction output: $output\n";
} else {
    echo "Extraction completed.\n";
}

// Clean up
if (file_exists($archive)) {
    echo "Archive kept for backup.\n";
}

echo "Done!\n";
?>