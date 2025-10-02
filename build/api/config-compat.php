<?php
/**
 * Config compatibility shim for live environments
 * - Provides Config::get/load when only DB_* constants or env vars exist
 * - Bridges DB_USER -> db.username if DB_USERNAME not set
 */

if (!class_exists('Config')) {
    class Config {
        private static $loaded = false;
        private static $cache = [];

        public static function load() {
            if (self::$loaded) return self::$cache;
            // Prime cache from env and constants
            self::$cache = [
                'db' => [
                    'host' => getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? (defined('DB_HOST') ? DB_HOST : '')),
                    'username' => getenv('DB_USERNAME') ?: ($_ENV['DB_USERNAME'] ?? (getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? (defined('DB_USER') ? DB_USER : '')))),
                    'password' => getenv('DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? (defined('DB_PASS') ? DB_PASS : '')),
                    'name' => getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? (defined('DB_NAME') ? DB_NAME : '')),
                    'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
                    'timeout' => (int)(getenv('DB_TIMEOUT') ?: 30),
                ],
                'app' => [
                    'env' => getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production'),
                    'debug' => (bool)(getenv('APP_DEBUG') ?: ($_ENV['APP_DEBUG'] ?? false)),
                ],
            ];
            self::$loaded = true;
            return self::$cache;
        }

        public static function get($key, $default = null) {
            $config = self::load();
            $parts = explode('.', $key);
            $val = $config;
            foreach ($parts as $p) {
                if (!is_array($val) || !array_key_exists($p, $val)) return $default;
                $val = $val[$p];
            }
            return $val;
        }
    }
}
?>

