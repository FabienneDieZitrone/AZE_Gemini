<?php
/**
 * Apply Performance Database Indexes
 * Version: 1.0
 * Author: Database Performance Expert
 * File: /scripts/apply-performance-indexes.php
 * Purpose: Apply performance optimization indexes to the database
 */

require_once __DIR__ . '/../api/db.php';

echo "=== Database Performance Index Application ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Read the migration file
$migration_file = __DIR__ . '/../migrations/002_performance_indexes.sql';

if (!file_exists($migration_file)) {
    echo "ERROR: Migration file not found: $migration_file\n";
    exit(1);
}

$migration_sql = file_get_contents($migration_file);
if (!$migration_sql) {
    echo "ERROR: Could not read migration file\n";
    exit(1);
}

// Split the migration into individual statements
$statements = array_filter(
    array_map('trim', explode(';', $migration_sql)),
    function($stmt) {
        return !empty($stmt) && 
               !str_starts_with($stmt, '--') && 
               !str_starts_with($stmt, 'COMMIT');
    }
);

echo "Found " . count($statements) . " index creation statements\n\n";

$success_count = 0;
$error_count = 0;

// Apply each index creation statement
foreach ($statements as $i => $statement) {
    if (empty(trim($statement))) continue;
    
    // Extract index name for reporting
    preg_match('/ADD INDEX `([^`]+)`/', $statement, $matches);
    $index_name = $matches[1] ?? "index_" . ($i + 1);
    
    echo "Applying index: $index_name... ";
    
    try {
        $result = $conn->query($statement);
        if ($result) {
            echo "SUCCESS\n";
            $success_count++;
        } else {
            // Check if index already exists
            if (str_contains($conn->error, 'Duplicate key name')) {
                echo "SKIPPED (already exists)\n";
                $success_count++;
            } else {
                echo "FAILED: " . $conn->error . "\n";
                $error_count++;
            }
        }
    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
        $error_count++;
    }
}

echo "\n=== Summary ===\n";
echo "Successful: $success_count\n";
echo "Errors: $error_count\n";
echo "Total: " . ($success_count + $error_count) . "\n";

if ($error_count > 0) {
    echo "\nWARNING: Some indexes failed to apply. Check the errors above.\n";
    exit(1);
} else {
    echo "\nSUCCESS: All performance indexes applied successfully!\n";
    
    // Run ANALYZE TABLE to update statistics
    echo "\nUpdating table statistics...\n";
    $tables = ['time_entries', 'approval_requests', 'users'];
    
    foreach ($tables as $table) {
        echo "Analyzing table: $table... ";
        $result = $conn->query("ANALYZE TABLE $table");
        if ($result) {
            echo "SUCCESS\n";
        } else {
            echo "FAILED: " . $conn->error . "\n";
        }
    }
}

$conn->close();
echo "\nDone.\n";