<?php
/**
 * Script to integrate error handlers into all API endpoints
 */

$apiDir = __DIR__ . '/api';
$excludeFiles = [
    'error-handler.php',
    'security-headers.php', 
    'structured-logger.php',
    'validation.php',
    'db.php',
    'config.php',
    'auth_helpers.php'
];

$apiFiles = scandir($apiDir);
$updated = 0;

foreach ($apiFiles as $file) {
    if (!str_ends_with($file, '.php') || in_array($file, $excludeFiles)) {
        continue;
    }
    
    $filePath = $apiDir . '/' . $file;
    $content = file_get_contents($filePath);
    
    // Check if already has error handler
    if (strpos($content, 'require_once') !== false && strpos($content, 'error-handler.php') !== false) {
        echo "✓ Already has error handler: $file\n";
        continue;
    }
    
    // Find the first require/include statement after the opening PHP tag
    $pattern = '/(<\?php[^>]*>\s*(?:\/\*[\s\S]*?\*\/\s*)?)/';
    
    if (preg_match($pattern, $content, $matches)) {
        $phpOpenAndComments = $matches[1];
        
        // Add error handler require after comments
        $newContent = $phpOpenAndComments . "\n// Error handling\nrequire_once __DIR__ . '/error-handler.php';\nrequire_once __DIR__ . '/security-headers.php';\n";
        
        // Replace the matched part with new content
        $content = preg_replace($pattern, $newContent, $content, 1);
        
        // Write back
        file_put_contents($filePath, $content);
        echo "✓ Updated: $file\n";
        $updated++;
    } else {
        echo "✗ Could not update: $file\n";
    }
}

echo "\nTotal files updated: $updated\n";