<?php

namespace Radle\API\v1\GitHub;

use WP_REST_Controller;
use WP_REST_Request;
use WP_Error;

class Check_Auth_Endpoint extends WP_REST_Controller {

    private $github_token_option = 'radle_github_access_token';

    public function __construct() {
        $this->namespace = 'radle/v1';
        $this->rest_base = 'github/check-auth';

        add_action('rest_api_init', [$this, 'register_routes']);

        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'check_auth'],
                'permission_callback' => [$this, 'permission_check'],
            ],
        ]);
    }

    public function permission_check() {
        global $radleLogs;

        $has_permission = current_user_can('manage_options');
        if (!$has_permission) {
            $radleLogs->log("Permission check failed for GitHub auth check", 'api');
        }
        return $has_permission;
    }

    public function check_auth($request) {
        global $radleLogs;

        $access_token = get_option($this->github_token_option);

        if (empty($access_token)) {
            $radleLogs->log("GitHub auth check failed: No access token found", 'api');
            return rest_ensure_response([
                'is_authorized' => false,
                'message' => __('No access token found.', 'radle'),
            ]);
        }

        // Determine whether to disable SSL verification
        $sslverify = true;
        if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'local') {
            $sslverify = false;
            $radleLogs->log("SSL verification disabled for local environment", 'api');
        }

        $radleLogs->log("Sending GitHub auth check request", 'api');
        $response = wp_remote_get(RADLE_GBTI_API_SERVER . '/oauth/check-auth', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
            'sslverify' => $sslverify,
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            $radleLogs->log("GitHub auth check request failed: " . $response->get_error_message(), 'api');
            return rest_ensure_response([
                'is_authorized' => false,
                'message' => $response->get_error_message(),
            ]);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['is_authorized']) && $data['is_authorized']) {
            $radleLogs->log("GitHub auth check successful", 'api');
            return rest_ensure_response($data);
        } else {
            $radleLogs->log("GitHub auth check failed: Authorization failed", 'api');
            return rest_ensure_response([
                'is_authorized' => false,
                'message' => __('Authorization failed.', 'radle'),
            ]);
        }
    }
}