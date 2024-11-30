<?php

namespace Radle\API\v1\radle;

use WP_REST_Request;
use WP_Error;

class Settings_Endpoint {
    public function register_routes() {
        register_rest_route('radle/v1', '/settings/update', [
            'methods' => 'POST',
            'callback' => [$this, 'update_settings'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }

    public function update_settings(WP_REST_Request $request) {
        $comment_system = $request->get_param('radle_comment_system');
        
        if (!in_array($comment_system, ['wordpress', 'radle', 'disabled'])) {
            return new WP_Error('invalid_comment_system', 'Invalid comment system value', ['status' => 400]);
        }

        update_option('radle_comment_system', $comment_system);

        return rest_ensure_response([
            'success' => true,
            'message' => __('Settings updated successfully', 'radle')
        ]);
    }
}
