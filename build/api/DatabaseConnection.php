<?php
/**
 * Secure Database Connection Manager
 * Version: 1.0 - Clean Architecture & Security Focused
 * Author: Refactoring Expert
 * Description: Single, secure, testable database connection manager
 *              following SOLID principles and security best practices
 */

// Hinweis: Verzicht auf strikte Typisierungen für maximale Serverkompatibilität

// Try to include configuration from multiple known locations
@include_once __DIR__ . '/../config.php'; // repo-level config
@include_once __DIR__ . '/config.php';    // live server config under /api
// Provide compatibility layer if Config class is missing or env names differ
require_once __DIR__ . '/config-compat.php';
require_once __DIR__ . '/structured-logger.php';

interface DatabaseConnectionInterface {
    /** @return mysqli */
    public function getConnection();
    public function beginTransaction();
    public function commit();
    public function rollback();
    public function close();
    /** @return bool */
    public function isConnected();
}

class DatabaseConnection implements DatabaseConnectionInterface {
    private static $instance = null; // self|null
    private $connection = null;      // mysqli|null
    private $logger;                 // StructuredLogger
    private $config = [];
    private $inTransaction = false;
    
    private function __construct() {
        $this->logger = new StructuredLogger();
        $this->config = $this->loadSecureConfig();
        $this->validateConfiguration();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Load secure configuration with fallback strategies
     */
    private function loadSecureConfig() {
        try {
            $config = Config::load();
            
            return [
                'host' => Config::get('db.host', ''),
                'username' => Config::get('db.username', ''),
                'password' => Config::get('db.password', ''),
                'database' => Config::get('db.name', ''),
                'charset' => Config::get('db.charset', 'utf8mb4'),
                'timeout' => Config::get('db.timeout', 30)
            ];
        } catch (Exception $e) {
            $this->logger->critical('Database configuration loading failed', [
                'error' => $e->getMessage()
            ]);
            throw new RuntimeException('Database configuration unavailable');
        }
    }
    
    /**
     * Validate database configuration
     */
    private function validateConfiguration() {
        $required = ['host', 'username', 'database'];
        $missing = [];
        
        foreach ($required as $field) {
            if (empty($this->config[$field])) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            $this->logger->critical('Database configuration incomplete', [
                'missing_fields' => $missing
            ]);
            throw new RuntimeException('Incomplete database configuration');
        }
        
        // Security check: Ensure we're not using default/weak credentials
        if ($this->config['username'] === 'root' || 
            $this->config['password'] === '' || 
            $this->config['password'] === 'password') {
            $this->logger->warning('Potentially insecure database credentials detected');
        }
    }
    
    /**
     * Get secure database connection with connection pooling
     */
    public function getConnection() {
        if ($this->connection === null || !$this->connection->ping()) {
            $this->establishConnection();
        }
        
        return $this->connection;
    }
    
    /**
     * Establish secure database connection
     */
    private function establishConnection() {
        try {
            // Check if MySQLi extension is available
            if (!extension_loaded('mysqli')) {
                throw new RuntimeException('MySQLi extension not available');
            }
            
            $this->connection = new mysqli(
                $this->config['host'],
                $this->config['username'],
                $this->config['password'],
                $this->config['database']
            );
            
            if ($this->connection->connect_error) {
                throw new RuntimeException(
                    'Database connection failed: ' . $this->connection->connect_error
                );
            }
            
            // Set secure connection options
            $this->configureConnection();
            
            $this->logger->info('Database connection established', [
                'host' => $this->config['host'],
                'database' => $this->config['database']
            ]);
            
        } catch (Exception $e) {
            $this->logger->critical('Database connection failed', [
                'error' => $e->getMessage(),
                'host' => $this->config['host']
            ]);
            throw new RuntimeException('Database connection unavailable: ' . $e->getMessage());
        }
    }
    
    /**
     * Configure connection for security and performance
     */
    private function configureConnection() {
        // Set charset for security (prevent charset confusion attacks)
        if (!$this->connection->set_charset($this->config['charset'])) {
            $this->logger->warning('Failed to set database charset', [
                'charset' => $this->config['charset'],
                'error' => $this->connection->error
            ]);
        }
        
        // Set SQL mode for strict data validation
        $this->connection->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
        
        // Set connection timeout
        $this->connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, $this->config['timeout']);
        
        // Disable autocommit for better transaction control
        $this->connection->autocommit(false);
    }
    
