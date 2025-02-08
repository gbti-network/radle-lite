<?php

namespace Radle\API\v1\Reddit;

use WP_REST_Controller;
use WP_Error;
use Radle\Modules\Reddit\Reddit_API;

/**
 * Handles REST API endpoints for retrieving Reddit entries.
 * 
 * This class provides functionality to fetch posts from a specified subreddit
 * using the Reddit API. All API interactions are logged for debugging and
 * monitoring purposes.
 * 
 * @since 1.0.0
 */
class Entries_Endpoint extends WP_REST_Controller {

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
     * Sets up the REST API endpoint for retrieving Reddit entries
     * with proper permission checks.
     */
    public function __construct() {
        $this->namespace = 'radle/v1';
        $this->rest_base = 'reddit/get-entries';

        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods'  => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_entries'],
                'permission_callback' => [$this, 'get_entries_permissions_check'],
            ],
        ]);
    }

    /**
     * Check if the current user has permission to fetch Reddit entries.
     * 
     * Verifies admin capabilities and logs any failed permission checks.
     * 
     * @param \WP_REST_Request $request The request object.
     * @return bool True if user has admin capabilities, false otherwise.
     */
    public function get_entries_permissions_check($request) {
        global $radleLogs;
        $has_permission = current_user_can('manage_options');
        
        if (!$has_permission) {
            $radleLogs->log("Permission check failed for fetching Reddit entries", 'api');
        }
        
        return $has_permission;
    }

    /**
     * Retrieve entries from the configured subreddit.
     * 
     * Fetches posts from Reddit using the configured subreddit setting.
     * Requires a subreddit to be set in WordPress options. All API
     * interactions are logged for monitoring.
     * 
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error Response with entries or error if fetch fails.
     */
    public function get_entries($request) {
        global $radleLogs;

        $redditAPI = Reddit_API::getInstance();
        $subreddit = get_option('radle_subreddit', '');

        // Validate subreddit is configured
        if (empty($subreddit)) {
            $radleLogs->log("Failed to fetch Reddit entries: No subreddit selected", 'api');
            return new WP_Error(
                'no_subreddit',
                __('No subreddit selected.', 'radle-lite'),
                ['status' => 400]
            );
        }

        $radleLogs->log("Fetching entries for subreddit: $subreddit", 'api');
        $entries = $redditAPI->get_subreddit_entries($subreddit);

        // Handle API errors
        if (is_wp_error($entries)) {
            $error_message = $entries->get_error_message();
            $radleLogs->log("Error fetching Reddit entries: $error_message", 'api');
            return $entries;
        }

        // Log successful fetch
        $entry_count = count($entries);
        $radleLogs->log("Successfully fetched $entry_count entries from subreddit: $subreddit", 'api');
        
        return rest_ensure_response($entries);
    }
}