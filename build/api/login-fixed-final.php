<?php
/**
 * Fixed Login Endpoint - Corrected session handling
 */

// CRITICAL: Set session parameters BEFORE any output or session_start()
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Lax');

// Set cookie params BEFORE session_start() - use empty domain
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '', // Let PHP determine the domain
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Basic headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://aze.mikropartner.de');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit();
}

try {
    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check session
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['oid'])) {
        http_response_code(401);
        echo json_encode(['message' => 'Unauthorized: No valid session found.']);
        exit();
    }
    
    // Get user data from session
    $azure_oid = $_SESSION['user']['oid'];
    $username = $_SESSION['user']['username'] ?? '';
    $display_name = $_SESSION['user']['name'] ?? '';
    
    // Load database config
    $envFile = __DIR__ . '/../.env.production';
    if (file_exists($envFile)) {
        $env = parse_ini_file($envFile);
        $db_host = $env['DB_HOST'] ?? 'vwp8374.webpack.hosteurope.de';
        $db_user = $env['DB_USER'] ?? 'db10454681-aze';
        $db_pass = $env['DB_PASS'] ?? 'Start.321';
        $db_name = $env['DB_NAME'] ?? 'db10454681-aze';
    } else {
        // Fallback values
        $db_host = 'vwp8374.webpack.hosteurope.de';
        $db_user = 'db10454681-aze';
        $db_pass = 'Start.321';
        $db_name = 'db10454681-aze';
    }
    
    // Database connection
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    // Find or create user
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
        // Create new user - NO created_at column!
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
    $conn->close();
    
    // Create response
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
    
    // Send response
    http_response_code(200);
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Login failed: ' . $e->getMessage(),
            'details' => [
                'originalError' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ],
            'recoveryStrategy' => null
        ]
    ]);
}
?>