    /**
     * Begin database transaction
     */
    public function beginTransaction() {
        $connection = $this->getConnection();
        
        if ($this->inTransaction) {
            $this->logger->warning('Attempting to begin transaction while already in transaction');
            return;
        }
        
        if (!$connection->begin_transaction()) {
            throw new RuntimeException('Failed to begin transaction');
        }
        
        $this->inTransaction = true;
        $this->logger->debug('Database transaction started');
    }
    
    /**
     * Commit database transaction
     */
    public function commit() {
        if (!$this->inTransaction) {
            $this->logger->warning('Attempting to commit without active transaction');
            return;
        }
        
        $connection = $this->getConnection();
        
        if (!$connection->commit()) {
            throw new RuntimeException('Failed to commit transaction');
        }
        
        $this->inTransaction = false;
        $this->logger->debug('Database transaction committed');
    }
    
    /**
     * Rollback database transaction
     */
    public function rollback() {
        if (!$this->inTransaction) {
            $this->logger->warning('Attempting to rollback without active transaction');
            return;
        }
        
        $connection = $this->getConnection();
        
        if (!$connection->rollback()) {
            $this->logger->error('Failed to rollback transaction');
        }
        
        $this->inTransaction = false;
        $this->logger->debug('Database transaction rolled back');
    }
    
    /**
     * Close database connection
     */
    public function close() {
        if ($this->connection !== null) {
            if ($this->inTransaction) {
                $this->rollback();
            }
            
            $this->connection->close();
            $this->connection = null;
            $this->logger->debug('Database connection closed');
        }
    }
    
    /**
     * Check if database is connected
     */
    public function isConnected() {
        return $this->connection !== null && $this->connection->ping();
    }
    
    /**
     * Prepare and execute secure statement
     */
    public function prepareAndExecute($query, $types = '', $params = []) {
        $connection = $this->getConnection();
        
        $stmt = $connection->prepare($query);
        if (!$stmt) {
            throw new RuntimeException('Statement preparation failed: ' . $connection->error);
        }
        
        if (!empty($types) && !empty($params)) {
            if (strlen($types) !== count($params)) {
                throw new InvalidArgumentException('Parameter count mismatch');
            }
            
            if (!$stmt->bind_param($types, ...$params)) {
                throw new RuntimeException('Parameter binding failed: ' . $stmt->error);
            }
        }
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Query execution failed: ' . $stmt->error);
        }
        
        return $stmt;
    }
    
    /**
     * Execute query and return results safely
     */
    public function executeQuery($query, $types = '', $params = []) {
        $stmt = $this->prepareAndExecute($query, $types, $params);
        $result = $stmt->get_result();
        
        if ($result === false) {
            $stmt->close();
            throw new RuntimeException('Failed to get query result');
        }
        
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $data;
    }
    
    /**
     * Execute non-select query (INSERT, UPDATE, DELETE)
     */
    public function executeNonQuery($query, $types = '', $params = []) {
        $stmt = $this->prepareAndExecute($query, $types, $params);
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $affectedRows;
    }
    
    /**
     * Get last inserted ID
     */
    public function getLastInsertId() {
        $connection = $this->getConnection();
        return (int)$connection->insert_id;
    }
    
    /**
     * Health check for monitoring
     */
    public function healthCheck() {
        try {
            $connection = $this->getConnection();
            $result = $connection->query("SELECT 1 as health_check");
            
            if ($result && $result->fetch_assoc()['health_check'] === 1) {
                return [
                    'status' => 'healthy',
                    'connected' => true,
                    'host' => $this->config['host'],
                    'database' => $this->config['database']
                ];
            }
            
            return ['status' => 'unhealthy', 'connected' => false];
            
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new RuntimeException('Cannot unserialize singleton');
    }
    
    /**
     * Cleanup on destruction
     */
    public function __destruct() {
        $this->close();
    }
}

// Global helper function for backward compatibility
function getDatabaseConnection() {
    return DatabaseConnection::getInstance()->getConnection();
}

// Legacy support - maintain existing $conn variable behavior
$conn = getDatabaseConnection();
