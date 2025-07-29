<?php
/**
 * Session Fix für fehlende User ID
 * Behebt den 500er Fehler beim Timer-Start
 */

session_start();

header('Content-Type: text/html; charset=UTF-8');

echo "<h1>🔧 Session User ID Fix</h1>";
echo "<pre style='background: #f0f0f0; padding: 10px;'>";

// Check if user is logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['oid'])) {
    echo "❌ Kein User in Session gefunden!\n";
    echo "Bitte erst einloggen.\n";
    echo "</pre>";
    exit;
}

// Get user from database
require_once __DIR__ . '/db.php';

$oid = $_SESSION['user']['oid'];
echo "Suche User mit OID: " . $oid . "\n";

$stmt = $conn->prepare("SELECT id, username, display_name, role FROM users WHERE azure_oid = ?");
$stmt->bind_param("s", $oid);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    echo "✓ User in Datenbank gefunden:\n";
    echo "  - ID: " . $user['id'] . "\n";
    echo "  - Username: " . $user['username'] . "\n";
    echo "  - Name: " . $user['display_name'] . "\n\n";
    
    // Update session with complete user data including ID
    $_SESSION['user']['id'] = $user['id'];
    $_SESSION['user']['email'] = $user['username'];
    $_SESSION['user']['role'] = $user['role'];
    
    echo "✅ SESSION UPDATED!\n";
    echo "Neue Session-Daten:\n";
    print_r($_SESSION['user']);
    
    echo "\n🎉 FIX ERFOLGREICH!\n";
    echo "Der Start-Button sollte jetzt funktionieren.\n";
    
    echo "</pre>";
    echo "<hr>";
    echo "<p><a href='/' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Zurück zur App</a></p>";
    
} else {
    echo "❌ User nicht in Datenbank gefunden!\n";
    echo "Der User muss erst in der Datenbank angelegt werden.\n";
    echo "</pre>";
}

$stmt->close();
$conn->close();
?>