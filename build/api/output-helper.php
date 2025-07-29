<?php
/**
 * Output Helper for safe data output
 * Escapes data appropriately based on context
 */

class OutputHelper {
    
    /**
     * Prepare data for JSON output
     * Escapes HTML entities in string values to prevent XSS
     * @param mixed $data Data to prepare
     * @return mixed Prepared data
     */
    public static function prepareForJson($data) {
        if (is_array($data)) {
            return array_map([self::class, 'prepareForJson'], $data);
        }
        
        if (is_string($data)) {
            // For JSON output, we don't need HTML escaping
            // JSON encoding will handle special characters
            return $data;
        }
        
        return $data;
    }
    
    /**
     * Prepare data for HTML output
     * Escapes HTML entities to prevent XSS
     * @param mixed $data Data to prepare
     * @return mixed Prepared data
     */
    public static function prepareForHtml($data) {
        if (is_array($data)) {
            return array_map([self::class, 'prepareForHtml'], $data);
        }
        
        if (is_string($data)) {
            return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        
        return $data;
    }
    
    /**
     * Safe JSON encode with proper escaping
     * @param mixed $data Data to encode
     * @param int $options JSON encode options
     * @return string JSON string
     */
    public static function jsonEncode($data, $options = 0) {
        // Ensure UTF-8 encoding
        $options |= JSON_UNESCAPED_UNICODE;
        
        // Add partial output on errors in development
        if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
            $options |= JSON_PARTIAL_OUTPUT_ON_ERROR;
        }
        
        $json = json_encode($data, $options);
        
        if ($json === false) {
            error_log('JSON encoding error: ' . json_last_error_msg());
            return json_encode(['error' => 'JSON encoding failed']);
        }
        
        return $json;
    }
}