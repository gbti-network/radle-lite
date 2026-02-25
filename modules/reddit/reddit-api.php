<?php

namespace Radle\Modules\Reddit;

use Radle\Modules\Reddit\User_Agent;
use Radle\Modules\Reddit\Rate_Limit_Monitor;

class Reddit_API {

    private static $instance = null;
    private $user_agent;
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $access_token;
    private $refresh_token;
    private $authenticated_user;
    private $subreddit;
    private $profile_image_cache = [];
    private $rate_limit_monitor;
    public static $cache_duration;

    private function __construct() {
        $this->user_agent = User_Agent::get();
        $this->client_id = get_option('radle_client_id');
        $this->client_secret = get_option('radle_client_secret');
        $this->redirect_uri = rest_url('radle/v1/reddit/oauth-callback');
        $this->access_token = get_option('radle_reddit_access_token');
        $this->refresh_token = get_option('radle_reddit_refresh_token');
        $this->subreddit = get_option('radle_subreddit');
        $this->rate_limit_monitor = new Rate_Limit_Monitor();
        self::$cache_duration = (int) get_option('radle_cache_duration', 0);
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_access_token() {
        return $this->access_token;
    }

    private function api_get($endpoint, $args = []) {
        $response = wp_remote_get($endpoint, $args);

        if (wp_remote_retrieve_response_code($response) == 401) {
            if ($this->refresh_access_token()) {
                $args['headers']['Authorization'] = 'Bearer ' . $this->access_token;
                $response = wp_remote_get($endpoint, $args);
            }
        }

        return $response;
    }

    private function api_post($endpoint, $args = []) {
        $response = wp_remote_post($endpoint, $args);

        if (wp_remote_retrieve_response_code($response) == 401) {
            if ($this->refresh_access_token()) {
                $args['headers']['Authorization'] = 'Bearer ' . $this->access_token;
                $response = wp_remote_post($endpoint, $args);
            }
        }

        return $response;
    }

    public function is_connected() {

        global $radleLogs;

        if (empty($this->access_token)) {
            $radleLogs->log("Access token is empty.", 'api-reddit-token-management');
            return false;
        }

        $endpoint = 'https://oauth.reddit.com/api/v1/me';

        $response = $this->api_get($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'User-Agent' =>  $this->user_agent
            ]
        ]);

        $this->monitor_rate_limits($response, $endpoint, []);

        if (is_wp_error($response)) {
            $radleLogs->log("Connection check failed: WP_Error encountered.", 'api-reddit-token-management');
            $radleLogs->log("Error message: " . $response->get_error_message(), 'api-reddit-token-management');
            return $this->refresh_access_token();
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            $radleLogs->log("Connection check failed. Response code: $response_code", 'api-reddit-token-management');
            $radleLogs->log("Response body: $body", 'api-reddit-token-management');
            return $this->refresh_access_token();
        }

        $body = json_decode($body, true);
        if (isset($body['name'])) {
            $this->authenticated_user = $body['name'];
        } else {
            $radleLogs->log("Connection check failed. 'name' not found in response body.", 'api-reddit-token-management');
            return false;
        }

