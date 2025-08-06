<?php
/**
 * Performance Monitoring Endpoint
 * Version: 1.0
 * Author: Database Performance Expert
 * File: /api/performance-monitor.php
 * Purpose: Provide runtime performance statistics for database queries
 * Note: Only enabled in development environment for security
 */

// Define API guard constant
define('API_GUARD', true);

require_once __DIR__ . '/db-wrapper.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/security-middleware.php';

// Initialize API
initialize_api();

// Apply security headers
initSecurityMiddleware();

// Check if performance monitoring is enabled
$is_development = (Config::get('environment') ?? 'production') === 'development';
$monitoring_enabled = $is_development || (Config::get('db.performance_monitoring') ?? false);

if (!$monitoring_enabled) {
    send_response(403, ['message' => 'Performance monitoring is disabled in production']);
    exit;
}

// Require authentication
$user_from_session = authorize_request();

// Only allow Admin users to view performance data
if ($user_from_session['role'] !== 'Admin') {
    send_response(403, ['message' => 'Access denied: Admin role required']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    send_response(405, ['message' => 'Method Not Allowed']);
    exit;
}

// Get current request performance stats
$current_stats = getQueryStats();

// Get system performance information
$performance_data = [
    'current_request' => $current_stats,
    'system' => [
        'memory_usage' => formatBytes(memory_get_usage(true)),
        'memory_peak' => formatBytes(memory_get_peak_usage(true)),
        'memory_limit' => ini_get('memory_limit'),
        'execution_time' => round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 4) . 's',
        'php_version' => phpversion(),
        'mysql_version' => getMysqlVersion()
    ],
    'database' => getDatabaseInfo(),
    'recommendations' => getPerformanceRecommendations($current_stats)
];

send_response(200, $performance_data);

/**
 * Get MySQL version
 */
function getMysqlVersion() {
    try {
        $db = initDB();
        $result = $db->query("SELECT VERSION() as version");
        $row = $result->fetch_assoc();
        return $row['version'];
    } catch (Exception $e) {
        return 'Unknown';
    }
}

/**
 * Get database configuration information
 */
function getDatabaseInfo() {
    try {
        $db = initDB();
        
        // Get some basic database stats
        $queries = [
            'tables' => "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE()",
            'indexes' => "SELECT COUNT(*) as count FROM information_schema.statistics WHERE table_schema = DATABASE()",
            'connection_count' => "SHOW STATUS LIKE 'Threads_connected'",
            'slow_query_log' => "SHOW VARIABLES LIKE 'slow_query_log'",
            'slow_query_time' => "SHOW VARIABLES LIKE 'long_query_time'"
        ];
        
        $db_info = [];
        
        foreach ($queries as $key => $query) {
            try {
                $result = $db->query($query);
                if ($key === 'connection_count' || $key === 'slow_query_log' || $key === 'slow_query_time') {
                    $row = $result->fetch_assoc();
                    $db_info[$key] = $row['Value'] ?? 'Unknown';
                } else {
                    $row = $result->fetch_assoc();
                    $db_info[$key] = $row['count'] ?? 0;
                }
            } catch (Exception $e) {
                $db_info[$key] = 'Error: ' . $e->getMessage();
            }
        }
        
        return $db_info;
        
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Generate performance recommendations based on current stats
 */
function getPerformanceRecommendations($stats) {
    $recommendations = [];
    
    if (!$stats) {
        return ['No query statistics available for this request'];
    }
    
    // Check for slow queries
    if ($stats['slow_queries'] > 0) {
        $recommendations[] = "Found {$stats['slow_queries']} slow queries. Consider optimizing queries or adding indexes.";
    }
    
    // Check total query count
    if ($stats['total_queries'] > 50) {
        $recommendations[] = "High query count ({$stats['total_queries']}). Consider implementing query caching or reducing N+1 queries.";
    }
    
    // Check memory usage
    $memory_mb = memory_get_peak_usage(true) / 1024 / 1024;
    if ($memory_mb > 64) {
        $recommendations[] = "High memory usage ({$stats['memory_peak']}). Consider optimizing data structures or implementing pagination.";
    }
    
    // Parse total duration to check if it's high
    if (isset($stats['total_duration'])) {
        $duration_ms = floatval(str_replace('ms', '', $stats['total_duration']));
        if ($duration_ms > 1000) {
            $recommendations[] = "High total query time ({$stats['total_duration']}). Optimize slow queries and consider database indexing.";
        }
    }
    
    if (empty($recommendations)) {
        $recommendations[] = "No performance issues detected in current request.";
    }
    
    // Add general recommendations
    $recommendations[] = "Ensure database indexes are applied by running: /migrations/002_performance_indexes.sql";
    $recommendations[] = "Monitor slow query log regularly in production environment.";
    
    return $recommendations;
}

/**
 * Format bytes to human readable format
 */
function formatBytes($size) {
    $units = ['B', 'KB', 'MB', 'GB'];
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, 2) . ' ' . $units[$i];
}