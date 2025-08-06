<?php
/**
 * Query Performance Logger
 * Version: 1.0
 * Author: Database Performance Expert
 * File: /api/query-logger.php
 * Purpose: Log and monitor database query performance
 */

class QueryLogger {
    private static $queries = [];
    private static $enabled = true;
    private static $slow_query_threshold = 0.1; // 100ms threshold for slow queries
    
    /**
     * Start timing a query
     */
    public static function startQuery($query, $params = []) {
        if (!self::$enabled) return null;
        
        $query_id = uniqid('query_', true);
        self::$queries[$query_id] = [
            'query' => $query,
            'params' => $params,
            'start_time' => microtime(true),
            'end_time' => null,
            'duration' => null,
            'memory_before' => memory_get_usage(true)
        ];
        
        return $query_id;
    }
    
    /**
     * End timing a query
     */
    public static function endQuery($query_id, $result_count = null) {
        if (!self::$enabled || !isset(self::$queries[$query_id])) return;
        
        $end_time = microtime(true);
        $query_data = &self::$queries[$query_id];
        $query_data['end_time'] = $end_time;
        $query_data['duration'] = $end_time - $query_data['start_time'];
        $query_data['memory_after'] = memory_get_usage(true);
        $query_data['memory_used'] = $query_data['memory_after'] - $query_data['memory_before'];
        $query_data['result_count'] = $result_count;
        
        // Log slow queries
        if ($query_data['duration'] > self::$slow_query_threshold) {
            self::logSlowQuery($query_data);
        }
    }
    
    /**
     * Log slow queries to error log
     */
    private static function logSlowQuery($query_data) {
        $log_entry = [
            'type' => 'SLOW_QUERY',
            'duration' => round($query_data['duration'] * 1000, 2) . 'ms',
            'memory_used' => self::formatBytes($query_data['memory_used']),
            'result_count' => $query_data['result_count'],
            'query' => self::sanitizeQuery($query_data['query']),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        error_log('SLOW_QUERY: ' . json_encode($log_entry));
    }
    
    /**
     * Get performance statistics for current request
     */
    public static function getStats() {
        if (!self::$enabled) return null;
        
        $completed_queries = array_filter(self::$queries, function($q) {
            return $q['end_time'] !== null;
        });
        
        if (empty($completed_queries)) {
            return [
                'total_queries' => 0,
                'total_duration' => 0,
                'avg_duration' => 0,
                'slow_queries' => 0
            ];
        }
        
        $durations = array_column($completed_queries, 'duration');
        $slow_queries = array_filter($durations, function($d) {
            return $d > self::$slow_query_threshold;
        });
        
        return [
            'total_queries' => count($completed_queries),
            'total_duration' => round(array_sum($durations) * 1000, 2) . 'ms',
            'avg_duration' => round((array_sum($durations) / count($durations)) * 1000, 2) . 'ms',
            'slow_queries' => count($slow_queries),
            'max_duration' => round(max($durations) * 1000, 2) . 'ms',
            'memory_peak' => self::formatBytes(memory_get_peak_usage(true))
        ];
    }
    
    /**
     * Enable/disable query logging
     */
    public static function setEnabled($enabled) {
        self::$enabled = $enabled;
    }
    
    /**
     * Set slow query threshold in seconds
     */
    public static function setSlowQueryThreshold($threshold) {
        self::$slow_query_threshold = $threshold;
    }
    
    /**
     * Sanitize query for logging (remove sensitive data)
     */
    private static function sanitizeQuery($query) {
        // Remove potential sensitive parameter values
        $query = preg_replace('/\'[^\']*\'/', "'***'", $query);
        $query = preg_replace('/\?/', '?', $query);
        return $query;
    }
    
    /**
     * Format bytes to human readable format
     */
    private static function formatBytes($size) {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        return round($size, 2) . ' ' . $units[$i];
    }
    
    /**
     * Add performance stats to response headers (for development)
     */
    public static function addStatsToHeaders() {
        if (!self::$enabled) return;
        
        $stats = self::getStats();
        if ($stats && !headers_sent()) {
            header('X-Query-Count: ' . $stats['total_queries']);
            header('X-Query-Time: ' . $stats['total_duration']);
            header('X-Query-Slow: ' . $stats['slow_queries']);
            header('X-Memory-Peak: ' . $stats['memory_peak']);
        }
    }
    
    /**
     * Reset query log (for testing)
     */
    public static function reset() {
        self::$queries = [];
    }
}

/**
 * Enhanced mysqli wrapper with query logging
 */
class LoggedMysqli extends mysqli {
    public function prepare($query) {
        $query_id = QueryLogger::startQuery($query);
        $stmt = parent::prepare($query);
        
        if ($stmt) {
            return new LoggedMysqliStatement($stmt, $query_id, $query);
        }
        
        QueryLogger::endQuery($query_id);
        return $stmt;
    }
    
    public function query($query, $resultmode = MYSQLI_STORE_RESULT) {
        $query_id = QueryLogger::startQuery($query);
        $result = parent::query($query, $resultmode);
        
        $count = null;
        if ($result instanceof mysqli_result) {
            $count = $result->num_rows;
        } elseif ($result === true) {
            $count = $this->affected_rows;
        }
        
        QueryLogger::endQuery($query_id, $count);
        return $result;
    }
}

/**
 * Enhanced mysqli_stmt wrapper with query logging
 */
class LoggedMysqliStatement {
    private $stmt;
    private $query_id;
    private $query;
    
    public function __construct($stmt, $query_id, $query) {
        $this->stmt = $stmt;
        $this->query_id = $query_id;
        $this->query = $query;
    }
    
    public function execute() {
        $result = $this->stmt->execute();
        
        if ($result) {
            $count = null;
            if ($this->stmt->result_metadata()) {
                // SELECT query - we'll count when get_result is called
            } else {
                // INSERT/UPDATE/DELETE query
                $count = $this->stmt->affected_rows;
            }
            QueryLogger::endQuery($this->query_id, $count);
        } else {
            QueryLogger::endQuery($this->query_id);
        }
        
        return $result;
    }
    
    public function get_result() {
        $result = $this->stmt->get_result();
        if ($result) {
            // Update count now that we have the result
            QueryLogger::endQuery($this->query_id, $result->num_rows);
        }
        return $result;
    }
    
    // Delegate all other method calls to the original statement
    public function __call($method, $args) {
        return call_user_func_array([$this->stmt, $method], $args);
    }
    
    public function __get($property) {
        return $this->stmt->$property;
    }
    
    public function __set($property, $value) {
        $this->stmt->$property = $value;
    }
}