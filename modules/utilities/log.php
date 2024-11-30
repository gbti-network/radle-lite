<?php

namespace Radle\Utilities;

class log {
    private $log_dir;
    private $master_log;
    private $module_logs = [];
    private $max_lines = 3000;
    private $logging_enabled;

    public function __construct() {
        $this->logging_enabled = RADLE_LOGGING_ENABLED;

        $upload_dir = wp_upload_dir();
        $this->log_dir = $upload_dir['basedir'] . '/radle/logs/';
        $this->master_log = $this->log_dir . 'all.log';

        $this->init_log_directory();
    }

    private function init_log_directory() {
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
        }
    }

    public function log($message, $module = 'general') {
        if (!$this->logging_enabled) {
            return;
        }

        $log_entry = $this->format_log_entry($message, $module);

        $this->write_to_master_log($log_entry);
        $this->write_to_module_log($log_entry, $module);
    }

    private function format_log_entry($message, $module) {
        $timestamp = current_time('Y-m-d H:i:s');
        return "[{$timestamp}] [{$module}] {$message}";
    }

    private function write_to_master_log($log_entry) {
        $this->write_to_log($this->master_log, $log_entry);
    }

    private function write_to_module_log($log_entry, $module) {
        $module_log = $this->get_module_log_file($module);
        $this->write_to_log($module_log, $log_entry);
    }

    private function get_module_log_file($module) {
        if (!isset($this->module_logs[$module])) {
            $this->module_logs[$module] = $this->log_dir . sanitize_file_name($module) . '.log'; // Use dynamic module names
        }
        return $this->module_logs[$module];
    }

    private function write_to_log($log_file, $log_entry) {
        $current_contents = file_exists($log_file) ? file($log_file, FILE_IGNORE_NEW_LINES) : [];
        array_unshift($current_contents, $log_entry);
        $current_contents = array_slice($current_contents, 0, $this->max_lines);

        file_put_contents($log_file, implode("\n", $current_contents));
    }
}
