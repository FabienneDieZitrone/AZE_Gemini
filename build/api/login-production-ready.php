<?php
/**
 * Production-Ready Login Endpoint
 * Handles Azure AD authentication callback and initializes user session
 */

// Error handling setup
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        echo json_encode([
            'success' => false,
            'message' => 'Internal server error',
            'debug' => [
                'type' => $error['type'],
                'message' => $error['message'],
                'file' => basename($error['file']),
                'line' => $error['line']
            ]
        ]);
    }
});

// CORS headers - set before any other output
$origin = $_SERVER['HTTP_ORIGIN'] ?? 'https://aze.mikropartner.de';
$allowed_origins = ['https://aze.mikropartner.de'];

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
}

header("Content-Type: application/json; charset=UTF-8");

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    http_response_code(204);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Initialize session with proper settings
if (session_status() === PHP_SESSION_NONE) {
    // Configure session cookie before starting
    $cookieParams = [
        'lifetime' => 0, // Browser session
        'path' => '/',
        'domain' => '', // Current domain
        'secure' => true, // HTTPS only
        'httponly' => true, // No JS access
        'samesite' => 'Lax' // CSRF protection, allows OAuth redirects
    ];
    
    session_set_cookie_params($cookieParams);
    
    // Start session
    if (!session_start()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to start session']);
        exit;
    }
}

// Check authentication
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['oid'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Not authenticated',
        'hint' => 'Please login through Azure AD first'
    ]);
    exit;
}

// Get user data from session
$sessionUser = $_SESSION['user'];

// Load database configuration
try {
    // Load config
    $configPath = __DIR__ . '/../config.php';
    if (!file_exists($configPath)) {
        throw new Exception('Configuration file not found');
    }
    require_once $configPath;
    
    // Get database credentials
    $config = Config::load();
    $db_host = Config::get('db.host');
    $db_name = Config::get('db.name');
    $db_user = Config::get('db.username');
    $db_pass = Config::get('db.password');
    
    if (!$db_host || !$db_name || !$db_user) {
        throw new Exception('Database configuration incomplete');
    }
    
} catch (Exception $e) {
    error_log('Login config error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Configuration error',
        'error' => $e->getMessage()
    ]);
    exit;
}

// Connect to database
try {
    $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($mysqli->connect_error) {
        throw new Exception('Database connection failed: ' . $mysqli->connect_error);
    }
    
    // Set charset
    $mysqli->set_charset('utf8mb4');
    
} catch (Exception $e) {
    error_log('Login database error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection error'
    ]);
    exit;
}

// Start transaction
$mysqli->begin_transaction();

try {
    // Find or create user
    $azure_oid = $sessionUser['oid'];
    $display_name = $sessionUser['name'] ?? $sessionUser['display_name'] ?? 'Unknown User';
    $email = $sessionUser['email'] ?? $sessionUser['preferred_username'] ?? $sessionUser['username'] ?? '';
    
    // Check if user exists
    $stmt = $mysqli->prepare("SELECT id, username, display_name, role FROM users WHERE azure_oid = ?");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $mysqli->error);
    }
    
    $stmt->bind_param('s', $azure_oid);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if ($user) {
        // User exists - update display name if changed
        $user_id = $user['id'];
        
        if ($user['display_name'] !== $display_name) {
            $updateStmt = $mysqli->prepare("UPDATE users SET display_name = ? WHERE id = ?");
            $updateStmt->bind_param('si', $display_name, $user_id);
            $updateStmt->execute();
            $updateStmt->close();
        }
        
    } else {
        // Create new user - without created_at column
        $insertStmt = $mysqli->prepare("INSERT INTO users (username, display_name, role, azure_oid) VALUES (?, ?, 'Mitarbeiter', ?)");
        if (!$insertStmt) {
            throw new Exception('Insert prepare failed: ' . $mysqli->error);
        }
        
        $insertStmt->bind_param('sss', $email, $display_name, $azure_oid);
        $insertStmt->execute();
        $user_id = $mysqli->insert_id;
        $insertStmt->close();
        
        // Create default master data
        $workdays = json_encode(['Mo', 'Di', 'Mi', 'Do', 'Fr']);
        $masterStmt = $mysqli->prepare("INSERT INTO master_data (user_id, weekly_hours, workdays, can_work_from_home) VALUES (?, 40.00, ?, 0)");
        $masterStmt->bind_param('is', $user_id, $workdays);
        $masterStmt->execute();
        $masterStmt->close();
    }
    
    // Commit transaction
    $mysqli->commit();
    
    // Prepare response
    $response = [
        'success' => true,
        'currentUser' => [
            'id' => $user_id,
            'name' => $display_name,
            'role' => $user['role'] ?? 'Mitarbeiter',
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
    
    // Store user info in session for future requests
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_role'] = $user['role'] ?? 'Mitarbeiter';
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $mysqli->rollback();
    error_log('Login transaction error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to process login',
        'error' => $e->getMessage()
    ]);
}

$mysqli->close();
?>