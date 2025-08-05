<?php
/**
 * Minimal login endpoint for debugging
 */

// Basic error handling
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// CORS headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://aze.mikropartner.de');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Step 1: Session
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
    
    // Step 2: Check session
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['oid'])) {
        http_response_code(401);
        echo json_encode(['message' => 'Unauthorized: No valid session found.']);
        exit();
    }
    
    $user_session = $_SESSION['user'];
    $azure_oid = $user_session['oid'];
    
    // Step 3: Database
    require_once __DIR__ . '/config.php';
    $db_config = Config::getDbConfig();
    
    $conn = new mysqli(
        $db_config['host'],
        $db_config['username'],
        $db_config['password'],
        $db_config['database']
    );
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed");
    }
    
    // Step 4: Find/Create user
    $stmt = $conn->prepare("SELECT id, role FROM users WHERE azure_oid = ?");
    $stmt->bind_param("s", $azure_oid);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        $current_user_id = $user['id'];
        $user_role = $user['role'];
    } else {
        // Create new user
        $username = $user_session['username'] ?? '';
        $display_name = $user_session['name'] ?? '';
        
        $insert = $conn->prepare("INSERT INTO users (username, display_name, role, azure_oid) VALUES (?, ?, 'Mitarbeiter', ?)");
        $insert->bind_param("sss", $username, $display_name, $azure_oid);
        $insert->execute();
        
        $current_user_id = $conn->insert_id;
        $user_role = 'Mitarbeiter';
        
        $insert->close();
    }
    
    $stmt->close();
    
    // Step 5: Return minimal success response
    $response = [
        'currentUser' => [
            'id' => $current_user_id,
            'name' => $user_session['name'] ?? '',
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
    
    $conn->close();
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Login-minimal error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => $e->getMessage(),
            'details' => [
                'originalError' => $e->getMessage()
            ],
            'recoveryStrategy' => null
        ]
    ]);
}
?>