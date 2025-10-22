<?php
/**
 * Migration: Add flexible_workdays column to master_data table
 *
 * SAFE TO RUN MULTIPLE TIMES: Checks if column exists before adding
 */
define('API_GUARD', true);
require_once __DIR__ . '/DatabaseConnection.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $conn = DatabaseConnection::getInstance()->getConnection();

    // Check if column already exists
    $columnExists = false;
    if ($res = $conn->query("SHOW COLUMNS FROM master_data LIKE 'flexible_workdays'")) {
        $columnExists = $res->num_rows > 0;
        $res->close();
    }

    if ($columnExists) {
        echo json_encode([
            'success' => true,
            'message' => 'Column flexible_workdays already exists',
            'action' => 'none'
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // Add the column
    $sql = "ALTER TABLE master_data ADD COLUMN flexible_workdays TINYINT(1) DEFAULT 0 COMMENT 'Flexible workdays enabled'";

    if (!$conn->query($sql)) {
        throw new Exception("Failed to add column: " . $conn->error);
    }

    // Commit the change
    $conn->commit();

    // Verify column was added
    $verified = false;
    if ($res = $conn->query("SHOW COLUMNS FROM master_data LIKE 'flexible_workdays'")) {
        $verified = $res->num_rows > 0;
        $res->close();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Column flexible_workdays added successfully',
        'action' => 'added',
        'verified' => $verified,
        'sql' => $sql
    ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    // Rollback on error
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
        // Ignore rollback errors
    }

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
