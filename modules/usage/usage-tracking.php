<?php

namespace Radle\Modules\Usage;

/**
 * Handles anonymous usage tracking for the Radle plugin.
 * 
 * This class manages the collection and transmission of anonymous usage data
 * to help improve the plugin. It tracks:
 * - Plugin activation/deactivation events
 * - Weekly usage pings
 * - WordPress and PHP version information
 * 
 * Features:
 * - Configurable domain sharing
 * - Anonymous site ID generation
 * - Secure data transmission
 * - Local environment detection
 * - Event logging
 * 
 * Privacy considerations:
 * - Only sends data if user opts in
 * - Supports anonymous site IDs
 * - No personal data collection
 */
class Usage_Tracking {

    /**
     * Name of the product being tracked
     * @var string
     */
    private $product_name;

    /**
     * Target domain for tracking events
     * @var string
     */
    private $target_domain;

    /**
     * Version of the plugin
     * @var string
     */
    private $plugin_version;

    /**
     * Initialize usage tracking with product information.
     * 
     * Sets up weekly ping scheduling and event handlers.
     * 
     * @param string $product_name Name of the product
     * @param string $target_domain Domain to send events to
     * @param string $plugin_version Current plugin version
     */
    public function __construct($product_name, $target_domain, $plugin_version) {
        $this->product_name = $product_name;
        $this->target_domain = $target_domain;
        $this->plugin_version = $plugin_version;

        // Schedule weekly ping
        add_action('init', array($this, 'schedule_weekly_ping'));
        add_action('radle_usage_weekly_ping_event', array($this, 'send_weekly_ping'));
    }

    /**
     * Handle plugin activation event.
     * 
     * Sends activation data including:
     * - Plugin version
     * - PHP version
     * - WordPress version
     * 
     * Note: Data is only sent if the user has explicitly opted in via the
     * 'radle_share_events' option. The send_product_event() method checks
     * this option and will not send any data if it is false or not set.
     * This ensures user privacy and compliance with WordPress.org guidelines.
     */
    public function activate() {
        $additional_data = array(
            'plugin_version' => $this->plugin_version,
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version')
        );
        $this->send_product_event('activated', $additional_data);
    }

    /**
     * Handle plugin deactivation event.
     * 
     * Sends deactivation data and cleans up scheduled tasks.
     * 
     * Note: Like all tracking events, deactivation data is only sent if
     * the user has explicitly opted in via the 'radle_share_events' option.
     * The send_product_event() method performs this check and will not
     * transmit any data if the user has not opted in. This maintains user
     * privacy and WordPress.org compliance even during deactivation.
     */
    public function deactivate() {
        $additional_data = array(
            'plugin_version' => $this->plugin_version,
            'wp_version' => get_bloginfo('version')
        );
        $this->send_product_event('deactivated', $additional_data);

        // Clear the scheduled ping event
        wp_clear_scheduled_hook('radle_usage_weekly_ping_event');
    }

    /**
     * Schedule the weekly ping event.
     * 
     * Uses WordPress cron to schedule regular usage pings.
     */
    public function schedule_weekly_ping() {
        if (!wp_next_scheduled('radle_usage_weekly_ping_event')) {
            wp_schedule_event(time(), 'weekly', 'radle_usage_weekly_ping_event');
        }
    }

    /**
     * Send weekly usage ping.
     * 
     * Collects and sends basic usage data:
     * - Plugin version
     * - WordPress version
     */
    public function send_weekly_ping() {
        $additional_data = array(
            'plugin_version' => $this->plugin_version,
            'wp_version' => get_bloginfo('version')
        );
        $this->send_product_event('ping', $additional_data);
    }

    /**
     * Send a product event to the tracking server.
     * 
     * Handles the actual transmission of event data, including:
     * - Event type validation
     * - Domain handling
     * - SSL verification
     * - Error logging
     * 
     * @param string $type Event type ('activated', 'ping', 'deactivated')
     * @param array $additional_data Additional event data to send
     * @return bool True if event was sent successfully
     */
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
            $additional_data['domain'] = wp_parse_url(get_site_url(), PHP_URL_HOST);
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

    /**
     * Generate or retrieve an anonymous site ID.
     * 
     * Creates a unique, anonymous identifier for the site
     * when domain sharing is disabled.
     * 
     * @return string Anonymous site identifier
     * @access private
     */
    private function generate_site_id() {
        $site_id = get_option('radle_site_id');
        if (!$site_id) {
            $site_id = wp_hash(site_url() . time());
            update_option('radle_site_id', $site_id);
        }
        return $site_id;
    }
}