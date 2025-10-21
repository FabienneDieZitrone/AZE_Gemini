<?php
/**
 * Debug Helpers - Zentrale Logging-Funktionen
 * Version: 1.0
 * Autor: MP-IT
 * Datei: /api/debug-helpers.php
 *
 * Features:
 * - hlog() mit Auto-Rotation (max 200 Einträge)
 * - test.html mit Clear-Button
 * - Keine Performance-Impact in Production
 */

if (!defined('API_GUARD')) {
    define('API_GUARD', true);
}

/**
 * HTML-Log mit Auto-Rotation
 * Schreibt Debug-Ausgaben nach test.html mit automatischer Bereinigung
 *
 * @param string $title Log-Titel
 * @param mixed $data Optionale Daten (wird als JSON formatiert)
 * @param string $level Log-Level (info, warning, error, success)
 */
if (!function_exists('hlog')) {
    function hlog($title, $data = null, $level = 'info') {
        // Nur in Development-Modus oder wenn APP_DEBUG=true
        if ((getenv('APP_ENV') === 'production') && (!filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOLEAN))) {
            return;
        }

        $logFile = __DIR__ . '/test.html';
        $maxLines = 200; // Maximal 200 Log-Einträge

        // Timestamp
        $ts = date('Y-m-d H:i:s');

        // Level-Farben
        $colors = [
            'info' => '#2196F3',
            'success' => '#4CAF50',
            'warning' => '#FF9800',
            'error' => '#F44336'
        ];
        $color = $colors[$level] ?? $colors['info'];

        // Formatiere Daten
        $dataHtml = '';
        if ($data !== null) {
            $payload = is_string($data) ? $data : json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $dataHtml = '<pre style="margin: 5px 0; padding: 10px; background: #f5f5f5; border-left: 3px solid ' . $color . '; overflow-x: auto;">'
                      . htmlspecialchars($payload)
                      . '</pre>';
        }

        // Log-Eintrag
        $entry = '<div style="margin: 10px 0; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">'
               . '<strong style="color: ' . $color . ';">[' . $ts . '] ' . htmlspecialchars($title) . '</strong>'
               . $dataHtml
               . '</div>';

        // Lese existierende Einträge
        $existingContent = '';
        if (file_exists($logFile)) {
            $existingContent = file_get_contents($logFile);
        }

        // Extrahiere Body-Content (ohne HTML-Wrapper)
        if (preg_match('/<body[^>]*>(.*)<\/body>/s', $existingContent, $matches)) {
            $bodyContent = $matches[1];
        } else {
            $bodyContent = $existingContent;
        }

        // Zähle Einträge (div-Tags)
        $entryCount = substr_count($bodyContent, '<div style="margin: 10px 0;');

        // Auto-Rotation: Entferne älteste Einträge wenn Limit erreicht
        if ($entryCount >= $maxLines) {
            // Extrahiere alle Einträge
            preg_match_all('/<div style="margin: 10px 0;[^>]*>.*?<\/div>/s', $bodyContent, $allEntries);

            if (!empty($allEntries[0])) {
                // Behalte nur die neuesten (maxLines - 1) Einträge
                $keepCount = $maxLines - 1;
                $entriesToKeep = array_slice($allEntries[0], -$keepCount);
                $bodyContent = implode("\n", $entriesToKeep);
            }
        }

        // Füge neuen Eintrag hinzu
        $bodyContent .= "\n" . $entry;

        // HTML-Wrapper mit Clear-Button
        $html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AZE Gemini - Debug Log</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background: #fafafa;
        }
        .header {
            position: sticky;
            top: 0;
            background: white;
            padding: 15px;
            border-bottom: 2px solid #2196F3;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            z-index: 100;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            color: #333;
        }
        .buttons {
            display: flex;
            gap: 10px;
        }
        button {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.2s;
        }
        .clear-btn {
            background: #F44336;
            color: white;
        }
        .clear-btn:hover {
            background: #D32F2F;
        }
        .refresh-btn {
            background: #4CAF50;
            color: white;
        }
        .refresh-btn:hover {
            background: #388E3C;
        }
        .scroll-top-btn {
            background: #2196F3;
            color: white;
        }
        .scroll-top-btn:hover {
            background: #1976D2;
        }
        .info {
            background: #E3F2FD;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #1976D2;
        }
        .log-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔍 AZE Gemini Debug Log</h1>
        <div class="buttons">
            <button class="scroll-top-btn" onclick="window.scrollTo({top: 0, behavior: \'smooth\'})">⬆️ Nach oben</button>
            <button class="refresh-btn" onclick="location.reload()">🔄 Aktualisieren</button>
            <button class="clear-btn" onclick="if(confirm(\'Log wirklich löschen?\')) { fetch(\'clear-test-log.php\').then(() => location.reload()); }">🗑️ Log löschen</button>
        </div>
    </div>

    <div class="info">
        <strong>ℹ️ Info:</strong> Zeigt maximal ' . $maxLines . ' neueste Log-Einträge. Ältere Einträge werden automatisch entfernt. Auto-Refresh alle 5 Sekunden.
    </div>

    <div class="log-container">
        ' . $bodyContent . '
    </div>

    <script>
        // Auto-refresh alle 5 Sekunden
        setTimeout(() => location.reload(), 5000);

        // Scroll zum neuesten Eintrag beim Laden
        window.addEventListener("load", () => {
            window.scrollTo(0, document.body.scrollHeight);
        });
    </script>
</body>
</html>';

        // Schreibe komplettes HTML
        @file_put_contents($logFile, $html);
    }
}

/**
 * Löscht test.html Log-Datei
 */
if (!function_exists('clearHtmlLog')) {
    function clearHtmlLog() {
        $logFile = __DIR__ . '/test.html';
        if (file_exists($logFile)) {
            @unlink($logFile);
        }

        // Erstelle leeres Log
        hlog('Log gelöscht', 'Alle vorherigen Einträge wurden entfernt', 'success');
    }
}
