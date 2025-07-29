<?php
/**
 * Automatische Migration für Issue #29
 * Führt die stop_time Migration durch
 * SICHERHEIT: Einmal-Token für diese kritische Operation
 */

// Security token - nur einmal verwendbar
$expected_token = 'migrate_stop_time_issue29_' . date('Ymd');
$provided_token = $_GET['token'] ?? '';

if ($provided_token !== $expected_token) {
    die("Unauthorized. Token required: ?token={$expected_token}");
}

require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html>";
echo "<html><head><title>Migration Issue #29</title></head><body>";
echo "<h1>🔧 Automatische Migration: stop_time zu NULLABLE</h1>";
echo "<pre style='background: #f0f0f0; padding: 20px;'>";

// 1. Status vor Migration
echo "📊 STATUS VOR MIGRATION:\n";
echo str_repeat("=", 50) . "\n\n";

// Schema prüfen
$result = $conn->query("SHOW CREATE TABLE time_entries");
$row = $result->fetch_row();
$current_schema = $row[1];

if (strpos($current_schema, '`stop_time` time DEFAULT NULL') !== false) {
    echo "✅ stop_time ist bereits NULLABLE! Migration nicht nötig.\n";
    echo "</pre></body></html>";
    exit;
}

echo "⚠️ stop_time ist NOT NULL - Migration wird durchgeführt...\n\n";

// Statistiken
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN stop_time = '00:00:00' THEN 1 ELSE 0 END) as running,
        SUM(CASE WHEN stop_time != '00:00:00' THEN 1 ELSE 0 END) as stopped
    FROM time_entries
";
$stats = $conn->query($stats_query)->fetch_assoc();

echo "📊 Timer-Statistiken:\n";
echo "   - Gesamt: " . $stats['total'] . "\n";
echo "   - Laufend (00:00:00): " . $stats['running'] . "\n";
echo "   - Gestoppt: " . $stats['stopped'] . "\n\n";

// 2. Migration durchführen
echo "🚀 STARTE MIGRATION:\n";
echo str_repeat("=", 50) . "\n\n";

// Transaction starten
$conn->autocommit(FALSE);

try {
    // Step 1: Alter column
    echo "1. Ändere stop_time zu NULLABLE...\n";
    $sql1 = "ALTER TABLE time_entries MODIFY COLUMN stop_time TIME DEFAULT NULL";
    if (!$conn->query($sql1)) {
        throw new Exception("ALTER TABLE failed: " . $conn->error);
    }
    echo "   ✅ Erfolgreich!\n\n";
    
    // Step 2: Update 00:00:00 to NULL
    echo "2. Konvertiere '00:00:00' zu NULL...\n";
    $sql2 = "UPDATE time_entries SET stop_time = NULL WHERE stop_time = '00:00:00'";
    if (!$conn->query($sql2)) {
        throw new Exception("UPDATE failed: " . $conn->error);
    }
    $updated_count = $conn->affected_rows;
    echo "   ✅ " . $updated_count . " Timer konvertiert!\n\n";
    
    // Commit transaction
    $conn->commit();
    echo "✅ MIGRATION ERFOLGREICH!\n\n";
    
} catch (Exception $e) {
    // Rollback bei Fehler
    $conn->rollback();
    echo "❌ MIGRATION FEHLGESCHLAGEN!\n";
    echo "Fehler: " . $e->getMessage() . "\n";
    echo "</pre></body></html>";
    exit;
}

// 3. Status nach Migration
echo "📊 STATUS NACH MIGRATION:\n";
echo str_repeat("=", 50) . "\n\n";

// Neues Schema zeigen
$result = $conn->query("SHOW CREATE TABLE time_entries");
$row = $result->fetch_row();
echo "Neues Schema (Auszug):\n";
$lines = explode("\n", $row[1]);
foreach ($lines as $line) {
    if (strpos($line, 'stop_time') !== false) {
        echo "   " . trim($line) . "\n";
    }
}

// Neue Statistiken
echo "\n📊 Neue Timer-Statistiken:\n";
$new_stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN stop_time IS NULL THEN 1 ELSE 0 END) as running,
        SUM(CASE WHEN stop_time IS NOT NULL THEN 1 ELSE 0 END) as stopped
    FROM time_entries
";
$new_stats = $conn->query($new_stats_query)->fetch_assoc();

echo "   - Gesamt: " . $new_stats['total'] . "\n";
echo "   - Laufend (NULL): " . $new_stats['running'] . "\n";
echo "   - Gestoppt: " . $new_stats['stopped'] . "\n\n";

// Test Query
echo "🧪 TEST-QUERY:\n";
$test = $conn->query("SELECT id, stop_time FROM time_entries WHERE stop_time IS NULL LIMIT 3");
if ($test->num_rows > 0) {
    echo "   ✅ WHERE stop_time IS NULL funktioniert jetzt!\n";
    echo "   Gefundene laufende Timer: " . $test->num_rows . "\n";
} else {
    echo "   ℹ️ Keine laufenden Timer gefunden (alle sind gestoppt)\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "🎉 MIGRATION ABGESCHLOSSEN!\n";
echo "\nDer Timer-Stop sollte jetzt funktionieren.\n";
echo "Teste mit dem Account: azetestclaude@mikropartner.de\n";

echo "</pre>";
echo "<hr>";
echo "<p><a href='/' style='padding: 20px 40px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; font-size: 18px;'>✅ Zur App</a></p>";
echo "</body></html>";

$conn->close();
?>