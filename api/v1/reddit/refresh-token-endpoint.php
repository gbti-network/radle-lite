<?php

namespace Radle\API\v1\Reddit;

use WP_REST_Controller;
use WP_Error;
use Radle\Modules\Reddit\Reddit_API;

class Refresh_Token_Endpoint extends WP_REST_Controller {

    public function __construct() {
        $namespace = 'radle/v1';
        $base = 'reddit/refresh-token';

        register_rest_route($namespace, '/' . $base, [
            [
                'methods' => 'POST',
                'callback' => [$this, 'refresh_token'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
        ]);
    }

    public function permissions_check($request) {
        global $radleLogs;
        $has_permission = current_user_can('edit_posts');
        if (!$has_permission) {
            $radleLogs->log("Permission check failed for refreshing Reddit token", 'api');
        }
        return $has_permission;
    }

    public function refresh_token($request) {
        global $radleLogs;

        $radleLogs->log("Attempting to refresh Reddit token", 'api');

        $redditAPI = Reddit_API::getInstance();
        $result = $redditAPI->authenticate();

        if ($result) {
            $radleLogs->log("Reddit token refreshed successfully", 'api');
            return rest_ensure_response([
                'message' => 'Token refreshed successfully',
                'refresh_needed' => true
            ]);
        } else {
            $radleLogs->log("Failed to refresh Reddit token", 'api');
            return new WP_Error('token_refresh_failed', 'Failed to refresh token', ['status' => 500]);
        }
    }
}