<?php

namespace Radle\API\v1\radle;

use WP_REST_Controller;
use WP_Error;

/**
 * Handles REST API endpoints for managing Reddit comment visibility.
 * 
 * This class provides functionality to toggle the visibility of Reddit comments
 * on WordPress posts. Comments can be hidden or shown through a toggle mechanism,
 * with the state being stored in post meta.
 * 
 * @since 1.0.0
 */
class Hide_Comment_Endpoint extends WP_REST_Controller {

    /**
     * Initialize the endpoint and register routes.
     * 
     * Sets up the REST API endpoint for toggling comment visibility with
     * proper validation for post ID and comment ID parameters.
     */
    public function __construct() {
        $namespace = 'radle/v1';
        $base = 'hide-comment';

        register_rest_route($namespace, '/' . $base, [
            [
                'methods' => 'POST',
                'callback' => [$this, 'toggle_comment_visibility'],
                'permission_callback' => [$this, 'permissions_check'],
                'args' => [
                    'post_id' => [
                        'required' => true,
                        'validate_callback' => function ($param) {
                            return is_numeric($param);
                        }
                    ],
                    'comment_id' => [
                        'required' => true,
                        'validate_callback' => function ($param) {
                            return is_string($param);
                        }
                    ]
                ]
            ],
        ]);
    }

    /**
     * Check if the current user has permission to manage comments.
     * 
     * @param \WP_REST_Request $request The request object.
     * @return bool True if user can edit posts, false otherwise.
     */
    public function permissions_check($request) {
        return current_user_can('edit_posts');
    }

    /**
     * Toggle the visibility state of a Reddit comment.
     * 
     * This method manages a list of hidden comment IDs stored in post meta.
     * If the comment ID exists in the list, it will be removed (shown);
     * if it doesn't exist, it will be added (hidden).
     * 
     * @param \WP_REST_Request $request Request object containing post_id and comment_id.
     * @return \WP_REST_Response Response containing success status and updated hidden comments list.
     */
    public function toggle_comment_visibility($request) {
        $post_id = $request->get_param('post_id');
        $comment_id = $request->get_param('comment_id');

        // Get current list of hidden comments
        $hidden_comments = get_post_meta($post_id, '_radle_hidden_comments', true);
        if (!is_array($hidden_comments)) {
            $hidden_comments = [];
        }

        // Toggle comment visibility state
        $index = array_search($comment_id, $hidden_comments);
        if ($index !== false) {
            unset($hidden_comments[$index]);
            $action = 'shown';
        } else {
            $hidden_comments[] = $comment_id;
            $action = 'hidden';
        }

        // Update the hidden comments list and reindex array
        update_post_meta($post_id, '_radle_hidden_comments', array_values($hidden_comments));

        return rest_ensure_response([
            'success' => true,
            'action' => $action,
            'hidden_comments' => $hidden_comments
        ]);
    }
}