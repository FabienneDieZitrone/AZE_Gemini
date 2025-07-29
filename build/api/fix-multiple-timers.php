<?php
/**
 * Fix für mehrere laufende Timer
 * Stoppt alle alten Timer außer dem neuesten
 */

session_start();

header('Content-Type: text/html; charset=UTF-8');

echo "<h1>🛠️ Fix Multiple Running Timers</h1>";
echo "<pre style='background: #f0f0f0; padding: 10px;'>";

// Check if user is logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    echo "❌ Nicht eingeloggt!\n";
    echo "</pre>";
    exit;
}

require_once __DIR__ . '/db.php';

$user_id = $_SESSION['user']['id'];
echo "User ID: " . $user_id . "\n";
echo "User: " . $_SESSION['user']['name'] . "\n\n";

// 1. Find all running timers for this user
echo "🔍 Suche laufende Timer...\n";
$stmt = $conn->prepare("
    SELECT id, date, start_time, created_at 
    FROM time_entries 
    WHERE user_id = ? AND stop_time IS NULL 
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$running_timers = [];
while ($row = $result->fetch_assoc()) {
    $running_timers[] = $row;
}
$stmt->close();

echo "Gefunden: " . count($running_timers) . " laufende Timer\n\n";

if (count($running_timers) > 1) {
    echo "⚠️ PROBLEM: Mehrere Timer laufen gleichzeitig!\n\n";
    
    // Show all running timers
    foreach ($running_timers as $index => $timer) {
        echo ($index + 1) . ". Timer ID " . $timer['id'] . ":\n";
        echo "   - Datum: " . $timer['date'] . "\n";
        echo "   - Start: " . $timer['start_time'] . "\n";
        echo "   - Erstellt: " . $timer['created_at'] . "\n";
        echo ($index === 0 ? "   ✅ BEHALTEN (neuester)\n" : "   ❌ WIRD GESTOPPT\n");
        echo "\n";
    }
    
    // Stop all except the newest (first in list)
    echo "🔧 Stoppe alte Timer...\n";
    $current_time = date('H:i:s');
    
    for ($i = 1; $i < count($running_timers); $i++) {
        $timer_id = $running_timers[$i]['id'];
        $stmt = $conn->prepare("
            UPDATE time_entries 
            SET stop_time = ?, updated_by = 'System Fix', updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->bind_param("si", $current_time, $timer_id);
        
        if ($stmt->execute()) {
            echo "✅ Timer ID " . $timer_id . " gestoppt\n";
        } else {
            echo "❌ Fehler beim Stoppen von Timer ID " . $timer_id . "\n";
        }
        $stmt->close();
    }
    
    echo "\n✅ FERTIG! Nur noch 1 Timer läuft.\n";
    
} elseif (count($running_timers) === 1) {
    echo "✅ Alles OK: Nur 1 Timer läuft.\n";
    echo "Timer ID: " . $running_timers[0]['id'] . "\n";
} else {
    echo "ℹ️ Keine laufenden Timer gefunden.\n";
}

$conn->close();

echo "</pre>";
echo "<hr>";
echo "<p><a href='/' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Zurück zur App</a></p>";
?>