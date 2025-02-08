<?php

namespace Radle\API\v1\radle;

use WP_REST_Controller;
use Radle\Modules\Reddit\Rate_Limit_Monitor;

/**
 * Handles REST API endpoints for Reddit API rate limit monitoring.
 * 
 * This class provides endpoints to retrieve and manage rate limit data
 * for Reddit API calls. It allows viewing historical rate limit data
 * over different time periods and managing the stored data.
 * 
 * @since 1.0.0
 */
class Rate_Limit_Data_Endpoint extends WP_REST_Controller {

    /**
     * Initialize the endpoint and register routes.
     * 
     * Sets up two REST API endpoints:
     * 1. GET endpoint for retrieving rate limit data with period filtering
     * 2. DELETE endpoint for clearing all stored rate limit data
     */
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

    /**
     * Check if the current user has permission to access rate limit data.
     * 
     * @param \WP_REST_Request $request The request object.
     * @return bool True if user has admin capabilities, false otherwise.
     */
    public function permissions_check($request) {
        return current_user_can('manage_options');
    }

    /**
     * Retrieve rate limit data for the specified time period.
     * 
     * Fetches historical rate limit data from the Rate_Limit_Monitor.
     * Available periods are: last-hour, 24h, 7d, and 30d.
     * 
     * @param \WP_REST_Request $request Request object containing the period parameter.
     * @return \WP_REST_Response Response containing the rate limit data.
     */
    public function get_rate_limit_data($request) {
        $period = $request->get_param('period');
        $monitor = new Rate_Limit_Monitor();
        $data = $monitor->get_rate_limit_data($period);
        return rest_ensure_response($data);
    }

    /**
     * Delete all stored rate limit monitoring data.
     * 
     * Completely clears the rate limit history from the database.
     * This action cannot be undone.
     * 
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error Success response or error if deletion fails.
     */
    public function delete_all_data($request) {
        $monitor = new Rate_Limit_Monitor();
        $result = $monitor->delete_all_data();

        if ($result) {
            return rest_ensure_response(['success' => true]);
        } else {
            return new WP_Error(
                'delete_failed',
                'Failed to delete rate limit data',
                ['status' => 500]
            );
        }
    }
}