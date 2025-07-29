<?php
/**
 * Safe OAuth Callback with detailed error handling
 */

// Capture all errors
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "PHP Error in OAuth Callback:\n";
    echo "Error: $errstr\n";
    echo "File: $errfile\n";
    echo "Line: $errline\n";
    exit;
});

// Exception handler
set_exception_handler(function($e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "Exception in OAuth Callback:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString();
    exit;
});

// Shutdown handler for fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        header('Content-Type: text/plain');
        echo "Fatal Error in OAuth Callback:\n";
        echo "Error: " . $error['message'] . "\n";
        echo "File: " . $error['file'] . "\n";
        echo "Line: " . $error['line'] . "\n";
        exit;
    }
});

try {
    // Session configuration
    ini_set('session.cookie_lifetime', 0);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'] ?? '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    // Include required files
    require_once __DIR__ . '/auth-oauth-client.php';
    require_once __DIR__ . '/validation.php';

    // Start session
    start_secure_session();

    // Validate GET parameters
    $allowed_params = ['state', 'error', 'error_description', 'code'];
    $get_params = InputValidator::validateGetParams($allowed_params);

    // CSRF check
    if (empty($get_params['state']) || !isset($_SESSION['oauth2state']) || !hash_equals($_SESSION['oauth2state'], $get_params['state'])) {
        unset($_SESSION['oauth2state']);
        http_response_code(400);
        echo 'Invalid state parameter. CSRF attack detected.';
        exit();
    }

    // Error from auth server
    if (isset($get_params['error'])) {
        http_response_code(500);
        echo 'Error from authentication server: ' . htmlspecialchars($get_params['error']) . ' - ' . htmlspecialchars($get_params['error_description'] ?? 'No description');
        exit();
    }

    // Exchange code for tokens
    if (!isset($get_params['code'])) {
        http_response_code(400);
        echo 'Authorization code not found in callback.';
        exit();
    }

    // Get tokens
    $tokens = getTokensFromCode($get_params['code']);
    
    // Decode ID token
    $id_token_parts = explode('.', $tokens['id_token']);
    $id_token_payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $id_token_parts[1])), true);
    
    // Save user info to session
    $_SESSION['user'] = [
        'oid' => $id_token_payload['oid'],
        'name' => $id_token_payload['name'],
        'username' => $id_token_payload['preferred_username']
    ];
    
    // Get user ID from database
    require_once __DIR__ . '/db.php';
    
    $stmt = $conn->prepare("SELECT id, role, username FROM users WHERE azure_oid = ?");
    if (!$stmt) {
        throw new Exception("Failed to prepare SELECT statement: " . $conn->error);
    }
    
    $stmt->bind_param("s", $id_token_payload['oid']);
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute SELECT: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        // User exists
        $_SESSION['user']['id'] = $user['id'];
        $_SESSION['user']['role'] = $user['role'];
        $_SESSION['user']['email'] = $user['username'];
        $stmt->close();
    } else {
        // Create new user
        $stmt->close();
        
        $stmt = $conn->prepare("INSERT INTO users (azure_oid, username, display_name, role) VALUES (?, ?, ?, 'Mitarbeiter')");
        if (!$stmt) {
            throw new Exception("Failed to prepare INSERT statement: " . $conn->error);
        }
        
        $email = $id_token_payload['preferred_username'];
        $stmt->bind_param("sss", 
            $id_token_payload['oid'], 
            $id_token_payload['preferred_username'],
            $id_token_payload['name']
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert user: " . $stmt->error);
        }
        
        $_SESSION['user']['id'] = $conn->insert_id;
        $_SESSION['user']['role'] = 'Mitarbeiter';
        $_SESSION['user']['email'] = $email;
        
        $stmt->close();
    }
    
    $conn->close();
    
    // Save tokens
    $_SESSION['auth_tokens'] = $tokens;
    
    // Security timestamps
    $_SESSION['created'] = time();
    $_SESSION['last_activity'] = time();
    
    // Clean up state
    unset($_SESSION['oauth2state']);
    
    // Regenerate session ID
    session_regenerate_id(true);
    
    // Write session and redirect
    session_write_close();
    
    header('Location: /');
    exit();
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "Exception: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    exit();
}
?>