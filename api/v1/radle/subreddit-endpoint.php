<?php

namespace Radle\API\v1\Radle;

use WP_REST_Controller;
use WP_Error;

class SubReddit_Endpoint extends WP_REST_Controller {

    public function __construct() {
        $this->namespace = 'radle/v1';
        $this->rest_base = 'radle/set-subreddit';

        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => 'POST',
                'callback' => [$this, 'set_subreddit'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
        ]);
    }

    public function permissions_check($request) {
        return current_user_can('manage_options');
    }

    public function set_subreddit($request) {
        $subreddit = $request->get_param('subreddit');

        if (empty($subreddit)) {
            return new WP_Error('invalid_subreddit', __('Invalid subreddit', 'radle'), ['status' => 400]);
        }

        update_option('radle_subreddit', $subreddit);

        return rest_ensure_response([
            'success' => true,
            'message' => __('Subreddit updated successfully', 'radle')
        ]);
    }
}