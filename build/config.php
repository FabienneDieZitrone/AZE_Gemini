<?php
/**
 * Titel: Sichere Konfiguration mit Environment Variables
 * Version: 1.0
 * Datum: 2025-07-24
 * Autor: Claude Code
 * Beschreibung: Lädt Konfiguration aus Environment Variables oder .env Datei
 */

class Config {
    private static $config = null;
    
    public static function load() {
        if (self::$config !== null) {
            return self::$config;
        }
        
        // Versuche .env Datei zu laden
        $envFile = __DIR__ . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '#') === 0) continue; // Skip comments
                
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Entferne Anführungszeichen falls vorhanden
                if (preg_match('/^"(.*)"$/', $value, $matches)) {
                    $value = $matches[1];
                } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
                    $value = $matches[1];
                }
                
                $_ENV[$key] = $value;
            }
        }
        
        self::$config = [
            'db' => [
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'username' => $_ENV['DB_USERNAME'] ?? $_ENV['DB_USER'] ?? '',
                'password' => $_ENV['DB_PASSWORD'] ?? $_ENV['DB_PASS'] ?? '',
                'name' => $_ENV['DB_NAME'] ?? '',
            ],
            'oauth' => [
                'client_id' => $_ENV['OAUTH_CLIENT_ID'] ?? '',
                'client_secret' => $_ENV['OAUTH_CLIENT_SECRET'] ?? '',
                'tenant_id' => $_ENV['OAUTH_TENANT_ID'] ?? '',
                'redirect_uri' => $_ENV['OAUTH_REDIRECT_URI'] ?? '',
                'api_app_id_uri' => $_ENV['OAUTH_API_APP_ID_URI'] ?? '',
            ],
            'app' => [
                'env' => $_ENV['APP_ENV'] ?? 'production',
                'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ]
        ];
        
        return self::$config;
    }
    
    public static function get($key, $default = null) {
        $config = self::load();
        
        // Unterstütze dot notation: 'db.host'
        $keys = explode('.', $key);
        $value = $config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
}