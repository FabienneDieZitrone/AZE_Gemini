<?php
/**
 * Transaction Helper - Prevents forgotten commits
 *
 * CRITICAL: Ensures all database updates are properly committed
 * Prevents data loss from uncommitted transactions
 *
 * Usage:
 *   executeWithCommit($conn, function($conn) {
 *       $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
 *       $stmt->bind_param('si', $newRole, $userId);
 *       $stmt->execute();
 *       return $stmt->affected_rows;
 *   });
 */

if (!defined('API_GUARD')) {
    die('Direct access not permitted');
}

/**
 * Execute database operation with automatic commit/rollback
 *
 * @param mysqli $conn Database connection
 * @param callable $operation Function that performs database operations
 * @return mixed Result from operation
 * @throws Exception on failure
 */
function executeWithCommit($conn, callable $operation) {
    $wasInTransaction = false;

    try {
        // Check if we're already in a transaction
        $conn->query("START TRANSACTION");
        $wasInTransaction = true;

        // Execute the operation
        $result = $operation($conn);

        // CRITICAL: Always commit if we started the transaction
        if ($wasInTransaction) {
            if (!$conn->commit()) {
                throw new Exception("Failed to commit transaction: " . $conn->error);
            }
            error_log("[Transaction-Helper] Transaction committed successfully");
        }

        return $result;

    } catch (Throwable $e) {
        // Rollback on any error
        if ($wasInTransaction && $conn) {
            $conn->rollback();
            error_log("[Transaction-Helper] Transaction rolled back due to error: " . $e->getMessage());
        }
        throw $e;
    }
}

/**
 * Execute UPDATE/INSERT/DELETE with automatic commit
 * Returns affected rows
 *
 * @param mysqli $conn
 * @param string $query SQL query with placeholders
 * @param string $types Type string (e.g., 'si' for string, int)
 * @param array $params Parameters to bind
 * @return int Affected rows
 */
function executeUpdateWithCommit($conn, $query, $types = '', $params = []) {
    return executeWithCommit($conn, function($conn) use ($query, $types, $params) {
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }

        if (!empty($types) && !empty($params)) {
            if (!$stmt->bind_param($types, ...$params)) {
                $stmt->close();
                throw new Exception("Failed to bind parameters: " . $stmt->error);
            }
        }

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Failed to execute query: " . $error);
        }

        $affectedRows = $stmt->affected_rows;
        $stmt->close();

        return $affectedRows;
    });
}

/**
 * Verify transaction was committed by checking autocommit status
 *
 * @param mysqli $conn
 * @return bool True if autocommit is on (no open transaction)
 */
function isTransactionCommitted($conn) {
    $result = $conn->query("SELECT @@autocommit as autocommit");
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['autocommit'] == 1;
    }
    return false;
}

/**
 * Force commit any pending transaction
 * Use this as safety measure at end of scripts that modify data
 *
 * @param mysqli $conn
 */
function ensureCommitted($conn) {
    try {
        $conn->commit();
        error_log("[Transaction-Helper] Safety commit executed");
    } catch (Throwable $e) {
        error_log("[Transaction-Helper] Safety commit failed (might be no transaction): " . $e->getMessage());
    }
}
