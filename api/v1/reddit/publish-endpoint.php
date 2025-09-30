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
        return (wp_verify_nonce($nonce, 'wp_rest') && current_user_can('edit_posts'));
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
        $images = $request->get_param('images');
        $prepared_assets = $request->get_param('prepared_assets'); // Pre-uploaded asset data from prepare-images endpoint
        $custom_url = $request->get_param('url');

        // Enhanced logging for debugging
        $radleLogs->log("Publishing request details:", 'radle-lite');
        $radleLogs->log("- Post ID: $post_id", 'radle-lite');
        $radleLogs->log("- Post Type: $post_type", 'radle-lite');
        $radleLogs->log("- Title: " . ($title ?: '[empty]'), 'radle-lite');
        $radleLogs->log("- Content: " . (substr($content ?: '[empty]', 0, 100)), 'radle-lite');
        $radleLogs->log("- Custom URL: " . ($custom_url ?: '[empty]'), 'radle-lite');
        $radleLogs->log("- Images: " . (is_array($images) ? implode(',', $images) : ($images ?: '[empty]')), 'radle-lite');
        $radleLogs->log("- Subreddit: $subreddit", 'radle-lite');

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

        // Check if post is already associated with a Reddit post
        $existing_reddit_id = get_post_meta($post_id, '_reddit_post_id', true);
        if (!empty($existing_reddit_id)) {
            // Handle pending state - post is still processing
            if ($existing_reddit_id === 'pending') {
                $radleLogs->log("Post is currently being processed on Reddit", 'radle-lite');
                return rest_ensure_response([
                    'message' => __('Post is currently being processed on Reddit. Please wait a moment and check your Reddit profile.', 'radle-lite'),
                    'processing' => true
                ]);
            }

            // Normal case - post already published with real ID
            $radleLogs->log("Post already associated with Reddit post ID: $existing_reddit_id", 'radle-lite');
            return rest_ensure_response([
                'message' => sprintf(
                    /* translators: %s: Reddit post ID */
                    __('Post already published to Reddit. Reddit post ID: %s', 'radle-lite'),
                    esc_html($existing_reddit_id)
                ),
                'url' => 'https://www.reddit.com/r/' . $subreddit . '/comments/' . esc_attr($existing_reddit_id)
            ]);
        }

        // Check for duplicate posts by searching Reddit
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
                'url' => 'https://www.reddit.com/r/' . $subreddit . '/comments/' . esc_attr($existing_post['id'])
            ]);
        } else {
            // Publish post based on type using switch for cleaner logic
            switch ($post_type) {
                case 'link':
                    // Use custom URL if provided, otherwise use post permalink
                    if (!empty($custom_url)) {
                        $radleLogs->log("Using custom URL: $custom_url", 'radle-lite');
                        // Process tokens in custom URL
                        $url = $redditAPI->replace_tokens($custom_url, $post);
                        $radleLogs->log("After token processing: $url", 'radle-lite');
                    } else {
                        $url = esc_url_raw(get_permalink($post_id));
                        $radleLogs->log("Using post permalink: $url", 'radle-lite');
                    }
                    $radleLogs->log("Final URL for Reddit link post: $url", 'radle-lite');
                    $response = $redditAPI->post_link_to_reddit($title, $url, $content);
                    break;

                case 'image':
                    // Check if assets were pre-prepared (new flow with WebSocket polling)
                    if (!empty($prepared_assets)) {
                        $radleLogs->log("Using pre-prepared assets for image post", 'radle-lite');
                        $response = $this->publish_with_prepared_assets($title, $content, $prepared_assets, $subreddit);
                    } else {
                        // Legacy flow: upload images during publish (no WebSocket polling)
                        $radleLogs->log("Processing image post with " . (is_array($images) ? count($images) . " images" : "no images"), 'radle-lite');
                        $response = $this->publish_image_post($title, $content, $images, $subreddit);
                    }
                    $radleLogs->log("Image post response received: " . substr($response, 0, 200), 'radle-lite');
                    break;

                default: // 'self' or any other type defaults to text post
                    $response = $redditAPI->post_to_reddit($title, $content);
                    break;
            }

            $response = json_decode($response, true);

            $radleLogs->log('Reddit API response: ' . wp_json_encode($response), 'radle-lite');

            // Handle successful publish - check both our custom format and Reddit's native format
            $is_success = false;
            if (isset($response['success'])) {
                // Our custom wrapper format (image posts)
                $is_success = ($response['success'] == 1);
            } elseif (isset($response['json'])) {
                // Reddit's native format (link/text posts) - success if no errors
                $is_success = empty($response['json']['errors']);
            }

            if ($is_success) {
                // Try to get reddit_id from our custom response first, then fallback to standard extraction
                $reddit_post_id = $response['reddit_id'] ?? $redditAPI->get_id_from_response($response);

                // If still no ID but we have a URL, extract from URL
                if (!$reddit_post_id && !empty($response['reddit_url'])) {
                    if (preg_match('~/comments/([a-z0-9]+)/~i', $response['reddit_url'], $matches)) {
                        $reddit_post_id = $matches[1];
                    }
                }

                $radleLogs->log('Reddit post ID: ' . $reddit_post_id, 'radle-lite');

                // Handle post in processing state (image posts may not have ID immediately)
                if (!$reddit_post_id && !empty($response['processing'])) {
                    $radleLogs->log("Post submitted successfully but is still processing (no ID yet)", 'radle-lite');

                    // Save a pending flag to prevent duplicate submissions while processing
                    update_post_meta($post_id, '_reddit_post_id', 'pending');
                    $radleLogs->log("Saved 'pending' state to post meta to prevent duplicate submissions", 'radle-lite');

                    $response_data = [
                        'message' => $response['message'] ?? __('Post submitted successfully. Post is processing and will appear shortly on Reddit.', 'radle-lite'),
                        'processing' => true,
                        'websocket_url' => $response['websocket_url'] ?? ''
                    ];

                    return rest_ensure_response($response_data);
                }

                if ($reddit_post_id) {
                    update_post_meta($post_id, '_reddit_post_id', $reddit_post_id);
                    $radleLogs->log("Post published to Reddit successfully. Post ID: $reddit_post_id", 'radle-lite');

                    // Use provided URL or construct one
                    $reddit_url = $response['reddit_url'] ?? ('https://www.reddit.com/r/' . $subreddit . '/comments/' . $reddit_post_id);

                    $response_data = [
                        'message' => __('Post published to Reddit successfully','radle-lite'),
                        'url' => $reddit_url
                    ];

                    // Add custom message if present (e.g., fallback scenario)
                    if (!empty($response['message'])) {
                        $response_data['message'] = $response['message'];
                    }

                    return rest_ensure_response($response_data);
                } else {
                    $radleLogs->log("Failed to extract post ID from Reddit response", 'radle-lite');
                    return new WP_Error('no_post_id_extracted', 'Failed to extract post ID from Reddit response', array('status' => 500));
                }
            } else {
                // Handle publish error - extract message from appropriate format
                $error_message = '';
                if (isset($response['errors']) && !empty($response['errors'])) {
                    // Our custom error format
                    $error_message = $response['errors'][0][1] ?? 'Unknown error';
                } elseif (isset($response['json']['errors']) && !empty($response['json']['errors'])) {
                    // Reddit's native error format
                    $error_message = $response['json']['errors'][0][1] ?? 'Reddit API error';
                } else {
                    // Fallback to get_api_error method
                    $error_message = $redditAPI->get_api_error($response);
                }

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

    /**
     * Publish post with pre-prepared assets (uploaded via prepare-images endpoint)
     *
     * @param string $title Post title
     * @param string $content Post description
     * @param array $prepared_assets Array of pre-uploaded asset data
     * @param string $subreddit Target subreddit
     * @return string JSON response
     */
    private function publish_with_prepared_assets($title, $content, $prepared_assets, $subreddit) {
        global $radleLogs;

        $radleLogs->log("publish_with_prepared_assets() started with " . count($prepared_assets) . " assets", 'radle-lite');

        // Initialize uploader for submission method
        try {
            $uploader = new \Radle\Modules\Reddit\Image_Upload();
        } catch (\Exception $e) {
            $radleLogs->log("Failed to initialize image uploader: " . $e->getMessage(), 'radle-lite');
            return json_encode([
                'success' => 0,
                'errors'  => [['INIT_ERROR', 'Failed to initialize image uploader: ' . $e->getMessage(), null]],
            ]);
        }

        // Transform prepared assets to expected format
        $assets = [];
        $s3_urls = [];  // Track S3 URLs for single image submission
        foreach ($prepared_assets as $asset) {
            if (isset($asset['asset_id'], $asset['mime_type'])) {
                $assets[] = [
                    'id' => $asset['asset_id'],
                    'mime' => $asset['mime_type']
                ];
                // Store S3 URL if available (for PRAW-style single image)
                if (!empty($asset['s3_url'])) {
                    $s3_urls[] = $asset['s3_url'];
                }
                $radleLogs->log("Added pre-prepared asset: {$asset['asset_id']}", 'radle-lite');
            }
        }

        if (empty($assets)) {
            return json_encode([
                'success' => 0,
                'errors'  => [['NO_VALID_ASSETS', __('No valid pre-prepared assets', 'radle-lite'), null]],
            ]);
        }

        // Choose submission method based on image count (PRAW approach)
        if (count($assets) > 1) {
            // Use gallery API for multi-image posts (PRAW approach - faster, true galleries)
            $radleLogs->log("Using gallery API for " . count($assets) . " images", 'radle-lite');
            $result = $uploader->submit_gallery($title, $subreddit, $assets, $content);
        } else {
            // Use PRAW submit_image approach for single image (kind='image' with S3 URL)
            $radleLogs->log("Using PRAW image submission for single image", 'radle-lite');
            if (!empty($s3_urls[0])) {
                $result = $uploader->submit_image_post($title, $subreddit, $s3_urls[0], $content);
            } else {
                // Fallback to RTJSON if S3 URL not available
                $radleLogs->log("S3 URL not available, falling back to RTJSON", 'radle-lite');
                $result = $uploader->submit_self_with_embedded_media($title, $subreddit, $assets, $content);
            }
        }

        if (!$result['success']) {
            return json_encode([
                'success' => 0,
                'errors'  => [['SUBMIT_FAILED', $result['error'] ?? __('Unknown error','radle-lite'), null]],
            ]);
        }

        // Extract reddit_id from URL if available
        $reddit_id = '';
        if (!empty($result['reddit_url']) && preg_match('~/comments/([a-z0-9]+)/~i', $result['reddit_url'], $matches)) {
            $reddit_id = $matches[1];
        }

        $image_count = count($assets);
        $submission_type = count($assets) > 1 ? 'gallery' : 'image';

        // Handle processing state for image posts
        if (!empty($result['processing'])) {
            $message = __('Image posted successfully. Post is processing and will appear shortly on Reddit.', 'radle-lite');
        } elseif ($submission_type === 'gallery') {
            /* translators: %d: number of images posted in the gallery */
            $message = sprintf(__('%d images posted as gallery successfully.', 'radle-lite'), $image_count);
        } else {
            $message = __('Image posted successfully with body text.', 'radle-lite');
        }

        return json_encode([
            'success'    => 1,
            'data'       => $result['data'] ?? [],
            'reddit_url' => $result['reddit_url'] ?? '',
            'reddit_id'  => $reddit_id,
            'message'    => $message,
            'submission_type' => $submission_type,
            'processing' => !empty($result['processing']),
            'websocket_url' => $result['websocket_url'] ?? ''
        ]);
    }

    /**
     * Publish images to Reddit using self+RTJSON approach (always includes body text)
     *
     * Handles the image upload workflow:
     * 1. Validates attachment IDs
     * 2. Uploads images to Reddit via media asset API
     * 3. Submits as self post with RTJSON embedded media (matches web UI behavior)
     *
     * @param string $title Post title
     * @param string $content Post description (always included)
     * @param array $images Array of WordPress attachment IDs
     * @param string $subreddit Target subreddit
     * @return string JSON response from Reddit API
     */
    private function publish_image_post($title, $content, $images, $subreddit) {
        global $radleLogs;

        $radleLogs->log("publish_image_post() started", 'radle-lite');

        // Normalize images -> array of ints
        $image_ids = is_array($images) ? $images : explode(',', (string)$images);
        $image_ids = array_values(array_filter(array_map('absint', array_map('trim', $image_ids))));

        if (empty($image_ids)) {
            $radleLogs->log("No images provided - returning error", 'radle-lite');
            return json_encode([
                'success' => 0,
                'errors'  => [['NO_IMAGES', __('No images provided', 'radle-lite'), null]],
            ]);
        }

        // Initialize uploader
        try {
            $uploader = new \Radle\Modules\Reddit\Image_Upload();
            $radleLogs->log("Image upload handler initialized successfully", 'radle-lite');
        } catch (\Exception $e) {
            $radleLogs->log("Failed to initialize image uploader: " . $e->getMessage(), 'radle-lite');
            return json_encode([
                'success' => 0,
                'errors'  => [['INIT_ERROR', 'Failed to initialize image uploader: ' . $e->getMessage(), null]],
            ]);
        }

        $radleLogs->log("Processing " . count($image_ids) . " images using unified self+RTJSON approach", 'radle-lite');

        // Upload all assets and collect asset data (ID + MIME type)
        $assets = [];
        foreach ($image_ids as $idx => $attachment_id) {
            if (!wp_attachment_is_image($attachment_id)) {
                $radleLogs->log("Skipping non-image attachment: $attachment_id", 'radle-lite');
                continue;
            }

            $radleLogs->log("Processing image " . ($idx + 1) . "/" . count($image_ids) . ": $attachment_id", 'radle-lite');

            // Validate image
            $validation = $uploader->validate_image($attachment_id);
            if (!$validation['success']) {
                $radleLogs->log("Validation failed for $attachment_id: " . ($validation['error'] ?? ''), 'radle-lite');
                continue;
            }

            // Request media asset
            $radleLogs->log("Requesting media asset for image $attachment_id", 'radle-lite');
            $asset_req = $uploader->request_media_asset($validation['file_path'], $validation['mime_type']);
            if (!$asset_req['success']) {
                $radleLogs->log("Asset request failed for $attachment_id: " . ($asset_req['error'] ?? ''), 'radle-lite');
                continue;
            }

            // Upload to Reddit CDN
            $radleLogs->log("Uploading to Reddit CDN for image $attachment_id", 'radle-lite');
            $upload = $uploader->upload_to_reddit_cdn($validation['file_path'], $asset_req['data']);
            if (!$upload['success']) {
                $radleLogs->log("CDN upload failed for $attachment_id: " . ($upload['error'] ?? ''), 'radle-lite');
                continue;
            }

            // Wait for asset completion (extended timeout)
            $radleLogs->log("Waiting for asset completion: {$upload['asset_id']}", 'radle-lite');
            $asset_completed = $uploader->wait_for_asset_completion($upload['asset_id']);
            if (!$asset_completed) {
                $radleLogs->log("Asset {$upload['asset_id']} failed to complete processing, skipping", 'radle-lite');
                continue;
            }

            // Store asset ID + MIME type for proper media_metadata
            $assets[] = [
                'id'   => $upload['asset_id'],
                'mime' => $validation['mime_type']
            ];
            $radleLogs->log("Successfully processed asset: {$upload['asset_id']} (MIME: {$validation['mime_type']})", 'radle-lite');
        }

        if (empty($assets)) {
            return json_encode([
                'success' => 0,
                'errors'  => [['NO_VALID_ASSETS', __('No valid assets processed', 'radle-lite'), null]],
            ]);
        }

        $radleLogs->log("Successfully processed " . count($assets) . " assets", 'radle-lite');

        // Choose submission method based on image count (PRAW approach)
        if (count($assets) > 1) {
            // Use gallery API for multi-image posts (PRAW approach - faster, true galleries)
            $radleLogs->log("Using gallery API for " . count($assets) . " images", 'radle-lite');
            $result = $uploader->submit_gallery($title, $subreddit, $assets, $content);
        } else {
            // Use RTJSON for single image (current approach - inline with text)
            $radleLogs->log("Using RTJSON for single image", 'radle-lite');
            $result = $uploader->submit_self_with_embedded_media($title, $subreddit, $assets, $content);
        }

        if (!$result['success']) {
            return json_encode([
                'success' => 0,
                'errors'  => [['SUBMIT_FAILED', $result['error'] ?? __('Unknown error','radle-lite'), null]],
            ]);
        }

        // Extract reddit_id from URL if available
        $reddit_id = '';
        if (!empty($result['reddit_url']) && preg_match('~/comments/([a-z0-9]+)/~i', $result['reddit_url'], $matches)) {
            $reddit_id = $matches[1];
        }

        $image_count = count($assets);
        $submission_type = count($assets) > 1 ? 'gallery' : 'image';

        // Handle processing state for image posts
        if (!empty($result['processing'])) {
            $message = __('Image posted successfully. Post is processing and will appear shortly on Reddit.', 'radle-lite');
        } elseif ($submission_type === 'gallery') {
            /* translators: %d: number of images posted in the gallery */
            $message = sprintf(__('%d images posted as gallery successfully.', 'radle-lite'), $image_count);
        } else {
            $message = __('Image posted successfully with body text.', 'radle-lite');
        }

        return json_encode([
            'success'    => 1,
            'data'       => $result['data'] ?? [],
            'reddit_url' => $result['reddit_url'] ?? '',
            'reddit_id'  => $reddit_id,
            'message'    => $message,
            'submission_type' => $submission_type,
            'processing' => !empty($result['processing']),
            'websocket_url' => $result['websocket_url'] ?? ''
        ]);
    }
}