<?php

namespace Radle\API\v1\Reddit;

use WP_REST_Controller;
use WP_Error;
use Radle\Modules\Reddit\Reddit_API;

class Entries_Endpoint extends WP_REST_Controller {

    public function __construct() {
        $this->namespace = 'radle/v1';
        $this->rest_base = 'reddit/get-entries';

        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods'  => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_entries'],
                'permission_callback' => [$this, 'get_entries_permissions_check'],
            ],
        ]);
    }

    public function get_entries_permissions_check($request) {
        global $radleLogs;
        $has_permission = current_user_can('manage_options');
        if (!$has_permission) {
            $radleLogs->log("Permission check failed for fetching Reddit entries", 'api');
        }
        return $has_permission;
    }

    public function get_entries($request) {
        global $radleLogs;

        $redditAPI = Reddit_API::getInstance();
        $subreddit = get_option('radle_subreddit', '');

        if (empty($subreddit)) {
            $radleLogs->log("Failed to fetch Reddit entries: No subreddit selected", 'api');
            return new WP_Error('no_subreddit', __('No subreddit selected.', 'radle'), ['status' => 400]);
        }

        $radleLogs->log("Fetching entries for subreddit: $subreddit", 'api');
        $entries = $redditAPI->get_subreddit_entries($subreddit);

        if (is_wp_error($entries)) {
            $error_message = $entries->get_error_message();
            $radleLogs->log("Error fetching Reddit entries: $error_message", 'api');
            return $entries;
        }

        $entry_count = count($entries);
        $radleLogs->log("Successfully fetched $entry_count entries from subreddit: $subreddit", 'api');
        return rest_ensure_response($entries);
    }
}