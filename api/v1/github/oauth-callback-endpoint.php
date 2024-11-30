<?php

namespace Radle\API\v1\GitHub;

use WP_REST_Controller;
use WP_Error;

class OAuth_Callback_Endpoint extends WP_REST_Controller {

    public function __construct() {
        $this->namespace = 'radle/v1';
        $this->rest_base = 'github/oauth-callback';

        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods'  => \WP_REST_Server::READABLE,
                'callback' => [$this, 'handle_callback'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    public function handle_callback($request) {
        global $radleLogs;

        // Get the access token from the request
        $access_token = $request->get_param('access_token');

        if (empty($access_token)) {
            $radleLogs->log("GitHub OAuth callback failed: Access token is missing", 'api');
            return new WP_Error('missing_access_token', __('Access token is missing.', 'radle'), ['status' => 400]);
        }

        // Store the access token in the database
        $sanitized_token = sanitize_text_field($access_token);
        $update_result = update_option('radle_github_access_token', $sanitized_token);

        if ($update_result) {
            $radleLogs->log("GitHub access token successfully stored", 'api');
        } else {
            $radleLogs->log("Failed to store GitHub access token", 'api');
        }

        // Check if we're in the welcome flow
        $state = $request->get_param('state');
        if ($state === 'welcome') {
            $redirect_url = admin_url('admin.php?page=radle-welcome');
            $radleLogs->log("Redirecting to welcome page after GitHub OAuth callback", 'api');
        } else {
            $redirect_url = admin_url('options-general.php?page=radle-settings&tab=github');
            $radleLogs->log("Redirecting to settings page after GitHub OAuth callback", 'api');
        }

        wp_redirect($redirect_url);
        exit;
    }
}