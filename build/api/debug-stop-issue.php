<?php
/**
 * Debug Tool für Issue #29 - Timer Stop Problem
 * Zeigt den aktuellen DB-Status und das Problem
 */

session_start();
require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=UTF-8');

echo "<h1>🔍 Debug: Timer Stop Issue #29</h1>";
echo "<pre style='background: #f0f0f0; padding: 10px;'>";

// 1. Show time_entries schema
echo "📊 DATENBANK SCHEMA:\n";
$result = $conn->query("DESCRIBE time_entries");
echo "Spalte        | Typ          | Null | Default\n";
echo "------------- | ------------ | ---- | -------\n";
while ($row = $result->fetch_assoc()) {
    if (in_array($row['Field'], ['stop_time', 'start_time'])) {
        printf("%-13s | %-12s | %-4s | %s\n", 
            $row['Field'], 
            $row['Type'], 
            $row['Null'], 
            $row['Default'] ?? 'NULL'
        );
    }
}

echo "\n⚠️ PROBLEM: stop_time ist 'NOT NULL' - kann kein NULL speichern!\n";
echo "Der Code versucht NULL für laufende Timer zu verwenden.\n\n";

// 2. Check for timers with 00:00:00
echo "📊 TIMER MIT stop_time = '00:00:00':\n";
$result = $conn->query("SELECT id, user_id, username, date, start_time, stop_time, created_at 
                        FROM time_entries 
                        WHERE stop_time = '00:00:00' 
                        ORDER BY created_at DESC 
                        LIMIT 10");

if ($result->num_rows > 0) {
    echo "ID  | User | Datum      | Start    | Stop     | Erstellt\n";
    echo "----|------|------------|----------|----------|---------------------\n";
    while ($row = $result->fetch_assoc()) {
        printf("%-3s | %-4s | %-10s | %-8s | %-8s | %s\n",
            $row['id'],
            $row['user_id'],
            $row['date'],
            $row['start_time'],
            $row['stop_time'],
            $row['created_at']
        );
    }
    echo "\n⚠️ Diese Timer haben stop_time='00:00:00' - vermutlich laufende Timer!\n";
} else {
    echo "Keine Timer mit stop_time='00:00:00' gefunden.\n";
}

// 3. Check current user's timers
if (isset($_SESSION['user']['id'])) {
    $user_id = $_SESSION['user']['id'];
    echo "\n📊 IHRE LETZTEN 5 TIMER (User ID: $user_id):\n";
    
    $stmt = $conn->prepare("SELECT id, date, start_time, stop_time, created_at 
                           FROM time_entries 
                           WHERE user_id = ? 
                           ORDER BY created_at DESC 
                           LIMIT 5");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "ID  | Datum      | Start    | Stop     | Status\n";
        echo "----|------------|----------|----------|--------\n";
        while ($row = $result->fetch_assoc()) {
            $status = ($row['stop_time'] === '00:00:00') ? '🟢 LÄUFT' : '⏹️ Gestoppt';
            printf("%-3s | %-10s | %-8s | %-8s | %s\n",
                $row['id'],
                $row['date'],
                $row['start_time'],
                $row['stop_time'],
                $status
            );
        }
    }
    $stmt->close();
}

// 4. SQL Debug Info
echo "\n📋 DEBUG INFO:\n";
echo "- Code erwartet: WHERE stop_time IS NULL\n";
echo "- Aber stop_time kann nie NULL sein (NOT NULL constraint)\n";
echo "- Vermutlich werden '00:00:00' als laufende Timer gespeichert\n";

echo "\n🔧 LÖSUNGSOPTIONEN:\n";
echo "1. MIGRATION: stop_time zu NULL ändern → /api/migrate-stop-time-nullable.php\n";
echo "2. QUICK-FIX: Code anpassen für '00:00:00' → /api/time-entries-quickfix.php\n";

echo "</pre>";

$conn->close();
?>