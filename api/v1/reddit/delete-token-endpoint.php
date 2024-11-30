<?php

namespace Radle\API\v1\reddit;

use WP_REST_Controller;
use WP_Error;
use Radle\Modules\Reddit\Reddit_API;

class Delete_Token_Endpoint extends WP_REST_Controller {

    public function __construct() {
        $namespace = 'radle/v1';
        $base = 'reddit/reset-token';

        register_rest_route($namespace, '/' . $base, [
            [
                'methods' => 'POST',
                'callback' => [$this, 'reset_token'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
        ]);
    }

    public function permissions_check($request) {
        global $radleLogs;
        $has_permission = current_user_can('manage_options');
        if (!$has_permission) {
            $radleLogs->log("Permission check failed for resetting Reddit token", 'api');
        }
        return $has_permission;
    }

    public function reset_token($request) {
        global $radleLogs;

        $radleLogs->log("Attempting to reset Reddit authorization", 'api');

        delete_option('radle_reddit_access_token');
        delete_option('radle_raddit_refresh_token');

        $radleLogs->log("Reddit authorization reset successfully", 'api');
        return rest_ensure_response(['message' => 'Authorization reset successfully']);
    }
}