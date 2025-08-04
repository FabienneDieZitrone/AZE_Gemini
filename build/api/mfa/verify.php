<?php
require_once '../config/database.php';
require_once '../middleware/auth.php';
require_once '../vendor/autoload.php';

use OTPHP\TOTP;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Verify authentication
$user = verifyToken();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$code = $input['code'] ?? '';
$useBackup = $input['useBackup'] ?? false;

if (empty($code)) {
    http_response_code(400);
    echo json_encode(['error' => 'Code is required']);
    exit;
}

try {
    $pdo = getDatabase();
    
    // Get user's MFA data
    $stmt = $pdo->prepare("
        SELECT mfa_secret, mfa_secret_iv, mfa_backup_codes, mfa_backup_codes_iv, mfa_enabled 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$user['id']]);
    $mfaData = $stmt->fetch();
    
    if (!$mfaData || (!$mfaData['mfa_secret'] && !$useBackup)) {
        http_response_code(400);
        echo json_encode(['error' => 'MFA not configured']);
        exit;
    }
    
    $isValid = false;
    
    if ($useBackup) {
        // Verify backup code
        $encryptedBackupCodes = base64_decode($mfaData['mfa_backup_codes']);
        $backupIv = base64_decode($mfaData['mfa_backup_codes_iv']);
        $backupCodesJson = openssl_decrypt($encryptedBackupCodes, 'AES-256-CBC', $_ENV['ENCRYPTION_KEY'], 0, $backupIv);
        $backupCodes = json_decode($backupCodesJson, true);
        
        if (in_array(strtoupper($code), $backupCodes)) {
            $isValid = true;
            
            // Remove used backup code
            $backupCodes = array_values(array_diff($backupCodes, [strtoupper($code)]));
            $newEncryptedBackupCodes = openssl_encrypt(json_encode($backupCodes), 'AES-256-CBC', $_ENV['ENCRYPTION_KEY'], 0, $newBackupIv = random_bytes(16));
            
            $stmt = $pdo->prepare("
                UPDATE users 
                SET mfa_backup_codes = ?, mfa_backup_codes_iv = ? 
                WHERE id = ?
            ");
            $stmt->execute([
                base64_encode($newEncryptedBackupCodes),
                base64_encode($newBackupIv),
                $user['id']
            ]);
        }
    } else {
        // Verify TOTP code
        $encryptedSecret = base64_decode($mfaData['mfa_secret']);
        $secretIv = base64_decode($mfaData['mfa_secret_iv']);
        $secret = openssl_decrypt($encryptedSecret, 'AES-256-CBC', $_ENV['ENCRYPTION_KEY'], 0, $secretIv);
        
        $totp = TOTP::create($secret);
        $isValid = $totp->verify($code);
    }
    
    if ($isValid && !$mfaData['mfa_enabled']) {
        // Enable MFA after successful verification during setup
        $stmt = $pdo->prepare("UPDATE users SET mfa_enabled = 1 WHERE id = ?");
        $stmt->execute([$user['id']]);
    }
    
    echo json_encode(['success' => $isValid]);
    
} catch (Exception $e) {
    error_log("MFA verification error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>