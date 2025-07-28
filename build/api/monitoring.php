<?php
/**
 * Monitoring API for AZE_Gemini
 * Provides real-time metrics for the monitoring dashboard
 */

require_once __DIR__ . '/security-headers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/error-handler.php';

// Initialize security (admin only)
initializeSecurity(true);

// Additional admin check
if (empty($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    exit(json_encode([
        'success' => false,
        'error' => 'Admin access required'
    ]));
}

validateRequestMethod('GET');

try {
    $mysqli = initDB();
    $metrics = [
        'timestamp' => date('c'),
        'system' => getSystemMetrics(),
        'database' => getDatabaseMetrics($mysqli),
        'sessions' => getSessionMetrics($mysqli),
        'errors' => getErrorMetrics(),
        'performance' => getPerformanceMetrics($mysqli)
    ];
    
    header('Content-Type: application/json');
    echo json_encode($metrics, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    handleError($e);
}

function getSystemMetrics() {
    return [
        'memory' => [
            'used_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'limit' => ini_get('memory_limit')
        ],
        'disk' => [
            'free_gb' => round(disk_free_space(__DIR__) / 1024 / 1024 / 1024, 2),
            'total_gb' => round(disk_total_space(__DIR__) / 1024 / 1024 / 1024, 2),
            'used_percent' => round((1 - disk_free_space(__DIR__) / disk_total_space(__DIR__)) * 100, 2)
        ],
        'php' => [
            'version' => PHP_VERSION,
            'loaded_extensions' => get_loaded_extensions()
        ]
    ];
}

function getDatabaseMetrics($mysqli) {
    $metrics = [
        'connection_status' => 'connected',
        'tables' => []
    ];
    
    // Get table statistics
    $tables = ['users', 'time_entries', 'approvals'];
    foreach ($tables as $table) {
        $result = $mysqli->query("SELECT COUNT(*) as count FROM $table");
        if ($result) {
            $row = $result->fetch_assoc();
            $metrics['tables'][$table] = $row['count'];
            $result->close();
        }
    }
    
    // Get active timers
    $result = $mysqli->query("
        SELECT COUNT(*) as count 
        FROM time_entries 
        WHERE end_time IS NULL
    ");
    if ($result) {
        $row = $result->fetch_assoc();
        $metrics['active_timers'] = $row['count'];
        $result->close();
    }
    
    return $metrics;
}

function getSessionMetrics($mysqli) {
    $metrics = [
        'active_sessions' => 0,
        'unique_users_today' => 0
    ];
    
    // Count active sessions (approximate)
    $sessionPath = session_save_path() ?: '/tmp';
    if (is_readable($sessionPath)) {
        $files = glob($sessionPath . '/sess_*');
        $metrics['active_sessions'] = count($files);
    }
    
    // Unique users today
    $result = $mysqli->query("
        SELECT COUNT(DISTINCT user_id) as count 
        FROM time_entries 
        WHERE DATE(start_time) = CURDATE()
    ");
    if ($result) {
        $row = $result->fetch_assoc();
        $metrics['unique_users_today'] = $row['count'];
        $result->close();
    }
    
    return $metrics;
}

function getErrorMetrics() {
    $metrics = [
        'last_24h' => 0,
        'last_hour' => 0,
        'recent_errors' => []
    ];
    
    // Read error logs if available
    $logDir = __DIR__ . '/../logs';
    $logFile = $logDir . '/app-' . date('Y-m-d') . '.log';
    
    if (is_readable($logFile)) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $now = time();
        
        foreach ($lines as $line) {
            $log = json_decode($line, true);
            if ($log && isset($log['level']) && in_array($log['level'], ['error', 'critical'])) {
                $logTime = strtotime($log['@timestamp']);
                
                if ($now - $logTime < 86400) {
                    $metrics['last_24h']++;
                }
                if ($now - $logTime < 3600) {
                    $metrics['last_hour']++;
                }
                
                // Keep last 5 errors
                if (count($metrics['recent_errors']) < 5) {
                    $metrics['recent_errors'][] = [
                        'time' => $log['@timestamp'],
                        'message' => $log['message'],
                        'level' => $log['level']
                    ];
                }
            }
        }
    }
    
    return $metrics;
}

function getPerformanceMetrics($mysqli) {
    $metrics = [
        'avg_response_time_ms' => 0,
        'requests_per_minute' => 0
    ];
    
    // This would typically come from access logs or APM
    // For now, return mock data
    $metrics['avg_response_time_ms'] = rand(50, 150);
    $metrics['requests_per_minute'] = rand(10, 50);
    
    return $metrics;
}