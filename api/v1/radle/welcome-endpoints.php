<?php

namespace Radle\API\v1\radle;

use WP_REST_Controller;
use WP_REST_Request;
use WP_Error;

/**
 * Handles REST API endpoints for the Welcome wizard functionality.
 * 
 * This class manages the onboarding process through a step-by-step wizard,
 * handling progress tracking and configuration settings for Reddit integration.
 * 
 * @since 1.0.0
 */
class Welcome_Endpoints extends WP_REST_Controller {

    /**
     * Option name for storing the welcome wizard progress.
     * @var string
     */
    private $option_name = 'radle_lite_welcome_progress';

    /**
     * Option name for controlling attribution display.
     * @var string
     */
    private $attribution_option = 'radle_lite_show_attribution';

    /**
     * Initialize the endpoints.
     */
    public function __construct() {
        $this->register_routes();
    }

    /**
     * Register REST API routes for the welcome wizard.
     * 
     * @return void
     */
    public function register_routes() {
        $namespace = 'radle/v1';
        $base = 'welcome';

        register_rest_route($namespace, '/' . $base . '/progress', [
            'methods' => 'POST',
            'callback' => [$this, 'update_progress'],
            'permission_callback' => [$this, 'permission_check'],
        ]);

        register_rest_route($namespace, '/' . $base . '/reset', [
            'methods' => 'POST',
            'callback' => [$this, 'reset_progress'],
            'permission_callback' => [$this, 'permission_check'],
        ]);
    }

    /**
     * Check if the current user has permission to access these endpoints.
     * 
     * @return bool True if user can manage options, false otherwise.
     */
    public function permission_check() {
        return current_user_can('manage_options');
    }

    /**
     * Update the welcome wizard progress and handle step-specific actions.
     * 
     * @param WP_REST_Request $request The request object.
     * @return \WP_REST_Response Response containing success status and current step.
     */
    public function update_progress(WP_REST_Request $request) {
        
        $step = (int) $request->get_param('step');
        update_option($this->option_name, intval($step));

        // Step 3: Save Reddit API credentials
        if ($step === 3) {
            $client_id = $request->get_param('client_id');
            $client_secret = $request->get_param('client_secret');
            
            if ($client_id && $client_secret) {
                update_option('radle_client_id', sanitize_text_field($client_id));
                update_option('radle_client_secret', sanitize_text_field($client_secret));
            }
        }

        // Step 5: Configure subreddit selection
        if ($step === 5) {
            $subreddit = $request->get_param('subreddit');
            if ($subreddit) {
                update_option('radle_subreddit', sanitize_text_field($subreddit));
            }
        }

        // Step 6: Set comment system preference
        if ($step === 6) {
            $enable_comments = $request->get_param('enable_comments');
            if (isset($enable_comments)) {
                update_option('radle_comment_system', $enable_comments ? 'radle' : 'wordpress');
            }
        }

        // Step 7: Save data sharing preferences
        if ($step === 7) {
            $share_events = $request->get_param('share_events');
            $share_domain = $request->get_param('share_domain');
            
            if (isset($share_events)) {
                update_option('radle_share_events', (bool)$share_events);
            }
            if (isset($share_domain)) {
                update_option('radle_share_domain', (bool)$share_domain);
            }
        }

        // Step 8: Configure attribution settings
        if ($step === 8) {
            $attribution_enabled = $request->get_param('attribution_enabled');
            if (isset($attribution_enabled)) {
                update_option($this->attribution_option, (bool)$attribution_enabled);
            }
        }

        return rest_ensure_response([
            'success' => true,
            'step' => $step
        ]);
    }

    /**
     * Reset the welcome wizard progress and clear all Reddit-related settings.
     * 
     * @return \WP_REST_Response Response indicating success status.
     */
    public function reset_progress() {
        // Reset welcome progress
        update_option($this->option_name, 1);

        // Clear all Reddit-related credentials and tokens
        delete_option('radle_client_id');
        delete_option('radle_client_secret');
        delete_option('radle_reddit_access_token');
        delete_option('radle_reddit_refresh_token');
        delete_option('radle_subreddit');

        return rest_ensure_response(['success' => true]);
    }
}