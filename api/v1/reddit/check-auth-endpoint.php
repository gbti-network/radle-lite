<?php

namespace Radle\API\v1\Reddit;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Error;
use Radle\Modules\Reddit\Reddit_API;

/**
 * Handles REST API endpoints for checking Reddit authentication status.
 * 
 * This class provides functionality to verify the current Reddit authentication
 * state, retrieve user information, and get a list of moderated subreddits.
 * All API interactions are logged and rate limits are monitored.
 * 
 * @since 1.0.0
 */
class Check_Auth_Endpoint extends WP_REST_Controller {

    /**
     * The namespace for the REST API endpoints.
     * @var string
     */
    protected $namespace;

    /**
     * The base path for the REST API endpoints.
     * @var string
     */
    protected $rest_base;

    /**
     * Initialize the endpoint and register routes.
     * 
     * Sets up the REST API endpoint for checking Reddit authentication.
     * This endpoint is publicly accessible but requires valid Reddit credentials
     * to return meaningful data.
     */
    public function __construct() {
        $this->namespace = 'radle/v1';
        $this->rest_base = 'reddit/check-auth';

        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods'  => \WP_REST_Server::READABLE,
                'callback' => [$this, 'check_auth'],
                'permission_callback' => function() {
                    return current_user_can('manage_options');
                }
            ],
        ]);
    }

    /**
     * Check Reddit authentication status and retrieve user information.
     * 
     * Performs several checks:
     * 1. Verifies the Reddit API connection
     * 2. Retrieves authenticated user information
     * 3. Gets list of moderated subreddits
     * 4. Checks current subreddit setting
     * 
     * Rate limits are monitored for all API calls and results are logged.
     * 
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response Response with auth status and user info.
     */
    public function check_auth($request) {
        global $radleLogs;
        $redditAPI = Reddit_API::getInstance();

        // Check authentication with Reddit's API
        $endpoint = 'https://oauth.reddit.com/api/v1/me';
        $response = wp_remote_get($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $redditAPI->get_access_token(),
                'User-Agent' => \Radle\Modules\Reddit\User_Agent::get()
            ]
        ]);

        // Monitor rate limits for the API call
        $redditAPI->monitor_rate_limits($response, $endpoint, ['action' => 'check_auth']);

        // Check if connected to Reddit
        if (!$redditAPI->is_connected()) {
            $radleLogs->log("Auth check failed: Not connected to Reddit", 'radle-lite');
            return new WP_REST_Response([
                'is_authorized' => false,
                'message' => __('Not connected to Reddit.', 'radle-lite')
            ], 401);
        }

        // Get user information
        $user_info = $redditAPI->get_user_info();

        if (is_wp_error($user_info)) {
            $radleLogs->log("Auth check failed: " . $user_info->get_error_message(), 'radle-lite');
            return new WP_REST_Response([
                'is_authorized' => false,
                'message' => $user_info->get_error_message(),
            ], 500);
        }

        // Get list of moderated subreddits and current setting
        $moderated_subreddits = $redditAPI->get_moderated_subreddits();
        $current_subreddit = get_option('radle_subreddit', '');

        if (is_wp_error($user_info)) {
            $radleLogs->log("Auth check failed: Unable to retrieve user information", 'radle-lite');
            return new WP_REST_Response([
                'is_authorized' => false,
                'message' => __('Failed to retrieve user information.', 'radle-lite')
            ], 500);
        }

        // Log successful authentication
        $radleLogs->log("Auth check successful for user: " . $user_info['name'], 'radle-lite');
        
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