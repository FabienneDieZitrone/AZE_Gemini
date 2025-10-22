<?php
/**
 * Database Backup Script
 * Creates SQL dump of entire database
 */
define('API_GUARD', true);
require_once __DIR__ . '/DatabaseConnection.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = DatabaseConnection::getInstance();
    $conn = $db->getConnection();

    // Get database name
    $dbName = $conn->query("SELECT DATABASE()")->fetch_row()[0];

    // Get all tables
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

    $sqlDump = "-- Database Backup\n";
    $sqlDump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sqlDump .= "-- Database: $dbName\n\n";
    $sqlDump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        // Table structure
        $createTable = $conn->query("SHOW CREATE TABLE `$table`")->fetch_row()[1];
        $sqlDump .= "DROP TABLE IF EXISTS `$table`;\n";
        $sqlDump .= $createTable . ";\n\n";

        // Table data
        $result = $conn->query("SELECT * FROM `$table`");
        if ($result->num_rows > 0) {
            $sqlDump .= "-- Data for table `$table`\n";
            while ($row = $result->fetch_assoc()) {
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = "'" . $conn->real_escape_string($value) . "'";
                    }
                }
                $sqlDump .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
            }
            $sqlDump .= "\n";
        }
    }

    $sqlDump .= "SET FOREIGN_KEY_CHECKS=1;\n";

    // Save to file
    $backupDir = __DIR__ . '/../backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    $filename = 'backup_' . date('Y-m-d_His') . '.sql';
    $filepath = $backupDir . '/' . $filename;
    file_put_contents($filepath, $sqlDump);

    echo json_encode([
        'success' => true,
        'message' => 'Backup created successfully',
        'filename' => $filename,
        'filepath' => $filepath,
        'size' => filesize($filepath),
        'tables' => count($tables),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
