<?php
// List all users in database
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
    
    // List all users
    echo "Users in database:\n";
    echo "==================\n";
    $users = $db->query("SELECT id, username, display_name, role, azure_oid FROM users ORDER BY id");
    if ($users) {
        while ($user = $users->fetch_assoc()) {
            echo "ID: " . $user['id'] . "\n";
            echo "Username: " . $user['username'] . "\n";
            echo "Display Name: " . $user['display_name'] . "\n";
            echo "Role: " . $user['role'] . "\n";
            echo "Azure OID: " . $user['azure_oid'] . "\n";
            echo "-------------------\n";
        }
    } else {
        echo "No users found or error: " . $db->error . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>