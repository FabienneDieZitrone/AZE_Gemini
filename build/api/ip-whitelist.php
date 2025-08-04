<?php
/**
 * IP-Whitelist für Home Office Berechtigung
 * Issue #72: Home Office Berechtigungs-System
 */

class IPWhitelist {
    private static $config = [
        // Büro IP-Bereiche
        'office' => [
            '192.168.1.0/24',  // Beispiel Büro-Netzwerk
            '10.0.0.0/16'      // Beispiel VPN-Bereich
        ],
        // Home Office IP-Bereiche (pro Benutzer konfigurierbar)
        'home_office' => [
            // Format: 'username' => ['IP1', 'IP2', 'CIDR-Range']
        ],
        // Globale Whitelist (z.B. für Admins)
        'global' => [
            // IPs die immer erlaubt sind
        ]
    ];
    
    public static function isAllowed($username = null) {
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // Prüfe globale Whitelist
        if (self::checkIPInRanges($clientIP, self::$config['global'])) {
            return true;
        }
        
        // Prüfe Büro-IPs
        if (self::checkIPInRanges($clientIP, self::$config['office'])) {
            return true;
        }
        
        // Prüfe benutzerspezifische Home Office IPs
        if ($username && isset(self::$config['home_office'][$username])) {
            if (self::checkIPInRanges($clientIP, self::$config['home_office'][$username])) {
                return true;
            }
        }
        
        return false;
    }
    
    private static function checkIPInRanges($ip, $ranges) {
        foreach ($ranges as $range) {
            if (self::ipInRange($ip, $range)) {
                return true;
            }
        }
        return false;
    }
    
    private static function ipInRange($ip, $range) {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        
        list($subnet, $mask) = explode('/', $range);
        return (ip2long($ip) & ~((1 << (32 - $mask)) - 1)) === ip2long($subnet);
    }
    
    public static function logAccess($username, $allowed) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'username' => $username,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'allowed' => $allowed
        ];
        
        error_log(json_encode($logData) . PHP_EOL, 3, __DIR__ . '/logs/ip-access.log');
    }
}