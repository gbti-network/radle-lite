<?php

namespace Radle\API\v1\radle;

use WP_REST_Controller;
use Radle\Modules\Reddit\Rate_Limit_Monitor;

class Rate_Limit_Data_Endpoint extends WP_REST_Controller {

    public function __construct() {
        $namespace = 'radle/v1';
        $base = 'rate-limit-data';

        register_rest_route($namespace, '/' . $base, [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_rate_limit_data'],
                'permission_callback' => [$this, 'permissions_check'],
                'args' => [
                    'period' => [
                        'default' => 'last-hour',
                        'enum' => ['last-hour', '24h', '7d', '30d'],
                    ],
                ],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_all_data'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
        ]);
    }

    public function permissions_check($request) {
        return current_user_can('manage_options');
    }

    public function get_rate_limit_data($request) {
        $period = $request->get_param('period');
        $monitor = new Rate_Limit_Monitor();
        $data = $monitor->get_rate_limit_data($period);
        return rest_ensure_response($data);
    }

    public function delete_all_data($request) {
        $monitor = new Rate_Limit_Monitor();
        $result = $monitor->delete_all_data();

        if ($result) {
            return rest_ensure_response(['success' => true]);
        } else {
            return new WP_Error('delete_failed', 'Failed to delete data', ['status' => 500]);
        }
    }
}