        $radleLogs->log("Connection check successful. Authenticated user: {$this->authenticated_user}", 'api-reddit-token-management');
        return true;
    }


    public function authenticate() {
        if ($this->access_token) {
            return true;
        }

        if ($this->refresh_token && $this->refresh_access_token()) {
            return true;
        }

        return false;
    }

    public function get_authorization_url($state = '') {
        if (empty($state)) {
            $state = wp_generate_password(24, false);
        }
        set_transient('radle_oauth_state', $state, 600);

        $query = http_build_query([
            'client_id' => $this->client_id,
            'response_type' => 'code',
            'state' => $state,
            'redirect_uri' => rest_url('radle/v1/reddit/oauth-callback'),
            'duration' => 'permanent',
            'scope' => 'identity edit submit read modconfig mysubreddits'
        ]);

        return 'https://www.reddit.com/api/v1/authorize?' . $query;
    }

    public function handle_authorization_response() {

        global $radleLogs;

        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            $radleLogs->log("Missing required parameters in authorization response.", 'api-reddit-token-management');
            return new \WP_Error('missing_parameters', 'Missing required parameters.');
        }

        $saved_state = get_transient('radle_oauth_state');
        if ($_GET['state'] !== $saved_state) {
            $radleLogs->log("Invalid state parameter in authorization response.", 'api-reddit-token-management');
            return new \WP_Error('invalid_state', 'Invalid state parameter.');
        }

        $response = $this->api_post('https://www.reddit.com/api/v1/access_token', [
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => sanitize_text_field(wp_unslash($_GET['code'])),
                'redirect_uri' => $this->redirect_uri,
            ],
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->client_id . ':' . $this->client_secret),
                'Content-Type' => 'application/x-www-form-urlencoded'
            ]
        ]);

        if (is_wp_error($response)) {
            $radleLogs->log("Error in authorization response: " . $response->get_error_message(), 'api');
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['access_token'])) {
            update_option('radle_reddit_access_token', $body['access_token']);
            update_option('radle_reddit_refresh_token', $body['refresh_token']);
            $this->access_token = $body['access_token'];
            $this->refresh_token = $body['refresh_token'];
            $radleLogs->log("Successfully obtained and stored access token.", 'api-reddit-token-management');
            return true;
        }

        $radleLogs->log("Failed to authenticate with Reddit.", 'api-reddit-token-management');

        return new \WP_Error('authentication_failed', 'Failed to authenticate with Reddit.');
    }

    private function refresh_access_token() {

        global $radleLogs;

        $response = $this->api_post('https://www.reddit.com/api/v1/access_token', [
            'body' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->refresh_token,
                'redirect_uri' => $this->redirect_uri,
            ],
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->client_id . ':' . $this->client_secret),
                'Content-Type' => 'application/x-www-form-urlencoded'
            ]
        ]);

        if (is_wp_error($response)) {
            $radleLogs->log('Reddit API token refresh error: ' . $response->get_error_message(), 'api-reddit-token-management');
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['access_token'])) {
            update_option('radle_reddit_access_token', $body['access_token']);
            $this->access_token = $body['access_token'];
            $this->refresh_token = get_option('radle_reddit_refresh_token');
            return true;
        }

        $radleLogs->log('Reddit API token refresh failed: ' . wp_remote_retrieve_body($response), 'api-reddit-token-management');
        return false;
    }

    private function generate_cache_key($method, $params) {
        return 'radle_' . md5($method . serialize($params));
    }

    private function get_cached_data($key) {
        if (self::$cache_duration <= 0) {
            return false;
        }
        return get_transient($key);
    }

    private function set_cached_data($key, $data) {
        if (self::$cache_duration > 0) {
            set_transient($key, $data, self::$cache_duration);
        }
    }

    public function monitor_rate_limits($response, $endpoint = '', $payload = []) {

        if (!$this->rate_limit_monitor->is_monitoring_enabled()) {
            return;
        }

        $used = wp_remote_retrieve_header($response, 'x-ratelimit-used') ?: 0;
        $remaining = wp_remote_retrieve_header($response, 'x-ratelimit-remaining') ?: 0;
        $reset = wp_remote_retrieve_header($response, 'x-ratelimit-reset') ?: 0;
        $status_code = wp_remote_retrieve_response_code($response);

        $is_failure = ($status_code === 429);

        $this->rate_limit_monitor->record_rate_limit_usage($used, $remaining, $reset, $is_failure, $endpoint, $payload);
    }

    public function clear_cache() {
        global $wpdb;

        // Use WordPress transient API instead of direct database query
        // This is more compatible with object caching plugins (Redis, Memcached, etc.)
        $transient_keys = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_radle_') . '%'
            )
        );

        foreach ($transient_keys as $transient) {
            // Remove '_transient_' prefix to get the actual transient name
            $transient_name = str_replace('_transient_', '', $transient);
            delete_transient($transient_name);
        }
    }

    public function permissions_check($request) {
        return current_user_can('edit_posts');
    }

    public function replace_tokens($template, $post) {
        $post_excerpt = $post->post_excerpt;

        if (!trim($post_excerpt)) {
            $post_excerpt = wp_trim_words($post->post_content, 20, '...');
        }

        $permalink = get_permalink($post->ID);

        // Escape brackets in title for markdown link compatibility
        // If title has [brackets], they need to be escaped as \[brackets\]
        $title_escaped = str_replace(['[', ']'], ['\[', '\]'], $post->post_title);

        $tokens = [
            '{post_title}' => $post->post_title,
            '{post_title_escaped}' => $title_escaped,
            '{post_excerpt}' => $post_excerpt,
            '{post_permalink}' => $permalink,
        ];

        /**
         * Filter the available tokens for template replacement
         *
         * Allows extensions (like Radle Pro) to add additional tokens
         * such as Yoast SEO meta fields or custom post meta
         *
         * @since 1.2.0
         * @param array $tokens Array of token => value pairs
         * @param WP_Post $post The post object being processed
         * @param string $post_excerpt The generated post excerpt
         */
        $tokens = apply_filters('radle_template_tokens', $tokens, $post, $post_excerpt);

        return strtr($template, $tokens);
    }

    public function get_api_error($response) {
        $error_message = __('Unknown error','radle-lite');

        if (is_array($response) || is_object($response)) {
            $error_message = $this->find_longest_string($response);
        }

        return $error_message;
    }

    private function find_longest_string($array) {
        $longest_string = '';

        foreach ($array as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $nested_longest = $this->find_longest_string($value);
                if (strlen($nested_longest) > strlen($longest_string)) {
                    $longest_string = $nested_longest;
                }
            } elseif (is_string($value) && strlen($value) > strlen($longest_string)) {
                $longest_string = $value;
            }
        }

        return $longest_string;
    }

    public function get_id_from_response($response) {
        // Handle JSON format with api_type=json - only parse when url exists
        if (isset($response['json']['data']['url'])) {
            $url = $response['json']['data']['url'];
            // https://www.reddit.com/r/<sr>/comments/<id>/<slug>/
            if (preg_match('~comments/([a-z0-9]+)/~i', $url, $matches)) {
                return $matches[1];
            }
        }

        // Fallback: Legacy jQuery format for compatibility
        if (isset($response['jquery']) && is_array($response['jquery'])) {
            foreach ($response['jquery'] as $entry) {
                if (isset($entry[3]) && is_array($entry[3]) && isset($entry[3][0]) && strpos($entry[3][0], 'https://www.reddit.com') !== false) {
                    $url_parts = explode('/', $entry[3][0]);
                    if (isset($url_parts[6])) {
                        return $url_parts[6];
                    }
                }
            }
        }

        return false;
    }

    public function get_authenticated_username() {
        if (!$this->authenticated_user) {
            $this->is_connected(); // populates $authenticated_user on success
        }
        return $this->authenticated_user;
    }

    public function search_post_by_title($title, $subreddit) {

        global $radleLogs;

        $cache_key = $this->generate_cache_key('search_post_by_title', [$title, $subreddit]);
        $cached_data = $this->get_cached_data($cache_key);

        if ($cached_data !== false) {
            $radleLogs->log("Returning cached search results for title: $title in subreddit: $subreddit", 'api-reddit-entries');
            return $cached_data;
        }

        $payload = "search?q=" . urlencode($title) . "&restrict_sr=1";

        $endpoint = "https://oauth.reddit.com/r/{$subreddit}/{$payload}";

        $response = $this->api_get($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'User-Agent' => $this->user_agent
            ]
        ]);

        if (is_wp_error($response)) {
            $radleLogs->log("Error searching for post: " . $response->get_error_message(),  'api-reddit-entries');
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $result = false;

        if (!empty($body['data']['children'])) {
            foreach ($body['data']['children'] as $child) {
                if (strtolower($child['data']['title']) == strtolower($title)) {
                    $result = $child['data'];
                    break;
                }
            }
        }

        $this->set_cached_data($cache_key, $result);

        $this->monitor_rate_limits($response, $endpoint, $payload);

        $radleLogs->log("Search completed for title: $title in subreddit: $subreddit. Result found: " . ($result ? 'Yes' : 'No'),  'api-reddit-entries');

        return $result;
    }

    public function post_to_reddit($title, $content, $subreddit = null) {

        global $radleLogs;

        // Use provided subreddit or fall back to instance property
        $target_subreddit = $subreddit ?? $this->subreddit;

        if (!$target_subreddit) {
            $radleLogs->log("Attempt to post to Reddit failed: No subreddit specified.",  'api-reddit-publishing');
            return new \WP_Error('no_subreddit', 'No subreddit specified.');
        }

        $endpoint = 'https://oauth.reddit.com/api/submit';

        $payload = [
            'sr' => $target_subreddit,
            'title' => $title,
            'text' => $content,
            'kind' => 'self'
        ];

        $response = $this->api_post($endpoint, [
            'body' => $payload,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'User-Agent' =>  $this->user_agent
            ]
        ]);

        if (!is_wp_error($response)) {
            $this->monitor_rate_limits($response, $endpoint, $payload);
            $radleLogs->log("Posted to Reddit. Destination: {$target_subreddit}, Title: $title", 'api-reddit-publishing');
        } else {
            $radleLogs->log("Error posting to Reddit: " . $response->get_error_message(), 'api-reddit-publishing');
        }

        return wp_remote_retrieve_body($response);
    }


    public function post_link_to_reddit($title, $url, $text = '', $subreddit = null) {

        global $radleLogs;

        // Use provided subreddit or fall back to settings
        $target_subreddit = $subreddit ?? get_option('radle_subreddit');
        if (!$target_subreddit) {
            $radleLogs->log("Attempt to post link to Reddit failed: No subreddit specified.", 'api-reddit-publishing');
            return new \WP_Error('no_subreddit', 'No subreddit specified.');
        }

        $radleLogs->log("Original URL received: $url", 'api-reddit-publishing');

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $radleLogs->log("Attempt to post invalid URL to Reddit: $url", 'api-reddit-publishing');
            return new \WP_Error('invalid_url', 'The URL provided is not valid.');
        }

        // Transform .local domains to gbti.network for Reddit posting
        $original_url = $url;
        $url = preg_replace('/https?:\/\/[^\/]+\.local\//', 'https://gbti.network/', $url);

        if ($original_url !== $url) {
            $radleLogs->log("URL transformation applied: $original_url -> $url", 'api-reddit-publishing');
        }

        // Validate the transformed URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $radleLogs->log("Transformed URL is invalid: $url", 'api-reddit-publishing');
            return new \WP_Error('invalid_transformed_url', 'The transformed URL is not valid.');
        }

        $endpoint = 'https://oauth.reddit.com/api/submit';

        $payload = [
            'sr' => $target_subreddit,
            'title' => $title,
            'url' => $url,
            'kind' => 'link'
        ];

        // Add body text if provided
        if (!empty($text)) {
            $payload['text'] = $text;
        }

        $response = $this->api_post($endpoint, [
            'body' => $payload,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'User-Agent' =>  $this->user_agent
            ]
        ]);

        if (is_wp_error($response)) {
            $radleLogs->log("Error posting link to Reddit: " . $response->get_error_message(), 'api-reddit-publishing');
            return $response;
        }

        $this->monitor_rate_limits($response, $endpoint, $payload);
        $radleLogs->log("Posted link to Reddit. Destination: $target_subreddit, Title: $title, URL: $url", 'api-reddit-publishing');

        return wp_remote_retrieve_body($response);
    }

    public function get_reddit_comments($reddit_post_id, $sort = 'new', $search = '') {

        global $radleLogs;

        $cache_key = $this->generate_cache_key('get_reddit_comments', [$reddit_post_id, $sort, $search]);
        $cached_data = $this->get_cached_data($cache_key);

        if ($cached_data !== false) {
            $radleLogs->log("Returning cached comments for post ID: $reddit_post_id", 'comments');
            return $cached_data;
        }

        $endpoint = 'https://oauth.reddit.com/comments';
        $payload = $reddit_post_id;

        // Build URL with sort parameter - Reddit API expects 'sort' parameter
        // Valid values: confidence (default), top, new, controversial, old, random, qa, live
        // Map our sort values to Reddit's sort values
        $reddit_sort = 'confidence'; // default
        switch ($sort) {
            case 'newest':
                $reddit_sort = 'new';
                break;
            case 'oldest':
                $reddit_sort = 'old';
                break;
            case 'most_popular':
                $reddit_sort = 'top';
                break;
        }

        $response = $this->api_get("{$endpoint}/{$reddit_post_id}?sort={$reddit_sort}", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'User-Agent' =>  $this->user_agent
            ]
        ]);

        if (is_wp_error($response)) {
            $radleLogs->log("Error fetching Reddit comments: " . $response->get_error_message(), 'comments');
            return [];
        }

        $this->monitor_rate_limits($response, $endpoint, $payload);

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body[1]['data']['children'])) {
            $radleLogs->log("No comments found for post ID: $reddit_post_id", 'comments');
            return [];
        }

        $original_poster = $body[0]['data']['children'][0]['data']['author'];
        $subreddit = $body[0]['data']['children'][0]['data']['subreddit'];

        $moderators = $this->get_subreddit_moderators($subreddit);

        $comments = $this->collect_comments($body[1]['data']['children'], $original_poster, $moderators);

        $this->add_profile_pictures($comments);

        $result = [
            'comments' => $comments,
            'subreddit' => $subreddit,
            'original_poster' => $original_poster,
        ];

        $this->set_cached_data($cache_key, $result);

        $radleLogs->log("Fetched and processed comments for post ID: $reddit_post_id", 'comments');

        return $result;
    }

    private function collect_comments($children, $original_poster, $moderators) {
        $comments = [];

        foreach ($children as $child) {
            if (!isset($child['data']) || !is_array($child['data'])) {
                continue;
            }

            $comment_data = $child['data'];
            $author = $comment_data['author'] ?? __('Unknown','radle-lite');

            // Check moderation status
            $approved_by = $comment_data['approved_by'] ?? null;
            $banned_by = $comment_data['banned_by'] ?? null;
            $removed_by_category = $comment_data['removed_by_category'] ?? null;

            // Always skip comments that have been explicitly removed or banned by moderators
            if ($banned_by !== null || $removed_by_category !== null) {
                continue;
            }

            // Detect user-deleted comments (Reddit sets author and body to "[deleted]")
            $body = $comment_data['body'] ?? '';
            $is_deleted = ($author === '[deleted]' || $author === null) &&
                          ($body === '[deleted]' || $body === '');

            // Always collect children first (deleted comments may have live replies)
            $children = isset($comment_data['replies']['data']['children']) && is_array($comment_data['replies']['data']['children'])
                ? $this->collect_comments($comment_data['replies']['data']['children'], $original_poster, $moderators)
                : [];

            // Skip deleted leaf comments; keep deleted parents as placeholder for thread context
            if ($is_deleted) {
                if (empty($children)) {
                    continue;
                }
                $comments[] = [
                    'id' => $comment_data['id'] ?? '',
                    'author' => '[deleted]',
                    'body' => '',
                    'permalink' => $comment_data['permalink'] ?? '',
                    'ups' => 0,
                    'downs' => 0,
                    'created_utc' => isset($comment_data['created_utc']) ? (int) $comment_data['created_utc'] : 0,
                    'is_op' => false,
                    'is_mod' => false,
                    'is_deleted' => true,
                    'approved_by' => null,
                    'children' => $children,
                ];
                continue;
            }

            $is_op = $author === $original_poster;
            $is_mod = !$is_op && in_array($author, $moderators);

            // Check approval filter setting
            $approval_filter = \Radle\Modules\Settings\Comment_Settings::get_comment_approval_filter();

            // If "approved_only" mode is enabled, filter unapproved comments
            if ($approval_filter === 'approved_only') {
                // Skip comments that are not from OP/mod and haven't been approved
                if (!$is_op && !$is_mod && $approved_by === null) {
                    // Check if this is a subreddit with mod approval enabled
                    // If approved_by field exists in the response, it means the subreddit has mod approval enabled
                    if (array_key_exists('approved_by', $comment_data)) {
                        continue;
                    }
                }
            }

            $comments[] = [
                'id' => $comment_data['id'] ?? '',
                'author' => $author,
                'body' => $comment_data['body'] ?? '',
                'permalink' => $comment_data['permalink'] ?? '',
                'ups' => isset($comment_data['ups']) ? (int) $comment_data['ups'] : 0,
                'downs' => isset($comment_data['downs']) ? (int) $comment_data['downs'] : 0,
                'created_utc' => isset($comment_data['created_utc']) ? (int) $comment_data['created_utc'] : 0,
                'is_op' => $is_op,
                'is_mod' => $is_mod,
                'approved_by' => $approved_by,
                'children' => $children,
            ];
        }

        return $comments;
    }

    private function get_subreddit_moderators($subreddit) {

        global $radleLogs;

        $transient_key = 'radle_mods_' . $subreddit;
        $moderators = get_transient($transient_key);

        if ($moderators !== false) {
            $radleLogs->log("Returning cached moderators for subreddit: $subreddit",  'comments' );
            return $moderators;
        }

        $endpoint = "https://oauth.reddit.com/r/{$subreddit}/about/moderators";

        $response = $this->api_get($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'User-Agent' =>  $this->user_agent
            ]
        ]);

        if (is_wp_error($response)) {
            $radleLogs->log("Error fetching subreddit moderators: " . $response->get_error_message(),  'comments' );
            return [];
        }

        $this->monitor_rate_limits($response, $endpoint);

        $body = json_decode(wp_remote_retrieve_body($response), true);

        $moderators = [];
        if (isset($body['data']['children'])) {
            foreach ($body['data']['children'] as $mod) {
                $moderators[] = $mod['name'];
            }
        }

        set_transient($transient_key, $moderators, 30 * MINUTE_IN_SECONDS);

        $radleLogs->log("Fetched and cached moderators for subreddit: $subreddit", 'comments');

        return $moderators;
    }

    public function get_user_info($username = null) {
        global $radleLogs;

        if (!$username) {
            $username = $this->authenticated_user;
        }

        if (!$username) {
            $radleLogs->log("No username provided and no authenticated user available.", 'api-reddit-user');
            return new \WP_Error('no_username', __('No username provided and no authenticated user available.','radle-lite'));
        }

        $cache_key = $this->generate_cache_key('get_user_info', [$username]);
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            // Check if cached data is an error response
            if (is_array($cached_data) && isset($cached_data['error']) && $cached_data['error'] === true) {
                $radleLogs->log("Returning cached error for $username (prevents aggressive polling)", 'api-reddit-user');
                return new \WP_Error('cached_error', $cached_data['message']);
            }

            $radleLogs->log("Returning cached user info for $username", 'api-reddit-user');
            return $cached_data;
        }

        $endpoint = "https://oauth.reddit.com/user/{$username}/about";

        $response = $this->api_get($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'User-Agent' => $this->user_agent
            ]
        ]);

        $this->monitor_rate_limits($response, $endpoint);

        if (is_wp_error($response)) {
            $radleLogs->log("Error fetching user info for $username: " . $response->get_error_message(), 'api-reddit-user');

            // Cache the error response for 90 seconds to prevent aggressive polling when rate limited
            $error_data = ['error' => true, 'message' => $response->get_error_message()];
            set_transient($cache_key, $error_data, 90);

            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['data'])) {
            $radleLogs->log("Error fetching user info for $username: Invalid response body.", 'api-reddit-user');
            return new \WP_Error('invalid_response', __('Invalid response when fetching user info.','radle-lite'));
        }

        $user_info_data = $body['data'];

        // Cache the user info for 6 hours (real avatars are stable)
        set_transient($cache_key, $user_info_data, 6 * HOUR_IN_SECONDS);

        $radleLogs->log("Fetched user info for $username", 'api-reddit-user');

        return $user_info_data;
    }


    public function get_owned_subreddits() {

        global $radleLogs;

        $endpoint = 'https://oauth.reddit.com/subreddits/mine/moderator';

        $response = $this->api_get($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'User-Agent' => $this->user_agent
            ]
        ]);

        if (is_wp_error($response)) {
            $radleLogs->log('Error fetching owned subreddits: ' . $response->get_error_message(), 'api-reddit-subreddits');
            return [];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $radleLogs->log('Error fetching owned subreddits. Response code: ' . $response_code, 'api-reddit-subreddits');
            return [];
        }

        $this->monitor_rate_limits($response, $endpoint);

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $subreddits = [];

        if (isset($body['data']['children'])) {
            foreach ($body['data']['children'] as $subreddit) {
                $subreddits[] = $subreddit['data']['display_name'];
            }
        }

        $radleLogs->log('Fetched owned subreddits: ' . implode(', ', $subreddits), 'api-reddit-subreddits');

        return $subreddits;
    }

    public function get_moderated_subreddits() {

        global $radleLogs;

        $endpoint = 'https://oauth.reddit.com/subreddits/mine/moderator';
        $response = $this->api_get($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'User-Agent' => $this->user_agent
            ]
        ]);

        if (is_wp_error($response)) {
            $radleLogs->log('Error fetching moderated subreddits: ' . $response->get_error_message(), 'api-reddit-subreddits');
            return [];
        }

        $subreddits_body = json_decode(wp_remote_retrieve_body($response), true);

        $this->monitor_rate_limits($response, $endpoint);

        $subreddits = [];
        if (isset($subreddits_body['data']['children'])) {
            foreach ($subreddits_body['data']['children'] as $subreddit) {
                $subreddits[] = $subreddit['data']['display_name'];
            }
        }

        $radleLogs->log('Fetched moderated subreddits: ' . implode(', ', $subreddits), 'api-reddit-subreddits');

        return $subreddits;
    }

    private function get_subscribed_subreddits() {

        global $radleLogs;

        $endpoint = 'https://oauth.reddit.com/subreddits/mine/subscriber';

        $response = $this->api_get($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'User-Agent' => $this->user_agent
            ]
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $radleLogs->log('Error fetching subscribed subreddits: ' . wp_remote_retrieve_body($response), 'api-reddit-subreddits');
            return [];
        }

        $this->monitor_rate_limits($response, $endpoint);

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $subreddits = [];

        if (isset($body['data']['children'])) {
            foreach ($body['data']['children'] as $subreddit) {
                $subreddits[] = $subreddit['data']['display_name'];
            }
        }

        $radleLogs->log('Fetched subscribed subreddits: ' . implode(', ', $subreddits), 'api-reddit-subreddits');

        return $subreddits;
    }

    public function get_profile_picture($username, $user_info = null) {

        global $radleLogs;

        if (isset($this->profile_image_cache[$username])) {
            return $this->profile_image_cache[$username];
        }

        if ($user_info === null) {
            $user_info = $this->get_user_info($username);

            if (is_wp_error($user_info)) {
                $radleLogs->log("Error fetching profile picture for $username: " . $user_info->get_error_message(), 'comments');
                return "https://www.redditstatic.com/avatars/defaults/v2/avatar_default_1.png";
            }
        }

        $profile_picture = $user_info['icon_img'] ?? "https://www.redditstatic.com/avatars/defaults/v2/avatar_default_1.png";
        $this->profile_image_cache[$username] = $profile_picture;

        $radleLogs->log("Fetched profile picture for $username", 'comments');

        return $profile_picture;
    }

    private function add_profile_pictures(&$comments) {

        global $radleLogs;

        foreach ($comments as &$comment) {
            $profile_picture = "https://www.redditstatic.com/avatars/defaults/v2/avatar_default_1.png";

            if (isset($this->profile_image_cache[$comment['author']])) {
                $profile_picture = $this->profile_image_cache[$comment['author']];
            } else {
                $user_info = $this->get_user_info($comment['author']);

                if (!is_wp_error($user_info)) {
                    $profile_picture = $user_info['icon_img'] ?? $profile_picture;
                    $this->profile_image_cache[$comment['author']] = $profile_picture;
                } else {
                    $radleLogs->log("Error fetching profile picture for {$comment['author']}: " . $user_info->get_error_message(), 'comments');
                    // Cache the default avatar to prevent repeated failed API calls within this request
                    $this->profile_image_cache[$comment['author']] = $profile_picture;
                }
            }

            $comment['profile_picture'] = $profile_picture;

            if (!empty($comment['children'])) {
                $this->add_profile_pictures($comment['children']);
            }
        }
        $radleLogs->log("Added profile pictures to comments", 'comments');
    }

    public function get_subreddit_entries($subreddit, $limit = 10) {

        global $radleLogs;

        $endpoint = "https://oauth.reddit.com/r/{$subreddit}/new.json?limit={$limit}";

        $response = $this->api_get($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'User-Agent' => $this->user_agent
            ]
        ]);

        if (is_wp_error($response)) {
            $radleLogs->log("Error fetching subreddit entries: " . $response->get_error_message(), 'api-reddit-subreddit');
            return $response;
        }

        $this->monitor_rate_limits($response, $endpoint);

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['data']['children'])) {
            $radleLogs->log("No entries found for subreddit: $subreddit", 'api');
            return new \WP_Error('no_entries', __('No entries found.','radle-lite'));
        }

        $entries = [];
        foreach ($body['data']['children'] as $child) {
            $entries[] = [
                'title' => $child['data']['title'],
                'url' => 'https://www.reddit.com' . $child['data']['permalink'],
                'author' => $child['data']['author'],
                'score' => $child['data']['score'],
            ];
        }

        $radleLogs->log("Fetched $limit entries from subreddit: $subreddit", 'api-reddit-subreddits');

        return $entries;
    }
}