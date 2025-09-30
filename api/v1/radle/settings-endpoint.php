<?php

namespace Radle\API\v1\radle;

use WP_REST_Request;
use WP_Error;

/**
 * Handles REST API endpoints for plugin settings management.
 * 
 * Provides endpoints to update plugin configuration settings,
 * specifically focusing on comment system preferences.
 * 
 * @since 1.0.0
 */
class Settings_Endpoint {
    
    /**
     * Register the settings update endpoint.
     * 
     * @return void
     */
    public function register_routes() {
        register_rest_route('radle/v1', '/settings/update', [
            'methods' => 'POST',
            'callback' => [$this, 'update_settings'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }

    /**
     * Update plugin settings based on the provided request parameters.
     * 
     * @param WP_REST_Request $request The request object containing settings data.
     * @return \WP_REST_Response|\WP_Error Response on success, WP_Error on failure.
     */
    public function update_settings(WP_REST_Request $request) {
        $comment_system = $request->get_param('radle_comment_system');
        
        // Validate comment system setting
        if (!in_array($comment_system, ['wordpress','radle', 'radle_above_wordpress', 'radle_below_wordpress', 'shortcode', 'disabled'])) {
            return new WP_Error(
                'invalid_comment_system',
                'Invalid comment system value',
                ['status' => 400]
            );
        }

        update_option('radle_comment_system', $comment_system);

        return rest_ensure_response([
            'success' => true,
            'message' => __('Settings updated successfully','radle-lite')
        ]);
    }
}
