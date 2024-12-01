<?php

namespace Radle\Modules\Usage;

class Usage_Tracking {
    private $product_name;
    private $target_domain;
    private $plugin_version;

    public function __construct($product_name, $target_domain, $plugin_version) {
        $this->product_name = $product_name;
        $this->target_domain = $target_domain;
        $this->plugin_version = $plugin_version;

        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Schedule weekly ping
        add_action('init', array($this, 'schedule_weekly_ping'));
        add_action('github_product_manager_weekly_ping_event', array($this, 'send_weekly_ping'));
    }

    public function activate() {
        $additional_data = array(
            'plugin_version' => $this->plugin_version,
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version')
        );
        $this->send_product_event('activated', $additional_data);
    }

    public function deactivate() {
        $additional_data = array(
            'plugin_version' => $this->plugin_version,
            'wp_version' => get_bloginfo('version')
        );
        $this->send_product_event('deactivated', $additional_data);

        // Clear the scheduled ping event
        wp_clear_scheduled_hook('github_product_manager_weekly_ping_event');
    }

    public function schedule_weekly_ping() {
        if (!wp_next_scheduled('github_product_manager_weekly_ping_event')) {
            wp_schedule_event(time(), 'weekly', 'github_product_manager_weekly_ping_event');
        }
    }

    public function send_weekly_ping() {
        $additional_data = array(
            'plugin_version' => $this->plugin_version,
            'wp_version' => get_bloginfo('version')
        );
        $this->send_product_event('ping', $additional_data);
    }

    public function send_product_event($type, $additional_data = array()) {

        global $radleLogs;

        $share_events = get_option('radle_share_events', false);
        $share_domain = get_option('radle_share_domain', false);

        if (!$share_events) {
            return;
        }

        $api_url = RADLE_GBTI_API_SERVER . "/product-events";

        $allowed_types = array('activated', 'ping', 'deactivated');
        if (!in_array($type, $allowed_types)) {
            $radleLogs->log('Invalid event type: ' . $type, 'usage');
            return false;
        }

        if ($share_domain) {
            $additional_data['domain'] = parse_url(get_site_url(), PHP_URL_HOST);
        } else {
            $additional_data['domain'] = $this->generate_site_id();
        }

        $body = array(
            'product' => $this->product_name,
            'type' => $type,
            'domain' => $additional_data['domain'],
            'data' => $additional_data
        );

        $sslverify = true;
        if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'local') {
            $sslverify = false;
        }

        $args = array(
            'body' => wp_json_encode($body),
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'github-product-callback'
            ),
            'timeout' => 30,
            'sslverify' => $sslverify
        );


        $response = wp_remote_post($api_url, $args);

        if (is_wp_error($response)) {
            $radleLogs->log('Failed to send product event: ' . $response->get_error_message(), 'usage');
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            $radleLogs->log('Unexpected response when sending product event: ' . $response_code . ' ' . $response_body, 'usage');
            return false;
        }

        return true;
    }

    private function generate_site_id() {
        $site_id = get_option('radle_site_id');
        if (!$site_id) {
            $site_id = wp_hash(site_url() . time());
            update_option('radle_site_id', $site_id);
        }
        return $site_id;
    }
}