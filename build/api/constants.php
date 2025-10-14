<?php
/**
 * Lightweight runtime constants for AZE_Gemini APIs
 * Provides safe defaults so includes like auth_helpers.php do not fatal if this file is missing.
 */

// Time constants
if (!defined('SECONDS_PER_HOUR')) { define('SECONDS_PER_HOUR', 3600); }
if (!defined('SECONDS_PER_DAY')) { define('SECONDS_PER_DAY', 86400); }

// App environment constant (optional)
if (!defined('APP_ENV')) {
    $env = getenv('APP_ENV');
    if ($env !== false && $env !== null && $env !== '') {
        define('APP_ENV', $env);
    }
}

// Nothing to return; this file only defines constants.
?>

