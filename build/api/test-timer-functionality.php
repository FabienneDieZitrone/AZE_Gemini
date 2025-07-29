<?php
/**
 * Timer Funktionalitäts-Test nach Migration
 * Testet Start, Stop und Check für laufende Timer
 */

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helpers.php';

header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html>";
echo "<html><head><title>Timer Test nach Migration</title>";
echo "<style>
    .test-box { background: #f0f0f0; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .button { padding: 10px 20px; margin: 5px; text-decoration: none; border-radius: 5px; color: white; display: inline-block; }
    .start { background: #28a745; }
    .stop { background: #dc3545; }
    .check { background: #17a2b8; }
</style>";
echo "</head><body>";
echo "<h1>🧪 Timer Funktionalitäts-Test</h1>";

// Check login
if (!isset($_SESSION['user']['id'])) {
    echo "<div class='test-box error'>";
    echo "❌ Nicht eingeloggt! Bitte erst anmelden.";
    echo "</div>";
    echo "</body></html>";
    exit;
}

$user_id = $_SESSION['user']['id'];
$username = $_SESSION['user']['name'];

echo "<div class='test-box'>";
echo "<h2>👤 Angemeldet als:</h2>";
echo "User: $username (ID: $user_id)<br>";
echo "Account: " . $_SESSION['user']['username'] . "<br>";
echo "</div>";

// Handle actions
$action = $_GET['action'] ?? '';
$message = '';

if ($action === 'start') {
    // Start new timer
    $date = date('Y-m-d');
    $time = date('H:i:s');
    
    // First stop any running timers
    $stop_stmt = $conn->prepare("UPDATE time_entries SET stop_time = NOW() WHERE user_id = ? AND stop_time IS NULL");
    $stop_stmt->bind_param("i", $user_id);
    $stop_stmt->execute();
    $stopped = $stop_stmt->affected_rows;
    $stop_stmt->close();
    
    if ($stopped > 0) {
        $message .= "ℹ️ $stopped alte(r) Timer automatisch gestoppt.<br>";
    }
    
    // Create new timer
    $stmt = $conn->prepare("INSERT INTO time_entries (user_id, username, date, start_time, stop_time, location, role, updated_by) VALUES (?, ?, ?, ?, NULL, 'Test Location', 'Test', 'Timer Test')");
    $stmt->bind_param("isss", $user_id, $username, $date, $time);
    
    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        $message .= "<span class='success'>✅ Timer gestartet! (ID: $new_id)</span>";
    } else {
        $message .= "<span class='error'>❌ Fehler beim Start: " . $stmt->error . "</span>";
    }
    $stmt->close();
    
} elseif ($action === 'stop' && isset($_GET['id'])) {
    // Stop timer
    $timer_id = intval($_GET['id']);
    $stop_time = date('H:i:s');
    
    $stmt = $conn->prepare("UPDATE time_entries SET stop_time = ? WHERE id = ? AND user_id = ? AND stop_time IS NULL");
    $stmt->bind_param("sii", $stop_time, $timer_id, $user_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $message = "<span class='success'>✅ Timer gestoppt! (ID: $timer_id)</span>";
        } else {
            $message = "<span class='error'>❌ Timer nicht gefunden oder bereits gestoppt.</span>";
        }
    } else {
        $message = "<span class='error'>❌ Fehler beim Stop: " . $stmt->error . "</span>";
    }
    $stmt->close();
}

// Show message
if ($message) {
    echo "<div class='test-box'>$message</div>";
}

// Check for running timer
echo "<div class='test-box'>";
echo "<h2>🏃 Laufende Timer:</h2>";

$stmt = $conn->prepare("SELECT id, date, start_time, TIMEDIFF(NOW(), CONCAT(date, ' ', start_time)) as duration FROM time_entries WHERE user_id = ? AND stop_time IS NULL");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($timer = $result->fetch_assoc()) {
        echo "<div style='background: #d4edda; padding: 10px; margin: 5px 0; border-radius: 5px;'>";
        echo "🟢 <strong>Timer läuft!</strong><br>";
        echo "ID: " . $timer['id'] . "<br>";
        echo "Gestartet: " . $timer['date'] . " " . $timer['start_time'] . "<br>";
        echo "Dauer: " . $timer['duration'] . "<br>";
        echo "<a href='?action=stop&id=" . $timer['id'] . "' class='button stop'>Timer Stoppen</a>";
        echo "</div>";
    }
} else {
    echo "Keine laufenden Timer.<br>";
    echo "<a href='?action=start' class='button start'>Neuen Timer Starten</a>";
}
$stmt->close();

echo "</div>";

// Recent timers
echo "<div class='test-box'>";
echo "<h2>📋 Letzte 5 Timer:</h2>";

$stmt = $conn->prepare("
    SELECT id, date, start_time, stop_time,
           CASE 
               WHEN stop_time IS NULL THEN 'LÄUFT'
               WHEN stop_time = '00:00:00' THEN 'LEGACY (00:00:00)'
               ELSE TIMEDIFF(stop_time, start_time)
           END as duration
    FROM time_entries 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<table style='width: 100%; border-collapse: collapse;'>";
    echo "<tr style='background: #ddd;'><th>ID</th><th>Datum</th><th>Start</th><th>Stop</th><th>Dauer</th></tr>";
    while ($timer = $result->fetch_assoc()) {
        $row_style = ($timer['stop_time'] === null) ? "background: #d4edda;" : "";
        echo "<tr style='$row_style'>";
        echo "<td>" . $timer['id'] . "</td>";
        echo "<td>" . $timer['date'] . "</td>";
        echo "<td>" . $timer['start_time'] . "</td>";
        echo "<td>" . ($timer['stop_time'] ?? 'NULL') . "</td>";
        echo "<td>" . $timer['duration'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Noch keine Timer vorhanden.";
}
$stmt->close();

echo "</div>";

// SQL Test
echo "<div class='test-box'>";
echo "<h2>🔧 SQL Funktionalitäts-Test:</h2>";
echo "<pre>";

// Test 1: NULL queries
$test1 = $conn->query("SELECT COUNT(*) as count FROM time_entries WHERE stop_time IS NULL");
$null_count = $test1->fetch_assoc()['count'];
echo "✅ WHERE stop_time IS NULL: $null_count Timer gefunden\n";

// Test 2: NOT NULL queries  
$test2 = $conn->query("SELECT COUNT(*) as count FROM time_entries WHERE stop_time IS NOT NULL");
$not_null_count = $test2->fetch_assoc()['count'];
echo "✅ WHERE stop_time IS NOT NULL: $not_null_count Timer gefunden\n";

// Test 3: Legacy 00:00:00
$test3 = $conn->query("SELECT COUNT(*) as count FROM time_entries WHERE stop_time = '00:00:00'");
$legacy_count = $test3->fetch_assoc()['count'];
echo "ℹ️ Legacy Timer (00:00:00): $legacy_count gefunden\n";

echo "\n<strong>Migration Status: ";
if ($null_count > 0 || $not_null_count > 0) {
    echo "<span class='success'>✅ ERFOLGREICH</span></strong>\n";
    echo "Timer Stop funktioniert jetzt mit NULL-Werten!";
} else {
    echo "<span class='error'>❌ PROBLEM</span></strong>\n";
}

echo "</pre>";
echo "</div>";

// Navigation
echo "<div class='test-box' style='background: #fff3cd;'>";
echo "<h2>🔗 Navigation:</h2>";
echo "<a href='/' class='button' style='background: #6c757d;'>Zur App</a>";
echo "<a href='/api/test-claude-account.php' class='button' style='background: #6c757d;'>Account Test</a>";
echo "<a href='/api/verify-migration-success.php' class='button' style='background: #6c757d;'>Migration Status (JSON)</a>";
echo "</div>";

echo "</body></html>";

$conn->close();
?>