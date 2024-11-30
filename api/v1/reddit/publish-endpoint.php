<?php

namespace Radle\API\v1\Reddit;

use WP_REST_Controller;
use WP_Error;
use Radle\Modules\Reddit\Reddit_API;

class Publish_Endpoint extends WP_REST_Controller {

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

    public function permissions_check($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        return current_user_can('edit_posts');
    }

    public function publish_post($request) {
        global $radleLogs;
        $post_id = $request->get_param('post_id');

        if (!$post_id) {
            $radleLogs->log("Invalid post ID provided", 'api');
            return new WP_Error('no_post_id', 'Invalid post ID', array('status' => 404));
        }

        $subreddit = get_option('radle_subreddit'); // Use the option from settings

        if (empty($subreddit)) {
            $radleLogs->log("Subreddit not specified", 'api');
            return new WP_Error('no_subreddit', 'Subreddit not specified', array('status' => 400));
        }

        $title = $request->get_param('title');
        $content = $request->get_param('content');
        $post_type = $request->get_param('post_type');

        $post = get_post($post_id);
        if (!$post || 'post' != $post->post_type) {
            $radleLogs->log("Invalid post: $post_id", 'api');
            return new WP_Error('invalid_post', 'Invalid post', array('status' => 404));
        }

        $redditAPI = Reddit_API::getInstance();

        $title = $redditAPI->replace_tokens($title, $post);
        $content = $redditAPI->replace_tokens($content, $post);

        $authenticated = $redditAPI->authenticate();
        if (!$authenticated) {
            $radleLogs->log("Failed to authenticate with Reddit API", 'api');
            return new WP_Error('authentication_failed', 'Failed to authenticate with Reddit API', array('status' => 500));
        }

        $existing_post = $redditAPI->search_post_by_title($title, $subreddit);

        if ($existing_post) {
            update_post_meta($post_id, '_reddit_post_id', $existing_post['id']);
            $radleLogs->log("Post already exists on Reddit. Associated with Reddit post ID: " . $existing_post['id'], 'api');
            return rest_ensure_response(['message' => __('Post already exists on Reddit. Associated with Reddit post ID: ' . $existing_post['id'], 'radle'), 'url' => 'https://www.reddit.com/' . $existing_post['id']]);
        } else {
            if ($post_type === 'link') {
                $url = esc_url_raw(get_permalink($post_id));
                $response = $redditAPI->post_link_to_reddit($title, $url);
            } else {
                $response = $redditAPI->post_to_reddit($title, $content);
            }

            $response = json_decode($response, true);

            $radleLogs->log('Reddit API response: ' . print_r($response, true), 'api');

            if (isset($response['success']) && $response['success'] == 1) {
                $reddit_post_id = $redditAPI->get_id_from_response($response);

                $radleLogs->log('Reddit post ID: ' . $reddit_post_id, 'api');

                if ($reddit_post_id) {
                    update_post_meta($post_id, '_reddit_post_id', $reddit_post_id);
                    $radleLogs->log("Post published to Reddit successfully. Post ID: $reddit_post_id", 'api');
                    return rest_ensure_response([
                        'message' => __('Post published to Reddit successfully', 'radle'),
                        'url' => 'https://www.reddit.com/r/' . $subreddit . '/comments/' . $reddit_post_id
                    ]);
                } else {
                    $radleLogs->log("Failed to extract post ID from Reddit response", 'api');
                    return new WP_Error('no_post_id_extracted', 'Failed to extract post ID from Reddit response', array('status' => 500));
                }
            } else {
                $error_message = $redditAPI->get_api_error($response);

                $radleLogs->log('Reddit API publish error: ' . json_encode($response), 'api');
                $radleLogs->log('Error message extracted: ' . $error_message, 'api');

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