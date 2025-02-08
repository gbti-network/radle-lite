<?php

namespace Radle\Utilities;

/**
 * Logging utility for the Radle plugin.
 * 
 * Provides a centralized logging system with features:
 * - Master log for all entries
 * - Module-specific log files
 * - Automatic log rotation
 * - Configurable logging levels
 * - Safe file system operations
 * 
 * Log files are stored in the WordPress uploads directory
 * under /radle/logs/ with proper file permissions.
 */
class log {

    /**
     * Directory path for log files
     * @var string
     */
    private $log_dir;

    /**
     * Path to the master log file
     * @var string
     */
    private $master_log;

    /**
     * Array of module-specific log file paths
     * @var array
     */
    private $module_logs = [];

    /**
     * Maximum number of lines to keep in log files
     * @var int
     */
    private $max_lines = 3000;

    /**
     * Whether logging is enabled
     * @var bool
     */
    private $logging_enabled;

    /**
     * Initialize the logging system.
     * 
     * Sets up log directory and files in WordPress uploads.
     */
    public function __construct() {
        $this->logging_enabled = RADLE_LOGGING_ENABLED;

        $upload_dir = wp_upload_dir();
        $this->log_dir = $upload_dir['basedir'] . '/radle/logs/';
        $this->master_log = $this->log_dir . 'all.log';

        $this->init_log_directory();
    }

    /**
     * Create the log directory if it doesn't exist.
     * 
     * @access private
     */
    private function init_log_directory() {
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
        }
    }

    /**
     * Log a message to both master and module-specific logs.
     * 
     * @param string $message Message to log
     * @param string $module Module identifier (default: 'general')
     */
    public function log($message, $module = 'general') {
        if (!$this->logging_enabled) {
            return;
        }

        $log_entry = $this->format_log_entry($message, $module);

        $this->write_to_master_log($log_entry);
        $this->write_to_module_log($log_entry, $module);
    }

    /**
     * Format a log entry with timestamp and module.
     * 
     * @param string $message Raw message
     * @param string $module Module identifier
     * @return string Formatted log entry
     * @access private
     */
    private function format_log_entry($message, $module) {
        $timestamp = current_time('Y-m-d H:i:s');
        return "[{$timestamp}] [{$module}] {$message}";
    }

    /**
     * Write an entry to the master log.
     * 
     * @param string $log_entry Formatted log entry
     * @access private
     */
    private function write_to_master_log($log_entry) {
        $this->write_to_log($this->master_log, $log_entry);
    }

    /**
     * Write an entry to a module-specific log.
     * 
     * @param string $log_entry Formatted log entry
     * @param string $module Module identifier
     * @access private
     */
    private function write_to_module_log($log_entry, $module) {
        $module_log = $this->get_module_log_file($module);
        $this->write_to_log($module_log, $log_entry);
    }

    /**
     * Get or create a module-specific log file path.
     * 
     * @param string $module Module identifier
     * @return string Path to module log file
     * @access private
     */
    private function get_module_log_file($module) {
        if (!isset($this->module_logs[$module])) {
            $this->module_logs[$module] = $this->log_dir . sanitize_file_name($module) . '.log'; // Use dynamic module names
        }
        return $this->module_logs[$module];
    }

    /**
     * Write an entry to a log file with rotation.
     * 
     * Handles:
     * - File system initialization
     * - Log rotation
     * - Safe file writing
     * - Proper permissions
     * 
     * @param string $log_file Path to log file
     * @param string $log_entry Formatted log entry
     * @access private
     */
    private function write_to_log($log_file, $log_entry) {
        global $wp_filesystem;

        // Initialize WP_Filesystem if not already done
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();

        // Read current contents
        $current_contents = [];
        if ($wp_filesystem->exists($log_file)) {
            $file_contents = $wp_filesystem->get_contents($log_file);
            if ($file_contents !== false) {
                $current_contents = explode("\n", $file_contents);
            }
        }

        // Add new entry and trim to max lines
        array_unshift($current_contents, $log_entry);
        $current_contents = array_slice($current_contents, 0, $this->max_lines);

        // Write back to file
        $wp_filesystem->put_contents($log_file, implode("\n", $current_contents), FS_CHMOD_FILE);
    }
}
