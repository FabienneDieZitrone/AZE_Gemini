<?php
/**
 * Structured Logger for AZE_Gemini
 * 
 * Provides structured logging with automatic log rotation
 * and optional database logging for audit trails
 */

class StructuredLogger {
    private $logDir;
    private $maxFileSize = 10485760; // 10MB
    private $maxFiles = 10;
    private $requestId;
    
    public function __construct($logDir = null) {
        $this->logDir = $logDir ?? __DIR__ . '/../logs';
        $this->requestId = $this->generateRequestId();
        
        // Create log directory if it doesn't exist
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }
    
    /**
     * Log levels
     */
    const EMERGENCY = 'emergency';
    const ALERT = 'alert';
    const CRITICAL = 'critical';
    const ERROR = 'error';
    const WARNING = 'warning';
    const NOTICE = 'notice';
    const INFO = 'info';
    const DEBUG = 'debug';
    
    /**
     * Log a message with context
     */
    public function log($level, $message, array $context = []) {
        $logEntry = $this->createLogEntry($level, $message, $context);
        
        // Write to file
        $this->writeToFile($logEntry);
        
        // Optionally write to database for critical logs
        if (in_array($level, [self::EMERGENCY, self::ALERT, self::CRITICAL, self::ERROR])) {
            $this->writeToDatabase($logEntry);
        }
    }
    
    /**
     * Convenience methods
     */
    public function emergency($message, array $context = []) {
        $this->log(self::EMERGENCY, $message, $context);
    }
    
    public function alert($message, array $context = []) {
        $this->log(self::ALERT, $message, $context);
    }
    
    public function critical($message, array $context = []) {
        $this->log(self::CRITICAL, $message, $context);
    }
    
    public function error($message, array $context = []) {
        $this->log(self::ERROR, $message, $context);
    }
    
    public function warning($message, array $context = []) {
        $this->log(self::WARNING, $message, $context);
    }
    
    public function notice($message, array $context = []) {
        $this->log(self::NOTICE, $message, $context);
    }
    
    public function info($message, array $context = []) {
        $this->log(self::INFO, $message, $context);
    }
    
    public function debug($message, array $context = []) {
        $this->log(self::DEBUG, $message, $context);
    }
    
    /**
     * Create structured log entry
     */
    private function createLogEntry($level, $message, array $context) {
        $entry = [
            '@timestamp' => date('c'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'request' => [
                'id' => $this->requestId,
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                'uri' => $_SERVER['REQUEST_URI'] ?? 'CLI',
                'ip' => $this->getClientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ],
            'user' => [
                'id' => $_SESSION['user_id'] ?? null,
                'email' => $_SESSION['user_email'] ?? null
            ],
            'server' => [
                'hostname' => gethostname(),
                'php_version' => PHP_VERSION
            ]
        ];
        
        // Add error details if available
        if (isset($context['exception']) && $context['exception'] instanceof Exception) {
            $entry['error'] = [
                'type' => get_class($context['exception']),
                'message' => $context['exception']->getMessage(),
                'code' => $context['exception']->getCode(),
                'file' => $context['exception']->getFile(),
                'line' => $context['exception']->getLine(),
                'trace' => $context['exception']->getTraceAsString()
            ];
        }
        
        return $entry;
    }
    
    /**
     * Write log entry to file with rotation
     */
    private function writeToFile(array $logEntry) {
        $filename = $this->getLogFilename();
        
        // Check if rotation is needed
        if (file_exists($filename) && filesize($filename) > $this->maxFileSize) {
            $this->rotateLogFiles();
        }
        
        // Write log entry as JSON
        $line = json_encode($logEntry) . PHP_EOL;
        file_put_contents($filename, $line, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Write critical logs to database
     */
    private function writeToDatabase(array $logEntry) {
        // Only write to DB if we have a connection
        if (!isset($GLOBALS['mysqli']) || !$GLOBALS['mysqli']) {
            return;
        }
        
        try {
            $mysqli = $GLOBALS['mysqli'];
            
            $stmt = $mysqli->prepare("
                INSERT INTO audit_logs (
                    timestamp, level, message, context, request_id, 
                    user_id, ip_address, user_agent, uri
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt) {
                $context = json_encode($logEntry['context']);
                $stmt->bind_param(
                    'sssssssss',
                    $logEntry['@timestamp'],
                    $logEntry['level'],
                    $logEntry['message'],
                    $context,
                    $logEntry['request']['id'],
                    $logEntry['user']['id'],
                    $logEntry['request']['ip'],
                    $logEntry['request']['user_agent'],
                    $logEntry['request']['uri']
                );
                
                $stmt->execute();
                $stmt->close();
            }
        } catch (Exception $e) {
            // Don't throw errors from logger
            // Write to error log instead
            error_log("Logger DB write failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get current log filename
     */
    private function getLogFilename() {
        return $this->logDir . '/app-' . date('Y-m-d') . '.log';
    }
    
    /**
     * Rotate log files
     */
    private function rotateLogFiles() {
        $baseFilename = $this->getLogFilename();
        
        // Remove oldest file if we're at max
        $oldestFile = $baseFilename . '.' . $this->maxFiles;
        if (file_exists($oldestFile)) {
            unlink($oldestFile);
        }
        
        // Rotate existing files
        for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
            $oldFile = $baseFilename . '.' . $i;
            $newFile = $baseFilename . '.' . ($i + 1);
            if (file_exists($oldFile)) {
                rename($oldFile, $newFile);
            }
        }
        
        // Rotate current file
        if (file_exists($baseFilename)) {
            rename($baseFilename, $baseFilename . '.1');
        }
    }
    
    /**
     * Generate unique request ID
     */
    private function generateRequestId() {
        return bin2hex(random_bytes(16));
    }
    
    /**
     * Get client IP address
     */
    private function getClientIp() {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                return trim($ips[0]);
            }
        }
        
        return '0.0.0.0';
    }
}

// Global logger instance
$GLOBALS['logger'] = new StructuredLogger();

/**
 * Global logging functions
 */
function logError($message, array $context = []) {
    $GLOBALS['logger']->error($message, $context);
}

function logWarning($message, array $context = []) {
    $GLOBALS['logger']->warning($message, $context);
}

function logInfo($message, array $context = []) {
    $GLOBALS['logger']->info($message, $context);
}

function logDebug($message, array $context = []) {
    $GLOBALS['logger']->debug($message, $context);
}