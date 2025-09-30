<?php
/**
 * Reddit Image Upload Handler
 *
 * Handles direct image uploads to Reddit via their media asset API
 * Supports JPEG, PNG, and GIF formats with proper validation
 *
 * @package Radle\Modules\Reddit
 * @since 1.1.0
 */

namespace Radle\Modules\Reddit;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Reddit Image Upload Class
 *
 * Manages the complete workflow for uploading images directly to Reddit:
 * 1. Request media asset credentials from Reddit API
 * 2. Upload file to Reddit's CDN (Amazon S3)
 * 3. Submit image post with asset ID
 */
class Image_Upload {

    /**
     * Supported image MIME types
     * @var array
     */
    private $supported_formats = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/avif'
    ];

    /**
     * Maximum file size in bytes (8MB for reliable Reddit processing)
     * @var int
     */
    private $max_file_size = 8388608;

    /**
     * Reddit API base URL
     * @var string
     */
    private $api_base = 'https://oauth.reddit.com';

    /**
     * User agent for API requests
     * @var string
     */
    private $user_agent;

    /**
     * Access token for authentication
     * @var string
     */
    private $access_token;

    /**
     * Logger instance
     * @var object
     */
    private $logger;

    /**
     * Constructor
     */
    public function __construct() {
        global $radleLogs;
        if ($radleLogs) $radleLogs->log("Image_Upload constructor started", 'radle-lite');

        $this->user_agent = $this->get_user_agent();
        if ($radleLogs) $radleLogs->log("User agent obtained successfully", 'radle-lite');

        $this->logger = new \Radle\Utilities\log();
        if ($radleLogs) $radleLogs->log("Logger initialized successfully", 'radle-lite');

        if ($radleLogs) $radleLogs->log("Image_Upload constructor completed", 'radle-lite');
    }

    /**
     * Validate image file before upload using WordPress core functions
     *
     * @param int $attachment_id WordPress attachment ID
     * @return array Validation result with success status and error message
     */
    public function validate_image($attachment_id) {
        $result = [
            'success' => false,
            'error' => '',
            'mime_type' => '',
            'file_size' => 0,
            'file_path' => ''
        ];

        // Use WordPress core to get attachment data
        $file_path = get_attached_file($attachment_id);
        if (!$file_path) {
            $result['error'] = __('Image attachment not found', 'radle-lite');
            return $result;
        }

        if (!file_exists($file_path)) {
            $result['error'] = __('Image file not found on server', 'radle-lite');
            return $result;
        }

        // Use WordPress core to get file info
        $file_info = wp_check_filetype($file_path);
        $mime_type = $file_info['type'];

        if (!in_array($mime_type, $this->supported_formats, true)) {
            $result['error'] = sprintf(
                /* translators: %s: list of supported image formats */
                __('Unsupported image format. Supported formats: %s', 'radle-lite'),
                'JPEG, PNG, GIF'
            );
            return $result;
        }

        // Check file size using WordPress core
        $file_size = filesize($file_path);
        if ($file_size > $this->max_file_size) {
            $result['error'] = sprintf(
                __('Image exceeds %s — not supported for Reddit uploads. Please use a smaller image.', 'radle-lite'),
                size_format($this->max_file_size)
            );
            return $result;
        }

        $result['success'] = true;
        $result['mime_type'] = $mime_type;
        $result['file_size'] = $file_size;
        $result['file_path'] = $file_path;

        return $result;
    }

    /**
     * Request media asset from Reddit API
     *
     * @param string $file_path Path to the image file
     * @param string $mime_type MIME type of the image
     * @return array API response with upload credentials
     */
    public function request_media_asset($file_path, $mime_type) {
        global $radleLogs;
        if ($radleLogs) $radleLogs->log("request_media_asset() called for file: $file_path", 'radle-lite');

        if ($radleLogs) $radleLogs->log("Getting access token", 'radle-lite');
        $this->access_token = $this->get_access_token();

        if (!$this->access_token) {
            if ($radleLogs) $radleLogs->log("No access token available", 'radle-lite');
            return [
                'success' => false,
                'error' => __('Reddit authentication required', 'radle-lite')
            ];
        }
        if ($radleLogs) $radleLogs->log("Access token obtained", 'radle-lite');

        $filename = basename($file_path);
        if ($radleLogs) $radleLogs->log("Making API request to Reddit media asset endpoint", 'radle-lite');

        $response = wp_remote_post($this->api_base . '/api/media/asset.json', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'User-Agent' => $this->user_agent,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'body' => [
                'filepath' => $filename,
                'mimetype' => $mime_type
            ],
            'timeout' => 30
        ]);

        if ($radleLogs) $radleLogs->log("wp_remote_post() call completed", 'radle-lite');

        if ($radleLogs) $radleLogs->log("API request completed, checking response", 'radle-lite');

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            if ($radleLogs) $radleLogs->log("WP Error in media asset request: $error_message", 'radle-lite');
            $this->logger->log('Reddit media asset request failed: ' . $error_message);
            return [
                'success' => false,
                'error' => __('Failed to request upload credentials from Reddit', 'radle-lite')
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($radleLogs) $radleLogs->log("Response code: $response_code", 'radle-lite');

        $body = wp_remote_retrieve_body($response);
        if ($radleLogs) $radleLogs->log("Response body length: " . strlen($body), 'radle-lite');

        $data = json_decode($body, true);
        if ($radleLogs) $radleLogs->log("JSON decode completed, data valid: " . ($data ? 'yes' : 'no'), 'radle-lite');

        if (!$data || !isset($data['args'], $data['asset'])) {
            if ($radleLogs) $radleLogs->log("Invalid media asset response structure", 'radle-lite');
            if ($radleLogs) $radleLogs->log("Response body: " . substr($body, 0, 200), 'radle-lite');
            $this->logger->log('Invalid Reddit media asset response: ' . $body);
            return [
                'success' => false,
                'error' => __('Invalid response from Reddit API', 'radle-lite')
            ];
        }

        if ($radleLogs) $radleLogs->log("Asset data structure: " . wp_json_encode($data['asset']), 'radle-lite');

        return [
            'success' => true,
            'data' => $data
        ];
    }

    /**
     * Upload image to Reddit's CDN
     *
     * @param string $file_path Path to the image file
     * @param array $asset_data Upload credentials from Reddit API
     * @return array Upload result
     */
    public function upload_to_reddit_cdn($file_path, $asset_data) {
        global $radleLogs;
        if ($radleLogs) $radleLogs->log("upload_to_reddit_cdn() called", 'radle-lite');
        if ($radleLogs) $radleLogs->log("Asset data keys: " . implode(', ', array_keys($asset_data)), 'radle-lite');

        $upload_url = $asset_data['args']['action'];
        if ($radleLogs) $radleLogs->log("Upload URL from Reddit: $upload_url", 'radle-lite');

        // Fix protocol-relative URLs from Reddit
        if (strpos($upload_url, '//') === 0) {
            $upload_url = 'https:' . $upload_url;
            if ($radleLogs) $radleLogs->log("Fixed protocol-relative URL to: $upload_url", 'radle-lite');
        }

        $fields = $asset_data['args']['fields'];
        if ($radleLogs) $radleLogs->log("Field count: " . count($fields), 'radle-lite');

        // Prepare multipart form data
        $boundary = wp_generate_password(12, false);
        $body = '';

        // Add all required fields
        foreach ($fields as $field) {
            $body .= "--$boundary\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$field['name']}\"\r\n\r\n";
            $body .= $field['value'] . "\r\n";
        }

        // Add file data
        $file_contents = file_get_contents($file_path);
        $filename = basename($file_path);

        $body .= "--$boundary\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"$filename\"\r\n";
        $body .= "Content-Type: " . mime_content_type($file_path) . "\r\n\r\n";
        $body .= $file_contents . "\r\n";
        $body .= "--$boundary--\r\n";

        if ($radleLogs) $radleLogs->log("Validating upload URL: " . (filter_var($upload_url, FILTER_VALIDATE_URL) ? 'valid' : 'invalid'), 'radle-lite');

        $response = wp_remote_post($upload_url, [
            'headers' => [
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
                'User-Agent' => $this->user_agent
            ],
            'body' => $body,
            'timeout' => 60
        ]);

        if ($radleLogs) $radleLogs->log("wp_remote_post to CDN completed", 'radle-lite');

        if (is_wp_error($response)) {
            if ($radleLogs) $radleLogs->log("WP_Error in CDN upload: " . $response->get_error_message(), 'radle-lite');
            $this->logger->log('Reddit CDN upload failed: ' . $response->get_error_message());
            return [
                'success' => false,
                'error' => __('Failed to upload image to Reddit', 'radle-lite')
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($radleLogs) $radleLogs->log("CDN upload response status: $status_code", 'radle-lite');

        if (!in_array($status_code, [200, 201, 204])) {
            if ($radleLogs) $radleLogs->log("CDN upload failed - invalid status code: $status_code", 'radle-lite');
            $this->logger->log('Reddit CDN upload failed with status: ' . $status_code);
            return [
                'success' => false,
                'error' => __('Image upload to Reddit failed', 'radle-lite')
            ];
        }

        if ($radleLogs) $radleLogs->log("CDN upload successful with status $status_code", 'radle-lite');

        $asset_id = $asset_data['asset']['asset_id'];
        $processing_state = $asset_data['asset']['processing_state'] ?? 'unknown';

        if ($radleLogs) $radleLogs->log("Asset processing state: $processing_state", 'radle-lite');

        // Extract S3 Location URL from response
        $s3_location_url = null;
        $response_body = wp_remote_retrieve_body($response);

        // Handle both success (201) and no content (204) responses
        if (($status_code === 201 && !empty($response_body)) || $status_code === 204) {
            if ($status_code === 201 && !empty($response_body)) {
                // Parse XML response to get Location
                if ($radleLogs) $radleLogs->log("Parsing S3 XML response for Location URL", 'radle-lite');
                $xml = simplexml_load_string($response_body);
                if ($xml && isset($xml->Location)) {
                    $s3_location_url = (string) $xml->Location;
                    if ($radleLogs) $radleLogs->log("Extracted S3 Location URL: $s3_location_url", 'radle-lite');
                }
            }

            // Fallback: construct URL from action and key if Location not found or 204 response
            if (!$s3_location_url) {
                $action = $asset_data['args']['action'];

                // Normalize protocol-relative URLs
                if (strpos($action, '//') === 0) {
                    $action = 'https:' . $action;
                    if ($radleLogs) $radleLogs->log("Normalized protocol-relative action URL: $action", 'radle-lite');
                }

                $key = null;
                foreach ($asset_data['args']['fields'] as $field) {
                    if ($field['name'] === 'key') {
                        $key = $field['value'];
                        break;
                    }
                }

                if ($action && $key) {
                    $s3_location_url = rtrim($action, '/') . '/' . $key;
                    if ($radleLogs) $radleLogs->log("Constructed S3 URL from action/key: $s3_location_url", 'radle-lite');
                }
            }
        }

        // If processing is incomplete, poll until complete or timeout
        if ($processing_state === 'incomplete') {
            if ($radleLogs) $radleLogs->log("Asset processing incomplete, polling for completion...", 'radle-lite');

            $max_attempts = 10; // Max 10 attempts (30 seconds total)
            $attempt = 0;
            $final_state = 'incomplete';

            while ($attempt < $max_attempts && $final_state === 'incomplete') {
                $attempt++;
                sleep(3); // Wait 3 seconds between polls

                if ($radleLogs) $radleLogs->log("Polling attempt $attempt/$max_attempts for asset completion...", 'radle-lite');

                // Poll Reddit's media asset status
                $status_result = $this->check_asset_status($asset_id);
                if ($status_result && isset($status_result['processing_state'])) {
                    $final_state = $status_result['processing_state'];
                    if ($radleLogs) $radleLogs->log("Asset processing state: $final_state", 'radle-lite');

                    if ($final_state === 'complete') {
                        if ($radleLogs) $radleLogs->log("Asset processing completed successfully", 'radle-lite');
                        break;
                    } elseif ($final_state === 'failed') {
                        if ($radleLogs) $radleLogs->log("Asset processing failed", 'radle-lite');
                        return [
                            'success' => false,
                            'error' => __('Reddit asset processing failed', 'radle-lite')
                        ];
                    }
                } else {
                    if ($radleLogs) $radleLogs->log("Could not check asset status, continuing...", 'radle-lite');
                    break;
                }
            }

            if ($final_state === 'incomplete') {
                if ($radleLogs) $radleLogs->log("Asset still incomplete after polling, proceeding anyway (Reddit may still accept)", 'radle-lite');
            }
        }

        return [
            'success' => true,
            'asset_id' => $asset_id,
            's3_location_url' => $s3_location_url
        ];
    }

    /**
     * Check Reddit asset processing status
     *
     * @param string $asset_id Reddit asset ID to check
     * @return array|null Asset status data or null on failure
     */
    private function check_asset_status($asset_id) {
        global $radleLogs;

        if (!$this->access_token) {
            return null;
        }

        $response = wp_remote_get($this->api_base . '/api/media/asset/' . $asset_id, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'User-Agent' => $this->user_agent
            ],
            'timeout' => 15
        ]);

        if (is_wp_error($response)) {
            if ($radleLogs) $radleLogs->log("Error checking asset status: " . $response->get_error_message(), 'radle-lite');
            return null;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            if ($radleLogs) $radleLogs->log("Asset status check returned code: $response_code", 'radle-lite');
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data) {
            if ($radleLogs) $radleLogs->log("Invalid JSON in asset status response", 'radle-lite');
            return null;
        }

        return $data['asset'] ?? null;
    }

    /**
     * Wait for asset to complete processing
     *
     * No-op method - waiting handled by JavaScript countdown in browser
     *
     * @param string $asset_id Reddit asset ID to wait for
     * @param int $max_attempts Unused
     * @param float $sleep_between Unused
     * @return bool Always returns true
     */
    public function wait_for_asset_completion($asset_id, $max_attempts = 20, $sleep_between = 2) {
        global $radleLogs;

        if ($radleLogs) $radleLogs->log("Skipping PHP wait for asset: $asset_id (JS handles timing)", 'radle-lite');

        // No blocking in PHP - JavaScript will wait before submitting
        return true;
    }

    /**
     * Submit self post with embedded media (mimics web UI "Images" posts)
     *
     * Supports images, GIFs, and videos with proper MIME type detection
     *
     * @param string $title Post title
     * @param string $subreddit Target subreddit
     * @param array $assets Array of asset data: [['id' => 'asset_id', 'mime' => 'mime_type'], ...]
     * @param string $body Optional post body text
     * @return array Normalized submission result with keys: success, reddit_url, reddit_id, data, error
     */
    public function submit_self_with_embedded_media($title, $subreddit, $assets, $body = '') {
        global $radleLogs;

        if ($radleLogs) $radleLogs->log("submit_self_with_embedded_media() called with title: $title, subreddit: $subreddit", 'radle-lite');

        // Get access token
        $this->access_token = $this->get_access_token();
        if (!$this->access_token) {
            if ($radleLogs) $radleLogs->log("Failed to get access token", 'radle-lite');
            return [
                'success' => false,
                'error' => __('Reddit authentication required', 'radle-lite')
            ];
        }

        // Build RTJSON doc
        $doc = [];

        // 1. Add body text as paragraphs
        if (!empty($body)) {
            $paragraphs = explode("\n", trim($body));
            foreach ($paragraphs as $p) {
                if (trim($p) !== '') {
                    $doc[] = [
                        'e' => 'par',
                        'c' => [
                            ['e' => 'text', 't' => $p]
                        ]
                    ];
                    if ($radleLogs) $radleLogs->log("Added paragraph: $p", 'radle-lite');
                }
            }
        }

        // 2. Add images wrapped in paragraphs (required by Reddit)
        foreach ($assets as $asset) {
            $asset_id = is_array($asset) ? $asset['id'] : $asset;
            $doc[] = [
                'e' => 'par',
                'c' => [
                    ['e' => 'media', 'id' => $asset_id]
                ]
            ];
            if ($radleLogs) $radleLogs->log("Added media embed for asset: $asset_id (wrapped in paragraph)", 'radle-lite');
        }

        $rtjson = wp_json_encode(['document' => $doc]);

        // Prepare POST data
        // Note: media_metadata is NOT needed when using Reddit's asset upload API
        // Assets uploaded via /api/media/asset are already tracked by Reddit
        $post_data = [
            'api_type' => 'json',
            'title' => $title,
            'sr' => $subreddit,
            'kind' => 'self',
            'richtext_json' => $rtjson
        ];

        if ($radleLogs) {
            $radleLogs->log("RTJSON: $rtjson", 'radle-lite');
            $radleLogs->log("POST data keys: " . implode(', ', array_keys($post_data)), 'radle-lite');
            $radleLogs->log("Submitting without media_metadata (using Reddit asset IDs)", 'radle-lite');
        }

        // 5. Submit to Reddit
        $response = wp_remote_post($this->api_base . '/api/submit', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'User-Agent' => $this->user_agent
            ],
            'body' => $post_data,
            'timeout' => 30
        ]);

        // Handle WP_Error
        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            if ($radleLogs) $radleLogs->log("WP_Error in submission: $error_msg", 'radle-lite');
            return [
                'success' => false,
                'error' => $error_msg
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($radleLogs) {
            $radleLogs->log("Self post submission response code: $response_code", 'radle-lite');
            $radleLogs->log("Self post submission response body: " . substr($body, 0, 500), 'radle-lite');
        }

        // Parse response
        $data = json_decode($body, true);

        if (!$data) {
            if ($radleLogs) $radleLogs->log("Invalid JSON response from Reddit", 'radle-lite');
            return [
                'success' => false,
                'error' => __('Invalid response from Reddit', 'radle-lite')
            ];
        }

        // Check for Reddit API errors
        if (isset($data['json']['errors']) && !empty($data['json']['errors'])) {
            $error_msg = isset($data['json']['errors'][0][1]) ? $data['json']['errors'][0][1] : __('Unknown error', 'radle-lite');
            if ($radleLogs) $radleLogs->log("Reddit API error: $error_msg", 'radle-lite');
            return [
                'success' => false,
                'error' => $error_msg,
                'data' => $data
            ];
        }

        // Extract Reddit post URL and ID
        $reddit_url = $data['json']['data']['url'] ?? '';
        $reddit_id = '';

        if (!empty($reddit_url) && preg_match('~/comments/([a-z0-9]+)/~i', $reddit_url, $matches)) {
            $reddit_id = $matches[1];
        }

        if ($radleLogs) $radleLogs->log("Successfully submitted post. Reddit ID: $reddit_id, URL: $reddit_url", 'radle-lite');

        return [
            'success' => true,
            'reddit_url' => $reddit_url,
            'reddit_id' => $reddit_id,
            'data' => $data
        ];
    }

    /**
     * Submit gallery post to Reddit (PRAW approach - no WebSocket monitoring needed)
     *
     * This method follows PRAW's gallery submission approach:
     * 1. Takes pre-uploaded asset IDs
     * 2. Submits immediately to /api/submit_gallery_post.json
     * 3. No WebSocket monitoring (Reddit processes asynchronously)
     * 4. Returns post URL immediately
     *
     * @param string $title Post title
     * @param string $subreddit Target subreddit name (without r/ prefix)
     * @param array $assets Array of asset data with 'id' and optional 'caption', 'outbound_url'
     * @param string $body Optional gallery description (Markdown supported)
     * @param array $options Optional parameters (nsfw, spoiler, flair_id, etc.)
     * @return array Normalized submission result with keys: success, reddit_url, reddit_id, data, error
     */
    public function submit_gallery($title, $subreddit, $assets, $body = '', $options = []) {
        global $radleLogs;

        if ($radleLogs) $radleLogs->log("submit_gallery() called with title: $title, subreddit: $subreddit, assets: " . count($assets), 'radle-lite');

        // Get access token
        $this->access_token = $this->get_access_token();
        if (!$this->access_token) {
            if ($radleLogs) $radleLogs->log("Failed to get access token", 'radle-lite');
            return [
                'success' => false,
                'error' => __('Reddit authentication required', 'radle-lite')
            ];
        }

        // Build gallery items array
        $items = [];
        foreach ($assets as $asset) {
            $asset_id = is_array($asset) ? $asset['id'] : $asset;
            $caption = is_array($asset) && isset($asset['caption']) ? $asset['caption'] : '';
            $outbound_url = is_array($asset) && isset($asset['outbound_url']) ? $asset['outbound_url'] : '';

            // Validate caption length (Reddit limit: 180 characters)
            if (strlen($caption) > 180) {
                if ($radleLogs) $radleLogs->log("Caption exceeds 180 characters, truncating", 'radle-lite');
                $caption = substr($caption, 0, 180);
            }

            $items[] = [
                'media_id' => $asset_id,
                'caption' => $caption,
                'outbound_url' => $outbound_url
            ];

            if ($radleLogs) $radleLogs->log("Added gallery item: media_id=$asset_id, caption=" . (empty($caption) ? '[empty]' : substr($caption, 0, 50)), 'radle-lite');
        }

        if (empty($items)) {
            if ($radleLogs) $radleLogs->log("No items to submit", 'radle-lite');
            return [
                'success' => false,
                'error' => __('No gallery items provided', 'radle-lite')
            ];
        }

        // Build request body
        $post_data = [
            'api_type' => 'json',
            'sr' => $subreddit,
            'title' => $title,
            'items' => $items,
            'show_error_list' => true,
            'sendreplies' => true,
            'validate_on_submit' => true
        ];

        // Add optional body text
        if (!empty($body)) {
            $post_data['text'] = $body;
            if ($radleLogs) $radleLogs->log("Added gallery description text", 'radle-lite');
        }

        // Add optional parameters
        if (isset($options['nsfw'])) {
            $post_data['nsfw'] = (bool)$options['nsfw'];
        }
        if (isset($options['spoiler'])) {
            $post_data['spoiler'] = (bool)$options['spoiler'];
        }
        if (!empty($options['flair_id'])) {
            $post_data['flair_id'] = $options['flair_id'];
            if (!empty($options['flair_text'])) {
                $post_data['flair_text'] = $options['flair_text'];
            }
        }

        if ($radleLogs) $radleLogs->log("Submitting gallery with " . count($items) . " items", 'radle-lite');
        if ($radleLogs) $radleLogs->log("Gallery API endpoint: /api/submit_gallery_post.json", 'radle-lite');

        // Submit to Reddit gallery API
        $response = wp_remote_post($this->api_base . '/api/submit_gallery_post.json', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json',
                'User-Agent' => $this->user_agent
            ],
            'body' => wp_json_encode($post_data),
            'timeout' => 30
        ]);

        // Handle WP_Error
        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            if ($radleLogs) $radleLogs->log("WP_Error in gallery submission: $error_msg", 'radle-lite');
            return [
                'success' => false,
                'error' => $error_msg
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($radleLogs) {
            $radleLogs->log("Gallery submission response code: $response_code", 'radle-lite');
            $radleLogs->log("Gallery submission response body: " . substr($body, 0, 500), 'radle-lite');
        }

        // Parse response
        $data = json_decode($body, true);

        if (!$data) {
            if ($radleLogs) $radleLogs->log("Invalid JSON response from Reddit gallery API", 'radle-lite');
            return [
                'success' => false,
                'error' => __('Invalid response from Reddit', 'radle-lite')
            ];
        }

        // Check for Reddit API errors
        if (isset($data['json']['errors']) && !empty($data['json']['errors'])) {
            $error_msg = isset($data['json']['errors'][0][1]) ? $data['json']['errors'][0][1] : __('Unknown error', 'radle-lite');
            if ($radleLogs) $radleLogs->log("Reddit gallery API error: $error_msg", 'radle-lite');
            return [
                'success' => false,
                'error' => $error_msg,
                'data' => $data
            ];
        }

        // Extract Reddit post URL and ID
        $reddit_url = $data['json']['data']['url'] ?? '';
        $reddit_id = '';

        // If no direct URL, check user_submitted_page (means post is processing)
        if (empty($reddit_url) && !empty($data['json']['data']['user_submitted_page'])) {
            if ($radleLogs) $radleLogs->log("Gallery post submitted but URL not immediately available (processing via WebSocket)", 'radle-lite');
            // Post was created successfully, just processing
            return [
                'success' => true,
                'reddit_url' => '',
                'reddit_id' => '',
                'data' => $data,
                'websocket_url' => $data['json']['data']['websocket_url'] ?? '',
                'processing' => true
            ];
        }

        if (!empty($reddit_url) && preg_match('~/comments/([a-z0-9]+)/~i', $reddit_url, $matches)) {
            $reddit_id = $matches[1];
        }

        if ($radleLogs) $radleLogs->log("Successfully submitted gallery. Reddit ID: $reddit_id, URL: $reddit_url", 'radle-lite');

        return [
            'success' => true,
            'reddit_url' => $reddit_url,
            'reddit_id' => $reddit_id,
            'data' => $data
        ];
    }


    /**
     * Submit single image post to Reddit (PRAW approach using S3 URL)
     *
     * This matches PRAW's submit_image() method:
     * 1. Takes uploaded asset with S3 URL
     * 2. Submits with kind="image" and url=<S3_URL>
     * 3. Includes optional selftext (body)
     *
     * @param string $title Post title
     * @param string $subreddit Target subreddit
     * @param string $s3_url S3 URL of uploaded image (https://reddit-uploaded-media.s3...)
     * @param string $selftext Optional post body text
     * @param array $options Optional parameters (nsfw, spoiler, flair_id, etc.)
     * @return array Normalized submission result with keys: success, reddit_url, reddit_id, data, error
     */
    public function submit_image_post($title, $subreddit, $s3_url, $selftext = '', $options = []) {
        global $radleLogs;

        if ($radleLogs) $radleLogs->log("submit_image_post() called with title: $title, subreddit: $subreddit", 'radle-lite');
        if ($radleLogs) $radleLogs->log("S3 URL: $s3_url", 'radle-lite');

        // Get access token
        $this->access_token = $this->get_access_token();
        if (!$this->access_token) {
            if ($radleLogs) $radleLogs->log("Failed to get access token", 'radle-lite');
            return [
                'success' => false,
                'error' => __('Reddit authentication required', 'radle-lite')
            ];
        }

        // Build request data (PRAW approach)
        $post_data = [
            'api_type' => 'json',
            'sr' => $subreddit,
            'title' => $title,
            'kind' => 'image',  // ← Image post type
            'url' => $s3_url,   // ← S3 URL (not asset_id!)
            'resubmit' => true,
            'sendreplies' => true,
            'validate_on_submit' => true
        ];

        // Add optional selftext (body)
        if (!empty($selftext)) {
            $post_data['text'] = $selftext;
            if ($radleLogs) $radleLogs->log("Added selftext to image post", 'radle-lite');
        }

        // Add optional parameters
        if (isset($options['nsfw'])) {
            $post_data['nsfw'] = (bool)$options['nsfw'];
        }
        if (isset($options['spoiler'])) {
            $post_data['spoiler'] = (bool)$options['spoiler'];
        }
        if (!empty($options['flair_id'])) {
            $post_data['flair_id'] = $options['flair_id'];
            if (!empty($options['flair_text'])) {
                $post_data['flair_text'] = $options['flair_text'];
            }
        }

        if ($radleLogs) $radleLogs->log("Submitting image post with kind=image and S3 URL", 'radle-lite');

        // Submit to Reddit (PRAW uses /api/submit for images)
        $response = wp_remote_post($this->api_base . '/api/submit', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'User-Agent' => $this->user_agent
            ],
            'body' => $post_data,
            'timeout' => 30
        ]);

        // Handle WP_Error
        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            if ($radleLogs) $radleLogs->log("WP_Error in image post submission: $error_msg", 'radle-lite');
            return [
                'success' => false,
                'error' => $error_msg
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($radleLogs) {
            $radleLogs->log("Image post submission response code: $response_code", 'radle-lite');
            $radleLogs->log("Image post submission response body: " . substr($body, 0, 500), 'radle-lite');
        }

        // Parse response
        $data = json_decode($body, true);

        if (!$data) {
            if ($radleLogs) $radleLogs->log("Invalid JSON response from Reddit", 'radle-lite');
            return [
                'success' => false,
                'error' => __('Invalid response from Reddit', 'radle-lite')
            ];
        }

        // Check for Reddit API errors
        if (isset($data['json']['errors']) && !empty($data['json']['errors'])) {
            $error_msg = isset($data['json']['errors'][0][1]) ? $data['json']['errors'][0][1] : __('Unknown error', 'radle-lite');
            if ($radleLogs) $radleLogs->log("Reddit API error: $error_msg", 'radle-lite');
            return [
                'success' => false,
                'error' => $error_msg,
                'data' => $data
            ];
        }

        // Extract Reddit post URL and ID
        // Note: For image posts, Reddit may return websocket_url for monitoring instead of direct URL
        $reddit_url = $data['json']['data']['url'] ?? '';
        $reddit_id = '';

        // If no direct URL, check user_submitted_page (means post is processing)
        if (empty($reddit_url) && !empty($data['json']['data']['user_submitted_page'])) {
            if ($radleLogs) $radleLogs->log("Image post submitted but URL not immediately available (processing via WebSocket)", 'radle-lite');
            // Post was created successfully, just processing
            // The WebSocket would give us the final URL, but we can consider this a success
            return [
                'success' => true,
                'reddit_url' => '', // Will be populated by WebSocket or user can check their profile
                'reddit_id' => '',
                'data' => $data,
                'websocket_url' => $data['json']['data']['websocket_url'] ?? '',
                'processing' => true // Flag that post is still processing
            ];
        }

        if (!empty($reddit_url) && preg_match('~/comments/([a-z0-9]+)/~i', $reddit_url, $matches)) {
            $reddit_id = $matches[1];
        }

        if ($radleLogs) $radleLogs->log("Successfully submitted image post. Reddit ID: $reddit_id, URL: $reddit_url", 'radle-lite');

        return [
            'success' => true,
            'reddit_url' => $reddit_url,
            'reddit_id' => $reddit_id,
            'data' => $data
        ];
    }


    /**
     * Get access token from Reddit API class
     *
     * @return string|false Access token or false if not available
     */
    private function get_access_token() {
        global $radleLogs;
        if ($radleLogs) $radleLogs->log("Creating reddit_api instance", 'radle-lite');

        $reddit_api = \Radle\Modules\Reddit\Reddit_API::getInstance();
        if ($radleLogs) $radleLogs->log("reddit_api instance created", 'radle-lite');

        if ($radleLogs) $radleLogs->log("Calling get_access_token() method", 'radle-lite');
        $token = $reddit_api->get_access_token();

        if ($radleLogs) $radleLogs->log("Access token result: " . ($token ? 'obtained' : 'null'), 'radle-lite');
        return $token;
    }

    /**
     * Get user agent string
     *
     * @return string User agent
     */
    private function get_user_agent() {
        global $radleLogs;
        if ($radleLogs) $radleLogs->log("get_user_agent() called", 'radle-lite');

        if ($radleLogs) $radleLogs->log("Calling User_Agent::get() static method", 'radle-lite');
        $user_agent = \Radle\Modules\Reddit\User_Agent::get();

        if ($radleLogs) $radleLogs->log("User agent obtained: " . substr($user_agent, 0, 50), 'radle-lite');
        return $user_agent;
    }
}