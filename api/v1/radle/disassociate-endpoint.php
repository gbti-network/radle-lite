<?php

namespace Radle\API\v1\radle;

use WP_REST_Controller;
use WP_Error;
use Radle\Modules\Reddit\Reddit_API;

class Disassociate_Endpoint extends WP_REST_Controller {

    public function __construct() {
        $namespace = 'radle/v1';
        $base = 'disassociate';

        register_rest_route($namespace, '/' . $base, [
            [
                'methods' => 'POST',
                'callback' => [$this, 'delete_reddit_id'],
                'permission_callback' => [$this, 'permissions_check'],
                'args' => [
                    'post_id' => [
                        'required' => true,
                        'validate_callback' => function($param, $request, $key) {
                            return is_numeric($param);
                        }
                    ]
                ]
            ],
        ]);
    }

    public function permissions_check($request) {
        global $radleLogs;

        $post_id = $request->get_param('post_id');
        $has_permission = current_user_can('edit_post', $post_id);
        if (!$has_permission) {
            $radleLogs->log("Permission check failed for disassociating Reddit post. Post ID: $post_id", 'api');
        }
        return $has_permission;
    }

    public function delete_reddit_id($request) {
        global $radleLogs;
        $post_id = $request->get_param('post_id');

        if (!current_user_can('edit_post', $post_id)) {
            $radleLogs->log("Unauthorized attempt to disassociate Reddit post. Post ID: $post_id", 'api');
            return new WP_Error('rest_forbidden', __('You do not have permissions to edit this post.','radle-demo'), ['status' => 403]);
        }

        $radleLogs->log("Disassociating Reddit post. Post ID: $post_id", 'api');
        delete_post_meta($post_id, '_reddit_post_id');

        $radleLogs->log("Reddit post successfully disassociated. Post ID: $post_id", 'api');
        return rest_ensure_response(['message' => __('Reddit post disassociated','radle-demo')]);
    }
}