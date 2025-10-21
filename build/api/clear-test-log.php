<?php
/**
 * Clear Test Log
 * Löscht test.html Debug-Log
 */
define('API_GUARD', true);
require_once __DIR__ . '/debug-helpers.php';

clearHtmlLog();

header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Log gelöscht']);
