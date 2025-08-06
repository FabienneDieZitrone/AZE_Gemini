<?php
/**
 * MFA Setup API Endpoint
 * Issue #115: Multi-Factor Authentication Implementation
 */

require_once '../auth_helpers.php';
require_once '../db.php';
require_once '../error-handler.php';
require_once '../csrf-protection.php';
require_once '../structured-logger.php';
require_once '../rate-limiting.php';
require_once '../csrf-middleware.php';

// Define API guard constant
define('API_GUARD', true);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Apply rate limiting
checkRateLimit('mfa');

// OPTIONS Request für CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Nur POST erlaubt
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(405, ['message' => 'Method not allowed']);
    exit();
}

// Session und Authentifizierung prüfen
session_start();
$session_user = check_session();
if (!$session_user) {
    send_response(401, ['message' => 'Nicht authentifiziert']);
    exit();
}

// Enhanced CSRF protection
if (!validateCsrfProtection()) {
    // Error response already sent by validateCsrfProtection()
    exit();
}

// Request-Body lesen
$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['userId'] ?? null;

// Validierung
if (!$user_id || $user_id != $session_user['id']) {
    send_response(403, ['message' => 'Nicht autorisiert']);
    exit();
}

// Datenbankverbindung
$conn = get_db_connection();

try {
    // Prüfe ob MFA bereits aktiviert ist
    $check_stmt = $conn->prepare("SELECT mfa_secret FROM users WHERE id = ?");
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user && !empty($user['mfa_secret'])) {
        send_response(400, ['message' => 'MFA ist bereits aktiviert']);
        exit();
    }
    
    // Generiere Secret für TOTP
    $secret = generate_secret();
    
    // Generiere Backup-Codes
    $backup_codes = generate_backup_codes();
    
    // Speichere temporär in Session (wird erst nach Verifizierung persistent)
    $_SESSION['mfa_setup'] = [
        'secret' => $secret,
        'backup_codes' => $backup_codes,
        'user_id' => $user_id
    ];
    
    // Erstelle QR-Code URL
    $issuer = 'AZE Zeiterfassung';
    $username = $session_user['username'];
    $qr_code_url = sprintf(
        'otpauth://totp/%s:%s?secret=%s&issuer=%s',
        urlencode($issuer),
        urlencode($username),
        $secret,
        urlencode($issuer)
    );
    
    // Log MFA setup attempt
    StructuredLogger::log('info', 'MFA setup initiated', [
        'user_id' => $user_id,
        'username' => $username
    ]);
    
    send_response(200, [
        'qrCodeUrl' => $qr_code_url,
        'secret' => $secret,
        'backupCodes' => $backup_codes
    ]);
    
} catch (Exception $e) {
    StructuredLogger::log('error', 'MFA setup failed', [
        'user_id' => $user_id,
        'error' => $e->getMessage()
    ]);
    send_response(500, ['message' => 'Fehler beim MFA-Setup']);
} finally {
    $conn->close();
}

/**
 * Generiert ein sicheres Secret für TOTP
 */
function generate_secret($length = 16) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // Base32 alphabet
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $chars[random_int(0, 31)];
    }
    return $secret;
}

/**
 * Generiert sichere Backup-Codes
 */
function generate_backup_codes($count = 8) {
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $codes[] = sprintf('%06d-%06d', random_int(100000, 999999), random_int(100000, 999999));
    }
    return $codes;
}

function send_response($status_code, $data) {
    http_response_code($status_code);
    echo json_encode($data);
    exit();
}