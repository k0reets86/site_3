<?php
/**
 * Logger Class
 * Handles logging to database and debug.log
 */

if (!defined('ABSPATH')) {
    exit;
}

class AINCC_Logger {

    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';

    /**
     * Log a debug message
     */
    public static function debug($message, $context = []) {
        self::log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Log an info message
     */
    public static function info($message, $context = []) {
        self::log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log a warning message
     */
    public static function warning($message, $context = []) {
        self::log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log an error message
     */
    public static function error($message, $context = []) {
        self::log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Main logging function
     */
    private static function log($level, $message, $context = []) {
        // Log to database
        try {
            $db = new AINCC_Database();
            $db->insert_log($level, $message, $context);
        } catch (Exception $e) {
            // Fallback to error_log if database fails
            error_log("[AINCC] DB Log Error: " . $e->getMessage());
        }

        // Also log to debug.log in development
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $context_string = !empty($context) ? ' | ' . json_encode($context) : '';
            error_log("[AINCC {$level}] {$message}{$context_string}");
        }
    }

    /**
     * Log API request
     */
    public static function api_request($provider, $endpoint, $request, $response, $duration) {
        self::debug("API Request: {$provider}", [
            'endpoint' => $endpoint,
            'request_size' => strlen(json_encode($request)),
            'response_size' => strlen(json_encode($response)),
            'duration_ms' => $duration,
        ]);
    }

    /**
     * Log cron execution
     */
    public static function cron($hook, $duration, $result) {
        self::info("Cron executed: {$hook}", [
            'duration_ms' => $duration,
            'result' => $result,
        ]);
    }
}
