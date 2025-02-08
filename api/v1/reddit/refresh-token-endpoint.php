<?php

namespace Radle\API\v1\Reddit;

use WP_REST_Controller;
use WP_Error;
use Radle\Modules\Reddit\Reddit_API;

/**
 * Handles REST API endpoints for refreshing Reddit OAuth tokens.
 * 
 * This class manages the process of refreshing expired Reddit OAuth tokens
 * to maintain continuous authentication. It includes permission checks
 * and comprehensive error logging.
 * 
 * @since 1.0.0
 */
class Refresh_Token_Endpoint extends WP_REST_Controller {

    /**
     * Initialize the endpoint and register routes.
     * 
     * Sets up the REST API endpoint for refreshing Reddit tokens
     * with proper permission checks.
     */
    public function __construct() {
        $namespace = 'radle/v1';
        $base = 'reddit/refresh-token';

        register_rest_route($namespace, '/' . $base, [
            [
                'methods' => 'POST',
                'callback' => [$this, 'refresh_token'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
        ]);
    }

    /**
     * Check if the current user has permission to refresh tokens.
     * 
     * Verifies that the user has edit_posts capability and logs any
     * failed permission checks.
     * 
     * @param \WP_REST_Request $request The request object.
     * @return bool True if user has required capabilities, false otherwise.
     */
    public function permissions_check($request) {
        global $radleLogs;
        $has_permission = current_user_can('edit_posts');
        if (!$has_permission) {
            $radleLogs->log("Permission check failed for refreshing Reddit token", 'api');
        }
        return $has_permission;
    }

    /**
     * Refresh the Reddit OAuth token.
     * 
     * Attempts to refresh the Reddit OAuth token using the stored refresh token.
     * The process includes:
     * 1. Logging the refresh attempt
     * 2. Authenticating with Reddit API
     * 3. Handling success/failure cases
     * 
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error Response object on success, WP_Error on failure.
     */
    public function refresh_token($request) {
        global $radleLogs;

        $radleLogs->log("Attempting to refresh Reddit token", 'api');

        // Attempt to refresh token
        $redditAPI = Reddit_API::getInstance();
        $result = $redditAPI->authenticate();

        // Handle refresh result
        if ($result) {
            $radleLogs->log("Reddit token refreshed successfully", 'api');
            return rest_ensure_response([
                'message' => 'Token refreshed successfully',
                'refresh_needed' => true
            ]);
        } else {
            $radleLogs->log("Failed to refresh Reddit token", 'api');
            return new WP_Error('token_refresh_failed', 'Failed to refresh token', ['status' => 500]);
        }
    }
}