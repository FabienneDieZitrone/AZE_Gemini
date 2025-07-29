<?php
/**
 * Migration: Make stop_time nullable for running timers
 * This fixes the critical Issue #29 where timers cannot be stopped
 */

session_start();
require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=UTF-8');

echo "<h1>🔧 Migration: stop_time to NULLABLE</h1>";
echo "<pre style='background: #f0f0f0; padding: 10px;'>";

// Check if user is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Admin') {
    echo "❌ Nur Admins können diese Migration ausführen!\n";
    echo "</pre>";
    exit;
}

echo "👤 Admin: " . $_SESSION['user']['name'] . "\n\n";

// 1. Show current schema
echo "📊 Aktuelles Schema:\n";
$result = $conn->query("SHOW CREATE TABLE time_entries");
$row = $result->fetch_row();
$current_schema = $row[1];
echo $current_schema . "\n\n";

// Check if stop_time is already nullable
if (strpos($current_schema, '`stop_time` time DEFAULT NULL') !== false) {
    echo "✅ stop_time ist bereits NULLABLE! Keine Migration nötig.\n";
    echo "</pre>";
    exit;
}

echo "⚠️ stop_time ist NOT NULL - Migration wird benötigt!\n\n";

// 2. Backup warning
echo "⚠️ WARNUNG: Diese Migration ändert die Datenbankstruktur!\n";
echo "Stellen Sie sicher, dass Sie ein Backup haben.\n\n";

// 3. Count affected rows
$result = $conn->query("SELECT COUNT(*) as count FROM time_entries WHERE stop_time = '00:00:00'");
$row = $result->fetch_assoc();
$affected = $row['count'];
echo "📊 Betroffene Einträge mit stop_time='00:00:00': " . $affected . "\n\n";

// 4. Perform migration
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    echo "🚀 Starte Migration...\n\n";
    
    // Step 1: Alter column to allow NULL
    echo "1. Ändere stop_time zu NULL...\n";
    $sql1 = "ALTER TABLE time_entries MODIFY COLUMN stop_time TIME DEFAULT NULL";
    if ($conn->query($sql1)) {
        echo "   ✅ Erfolgreich!\n";
    } else {
        echo "   ❌ Fehler: " . $conn->error . "\n";
        exit;
    }
    
    // Step 2: Update 00:00:00 to NULL
    echo "\n2. Update stop_time='00:00:00' zu NULL...\n";
    $sql2 = "UPDATE time_entries SET stop_time = NULL WHERE stop_time = '00:00:00'";
    if ($conn->query($sql2)) {
        $updated = $conn->affected_rows;
        echo "   ✅ " . $updated . " Einträge aktualisiert!\n";
    } else {
        echo "   ❌ Fehler: " . $conn->error . "\n";
    }
    
    echo "\n✅ MIGRATION ERFOLGREICH!\n";
    echo "\n📋 Neues Schema:\n";
    $result = $conn->query("SHOW CREATE TABLE time_entries");
    $row = $result->fetch_row();
    echo $row[1] . "\n";
    
} else {
    echo "❓ Migration bereit. Klicken Sie auf 'Migration ausführen' um fortzufahren.\n";
    echo "</pre>";
    echo "<hr>";
    echo "<p>";
    echo "<a href='?confirm=yes' style='padding: 10px 20px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px;' onclick='return confirm(\"Sind Sie sicher? Dies ändert die Datenbankstruktur!\")'>⚠️ Migration ausführen</a>";
    echo "<a href='/' style='padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px;'>Abbrechen</a>";
    echo "</p>";
    exit;
}

echo "</pre>";
echo "<hr>";
echo "<p><a href='/' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;'>✅ Zurück zur App</a></p>";

$conn->close();
?>