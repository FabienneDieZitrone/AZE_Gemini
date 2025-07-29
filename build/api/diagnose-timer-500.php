<?php
/**
 * Diagnose-Script für 500er Fehler beim Timer-Start
 * Zeigt genau was in der Session fehlt
 */

session_start();

header('Content-Type: text/html; charset=UTF-8');

echo "<h1>🔍 Timer 500 Error Diagnose</h1>";
echo "<pre style='background: #f0f0f0; padding: 10px;'>";

// 1. Session Status
echo "SESSION STATUS:\n";
echo "- Session ID: " . session_id() . "\n";
echo "- Session aktiv: " . (session_status() === PHP_SESSION_ACTIVE ? "✓ Ja" : "✗ Nein") . "\n\n";

// 2. User in Session?
echo "USER IN SESSION:\n";
if (isset($_SESSION['user'])) {
    echo "✓ User gefunden:\n";
    print_r($_SESSION['user']);
    echo "\n";
    
    // Check for ID
    if (!isset($_SESSION['user']['id'])) {
        echo "⚠️ PROBLEM: User ID fehlt in Session!\n";
        echo "   Dies verursacht den 500er Fehler beim Timer-Start.\n";
    }
} else {
    echo "✗ Kein User in Session!\n";
}

// 3. Database User lookup
echo "\nDATABASE CHECK:\n";
if (isset($_SESSION['user']['oid'])) {
    require_once __DIR__ . '/db.php';
    
    $oid = $_SESSION['user']['oid'];
    $stmt = $conn->prepare("SELECT id, username, name FROM users WHERE oid = ?");
    $stmt->bind_param("s", $oid);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        echo "✓ User in DB gefunden:\n";
        echo "  - ID: " . $user['id'] . "\n";
        echo "  - Username: " . $user['username'] . "\n";
        echo "  - Name: " . $user['name'] . "\n";
        
        echo "\n🔧 FIX VORSCHLAG:\n";
        echo "Die User ID " . $user['id'] . " muss in die Session!\n";
    } else {
        echo "✗ User nicht in DB gefunden!\n";
    }
    
    $stmt->close();
    $conn->close();
}

echo "\n</pre>";

// Quick Fix Link
if (isset($_SESSION['user']) && !isset($_SESSION['user']['id'])) {
    echo "<hr>";
    echo "<h2>🛠️ Quick Fix verfügbar!</h2>";
    echo "<p><a href='fix-session-userid.php' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;'>User ID in Session setzen</a></p>";
}
?>