<?php
/**
 * Unauthenticated Logging Endpoint
 * Nimmt Client-Fehlerlogs entgegen (ohne Session-Pflicht).
 */

define('API_GUARD', true);

require_once __DIR__ . '/structured-logger.php';
require_once __DIR__ . '/security-middleware.php';
require_once __DIR__ . '/auth_helpers.php';

// Security-/CORS-Header setzen, aber KEINE Session-Prüfung erzwingen
initialize_api();
initSecurityMiddleware();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// JSON-Body lesen
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) { $payload = ['raw' => substr((string)$raw, 0, 2000)]; }

// Sicher loggen (kein Throw)
try {
    $level = 'error';
    if (isset($payload['level']) && in_array($payload['level'], ['debug','info','warning','error','critical'], true)) {
        $level = $payload['level'];
    }
    $message = (string)($payload['message'] ?? 'client_log');
    $context = $payload;
    unset($context['message'], $context['level']);
    // Schreibe Logeintrag
    switch ($level) {
        case 'debug': $GLOBALS['logger']->debug($message, $context); break;
        case 'info': $GLOBALS['logger']->info($message, $context); break;
        case 'warning': $GLOBALS['logger']->warning($message, $context); break;
        case 'critical': $GLOBALS['logger']->critical($message, $context); break;
        default: $GLOBALS['logger']->error($message, $context); break;
    }
} catch (Throwable $e) {
    error_log('logs.php write failed: ' . $e->getMessage());
}

http_response_code(204);
exit;
?>

