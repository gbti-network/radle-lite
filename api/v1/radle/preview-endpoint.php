<?php

namespace Radle\API\v1\radle;

use WP_REST_Controller;
use WP_Error;
use Radle\Modules\Reddit\Reddit_API;

/**
 * Handles REST API endpoints for post preview functionality.
 * 
 * This class manages the preview generation for posts before they are
 * submitted to Reddit, including token replacement and content formatting.
 * 
 * @since 1.0.0
 */
class Preview_Endpoint extends WP_REST_Controller {

    /**
     * Initialize the endpoint and register routes.
     */
    public function __construct() {
        $namespace = 'radle/v1';
        $base = 'preview';

        register_rest_route($namespace, '/' . $base, [
            [
                'methods' => 'POST',
                'callback' => [$this, 'preview_post'],
                'permission_callback' => [$this, 'permissions_check'],
                'args' => $this->get_endpoint_args_for_item_schema(true),
            ],
        ]);
    }

    /**
     * Check if the current user has permission to preview posts.
     * 
     * @param \WP_REST_Request $request The request object.
     * @return bool True if user can edit posts, false otherwise.
     */
    public function permissions_check($request) {
        return current_user_can('edit_posts');
    }

    /**
     * Generate a preview of how the post will appear on Reddit.
     *
     * Takes a post's title and content, replaces any tokens with actual values,
     * and returns the formatted preview content.
     *
     * @param \WP_REST_Request $request Request object containing post data.
     * @return \WP_REST_Response|\WP_Error Response with preview data or error.
     */
    public function preview_post($request) {
        $title = $request->get_param('title');
        $content = $request->get_param('content');
        $post_id = $request->get_param('post_id');
        $post_type = $request->get_param('post_type');
        $images = $request->get_param('images');

        // Validate post exists and is the correct type
        $post = get_post($post_id);
        if (!$post || 'post' != $post->post_type) {
            return new WP_Error(
                'invalid_post',
                'Invalid post',
                array('status' => 404)
            );
        }

        $redditAPI = Reddit_API::getInstance();

        // Replace tokens in title and content with actual values
        $title = $redditAPI->replace_tokens($title, $post);
        $content = $redditAPI->replace_tokens($content, $post);

        $response = [
            'title' => $title,
            'content' => $content
        ];

        // Handle image posts - add image data
        if ($post_type === 'image' && !empty($images) && is_array($images)) {
            $image_data = [];

            foreach ($images as $image_id) {
                $attachment_id = intval($image_id);
                $image_url = wp_get_attachment_image_url($attachment_id, 'large');
                $image_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);

                if ($image_url) {
                    $image_data[] = [
                        'id' => $attachment_id,
                        'url' => $image_url,
                        'alt' => $image_alt ?: $title
                    ];
                }
            }

            $response['images'] = $image_data;
        }

        return rest_ensure_response($response);
    }
}
