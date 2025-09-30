<?php

namespace Radle\API\v1\reddit;

use WP_REST_Controller;
use WP_Error;
use Radle\Modules\Reddit\Reddit_API;

/**
 * Handles REST API endpoints for resetting Reddit authentication tokens.
 * 
 * This class provides functionality to clear Reddit authentication tokens
 * from WordPress options, effectively logging out the user from Reddit.
 * All actions are logged for debugging and audit purposes.
 * 
 * @since 1.0.0
 */
class Delete_Token_Endpoint extends WP_REST_Controller {

    /**
     * Initialize the endpoint and register routes.
     * 
     * Sets up the REST API endpoint for resetting Reddit tokens
     * with proper permission checks.
     */
    public function __construct() {
        $namespace = 'radle/v1';
        $base = 'reddit/reset-token';

        register_rest_route($namespace, '/' . $base, [
            [
                'methods' => 'POST',
                'callback' => [$this, 'reset_token'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
        ]);
    }

    /**
     * Check if the current user has permission to reset tokens.
     * 
     * Verifies admin capabilities and logs any failed permission checks.
     * 
     * @param \WP_REST_Request $request The request object.
     * @return bool True if user has admin capabilities, false otherwise.
     */
    public function permissions_check($request) {
        global $radleLogs;
        $has_permission = current_user_can('manage_options');
        
        if (!$has_permission) {
            $radleLogs->log("Permission check failed for resetting Reddit token", 'api');
        }
        
        return $has_permission;
    }

    /**
     * Reset Reddit authentication by deleting stored tokens.
     * 
     * Removes both access and refresh tokens from WordPress options,
     * effectively disconnecting the user from Reddit.
     * 
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response Response indicating success status.
     */
    public function reset_token($request) {
        global $radleLogs;

        $radleLogs->log("Attempting to reset Reddit authorization", 'api');

        // Delete both access and refresh tokens
        delete_option('radle_reddit_access_token');
        delete_option('radle_reddit_refresh_token');

        $radleLogs->log("Reddit authorization reset successfully", 'api');
        
        return rest_ensure_response([
            'success' => true,
            'message' => 'Authorization reset successfully'
        ]);
    }
}