<?php
/**
 * Login Endpoint - Based on working health.php structure
 */

require_once __DIR__ . '/security-headers.php';
require_once __DIR__ . '/db-wrapper.php';
require_once __DIR__ . '/structured-logger.php';
require_once __DIR__ . '/auth_helpers.php';

// Initialize security
initializeSecurity(false); // We'll check auth manually
validateRequestMethod('POST');

// Initialize API response headers
header('Content-Type: application/json');

try {
    // Start session
    start_secure_session();
    
    // Verify session exists
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['oid'])) {
        http_response_code(401);
        echo json_encode(['message' => 'Unauthorized: No valid session found.']);
        exit();
    }
    
    // Get user from session
    $user_session = $_SESSION['user'];
    $azure_oid = $user_session['oid'];
    $username = $user_session['username'] ?? '';
    $display_name = $user_session['name'] ?? '';
    
    // Initialize database
    $mysqli = initDB();
    
    // Find or create user
    $stmt = $mysqli->prepare("SELECT id, username, display_name, role FROM users WHERE azure_oid = ?");
    $stmt->bind_param("s", $azure_oid);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        $current_user_id = $user['id'];
        $user_role = $user['role'];
        $user_display_name = $user['display_name'];
    } else {
        // Create new user
        $insert = $mysqli->prepare("INSERT INTO users (username, display_name, role, azure_oid) VALUES (?, ?, 'Mitarbeiter', ?)");
        $insert->bind_param("sss", $username, $display_name, $azure_oid);
        $insert->execute();
        
        $current_user_id = $mysqli->insert_id;
        $user_role = 'Mitarbeiter';
        $user_display_name = $display_name;
        
        $insert->close();
    }
    
    $stmt->close();
    
    // Build response
    $response = [
        'currentUser' => [
            'id' => $current_user_id,
            'name' => $user_display_name,
            'role' => $user_role,
            'azureOid' => $azure_oid
        ],
        'users' => [],
        'masterData' => new stdClass(),
        'timeEntries' => [],
        'approvalRequests' => [],
        'history' => [],
        'globalSettings' => [
            'overtimeThreshold' => 8.0,
            'changeReasons' => ['Vergessen', 'Fehler', 'Nachträglich'],
            'locations' => ['Büro', 'Home-Office', 'Außendienst']
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    logError('Login error', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    http_response_code(500);
    echo json_encode([
        'message' => 'Login failed',
        'error' => $e->getMessage()
    ]);
}