<?php

namespace Radle\API\v1\radle;

use WP_REST_Controller;
use WP_REST_Request;
use WP_Error;

class Welcome_Endpoints extends WP_REST_Controller {

    private $option_name = 'radle_welcome_progress';

    public function __construct() {
        $this->register_routes();
    }

    public function register_routes() {
        $namespace = 'radle/v1';
        $base = 'welcome';

        register_rest_route($namespace, '/' . $base . '/update-progress', [
            'methods' => 'POST',
            'callback' => [$this, 'update_progress'],
            'permission_callback' => [$this, 'permission_check'],
        ]);

        register_rest_route($namespace, '/' . $base . '/reset-progress', [
            'methods' => 'POST',
            'callback' => [$this, 'reset_progress'],
            'permission_callback' => [$this, 'permission_check'],
        ]);

        register_rest_route($namespace, '/' . $base . '/exchange-token', [
            'methods' => 'POST',
            'callback' => [$this, 'exchange_token'],
            'permission_callback' => [$this, 'permission_check'],
        ]);

    }

    public function permission_check() {
        return current_user_can('manage_options');
    }

    public function update_progress(WP_REST_Request $request) {

        $step = $request->get_param('step');
        update_option($this->option_name, intval($step));

        // Save sharing preferences if provided (step 4 to 5)
        $share_events = $request->get_param('radle_share_events');
        $share_domain = $request->get_param('radle_share_domain');
        if (isset($share_events) && isset($share_domain)) {
            $share_events = $request->get_param('radle_share_events') === 'true';
            $share_domain = $request->get_param('radle_share_domain') === 'true';

            update_option('radle_share_events', $share_events);
            update_option('radle_share_domain', $share_domain);
        }

        // Save Reddit credentials if provided (step 5 to 6)
        $reddit_client_id = $request->get_param('reddit_client_id');
        $reddit_client_secret = $request->get_param('reddit_client_secret');
        if ($reddit_client_id && $reddit_client_secret) {
            update_option('radle_client_id', sanitize_text_field($reddit_client_id));
            update_option('radle_client_secret', sanitize_text_field($reddit_client_secret));
        }

        return rest_ensure_response(['success' => true]);
    }

    public function reset_progress() {
        update_option($this->option_name, 1);
        delete_option('radle_github_access_token');
        delete_option('radle_reddit_access_token');
        delete_option('radle_reddit_refresh_token');
        return rest_ensure_response(['success' => true]);
    }

    public function exchange_token(WP_REST_Request $request) {
        $token = $request->get_param('access_token');

        if (empty($token)) {
            return rest_ensure_response([
                'success' => false,
                'message' => 'No access token provided'
            ]);
        }

        update_option('radle_github_access_token', $token);
        return rest_ensure_response(['success' => true]);
    }
}