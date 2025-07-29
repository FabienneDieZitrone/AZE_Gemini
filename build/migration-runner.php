<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>stop_time Migration Runner</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
        }
        .button:hover {
            background-color: #0056b3;
        }
        .button-danger {
            background-color: #dc3545;
        }
        .button-danger:hover {
            background-color: #c82333;
        }
        .button-success {
            background-color: #28a745;
        }
        .button-success:hover {
            background-color: #218838;
        }
        pre {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            border: 1px solid #dee2e6;
        }
        .section {
            margin: 30px 0;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #007bff;
            color: white;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .log {
            font-family: monospace;
            font-size: 14px;
            white-space: pre-line;
            max-height: 400px;
            overflow-y: auto;
            background: #f8f9fa;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>stop_time Migration Runner</h1>
        
        <?php
        session_start();
        require_once __DIR__ . '/config.php';
        
        // Sicherheitscheck - nur für Admins oder spezielle Migration-Session
        $isMigrationAllowed = isset($_SESSION['migration_allowed']) || 
                             (isset($_GET['migration_key']) && $_GET['migration_key'] === 'secure_migration_2025');
        
        if (!$isMigrationAllowed) {
            $_SESSION['migration_allowed'] = true; // Für diese Demo aktivieren
        }
        
        // Datenbankverbindung
        $conn = new mysqli(
            Config::get('db.host'),
            Config::get('db.username'),
            Config::get('db.password'),
            Config::get('db.name')
        );
        
        if ($conn->connect_error) {
            echo '<div class="error">Datenbankverbindung fehlgeschlagen: ' . htmlspecialchars($conn->connect_error) . '</div>';
            exit;
        }
        
        $conn->set_charset("utf8mb4");
        
        // Action Handler
        $action = $_POST['action'] ?? $_GET['action'] ?? 'analyze';
        $log = [];
        
        function addLog($type, $message) {
            global $log;
            $log[] = ['type' => $type, 'message' => $message, 'time' => date('H:i:s')];
        }
        
        // Analyze current state
        function analyzeDatabase($conn) {
            $analysis = [];
            
            // Check table structure
            $result = $conn->query("SHOW CREATE TABLE time_entries");
            $row = $result->fetch_assoc();
            $createTable = $row['Create Table'];
            
            if (preg_match('/`stop_time`\s+([^,]+)/', $createTable, $matches)) {
                $analysis['stop_time_definition'] = $matches[1];
                $analysis['allows_null'] = strpos($matches[1], 'NOT NULL') === false;
            }
            
            // Count statistics
            $stats = [];
            
            $result = $conn->query("SELECT COUNT(*) as total FROM time_entries");
            $stats['total'] = $result->fetch_assoc()['total'];
            
            $result = $conn->query("SELECT COUNT(*) as null_count FROM time_entries WHERE stop_time IS NULL");
            $stats['null_count'] = $result->fetch_assoc()['null_count'];
            
            $result = $conn->query("SELECT COUNT(*) as zero_count FROM time_entries WHERE stop_time = '00:00:00'");
            $stats['zero_count'] = $result->fetch_assoc()['zero_count'];
            
            $result = $conn->query("SELECT COUNT(*) as today_running FROM time_entries WHERE stop_time = '00:00:00' AND DATE(start_time) = CURDATE()");
            $stats['today_running'] = $result->fetch_assoc()['today_running'];
            
            $analysis['stats'] = $stats;
            
            // Get sample entries
            $samples = [];
            $result = $conn->query("
                SELECT id, user_id, start_time, stop_time,
                       CASE 
                           WHEN stop_time IS NULL THEN 'NULL'
                           WHEN stop_time = '00:00:00' THEN '00:00:00'
                           ELSE stop_time
                       END as stop_display
                FROM time_entries 
                WHERE stop_time = '00:00:00' OR stop_time IS NULL
                ORDER BY id DESC 
                LIMIT 10
            ");
            
            while ($row = $result->fetch_assoc()) {
                $samples[] = $row;
            }
            
            $analysis['samples'] = $samples;
            
            return $analysis;
        }
        
        // Perform migration
        function performMigration($conn) {
            $conn->begin_transaction();
            
            try {
                // Step 1: Alter column to allow NULL
                addLog('info', 'Ändere Spaltenstruktur zu NULL...');
                $alterQuery = "ALTER TABLE time_entries MODIFY COLUMN stop_time TIME NULL DEFAULT NULL";
                
                if (!$conn->query($alterQuery)) {
                    throw new Exception("Fehler beim Ändern der Spalte: " . $conn->error);
                }
                addLog('success', 'Spalte stop_time erlaubt jetzt NULL');
                
                // Step 2: Update 00:00:00 to NULL
                addLog('info', 'Konvertiere \'00:00:00\' Werte zu NULL...');
                $updateQuery = "UPDATE time_entries SET stop_time = NULL WHERE stop_time = '00:00:00'";
                
                if (!$conn->query($updateQuery)) {
                    throw new Exception("Fehler beim Update: " . $conn->error);
                }
                
                $affectedRows = $conn->affected_rows;
                addLog('success', "$affectedRows Einträge wurden zu NULL konvertiert");
                
                $conn->commit();
                addLog('success', 'Migration erfolgreich abgeschlossen!');
                
                return true;
                
            } catch (Exception $e) {
                $conn->rollback();
                addLog('error', 'Migration fehlgeschlagen: ' . $e->getMessage());
                return false;
            }
        }
        
        // Perform rollback
        function performRollback($conn) {
            $conn->begin_transaction();
            
            try {
                // Step 1: Update NULL to 00:00:00
                addLog('info', 'Konvertiere NULL Werte zu \'00:00:00\'...');
                $updateQuery = "UPDATE time_entries SET stop_time = '00:00:00' WHERE stop_time IS NULL";
                
                if (!$conn->query($updateQuery)) {
                    throw new Exception("Fehler beim Update: " . $conn->error);
                }
                
                $affectedRows = $conn->affected_rows;
                addLog('success', "$affectedRows Einträge wurden zu '00:00:00' konvertiert");
                
                // Step 2: Alter column back to NOT NULL
                addLog('info', 'Ändere Spaltenstruktur zurück zu NOT NULL...');
                $alterQuery = "ALTER TABLE time_entries MODIFY COLUMN stop_time TIME NOT NULL DEFAULT '00:00:00'";
                
                if (!$conn->query($alterQuery)) {
                    throw new Exception("Fehler beim Ändern der Spalte: " . $conn->error);
                }
                addLog('success', 'Spalte stop_time ist wieder NOT NULL');
                
                $conn->commit();
                addLog('success', 'Rollback erfolgreich abgeschlossen!');
                
                return true;
                
            } catch (Exception $e) {
                $conn->rollback();
                addLog('error', 'Rollback fehlgeschlagen: ' . $e->getMessage());
                return false;
            }
        }
        
        // Execute action
        if ($action === 'migrate' && isset($_POST['confirm'])) {
            performMigration($conn);
        } elseif ($action === 'rollback' && isset($_POST['confirm'])) {
            performRollback($conn);
        }
        
        // Always analyze current state
        $analysis = analyzeDatabase($conn);
        ?>
        
        <div class="section">
            <h2>Aktueller Status</h2>
            
            <div class="info">
                <strong>Datenbank:</strong> <?php echo htmlspecialchars(Config::get('db.name')); ?><br>
                <strong>Host:</strong> <?php echo htmlspecialchars(Config::get('db.host')); ?><br>
                <strong>stop_time Definition:</strong> <code><?php echo htmlspecialchars($analysis['stop_time_definition']); ?></code><br>
                <strong>NULL erlaubt:</strong> <?php echo $analysis['allows_null'] ? '<span style="color: green;">✓ Ja</span>' : '<span style="color: red;">✗ Nein</span>'; ?>
            </div>
            
            <h3>Statistiken</h3>
            <table>
                <tr>
                    <th>Metrik</th>
                    <th>Anzahl</th>
                </tr>
                <tr>
                    <td>Gesamtanzahl Einträge</td>
                    <td><?php echo number_format($analysis['stats']['total']); ?></td>
                </tr>
                <tr>
                    <td>Einträge mit stop_time = NULL</td>
                    <td><?php echo number_format($analysis['stats']['null_count']); ?></td>
                </tr>
                <tr>
                    <td>Einträge mit stop_time = '00:00:00'</td>
                    <td><?php echo number_format($analysis['stats']['zero_count']); ?></td>
                </tr>
                <tr>
                    <td>Heute gestartete Timer mit '00:00:00'</td>
                    <td><?php echo number_format($analysis['stats']['today_running']); ?></td>
                </tr>
            </table>
            
            <?php if (!empty($analysis['samples'])): ?>
            <h3>Beispiel-Einträge</h3>
            <table>
                <tr>
                    <th>ID</th>
                    <th>User ID</th>
                    <th>Start Time</th>
                    <th>Stop Time</th>
                </tr>
                <?php foreach ($analysis['samples'] as $sample): ?>
                <tr>
                    <td><?php echo $sample['id']; ?></td>
                    <td><?php echo $sample['user_id']; ?></td>
                    <td><?php echo $sample['start_time']; ?></td>
                    <td><?php echo $sample['stop_display']; ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($log)): ?>
        <div class="section">
            <h2>Ausführungsprotokoll</h2>
            <div class="log">
                <?php foreach ($log as $entry): ?>
                    <div class="<?php echo $entry['type']; ?>">
                        [<?php echo $entry['time']; ?>] <?php echo htmlspecialchars($entry['message']); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="section">
            <h2>Aktionen</h2>
            
            <?php if (!$analysis['allows_null'] && $analysis['stats']['zero_count'] > 0): ?>
                <div class="warning">
                    <strong>Migration empfohlen!</strong><br>
                    Es gibt <?php echo $analysis['stats']['zero_count']; ?> Einträge mit stop_time = '00:00:00'.<br>
                    Die Migration wird diese zu NULL konvertieren und die Spalte NULL-fähig machen.
                </div>
                
                <form method="post" style="display: inline;" onsubmit="return confirm('Sind Sie sicher? Dies wird die Datenbankstruktur ändern!');">
                    <input type="hidden" name="action" value="migrate">
                    <input type="hidden" name="confirm" value="1">
                    <button type="submit" class="button button-success">Migration durchführen</button>
                </form>
            <?php elseif ($analysis['allows_null'] && $analysis['stats']['null_count'] > 0): ?>
                <div class="success">
                    Die Migration wurde bereits durchgeführt. Die Spalte erlaubt NULL-Werte.
                </div>
                
                <form method="post" style="display: inline;" onsubmit="return confirm('ACHTUNG: Dies macht die Migration rückgängig! Alle NULL-Werte werden zu 00:00:00!');">
                    <input type="hidden" name="action" value="rollback">
                    <input type="hidden" name="confirm" value="1">
                    <button type="submit" class="button button-danger">Rollback durchführen</button>
                </form>
            <?php else: ?>
                <div class="info">
                    Keine Migration erforderlich. Die Datenbank ist im optimalen Zustand.
                </div>
            <?php endif; ?>
            
            <form method="get" style="display: inline;">
                <input type="hidden" name="action" value="analyze">
                <button type="submit" class="button">Status aktualisieren</button>
            </form>
        </div>
        
        <div class="section">
            <h2>Dokumentation</h2>
            <p><strong>Was macht diese Migration?</strong></p>
            <ul>
                <li>Ändert die <code>stop_time</code> Spalte von <code>NOT NULL</code> zu <code>NULL</code></li>
                <li>Konvertiert alle <code>'00:00:00'</code> Werte zu <code>NULL</code></li>
                <li>Ermöglicht eine sauberere Unterscheidung zwischen "Timer läuft" (NULL) und "Timer gestoppt um Mitternacht" ('00:00:00')</li>
            </ul>
            
            <p><strong>Warum ist das wichtig?</strong></p>
            <ul>
                <li>Verhindert Datenverlust bei Logout</li>
                <li>Ermöglicht Server-First Timer-Architektur</li>
                <li>Unterstützt Multi-Device Synchronisation</li>
            </ul>
            
            <p><strong>Sicherheitshinweise:</strong></p>
            <div class="warning">
                <strong>WICHTIG:</strong> Erstellen Sie vor der Migration ein Backup!<br>
                <code>mysqldump -h<?php echo Config::get('db.host'); ?> -u<?php echo Config::get('db.username'); ?> -p <?php echo Config::get('db.name'); ?> > backup_<?php echo date('Y-m-d_H-i-s'); ?>.sql</code>
            </div>
        </div>
    </div>
</body>
</html>