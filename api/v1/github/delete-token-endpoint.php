<?php

namespace Radle\API\v1\GitHub;

use WP_REST_Controller;
use WP_REST_Request;
use WP_Error;

class Delete_Token_Endpoint extends WP_REST_Controller {

    private $github_token_option = 'radle_github_access_token';

    public function __construct() {
        $this->namespace = 'radle/v1';
        $this->rest_base = 'github/delete-token';

        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => 'POST',
                'callback' => [$this, 'delete_token'],
                'permission_callback' => [$this, 'permission_check'],
            ],
        ]);
    }

    public function permission_check() {
        global $radleLogs;
        $has_permission = current_user_can('manage_options');
        if (!$has_permission) {
            $radleLogs->log("Permission check failed for GitHub token deletion", 'api');
        }
        return $has_permission;
    }

    public function delete_token() {
        global $radleLogs;

        $radleLogs->log("Attempting to delete GitHub access token", 'api');

        $deleted = delete_option($this->github_token_option);

        if (!$deleted) {
            $radleLogs->log("Failed to delete GitHub access token", 'api');
            return rest_ensure_response([
                'success' => false,
                'message' => 'Failed to delete the token'
            ]);
        }

        $radleLogs->log("GitHub access token successfully deleted", 'api');
        return rest_ensure_response([
            'success' => true,
            'message' => 'Token successfully deleted'
        ]);
    }
}