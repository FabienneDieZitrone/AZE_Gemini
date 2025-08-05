<?php
// Direct user creation - no dependencies
try {
    // Direct DB connection
    $db = new mysqli(
        'vwp8374.webpack.hosteurope.de',
        'db10454681-aze', 
        'Start.321',
        'db10454681-aze'
    );
    
    if ($db->connect_error) {
        die('DB Error: ' . $db->connect_error);
    }
    
    // Create test user
    $email = 'azetestclaude@mikropartner.de';
    $password_hash = password_hash('a1b2c3d4', PASSWORD_DEFAULT);
    
    // Check if exists
    $check = $db->query("SELECT id FROM users WHERE email = '$email'");
    if ($check && $check->num_rows > 0) {
        echo "User already exists with ID: " . $check->fetch_assoc()['id'];
        exit;
    }
    
    // Insert user
    $sql = "INSERT INTO users (email, password_hash, name, role, active) VALUES (?, ?, 'Claude Test', 'Benutzer', 1)";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("ss", $email, $password_hash);
    
    if ($stmt->execute()) {
        echo "User created successfully! ID: " . $db->insert_id;
    } else {
        echo "Error: " . $db->error;
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>