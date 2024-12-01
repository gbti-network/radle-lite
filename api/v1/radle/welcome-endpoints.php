<?php

namespace Radle\API\v1\radle;

use WP_REST_Controller;
use WP_REST_Request;
use WP_Error;

class Welcome_Endpoints extends WP_REST_Controller {

    private $option_name = 'radle_demo_welcome_progress';
    private $attribution_option = 'radle_demo_show_attribution';

    public function __construct() {
        $this->register_routes();
    }

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

    public function permission_check() {
        return current_user_can('manage_options');
    }

    public function update_progress(WP_REST_Request $request) {
        $step = $request->get_param('step');
        update_option($this->option_name, intval($step));

        // Handle Reddit credentials (step 2 to 3)
        if ($step === 3) {
            $client_id = $request->get_param('client_id');
            $client_secret = $request->get_param('client_secret');
            
            if ($client_id && $client_secret) {
                update_option('radle_client_id', sanitize_text_field($client_id));
                update_option('radle_client_secret', sanitize_text_field($client_secret));
            }
        }

        // Handle comment system preference (step 5 to 6)
        if ($step === 6) {
            $enable_comments = $request->get_param('enable_comments');
            if (isset($enable_comments)) {
                update_option('radle_comment_system', $enable_comments ? 'radle' : 'wordpress');
            }
        }

        // Handle attribution preference (step 6 to 7)
        if ($step === 7) {
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

    public function reset_progress() {
        update_option($this->option_name, 1);
        return rest_ensure_response(['success' => true]);
    }
}