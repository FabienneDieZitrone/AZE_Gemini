<?php
/**
 * Database Initialization Helper
 * Provides backward compatibility for APIs using $conn directly
 */

// Include the wrapper which includes db.php
require_once __DIR__ . '/db-wrapper.php';

// Make $conn available for backward compatibility
global $conn;
$conn = initDB();

// Also provide the initDB function globally
if (!function_exists('initDB')) {
    die("CRITICAL ERROR: db-wrapper.php not loaded properly!");
}
?>