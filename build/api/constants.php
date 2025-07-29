<?php
/**
 * AZE_Gemini - PHP Constants
 * Centralized configuration values
 */

// Time constants
define('SECONDS_PER_MINUTE', 60);
define('SECONDS_PER_HOUR', 3600);
define('SECONDS_PER_DAY', 86400);
define('MINUTES_PER_HOUR', 60);
define('HOURS_PER_DAY', 24);

// API configuration
define('API_TIMEOUT', 15);
define('SESSION_LIFETIME', 86400); // 24 hours

// Work defaults
define('DEFAULT_DAILY_SOLL_HOURS', 8);
define('DEFAULT_WEEKLY_SOLL_HOURS', 40);

// Database constants
define('MAX_QUERY_TIME', 5);
define('CONNECTION_TIMEOUT', 10);

// Security
define('BCRYPT_COST', 10);
define('RATE_LIMIT_WINDOW', 3600); // 1 hour
define('RATE_LIMIT_MAX_REQUESTS', 1000);