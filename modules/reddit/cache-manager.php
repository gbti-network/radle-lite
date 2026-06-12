<?php
/**
 * Radle Cache Manager
 *
 * Handles clearing Reddit comment caches when comment settings are saved.
 * Hooks into WordPress option updates to clear caches when needed.
 *
 * @package Radle
 */

namespace Radle\Modules\Reddit;

if (!defined('ABSPATH')) {
    exit;
}

class Cache_Manager {

    /**
     * Settings that require cache clearing when updated
     * @var array
     */
    private $cache_affecting_settings = [
        'radle_max_depth_level',
        'radle_max_siblings',
        'radle_cache_duration',
    ];

    /**
     * Initialize the cache manager
     */
    public function __construct() {
        // Hook into option updates for cache-affecting settings
        foreach ($this->cache_affecting_settings as $setting) {
            add_action("update_option_{$setting}", [$this, 'on_setting_updated'], 10, 2);
        }
    }

    /**
     * Handle setting update event
     *
     * Clears all Reddit comment caches when a cache-affecting setting is updated.
     *
     * @param mixed $old_value The old option value
     * @param mixed $new_value The new option value
     * @return void
     */
    public function on_setting_updated($old_value, $new_value) {
        // Only clear cache if value actually changed
        if ($old_value === $new_value) {
            return;
        }

        $this->clear_reddit_cache();
        $this->log("Setting updated from '{$old_value}' to '{$new_value}' - Reddit comment cache cleared");
    }

    /**
     * Clear all Reddit comment caches
     *
     * Removes all transients that start with 'radle_'
     *
     * @return void
     */
    private function clear_reddit_cache() {
        // SECURITY/PERF: Only run the direct query when caching is actually enabled.
        // This makes the routine a no-op on unrelated saves and when caching is off.
        if ((int) get_option('radle_cache_duration', 0) <= 0) {
            return;
        }

        global $wpdb;

        // Use WordPress transient API instead of direct database query
        // This is more compatible with object caching plugins (Redis, Memcached, etc.)
        $transient_keys = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_radle_') . '%'
            )
        );

        foreach ($transient_keys as $transient) {
            // Remove '_transient_' prefix to get the actual transient name
            $transient_name = str_replace('_transient_', '', $transient);
            delete_transient($transient_name);
        }

        $this->log('Cleared ' . count($transient_keys) . ' Reddit comment cache entries');
    }

    /**
     * Helper function for logging
     *
     * @param string $message Message to log
     */
    private function log($message) {
        if (defined('RADLE_LOGGING_ENABLED') && RADLE_LOGGING_ENABLED) {
            global $radleLogs;
            if ($radleLogs) {
                $radleLogs->log($message, 'radle-cache-manager');
            }
        }
    }
}
