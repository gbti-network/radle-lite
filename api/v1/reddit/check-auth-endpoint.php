<?php

namespace Radle\API\v1\Reddit;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Error;
use Radle\Modules\Reddit\Reddit_API;

class Check_Auth_Endpoint extends WP_REST_Controller {

    public function __construct() {
        $this->namespace = 'radle/v1';
        $this->rest_base = 'reddit/check-auth';

        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods'  => \WP_REST_Server::READABLE,
                'callback' => [$this, 'check_auth'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    public function check_auth($request) {
        global $radleLogs;
        $redditAPI = Reddit_API::getInstance();

        if (!$redditAPI->is_connected()) {
            $radleLogs->log("Auth check failed: Not connected to Reddit", 'api');
            return new WP_REST_Response([
                'is_authorized' => false,
                'message' => __('Not connected to Reddit.', 'api-reddit-token-management')
            ], 401);
        }

        $user_info = $redditAPI->get_user_info();

        if (is_wp_error($user_info)) {
            $radleLogs->log("Auth check failed: " . $user_info->get_error_message(), 'api-reddit-token-management');
            return new WP_REST_Response([
                'is_authorized' => false,
                'message' => $user_info->get_error_message(),
            ], 500);
        }

        $moderated_subreddits = $redditAPI->get_moderated_subreddits();
        $current_subreddit = get_option('radle_subreddit', '');

        if (is_wp_error($user_info)) {
            $radleLogs->log("Auth check failed: Unable to retrieve user information", 'api-reddit-token-management');
            return new WP_REST_Response([
                'is_authorized' => false,
                'message' => __('Failed to retrieve user information.', 'radle')
            ], 500);
        }

        $radleLogs->log("Auth check successful for user: " . $user_info['name'], 'api-reddit-token-management');
        return new WP_REST_Response([
            'is_authorized' => true,
            'user_info' => [
                'user_name' => $user_info['name'],
                'avatar_url' => $redditAPI->get_profile_picture($user_info['name'], $user_info),
            ],
            'moderated_subreddits' => $moderated_subreddits,
            'current_subreddit' => $current_subreddit
        ], 200);
    }
}