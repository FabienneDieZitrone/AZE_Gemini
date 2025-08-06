<?php
/**
 * Enhanced Database Wrapper with Performance Monitoring
 * Version: 2.0 - Enhanced for performance monitoring
 * Author: Database Performance Expert
 * File: /api/db-wrapper.php
 * Provides function-based access to database connection with query logging
 */

// Include performance monitoring
require_once __DIR__ . '/query-logger.php';

// Include the original db.php to get the $conn variable
require_once __DIR__ . '/db.php';

// Initialize query logger
$is_development = (Config::get('environment') ?? 'production') === 'development';
QueryLogger::setEnabled($is_development || (Config::get('db.query_logging') ?? false));

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
 * Execute a prepared statement with performance logging
 * @param string $sql SQL query with placeholders
 * @param string $types Type string (e.g., "si" for string, integer)
 * @param array $params Parameters to bind
 * @return mysqli_stmt|false
 */
function executeQuery($sql, $types = '', $params = []) {
    $db = initDB();
    
    // Start query logging
    $query_id = QueryLogger::startQuery($sql, $params);
    
    try {
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
        
        // Count results for logging
        $count = null;
        if ($stmt->result_metadata()) {
            $result = $stmt->get_result();
            $count = $result ? $result->num_rows : 0;
            // Reset the result set for the caller
            $stmt->execute();
        } else {
            $count = $stmt->affected_rows;
        }
        
        QueryLogger::endQuery($query_id, $count);
        return $stmt;
        
    } catch (Exception $e) {
        QueryLogger::endQuery($query_id);
        throw $e;
    }
}

/**
 * Get paginated results with automatic count query and performance monitoring
 * @param string $base_query Base SELECT query without LIMIT
 * @param string $count_query COUNT query to get total results
 * @param array $params Parameters for both queries
 * @param string $types Parameter types
 * @param int $page Page number (1-based)
 * @param int $limit Results per page
 * @return array Array with 'data' and 'pagination' keys
 */
function getPaginatedResults($base_query, $count_query, $params = [], $types = '', $page = 1, $limit = 20) {
    $offset = ($page - 1) * $limit;
    $paginated_query = $base_query . " LIMIT ? OFFSET ?";
    
    // Add limit/offset parameters
    $paginated_params = array_merge($params, [$limit, $offset]);
    $paginated_types = $types . 'ii';
    
    $db = initDB();
    
    // Execute paginated query
    $stmt = executeQuery($paginated_query, $paginated_types, $paginated_params);
    $result = $stmt->get_result();
    $data = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    
    // Execute count query
    $count_stmt = executeQuery($count_query, $types, $params);
    $count_result = $count_stmt->get_result();
    $total = $count_result ? $count_result->fetch_assoc()['total'] : 0;
    $count_stmt->close();
    
    return [
        'data' => $data,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'pages' => (int)ceil($total / $limit),
            'hasNext' => $page < ceil($total / $limit),
            'hasPrev' => $page > 1
        ]
    ];
}

/**
 * Get query performance statistics for current request
 * @return array|null Performance stats
 */
function getQueryStats() {
    return QueryLogger::getStats();
}

/**
 * Add performance headers to response (for development)
 */
function addPerformanceHeaders() {
    QueryLogger::addStatsToHeaders();
}
?>