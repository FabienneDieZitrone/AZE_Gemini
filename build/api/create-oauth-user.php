<?php
// Create OAuth test user
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
    
    // Create OAuth test user
    $username = 'azetestclaude@mikropartner.de';
    $display_name = 'Claude Test';
    $role = 'Mitarbeiter';
    $azure_oid = 'test-' . uniqid(); // Fake Azure OID for testing
    
    // Check if exists
    $check = $db->query("SELECT id FROM users WHERE username = '$username'");
    if ($check && $check->num_rows > 0) {
        echo "User already exists with ID: " . $check->fetch_assoc()['id'];
        exit;
    }
    
    // Insert user
    $sql = "INSERT INTO users (username, display_name, role, azure_oid) VALUES (?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("ssss", $username, $display_name, $role, $azure_oid);
    
    if ($stmt->execute()) {
        echo "OAuth user created successfully! ID: " . $db->insert_id . "\n";
        echo "Username: " . $username . "\n";
        echo "Role: " . $role . "\n";
        echo "Note: This is an OAuth-only system. Use Azure AD to login.";
    } else {
        echo "Error: " . $db->error;
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>