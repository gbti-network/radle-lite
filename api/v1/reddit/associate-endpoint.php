<?php

namespace Radle\API\v1\Reddit;

use WP_REST_Controller;
use WP_Error;

/**
 * Handles REST API endpoints for associating WordPress posts with Reddit posts.
 *
 * This class provides functionality to link a WordPress post with its corresponding
 * Reddit post after the Reddit post has been fully processed. This is used when
 * the Reddit API returns a processing state and the final post ID is retrieved
 * via WebSocket monitoring.
 *
 * @since 1.0.0
 */
class Associate_Endpoint extends WP_REST_Controller {

    /**
     * Initialize the endpoint and register routes.
     *
     * Sets up the REST API endpoint for post association with proper
     * validation and permission checks.
     */
    public function __construct() {
        $namespace = 'radle/v1';
        $base = 'reddit/associate';

        register_rest_route($namespace, '/' . $base, [
            [
                'methods' => 'POST',
                'callback' => [$this, 'associate_reddit_post'],
                'permission_callback' => [$this, 'permissions_check'],
                'args' => [
                    'post_id' => [
                        'required' => true,
                        'validate_callback' => function($param, $request, $key) {
                            return is_numeric($param);
                        }
                    ],
                    'reddit_id' => [
                        'required' => true,
                        'validate_callback' => function($param, $request, $key) {
                            return is_string($param) && !empty($param);
                        }
                    ],
                    'reddit_url' => [
                        'required' => false,
                        'validate_callback' => function($param, $request, $key) {
                            return is_string($param);
                        }
                    ]
                ]
            ],
        ]);
    }

    /**
     * Check if the current user has permission to associate the post.
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
            $radleLogs->log("Permission check failed for associating Reddit post. Post ID: $post_id", 'api');
        }

        return $has_permission;
    }

    /**
     * Associate a Reddit post ID with a WordPress post.
     *
     * Stores the Reddit post ID in the post meta, creating the connection
     * between WordPress and Reddit. This is typically called after WebSocket
     * monitoring has retrieved the final post ID from a processing Reddit post.
     *
     * @param \WP_REST_Request $request The request object containing post details.
     * @return \WP_REST_Response|\WP_Error Success message or error on failure.
     */
    public function associate_reddit_post($request) {
        global $radleLogs;

        $post_id = $request->get_param('post_id');
        $reddit_id = $request->get_param('reddit_id');
        $reddit_url = $request->get_param('reddit_url');

        // Double-check permissions as an extra security measure
        if (!current_user_can('edit_post', $post_id)) {
            $radleLogs->log("Unauthorized attempt to associate Reddit post. Post ID: $post_id", 'api');
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permissions to edit this post.', 'radle-lite'),
                ['status' => 403]
            );
        }

        // Validate post exists
        $post = get_post($post_id);
        if (!$post) {
            $radleLogs->log("Attempted to associate non-existent post. Post ID: $post_id", 'api');
            return new WP_Error(
                'invalid_post',
                __('Post not found.', 'radle-lite'),
                ['status' => 404]
            );
        }

        $radleLogs->log("Associating Reddit post with WordPress post. WP Post ID: $post_id, Reddit ID: $reddit_id", 'api');

        // Store the Reddit post ID
        update_post_meta($post_id, '_reddit_post_id', sanitize_text_field($reddit_id));

        $radleLogs->log("Reddit post successfully associated. Post ID: $post_id, Reddit ID: $reddit_id", 'api');

        return rest_ensure_response([
            'success' => true,
            'message' => __('Reddit post associated successfully', 'radle-lite'),
            'reddit_id' => $reddit_id,
            'reddit_url' => $reddit_url
        ]);
    }
}