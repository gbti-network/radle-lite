<?php

namespace Radle\API\v1\Reddit;

use WP_REST_Controller;
use WP_Error;
use Radle\Modules\Reddit\Reddit_API;

class OAuth_Callback_Endpoint extends WP_REST_Controller {

    public function __construct() {
        $this->namespace = 'radle/v1';
        $this->rest_base = 'reddit/oauth-callback';

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

        $radleLogs->log("Handling Reddit OAuth callback", 'api');

        $redditAPI = Reddit_API::getInstance();
        $result = $redditAPI->handle_authorization_response();

        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
            $radleLogs->log("Reddit OAuth error: $error_message", 'api');
            return new WP_Error('oauth_error', $error_message, ['status' => 400]);
        }

        $redirect_to = $request->get_param('state');
        if ($redirect_to === 'welcome') {
            $redirect_url = admin_url('admin.php?page=radle-welcome&step=7');
            $radleLogs->log("Redirecting to welcome page after successful OAuth", 'api');
        } else {
            $redirect_url = admin_url('options-general.php?page=radle-settings&tab=reddit');
            $radleLogs->log("Redirecting to settings page after successful OAuth", 'api');
        }

        wp_redirect($redirect_url);
        exit;
    }
}