<?php
/**
 * Mock Authentication API for Testing
 * Simulates OAuth login without actual Azure AD
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'db.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['email']) || !isset($input['name'])) {
        throw new Exception('Missing required fields');
    }
    
    $email = $input['email'];
    $name = $input['name'];
    
    // Validate test user
    if ($email !== 'azetestclaude@mikropartner.de') {
        throw new Exception('Invalid test user');
    }
    
    // Set session variables (simulate OAuth success)
    $_SESSION['email'] = $email;
    $_SESSION['name'] = $name;
    $_SESSION['authenticated'] = true;
    
    // Get or create user in database
    $db = getDBConnection();
    
    // Check if user exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Create user
        $stmt = $db->prepare("INSERT INTO users (email, name, created_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("ss", $email, $name);
        $stmt->execute();
        $userId = $db->insert_id;
    } else {
        $user = $result->fetch_assoc();
        $userId = $user['id'];
    }
    
    $_SESSION['user_id'] = $userId;
    
    echo json_encode([
        'success' => true,
        'user_id' => $userId,
        'email' => $email,
        'session_id' => session_id()
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>