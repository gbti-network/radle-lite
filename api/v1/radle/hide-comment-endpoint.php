<?php


namespace Radle\API\v1\radle;

use WP_REST_Controller;
use WP_Error;

class Hide_Comment_Endpoint extends WP_REST_Controller {

    public function __construct()  {
        $namespace = 'radle/v1';
        $base = 'hide-comment';

        register_rest_route($namespace, '/' . $base, [
            [
                'methods' => 'POST',
                'callback' => [$this, 'toggle_comment_visibility'],
                'permission_callback' => [$this, 'permissions_check'],
                'args' => [
                    'post_id' => [
                        'required' => true,
                        'validate_callback' => function ($param) {
                            return is_numeric($param);
                        }
                    ],
                    'comment_id' => [
                        'required' => true,
                        'validate_callback' => function ($param) {
                            return is_string($param);
                        }
                    ]
                ]
            ],
        ]);
    }

    public function permissions_check($request)
    {
        return current_user_can('edit_posts');
    }

    public function toggle_comment_visibility($request)
    {
        $post_id = $request->get_param('post_id');
        $comment_id = $request->get_param('comment_id');

        $hidden_comments = get_post_meta($post_id, '_radle_hidden_comments', true);
        if (!is_array($hidden_comments)) {
            $hidden_comments = [];
        }

        $index = array_search($comment_id, $hidden_comments);
        if ($index !== false) {
            unset($hidden_comments[$index]);
            $action = 'shown';
        } else {
            $hidden_comments[] = $comment_id;
            $action = 'hidden';
        }

        update_post_meta($post_id, '_radle_hidden_comments', array_values($hidden_comments));

        return rest_ensure_response([
            'success' => true,
            'action' => $action,
            'hidden_comments' => $hidden_comments
        ]);
    }
}