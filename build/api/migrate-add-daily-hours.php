<?php
/**
 * Migration: Add daily_hours column to master_data table
 * Purpose: Store individual daily hour assignments per weekday
 *
 * Usage: curl -k https://aze.mikropartner.de/api/migrate-add-daily-hours.php
 */

define('API_GUARD', true);
require_once __DIR__ . '/DatabaseConnection.php';

// Set JSON response header
header('Content-Type: application/json; charset=utf-8');

try {
    $db = DatabaseConnection::getInstance();
    $conn = $db->getConnection();

    // Start transaction
    $conn->begin_transaction();

    // Check if column already exists
    $columnExists = false;
    if ($res = $conn->query("SHOW COLUMNS FROM master_data LIKE 'daily_hours'")) {
        $columnExists = $res->num_rows > 0;
        $res->close();
    }

    if ($columnExists) {
        echo json_encode([
            'success' => true,
            'message' => 'Column daily_hours already exists',
            'action' => 'none'
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // Add the column as JSON type
    $sql = "ALTER TABLE master_data ADD COLUMN daily_hours JSON DEFAULT NULL COMMENT 'Individual daily hour assignments per weekday'";

    if (!$conn->query($sql)) {
        throw new Exception("Failed to add column: " . $conn->error);
    }

    // Commit transaction
    $conn->commit();

    // Verify column was added
    $verified = false;
    if ($res = $conn->query("SHOW COLUMNS FROM master_data LIKE 'daily_hours'")) {
        $verified = $res->num_rows > 0;
        $res->close();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Column daily_hours added successfully',
        'action' => 'added',
        'verified' => $verified,
        'sql' => $sql
    ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    if (isset($conn)) {
        $conn->rollback();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
?>
