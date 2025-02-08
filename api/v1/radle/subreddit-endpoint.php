<?php

namespace Radle\API\v1\Radle;

use WP_REST_Controller;
use WP_Error;

/**
 * Handles REST API endpoints for managing the target subreddit.
 * 
 * This class provides functionality to set and update the subreddit
 * where posts will be shared. The subreddit setting is stored as
 * a WordPress option.
 * 
 * @since 1.0.0
 */
class SubReddit_Endpoint extends WP_REST_Controller {

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
     * Sets up the REST API endpoint for updating the target subreddit
     * with proper permission checks.
     */
    public function __construct() {
        $this->namespace = 'radle/v1';
        $this->rest_base = 'radle/set-subreddit';

        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => 'POST',
                'callback' => [$this, 'set_subreddit'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
        ]);
    }

    /**
     * Check if the current user has permission to modify subreddit settings.
     * 
     * @param \WP_REST_Request $request The request object.
     * @return bool True if user has admin capabilities, false otherwise.
     */
    public function permissions_check($request) {
        return current_user_can('manage_options');
    }

    /**
     * Update the target subreddit setting.
     * 
     * Validates and stores the provided subreddit name in WordPress options.
     * The subreddit name must not be empty.
     * 
     * @param \WP_REST_Request $request Request object containing the subreddit parameter.
     * @return \WP_REST_Response|\WP_Error Success response or error if validation fails.
     */
    public function set_subreddit($request) {
        $subreddit = $request->get_param('subreddit');

        // Validate subreddit is not empty
        if (empty($subreddit)) {
            return new WP_Error(
                'invalid_subreddit',
                __('Invalid subreddit', 'radle-lite'),
                ['status' => 400]
            );
        }

        // Update the subreddit setting
        update_option('radle_subreddit', $subreddit);

        return rest_ensure_response([
            'success' => true,
            'message' => __('Subreddit updated successfully', 'radle-lite')
        ]);
    }
}