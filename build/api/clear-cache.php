<?php
/**
 * Cache-Clearing Script
 * Löscht OPcache und gibt Status zurück
 */

header('Content-Type: application/json');

$result = [
    'timestamp' => date('Y-m-d H:i:s'),
    'opcache' => [
        'enabled' => function_exists('opcache_get_status'),
        'cleared' => false,
    ],
    'file_cache' => [
        'index_html_exists' => file_exists(__DIR__ . '/../dist/index.html'),
        'index_html_mtime' => file_exists(__DIR__ . '/../dist/index.html')
            ? date('Y-m-d H:i:s', filemtime(__DIR__ . '/../dist/index.html'))
            : null,
    ]
];

// Clear OPcache if available
if (function_exists('opcache_reset')) {
    $result['opcache']['cleared'] = opcache_reset();
}

// Clear stat cache
clearstatcache(true);
$result['stat_cache_cleared'] = true;

// Check actual content of index.html
if (file_exists(__DIR__ . '/../dist/index.html')) {
    $content = file_get_contents(__DIR__ . '/../dist/index.html');
    // Check which JS bundle is referenced
    if (preg_match('/src="\/assets\/(index-[^"]+\.js)"/', $content, $matches)) {
        $result['file_cache']['current_bundle'] = $matches[1];
        $result['file_cache']['is_correct'] = ($matches[1] === 'index-C02UeB1c.js');
    }
}

echo json_encode($result, JSON_PRETTY_PRINT);
