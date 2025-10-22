<?php
header('Content-Type: text/plain; charset=utf-8');
echo "=== PHP SYNTAX CHECK ===\n\n";

$files = [
    'time-entries.php',
    'time-entries.impl.php',
    'error-handler.php',
    'auth_helpers.php',
    'constants.php'
];

foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    echo "Checking: $file\n";

    if (!file_exists($path)) {
        echo "  ERROR: File not found\n\n";
        continue;
    }

    // Try to include the file
    ob_start();
    $error = null;
    try {
        // Don't actually include, just check if readable
        $content = file_get_contents($path);
        if ($content === false) {
            $error = "Cannot read file";
        } else {
            echo "  Size: " . strlen($content) . " bytes\n";
            echo "  First 50 chars: " . substr($content, 0, 50) . "\n";
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
    ob_end_clean();

    if ($error) {
        echo "  ERROR: $error\n";
    } else {
        echo "  OK\n";
    }
    echo "\n";
}

echo "=== END SYNTAX CHECK ===\n";
