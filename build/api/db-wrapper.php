<?php
/**
 * Database Wrapper Functions for AZE_Gemini
 * Provides function-based access to database connection
 */

// Include the original db.php to get the $conn variable
require_once __DIR__ . '/db.php';

// Store the connection globally
global $db_connection;
$db_connection = $conn;

/**
 * Initialize database connection
 * @return mysqli Database connection object
 * @throws Exception If connection fails
 */
function initDB() {
    global $db_connection;
    
    if (!$db_connection) {
        throw new Exception("Database connection not initialized");
    }
    
    // Check if connection is still alive
    if (!$db_connection->ping()) {
        // Try to reconnect
        require_once __DIR__ . '/db.php';
        global $conn;
        $db_connection = $conn;
        
        if (!$db_connection || !$db_connection->ping()) {
            throw new Exception("Database connection lost and reconnection failed");
        }
    }
    
    return $db_connection;
}

/**
 * Get the global database connection
 * @return mysqli|null
 */
function getDB() {
    global $db_connection;
    return $db_connection;
}

/**
 * Close database connection
 */
function closeDB() {
    global $db_connection;
    if ($db_connection) {
        $db_connection->close();
        $db_connection = null;
    }
}

/**
 * Execute a prepared statement
 * @param string $sql SQL query with placeholders
 * @param string $types Type string (e.g., "si" for string, integer)
 * @param array $params Parameters to bind
 * @return mysqli_stmt|false
 */
function executeQuery($sql, $types = '', $params = []) {
    $db = initDB();
    
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $db->error);
    }
    
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    return $stmt;
}
?>