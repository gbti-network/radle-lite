<?php

namespace Radle\API\v1\radle;

use WP_REST_Controller;
use WP_Error;
use Radle\Modules\Reddit\Reddit_API;

/**
 * Handles REST API endpoints for disassociating WordPress posts from Reddit.
 * 
 * This class provides functionality to remove the connection between a WordPress post
 * and its corresponding Reddit post, effectively unlinking them in the database.
 * All operations are logged for debugging and audit purposes.
 * 
 * @since 1.0.0
 */
class Disassociate_Endpoint extends WP_REST_Controller {

    /**
     * Initialize the endpoint and register routes.
     * 
     * Sets up the REST API endpoint for post disassociation with proper
     * validation and permission checks.
     */
    public function __construct() {
        $namespace = 'radle/v1';
        $base = 'disassociate';

        register_rest_route($namespace, '/' . $base, [
            [
                'methods' => 'POST',
                'callback' => [$this, 'delete_reddit_id'],
                'permission_callback' => [$this, 'permissions_check'],
                'args' => [
                    'post_id' => [
                        'required' => true,
                        'validate_callback' => function($param, $request, $key) {
                            return is_numeric($param);
                        }
                    ]
                ]
            ],
        ]);
    }

    /**
     * Check if the current user has permission to disassociate the post.
     * 
     * Verifies that the user has edit permissions for the specified post
     * and logs any failed permission checks.
     * 
     * @param \WP_REST_Request $request The request object containing the post ID.
     * @return bool True if user has permission, false otherwise.
     */
    public function permissions_check($request) {
        global $radleLogs;

        $post_id = $request->get_param('post_id');
        $has_permission = current_user_can('edit_post', $post_id);
        
        if (!$has_permission) {
            $radleLogs->log("Permission check failed for disassociating Reddit post. Post ID: $post_id", 'api');
        }
        
        return $has_permission;
    }

    /**
     * Remove the Reddit post ID association from a WordPress post.
     * 
     * Deletes the '_reddit_post_id' meta key from the specified post,
     * effectively removing its connection to Reddit. All steps are logged
     * for tracking purposes.
     * 
     * @param \WP_REST_Request $request The request object containing the post ID.
     * @return \WP_REST_Response|\WP_Error Success message or error on failure.
     */
    public function delete_reddit_id($request) {
        global $radleLogs;
        $post_id = $request->get_param('post_id');

        // Double-check permissions as an extra security measure
        if (!current_user_can('edit_post', $post_id)) {
            $radleLogs->log("Unauthorized attempt to disassociate Reddit post. Post ID: $post_id", 'api');
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permissions to edit this post.', 'radle-lite'),
                ['status' => 403]
            );
        }

        $radleLogs->log("Disassociating Reddit post. Post ID: $post_id", 'api');
        delete_post_meta($post_id, '_reddit_post_id');

        $radleLogs->log("Reddit post successfully disassociated. Post ID: $post_id", 'api');
        return rest_ensure_response([
            'message' => __('Reddit post disassociated', 'radle-lite')
        ]);
    }
}