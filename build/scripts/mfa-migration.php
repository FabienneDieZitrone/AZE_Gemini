<?php
/**
 * MFA Migration Script
 * Issue #115: Multi-Factor Authentication Implementation
 * Date: 2025-08-06
 * Author: Claude Code
 * 
 * This script safely migrates the database to support MFA functionality
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/structured-logger.php';

class MFAMigration {
    private $conn;
    private $dry_run;
    
    public function __construct($dry_run = false) {
        $this->dry_run = $dry_run;
        echo "MFA Migration Script\n";
        echo "==================\n\n";
        
        if ($dry_run) {
            echo "*** DRY RUN MODE - No changes will be made ***\n\n";
        }
    }
    
    public function run() {
        try {
            $this->conn = get_db_connection();
            $this->conn->begin_transaction();
            
            echo "1. Checking current database schema...\n";
            $this->checkCurrentSchema();
            
            echo "2. Creating backup of affected tables...\n";
            $this->createBackup();
            
            echo "3. Adding MFA columns to users table...\n";
            $this->addMFAColumns();
            
            echo "4. Creating MFA settings table...\n";
            $this->createMFASettingsTable();
            
            echo "5. Creating MFA audit log table...\n";
            $this->createMFAAuditTable();
            
            echo "6. Updating global settings...\n";
            $this->updateGlobalSettings();
            
            echo "7. Creating indexes for performance...\n";
            $this->createIndexes();
            
            echo "8. Creating cleanup procedure...\n";
            $this->createCleanupProcedure();
            
            echo "9. Running post-migration checks...\n";
            $this->runPostMigrationChecks();
            
            if (!$this->dry_run) {
                $this->conn->commit();
                echo "\n✅ Migration completed successfully!\n";
                $this->logMigration('success');
            } else {
                $this->conn->rollback();
                echo "\n✅ Dry run completed - no changes made\n";
            }
            
        } catch (Exception $e) {
            if (isset($this->conn)) {
                $this->conn->rollback();
            }
            echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
            $this->logMigration('failed', $e->getMessage());
            exit(1);
        } finally {
            if (isset($this->conn)) {
                $this->conn->close();
            }
        }
    }
    
    private function checkCurrentSchema() {
        // Check if MFA columns already exist
        $result = $this->conn->query("SHOW COLUMNS FROM users LIKE 'mfa_enabled'");
        if ($result->num_rows > 0) {
            throw new Exception("MFA columns already exist. Migration may have been run already.");
        }
        
        // Check if required tables exist
        $required_tables = ['users', 'global_settings'];
        foreach ($required_tables as $table) {
            $result = $this->conn->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows === 0) {
                throw new Exception("Required table '$table' not found");
            }
        }
        
        echo "   ✓ Schema check passed\n";
    }
    
    private function createBackup() {
        if ($this->dry_run) {
            echo "   ✓ Would create backup tables\n";
            return;
        }
        
        $timestamp = date('Y_m_d_H_i_s');
        
        // Backup users table
        $this->conn->query("CREATE TABLE users_backup_$timestamp AS SELECT * FROM users");
        
        // Backup global_settings table
        $this->conn->query("CREATE TABLE global_settings_backup_$timestamp AS SELECT * FROM global_settings");
        
        echo "   ✓ Backup tables created with suffix: $timestamp\n";
    }
    
    private function addMFAColumns() {
        $columns = [
            "mfa_enabled BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Whether MFA is enabled for this user'",
            "mfa_secret VARCHAR(255) NULL COMMENT 'Encrypted TOTP secret'",
            "mfa_secret_iv VARCHAR(255) NULL COMMENT 'Initialization vector for secret encryption'",
            "mfa_backup_codes TEXT NULL COMMENT 'Encrypted JSON array of backup codes'",
            "mfa_backup_codes_iv VARCHAR(255) NULL COMMENT 'Initialization vector for backup codes'",
            "mfa_setup_at TIMESTAMP NULL DEFAULT NULL COMMENT 'When MFA was first set up'",
            "mfa_last_used TIMESTAMP NULL DEFAULT NULL COMMENT 'Last time MFA was successfully used'"
        ];
        
        foreach ($columns as $column) {
            if (!$this->dry_run) {
                $this->conn->query("ALTER TABLE users ADD COLUMN $column");
            }
            echo "   ✓ Added column: " . explode(' ', $column)[0] . "\n";
        }
    }
    
    private function createMFASettingsTable() {
        $sql = "
        CREATE TABLE user_mfa_settings (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            method ENUM('totp','sms','email') NOT NULL DEFAULT 'totp',
            is_primary BOOLEAN NOT NULL DEFAULT TRUE,
            backup_codes_remaining INT NOT NULL DEFAULT 8,
            last_backup_code_used_at TIMESTAMP NULL DEFAULT NULL,
            failed_attempts INT NOT NULL DEFAULT 0,
            last_failed_attempt_at TIMESTAMP NULL DEFAULT NULL,
            locked_until TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_method_unique (user_id, method),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        if (!$this->dry_run) {
            $this->conn->query($sql);
        }
        echo "   ✓ Created user_mfa_settings table\n";
    }
    
    private function createMFAAuditTable() {
        $sql = "
        CREATE TABLE mfa_audit_log (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            action ENUM('setup','verify_success','verify_fail','backup_code_used','disabled','reset') NOT NULL,
            method_used ENUM('totp','backup_code') NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            details JSON NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id_action (user_id, action),
            KEY created_at (created_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        if (!$this->dry_run) {
            $this->conn->query($sql);
        }
        echo "   ✓ Created mfa_audit_log table\n";
    }
    
    private function updateGlobalSettings() {
        $columns = [
            "mfa_required_roles JSON NULL DEFAULT NULL COMMENT 'Roles that require MFA'",
            "mfa_grace_period_hours INT NOT NULL DEFAULT 24 COMMENT 'Hours before MFA is required for new users'",
            "mfa_backup_codes_count INT NOT NULL DEFAULT 8 COMMENT 'Number of backup codes to generate'",
            "mfa_max_failed_attempts INT NOT NULL DEFAULT 5 COMMENT 'Max failed MFA attempts before lockout'",
            "mfa_lockout_duration_minutes INT NOT NULL DEFAULT 30 COMMENT 'Lockout duration in minutes'"
        ];
        
        foreach ($columns as $column) {
            if (!$this->dry_run) {
                $this->conn->query("ALTER TABLE global_settings ADD COLUMN $column");
            }
            echo "   ✓ Added setting: " . explode(' ', $column)[0] . "\n";
        }
        
        // Set default values
        if (!$this->dry_run) {
            $this->conn->query("
                UPDATE global_settings 
                SET 
                    mfa_required_roles = '[\"Admin\", \"Bereichsleiter\"]',
                    mfa_grace_period_hours = 24,
                    mfa_backup_codes_count = 8,
                    mfa_max_failed_attempts = 5,
                    mfa_lockout_duration_minutes = 30
                WHERE id = 1
            ");
        }
        echo "   ✓ Updated default MFA settings\n";
    }
    
    private function createIndexes() {
        $indexes = [
            "CREATE INDEX idx_users_mfa_enabled ON users(mfa_enabled)",
            "CREATE INDEX idx_users_mfa_secret ON users(mfa_secret)",
            "CREATE INDEX idx_mfa_settings_user_primary ON user_mfa_settings(user_id, is_primary)"
        ];
        
        foreach ($indexes as $sql) {
            if (!$this->dry_run) {
                $this->conn->query($sql);
            }
            echo "   ✓ Created index: " . substr($sql, strpos($sql, 'idx_')) . "\n";
        }
    }
    
    private function createCleanupProcedure() {
        $sql = "
        CREATE PROCEDURE CleanupExpiredMFALockouts()
        BEGIN
            UPDATE user_mfa_settings 
            SET locked_until = NULL, failed_attempts = 0 
            WHERE locked_until IS NOT NULL AND locked_until < NOW();
        END
        ";
        
        if (!$this->dry_run) {
            // Drop procedure if exists, then create
            $this->conn->query("DROP PROCEDURE IF EXISTS CleanupExpiredMFALockouts");
            $this->conn->query($sql);
        }
        echo "   ✓ Created cleanup procedure\n";
    }
    
    private function runPostMigrationChecks() {
        // Verify all tables exist
        $tables = ['users', 'user_mfa_settings', 'mfa_audit_log', 'global_settings'];
        foreach ($tables as $table) {
            $result = $this->conn->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows === 0) {
                throw new Exception("Table '$table' was not created properly");
            }
        }
        
        // Verify MFA columns exist
        $result = $this->conn->query("SHOW COLUMNS FROM users LIKE 'mfa_%'");
        if ($result->num_rows < 6) {
            throw new Exception("Not all MFA columns were added to users table");
        }
        
        // Verify procedure exists
        $result = $this->conn->query("SHOW PROCEDURE STATUS LIKE 'CleanupExpiredMFALockouts'");
        if ($result->num_rows === 0 && !$this->dry_run) {
            throw new Exception("Cleanup procedure was not created");
        }
        
        echo "   ✓ All post-migration checks passed\n";
    }
    
    private function logMigration($status, $error = null) {
        $log_data = [
            'timestamp' => date('c'),
            'status' => $status,
            'dry_run' => $this->dry_run,
            'version' => '2.0',
            'error' => $error
        ];
        
        $log_file = __DIR__ . '/../logs/mfa_migration.log';
        $log_dir = dirname($log_file);
        
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        file_put_contents($log_file, json_encode($log_data) . "\n", FILE_APPEND);
    }
}

// Command line interface
$dry_run = in_array('--dry-run', $argv) || in_array('-d', $argv);
$help = in_array('--help', $argv) || in_array('-h', $argv);

if ($help) {
    echo "MFA Migration Script\n";
    echo "Usage: php mfa-migration.php [options]\n\n";
    echo "Options:\n";
    echo "  --dry-run, -d    Run without making changes (test mode)\n";
    echo "  --help, -h       Show this help message\n\n";
    echo "Examples:\n";
    echo "  php mfa-migration.php --dry-run    # Test migration\n";
    echo "  php mfa-migration.php              # Run migration\n\n";
    exit(0);
}

// Run migration
$migration = new MFAMigration($dry_run);
$migration->run();
?>