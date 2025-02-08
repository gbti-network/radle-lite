<?php

namespace Radle\API\v1\Reddit;

use WP_REST_Controller;
use WP_Error;
use Radle\Modules\Reddit\Reddit_API;

/**
 * Handles REST API endpoints for publishing WordPress posts to Reddit.
 * 
 * This class manages the process of publishing WordPress posts to Reddit,
 * including title and content processing, duplicate post detection, and
 * error handling. It supports both text and link post types.
 * 
 * @since 1.0.0
 */
class Publish_Endpoint extends WP_REST_Controller {

    /**
     * Initialize the endpoint and register routes.
     * 
     * Sets up the REST API endpoint for publishing posts to Reddit
     * with proper permission checks and argument validation.
     */
    public function __construct() {
        $namespace = 'radle/v1';
        $base = 'reddit/publish';

        register_rest_route($namespace, '/' . $base, [
            [
                'methods' => 'POST',
                'callback' => [$this, 'publish_post'],
                'permission_callback' => [$this, 'permissions_check'],
                'args' => $this->get_endpoint_args_for_item_schema(true),
            ],
        ]);
    }

    /**
     * Check if the current user has permission to publish posts.
     * 
     * Verifies the nonce and checks if the user has edit_posts capability.
     * 
     * @param \WP_REST_Request $request The request object.
     * @return bool True if user has required capabilities, false otherwise.
     */
    public function permissions_check($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        return current_user_can('edit_posts');
    }

    /**
     * Publish a WordPress post to Reddit.
     * 
     * Handles the entire publishing workflow:
     * 1. Validates post ID and subreddit
     * 2. Processes title and content with token replacement
     * 3. Authenticates with Reddit
     * 4. Checks for duplicate posts
     * 5. Publishes as either text or link post
     * 6. Stores Reddit post ID in WordPress meta
     * 
     * @param \WP_REST_Request $request Request object containing post details.
     * @return \WP_REST_Response|\WP_Error Response object on success, WP_Error on failure.
     */
    public function publish_post($request) {
        global $radleLogs;
        $post_id = $request->get_param('post_id');

        // Validate post ID
        if (!$post_id) {
            $radleLogs->log("Invalid post ID provided", 'radle-lite');
            return new WP_Error('no_post_id', 'Invalid post ID', array('status' => 404));
        }

        // Get and validate subreddit
        $subreddit = get_option('radle_subreddit'); // Use the option from settings

        if (empty($subreddit)) {
            $radleLogs->log("Subreddit not specified", 'radle-lite');
            return new WP_Error('no_subreddit', 'Subreddit not specified', array('status' => 400));
        }

        // Get post details
        $title = $request->get_param('title');
        $content = $request->get_param('content');
        $post_type = $request->get_param('post_type');

        // Validate post existence and type
        $post = get_post($post_id);
        if (!$post || 'post' != $post->post_type) {
            $radleLogs->log("Invalid post: $post_id", 'radle-lite');
            return new WP_Error('invalid_post', 'Invalid post', array('status' => 404));
        }

        $redditAPI = Reddit_API::getInstance();

        // Process title and content tokens
        $title = $redditAPI->replace_tokens($title, $post);
        $content = $redditAPI->replace_tokens($content, $post);

        // Authenticate with Reddit
        $authenticated = $redditAPI->authenticate();
        if (!$authenticated) {
            $radleLogs->log("Failed to authenticate with Reddit API", 'radle-lite');
            return new WP_Error('authentication_failed', 'Failed to authenticate with Reddit API', array('status' => 500));
        }

        // Check for duplicate posts
        $existing_post = $redditAPI->search_post_by_title($title, $subreddit);

        if ($existing_post) {
            update_post_meta($post_id, '_reddit_post_id', $existing_post['id']);
            $radleLogs->log(sprintf("Post already exists on Reddit. Associated with Reddit post ID: %s", $existing_post['id']), 'radle-lite');
            return rest_ensure_response([
                'message' => sprintf(
                    /* translators: %s: Reddit post ID */
                    __('Post already exists on Reddit. Associated with Reddit post ID: %s', 'radle-lite'),
                    esc_html($existing_post['id'])
                ),
                'url' => 'https://www.reddit.com/' . esc_attr($existing_post['id'])
            ]);
        } else {
            // Publish post based on type
            if ($post_type === 'link') {
                $url = esc_url_raw(get_permalink($post_id));
                $response = $redditAPI->post_link_to_reddit($title, $url);
            } else {
                $response = $redditAPI->post_to_reddit($title, $content);
            }

            $response = json_decode($response, true);

            $radleLogs->log('Reddit API response: ' . wp_json_encode($response), 'radle-lite');

            // Handle successful publish
            if (isset($response['success']) && $response['success'] == 1) {
                $reddit_post_id = $redditAPI->get_id_from_response($response);

                $radleLogs->log('Reddit post ID: ' . $reddit_post_id, 'radle-lite');

                if ($reddit_post_id) {
                    update_post_meta($post_id, '_reddit_post_id', $reddit_post_id);
                    $radleLogs->log("Post published to Reddit successfully. Post ID: $reddit_post_id", 'radle-lite');
                    return rest_ensure_response([
                        'message' => __('Post published to Reddit successfully','radle-lite'),
                        'url' => 'https://www.reddit.com/r/' . $subreddit . '/comments/' . $reddit_post_id
                    ]);
                } else {
                    $radleLogs->log("Failed to extract post ID from Reddit response", 'radle-lite');
                    return new WP_Error('no_post_id_extracted', 'Failed to extract post ID from Reddit response', array('status' => 500));
                }
            } else {
                // Handle publish error
                $error_message = $redditAPI->get_api_error($response);

                $radleLogs->log('Reddit API publish error: ' . wp_json_encode($response), 'radle-lite');
                $radleLogs->log('Error message extracted: ' . $error_message, 'radle-lite');

                return rest_ensure_response([
                    'code' => 'publish_failed',
                    'message' => 'Failed to publish post to Reddit.',
                    'data' => [
                        'status' => 500,
                        'error_message' => $error_message,
                        'details' => $response
                    ]
                ]);
            }
        }
    }
}