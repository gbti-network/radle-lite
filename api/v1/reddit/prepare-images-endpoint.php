<?php

namespace Radle\API\v1\Reddit;

use WP_REST_Controller;
use WP_Error;

/**
 * Handles REST API endpoint for preparing images for Reddit upload.
 *
 * This endpoint uploads images to Reddit's CDN and returns WebSocket URLs
 * for the browser to monitor asset processing status before final submission.
 *
 * @since 1.0.14
 */
class Prepare_Images_Endpoint extends WP_REST_Controller {

    /**
     * Initialize the endpoint and register routes.
     */
    public function __construct() {
        $namespace = 'radle/v1';
        $base = 'reddit/prepare-images';

        register_rest_route($namespace, '/' . $base, [
            [
                'methods' => 'POST',
                'callback' => [$this, 'prepare_images'],
                'permission_callback' => [$this, 'permissions_check'],
                'args' => $this->get_endpoint_args_for_item_schema(true),
            ],
        ]);
    }

    /**
     * Check if the current user has permission to upload images.
     *
     * @param \WP_REST_Request $request The request object.
     * @return bool True if user has required capabilities, false otherwise.
     */
    public function permissions_check($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        return (wp_verify_nonce($nonce, 'wp_rest') && current_user_can('edit_posts'));
    }

    /**
     * Prepare images for Reddit by uploading to CDN.
     *
     * Returns asset IDs and WebSocket URLs for browser-based status monitoring.
     *
     * @param \WP_REST_Request $request Request object containing image IDs.
     * @return \WP_REST_Response|WP_Error Response object with asset data on success, WP_Error on failure.
     */
    public function prepare_images($request) {
        global $radleLogs;

        $images = $request->get_param('images');

        if (empty($images)) {
            $radleLogs->log("No images provided to prepare-images endpoint", 'radle-lite');
            return new WP_Error('no_images', 'No images provided', array('status' => 400));
        }

        // Normalize to array of ints
        $image_ids = is_array($images) ? $images : explode(',', (string)$images);
        $image_ids = array_values(array_filter(array_map('absint', array_map('trim', $image_ids))));

        if (empty($image_ids)) {
            $radleLogs->log("No valid image IDs after normalization", 'radle-lite');
            return new WP_Error('no_valid_images', 'No valid image IDs provided', array('status' => 400));
        }

        $radleLogs->log("Preparing " . count($image_ids) . " images for Reddit upload", 'radle-lite');

        // Initialize uploader
        try {
            $uploader = new \Radle\Modules\Reddit\Image_Upload();
        } catch (\Exception $e) {
            $radleLogs->log("Failed to initialize image uploader: " . $e->getMessage(), 'radle-lite');
            return new WP_Error('init_error', 'Failed to initialize image uploader', array('status' => 500));
        }

        $assets = [];
        $errors = [];

        foreach ($image_ids as $idx => $attachment_id) {
            if (!wp_attachment_is_image($attachment_id)) {
                $radleLogs->log("Skipping non-image attachment: $attachment_id", 'radle-lite');
                $errors[] = sprintf('Attachment %d is not an image', $attachment_id);
                continue;
            }

            $radleLogs->log("Processing image " . ($idx + 1) . "/" . count($image_ids) . ": $attachment_id", 'radle-lite');

            // Validate image
            $validation = $uploader->validate_image($attachment_id);
            if (!$validation['success']) {
                $radleLogs->log("Validation failed for $attachment_id: " . ($validation['error'] ?? ''), 'radle-lite');
                $errors[] = sprintf('Image %d validation failed: %s', $attachment_id, $validation['error'] ?? 'Unknown error');
                continue;
            }

            // Request media asset
            $radleLogs->log("Requesting media asset for image $attachment_id", 'radle-lite');
            $asset_req = $uploader->request_media_asset($validation['file_path'], $validation['mime_type']);
            if (!$asset_req['success']) {
                $radleLogs->log("Asset request failed for $attachment_id: " . ($asset_req['error'] ?? ''), 'radle-lite');
                $errors[] = sprintf('Asset request failed for image %d: %s', $attachment_id, $asset_req['error'] ?? 'Unknown error');
                continue;
            }

            // Upload to Reddit CDN
            $radleLogs->log("Uploading to Reddit CDN for image $attachment_id", 'radle-lite');
            $upload = $uploader->upload_to_reddit_cdn($validation['file_path'], $asset_req['data']);
            if (!$upload['success']) {
                $radleLogs->log("CDN upload failed for $attachment_id: " . ($upload['error'] ?? ''), 'radle-lite');
                $errors[] = sprintf('CDN upload failed for image %d: %s', $attachment_id, $upload['error'] ?? 'Unknown error');
                continue;
            }

            // Extract WebSocket URL from asset data (for future use if needed)
            $websocket_url = $asset_req['data']['asset']['websocket_url'] ?? '';

            $assets[] = [
                'attachment_id' => $attachment_id,
                'asset_id' => $upload['asset_id'],
                's3_url' => $upload['s3_location_url'] ?? '',  // S3 URL for PRAW-style submission
                'mime_type' => $validation['mime_type'],
                'websocket_url' => $websocket_url,
                'uploaded_at' => time() // Track when upload completed
            ];

            $radleLogs->log("Successfully uploaded asset to Reddit CDN: {$upload['asset_id']}", 'radle-lite');
        }

        if (empty($assets)) {
            $radleLogs->log("No assets were successfully prepared", 'radle-lite');
            return new WP_Error(
                'no_assets_prepared',
                'Failed to prepare any images',
                array(
                    'status' => 500,
                    'errors' => $errors
                )
            );
        }

        $radleLogs->log("Successfully prepared " . count($assets) . " assets", 'radle-lite');

        return rest_ensure_response([
            'success' => true,
            'assets' => $assets,
            'errors' => $errors
        ]);
    }
}