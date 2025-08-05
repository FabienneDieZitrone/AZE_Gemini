<?php
// Check database schema
try {
    $db = new mysqli(
        'vwp8374.webpack.hosteurope.de',
        'db10454681-aze', 
        'Start.321',
        'db10454681-aze'
    );
    
    if ($db->connect_error) {
        die('DB Error: ' . $db->connect_error);
    }
    
    echo "Connected to database successfully!\n\n";
    
    // Show all tables
    echo "Tables in database:\n";
    $tables = $db->query("SHOW TABLES");
    while ($table = $tables->fetch_array()) {
        echo "- " . $table[0] . "\n";
    }
    
    // Check users table structure
    echo "\n\nStructure of 'users' table:\n";
    $columns = $db->query("SHOW COLUMNS FROM users");
    if ($columns) {
        while ($col = $columns->fetch_assoc()) {
            echo $col['Field'] . " (" . $col['Type'] . ")\n";
        }
    } else {
        echo "Users table not found. Checking for other user tables...\n";
        
        // Look for user-related tables
        $tables = $db->query("SHOW TABLES LIKE '%user%'");
        while ($table = $tables->fetch_array()) {
            echo "\nFound table: " . $table[0] . "\n";
            $cols = $db->query("SHOW COLUMNS FROM " . $table[0]);
            while ($col = $cols->fetch_assoc()) {
                echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>