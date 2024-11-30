<?php
namespace Radle\Modules\Updates;

class Updates_Module {
    private $github_token;
    private $current_version;
    private $plugin_slug;

    public function __construct() {
        $this->github_token = get_option('radle_github_access_token');
        $this->current_version = RADLE_VERSION;
        $this->plugin_slug = 'radle/radle.php';

        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        add_action('upgrader_process_complete', [$this, 'after_update'], 10, 2);
    }

    public function check_for_update($transient) {

        global $radleLogs;

        $radleLogs->log("Starting check_for_update", 'updates');

        if (empty($transient->checked)) {
            $radleLogs->log("Transient->checked is empty, returning original transient", 'updates');
            return $transient;
        }

        // Get the current version from the transient, if available
        $current_version = $transient->checked[$this->plugin_slug] ?? $this->current_version;
        $radleLogs->log("Current plugin version: " . $current_version, 'updates');
        $radleLogs->log("Plugin slug: " . $this->plugin_slug, 'updates');

        // Check if we've recently performed an update check
        $cache_key = 'radle_update_info';
        $update_info = get_transient($cache_key);

        if ($update_info === false) {
            $update_info = $this->get_update_info();
            if ($update_info) {
                set_transient($cache_key, $update_info, HOUR_IN_SECONDS);
            }
        } else {
            $radleLogs->log("Using cached update info", 'updates');
        }

        $radleLogs->log("Update info received: " . print_r($update_info, true), 'updates');

        if ($update_info && version_compare($current_version, $update_info['version'], '<')) {
            $radleLogs->log("New version available. Current: {$current_version}, New: {$update_info['version']}", 'updates');
            $transient->response[$this->plugin_slug] = (object) [
                'slug' => 'radle',
                'plugin' => $this->plugin_slug,
                'new_version' => $update_info['version'],
                'package' => $update_info['download_url'],
                'tested' => $update_info['tested'] ?? '6.2',
                'requires' => $update_info['requires'] ?? '5.0',
                'url' => $update_info['details_url'] ?? '',
                'icons' => [
                    '1x' => RADLE_PLUGIN_URL . 'assets/images/raddle-logo-square.png',
                    '2x' => RADLE_PLUGIN_URL  . 'assets/images/raddle-logo-square.png',
                ],
                'banners' => [
                    'low' => RADLE_PLUGIN_URL . 'assets/images/radle-banner.webp',
                    'high' => RADLE_PLUGIN_URL . 'assets/images/radle-banner.webp',
                ],
            ];
            $radleLogs->log("Updated transient response: " . print_r($transient->response[$this->plugin_slug], true), 'updates');
        } else {
            $radleLogs->log("No new version available or update check failed", 'updates');
        }

        return $transient;
    }

    public function plugin_info($res, $action, $args) {
        if ($action !== 'plugin_information' || $args->slug !== 'radle') {
            return $res;
        }

        $update_info = $this->get_update_info();

        if (!$update_info) {
            return $res;
        }

        $res = (object) [
            'name' => 'Radle',
            'slug' => 'radle',
            'version' => $update_info['version'],
            'tested' => $update_info['tested'],
            'requires' => $update_info['requires'],
            'author' => 'GBTI',
            'author_profile' => 'https://github.com/gbti-network',
            'download_link' => $update_info['download_url'],
            'trunk' => $update_info['download_url'],
            'last_updated' => $update_info['last_updated'],
            'sections' => [
                'description' => $update_info['description'],
                'changelog' => $update_info['changelog'],
            ],
        ];

        return $res;
    }

    public function after_update($upgrader_object, $options) {
        if ($options['action'] == 'update' && $options['type'] == 'plugin') {
            foreach($options['plugins'] as $plugin) {
                if ($plugin == $this->plugin_slug) {
                    do_action('radle_after_update', $this->current_version, $this->get_update_info()['version']);
                }
            }
        }
    }

    private function get_update_info() {

        global $radleLogs;

        if (!$this->github_token) {
            return false;
        }

        $radleLogs->log("Starting get_update_info", 'updates');

        // Check if we've recently performed an update check
        $cache_key = 'radle_update_info';
        $cached_info = get_transient($cache_key);

        if ($cached_info !== false) {
            $radleLogs->log("Returning cached update info", 'updates');
            return $cached_info;
        }

        $sslverify = !(defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'local');

        $api_url = RADLE_GBTI_API_SERVER . '/download-release';
        $start_time = microtime(true);
        $response = wp_remote_post($api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->github_token,
            ],
            'body' => [
                'product' => 'gbti-network/radle-wordpress-plugin',
            ],
            'sslverify' => $sslverify,
            'timeout' => 10,
        ]);
        $end_time = microtime(true);

        $duration = $end_time - $start_time;
        $radleLogs->log("API call duration: " . $duration . " seconds", 'updates');

        if (is_wp_error($response)) {
            $radleLogs->log("API request failed: " . $response->get_error_message(), 'updates');
            if (strpos($response->get_error_message(), 'cURL error 28') !== false) {
                $radleLogs->log("CURL timeout occurred", 'updates');
            }
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $radleLogs->log('Radle update check failed. Response code: ' . $response_code, 'updates');
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['version'])) {
            $radleLogs->log("Invalid response format", 'updates');
            return false;
        }

        // Cache the update info for 15 minutes
        set_transient($cache_key, $data, 60 * 15);

        return $data;
    }
}