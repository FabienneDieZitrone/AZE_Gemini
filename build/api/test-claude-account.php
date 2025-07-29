<?php
/**
 * Test-Script für den Claude Test Account
 * Prüft ob der Account funktioniert und Timer korrekt arbeiten
 */

session_start();
require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html>";
echo "<html><head><title>Claude Account Test</title></head><body>";
echo "<h1>🧪 Test für azetestclaude@mikropartner.de</h1>";

// Account Info
echo "<div style='background: #e3f2fd; padding: 20px; margin: 20px 0; border-radius: 8px;'>";
echo "<h2>📧 Test-Account Details:</h2>";
echo "<p><strong>Email:</strong> azetestclaude@mikropartner.de<br>";
echo "<strong>Passwort:</strong> a1b2c3d4<br>";
echo "<strong>Zweck:</strong> Automatisierte Tests für Claude</p>";
echo "</div>";

// Session Check
echo "<div style='background: #f0f0f0; padding: 20px; margin: 20px 0; border-radius: 8px;'>";
echo "<h2>🔐 Session Status:</h2>";
echo "<pre>";

if (isset($_SESSION['user'])) {
    echo "✅ Eingeloggt als: " . $_SESSION['user']['name'] . "\n";
    echo "   User ID: " . ($_SESSION['user']['id'] ?? 'FEHLT!') . "\n";
    echo "   OID: " . $_SESSION['user']['oid'] . "\n";
    echo "   Role: " . ($_SESSION['user']['role'] ?? 'N/A') . "\n";
    
    // Check if test user
    if (strpos($_SESSION['user']['username'], 'azetestclaude') !== false) {
        echo "\n✅ Dies ist der Claude Test Account!\n";
    }
} else {
    echo "❌ Nicht eingeloggt\n";
    echo "\nBitte zuerst einloggen:\n";
    echo "1. Gehe zu https://aze.mikropartner.de\n";
    echo "2. Klicke 'Mit Microsoft anmelden'\n";
    echo "3. Nutze: azetestclaude@mikropartner.de / a1b2c3d4\n";
}
echo "</pre>";
echo "</div>";

// Database Check
if (isset($_SESSION['user']['oid'])) {
    echo "<div style='background: #f0f0f0; padding: 20px; margin: 20px 0; border-radius: 8px;'>";
    echo "<h2>💾 Datenbank Status:</h2>";
    echo "<pre>";
    
    $oid = $_SESSION['user']['oid'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE azure_oid = ?");
    $stmt->bind_param("s", $oid);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        echo "✅ User in Datenbank gefunden:\n";
        echo "   ID: " . $user['id'] . "\n";
        echo "   Username: " . $user['username'] . "\n";
        echo "   Name: " . $user['display_name'] . "\n";
        echo "   Role: " . $user['role'] . "\n";
        echo "   Erstellt: " . $user['created_at'] . "\n";
        
        // Timer Check
        echo "\n📊 Timer für diesen User:\n";
        $timer_stmt = $conn->prepare("
            SELECT id, date, start_time, stop_time,
                   CASE WHEN stop_time IS NULL THEN '🟢 LÄUFT' ELSE '⏹️ Gestoppt' END as status
            FROM time_entries 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $timer_stmt->bind_param("i", $user['id']);
        $timer_stmt->execute();
        $timer_result = $timer_stmt->get_result();
        
        if ($timer_result->num_rows > 0) {
            while ($timer = $timer_result->fetch_assoc()) {
                printf("   Timer %d: %s %s - %s %s\n", 
                    $timer['id'],
                    $timer['date'],
                    $timer['start_time'],
                    $timer['stop_time'] ?? 'läuft',
                    $timer['status']
                );
            }
        } else {
            echo "   Noch keine Timer vorhanden\n";
        }
        $timer_stmt->close();
        
    } else {
        echo "❌ User nicht in Datenbank gefunden\n";
        echo "   Der User wird beim ersten Login automatisch angelegt.\n";
    }
    $stmt->close();
    echo "</pre>";
    echo "</div>";
}

// Migration Status
echo "<div style='background: #e8f5e9; padding: 20px; margin: 20px 0; border-radius: 8px;'>";
echo "<h2>🔧 Migration Status:</h2>";
echo "<pre>";

$result = $conn->query("DESCRIBE time_entries");
while ($row = $result->fetch_assoc()) {
    if ($row['Field'] === 'stop_time') {
        if ($row['Null'] === 'YES') {
            echo "✅ Migration erfolgreich! stop_time ist NULLABLE\n";
            echo "   Timer Stop sollte jetzt funktionieren!\n";
        } else {
            echo "❌ Migration ausstehend! stop_time ist noch NOT NULL\n";
        }
        break;
    }
}
echo "</pre>";
echo "</div>";

// Test Actions
if (isset($_SESSION['user'])) {
    echo "<div style='background: #fff3cd; padding: 20px; margin: 20px 0; border-radius: 8px;'>";
    echo "<h2>🧪 Test-Aktionen:</h2>";
    echo "<p>";
    echo "<a href='/' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Zur App</a>";
    echo "<a href='/api/debug-session-timer.php' style='padding: 10px 20px; background: #17a2b8; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Timer Debug</a>";
    echo "<a href='/api/auth-logout.php' style='padding: 10px 20px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px;'>Logout</a>";
    echo "</p>";
    echo "</div>";
}

echo "</body></html>";

$conn->close();
?>