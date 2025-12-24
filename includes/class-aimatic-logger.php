<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIMatic_Logger {
    
    private static $log_dir = '';
    private static $log_file = 'aimatic-activity.log';
    
    /**
     * Initialize logger directory
     */
    public static function init() {
        $upload_dir = wp_upload_dir();
        self::$log_dir = $upload_dir['basedir'] . '/aimatic-logs';
        
        if (!file_exists(self::$log_dir)) {
            wp_mkdir_p(self::$log_dir);
            // Protect logs
            file_put_contents(self::$log_dir . '/index.php', '<?php // Silence is golden');
            file_put_contents(self::$log_dir . '/.htaccess', 'deny from all');
        }
    }
    
    /**
     * Write a log message
     * 
     * @param string $message The message to log
     * @param string $level INFO, ERROR, DEBUG, WARNING
     */
    public static function log($message, $level = 'INFO') {
        if (empty(self::$log_dir)) {
            self::init();
        }
        
        $timestamp = current_time('mysql');
        $formatted_message = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        
        file_put_contents(self::$log_dir . '/' . self::$log_file, $formatted_message, FILE_APPEND);
    }
    
    /**
     * Get Log File Path
     */
    public static function get_log_path() {
        if (empty(self::$log_dir)) {
            self::init();
        }
        return self::$log_dir . '/' . self::$log_file;
    }
    
    /**
     * Clear Logs
     */
    public static function clear_logs() {
        $path = self::get_log_path();
        if (file_exists($path)) {
            file_put_contents($path, ''); // Empty file
            return true;
        }
        return false;
    }
    
    /**
     * Read Logs (Last N Lines)
     */
    public static function read_logs($lines = 100) {
        $path = self::get_log_path();
        if (!file_exists($path)) {
            return array("Log file is empty or does not exist.");
        }
        
        // simple read for now, can be optimized for huge files later
        $content = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$content) return array();
        
        return array_slice($content, -$lines);
    }
}
