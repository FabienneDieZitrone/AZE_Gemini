<?php
/**
 * Fixed Login Endpoint
 * Based on working health.php structure
 */

// Security
define('API_GUARD', true);

// Include required files - TEMPORARILY SKIP security-headers.php for debugging
// require_once __DIR__ . '/security-headers.php';
require_once __DIR__ . '/auth_helpers.php';

// Initialize API
initialize_api();

// Check method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(405, ['message' => 'Method Not Allowed']);
    exit();
}

// Set JSON response header
header('Content-Type: application/json');

try {
    // Start session
    start_secure_session();
    
    // Check if user is in session
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['oid'])) {
        send_response(401, ['message' => 'Unauthorized: No valid session found.']);
        exit();
    }
    
    // Get user from session
    $user_session = $_SESSION['user'];
    $azure_oid = $user_session['oid'];
    $username = $user_session['username'] ?? '';
    $display_name = $user_session['name'] ?? '';
    
    // Database connection
    require_once __DIR__ . '/db.php';
    
    // Find user in database
    $stmt = $conn->prepare("SELECT id, username, display_name, role FROM users WHERE azure_oid = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("s", $azure_oid);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        $current_user_id = $user['id'];
        $user_role = $user['role'];
        $user_display_name = $user['display_name'];
    } else {
        // Create new user (without created_at)
        $insert = $conn->prepare("INSERT INTO users (username, display_name, role, azure_oid) VALUES (?, ?, 'Mitarbeiter', ?)");
        if (!$insert) {
            throw new Exception("Insert prepare failed: " . $conn->error);
        }
        
        $insert->bind_param("sss", $username, $display_name, $azure_oid);
        if (!$insert->execute()) {
            throw new Exception("Insert execute failed: " . $insert->error);
        }
        
        $current_user_id = $conn->insert_id;
        $user_role = 'Mitarbeiter';
        $user_display_name = $display_name;
        
        $insert->close();
    }
    
    $stmt->close();
    
    // Create minimal response
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
    
    // Send successful response
    send_response(200, $response);
    
} catch (Exception $e) {
    error_log("Login-fixed error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    // Detailed error response for debugging
    $errorData = [
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Login failed',
            'details' => [
                'originalError' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ],
            'recoveryStrategy' => null
        ]
    ];
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode($errorData);
    exit();
}

$conn->close();
?>