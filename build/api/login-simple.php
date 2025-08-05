<?php
/**
 * Simple login endpoint for testing
 * This bypasses complex security middleware to isolate the login issue
 */

// Set headers
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: https://aze.mikropartner.de');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['email']) || !isset($input['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and password required']);
        exit;
    }
    
    // Include database connection
    define('API_GUARD', true);
    require_once __DIR__ . '/db.php';
    
    // Find user
    $email = $input['email'];
    $stmt = $db->prepare("SELECT user_id, email, name, role, password_hash FROM users WHERE email = ? AND active = 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    
    // Verify password
    if (!password_verify($input['password'], $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        exit;
    }
    
    // Start session
    session_start();
    
    // Set session data
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['authenticated'] = true;
    
    // Return success response
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $user['user_id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role']
        ],
        'authMethod' => 'credentials'
    ]);
    
} catch (Exception $e) {
    error_log('Login error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Login failed: ' . $e->getMessage()]);
}
?>