<?php
/**
 * Realer Test der Autorisierungs-Middleware
 * Führt echte Tests gegen die API durch
 */

// Lade Environment-Variablen
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

// Test-Konfiguration
$BASE_URL = 'https://aze.mikropartner.de/api/';
$TEST_URL = 'https://aze.mikropartner.de/test/api/';

// Farben für Output
$GREEN = "\033[0;32m";
$RED = "\033[0;31m";
$YELLOW = "\033[1;33m";
$NC = "\033[0m";

echo "🧪 AZE Gemini - Reale Autorisierungs-Tests\n";
echo "==========================================\n\n";

// Test 1: Auth-Status ohne Session
echo "Test 1: Auth-Status ohne Session\n";
$ch = curl_init($BASE_URL . 'auth-status.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 401) {
    echo "{$GREEN}✅ PASS{$NC} - 401 Unauthorized ohne Session\n\n";
} else {
    echo "{$RED}❌ FAIL{$NC} - Erwartete 401, erhielt $httpCode\n\n";
}

// Test 2: Teste verschiedene Endpoints ohne Authentifizierung
$endpoints = ['users.php', 'time-entries.php', 'settings.php', 'masterdata.php'];
echo "Test 2: Endpoints ohne Authentifizierung\n";

foreach ($endpoints as $endpoint) {
    $ch = curl_init($BASE_URL . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 401 || $httpCode === 403) {
        echo "  $endpoint: {$GREEN}✅ PASS{$NC} - $httpCode (Zugriff verweigert)\n";
    } else {
        echo "  $endpoint: {$RED}❌ FAIL{$NC} - Erwartete 401/403, erhielt $httpCode\n";
    }
}

echo "\n";

// Test 3: Login-Versuch (simuliert, da OAuth erforderlich)
echo "Test 3: Login-Endpoint Verfügbarkeit\n";
$ch = curl_init($BASE_URL . 'login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['test' => true]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "  login.php: HTTP $httpCode";
if ($httpCode >= 200 && $httpCode < 500) {
    echo " {$GREEN}✅ Endpoint erreichbar{$NC}\n";
} else {
    echo " {$RED}❌ Endpoint nicht erreichbar{$NC}\n";
}

// Test 4: CSRF-Token abrufen
echo "\nTest 4: CSRF-Token Endpoint\n";
$ch = curl_init($BASE_URL . 'csrf-protection.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$data = json_decode($response, true);
curl_close($ch);

if ($httpCode === 200 && isset($data['csrf_token'])) {
    echo "  {$GREEN}✅ PASS{$NC} - CSRF-Token erhalten\n";
} else {
    echo "  {$RED}❌ FAIL{$NC} - Kein CSRF-Token erhalten\n";
}

// Test 5: Security Headers prüfen
echo "\nTest 5: Security Headers\n";
$ch = curl_init($BASE_URL . 'auth-status.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $header_size);
curl_close($ch);

$security_headers = [
    'X-Content-Type-Options',
    'X-Frame-Options',
    'X-XSS-Protection',
    'Strict-Transport-Security'
];

foreach ($security_headers as $header) {
    if (stripos($headers, $header) !== false) {
        echo "  $header: {$GREEN}✅ Vorhanden{$NC}\n";
    } else {
        echo "  $header: {$RED}❌ Fehlt{$NC}\n";
    }
}

// Zusammenfassung
echo "\n==========================================\n";
echo "📊 Test-Zusammenfassung\n";
echo "Alle kritischen Sicherheitstests durchgeführt.\n";
echo "\nHinweis: Vollständige Autorisierungstests erfordern\n";
echo "eine gültige Azure AD Session.\n";