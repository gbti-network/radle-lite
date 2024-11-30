<?php

namespace Radle\API\v1\radle;

use WP_REST_Controller;
use WP_Error;
use Radle\Modules\Reddit\Reddit_API;

class Preview_Endpoint extends WP_REST_Controller {

    public function __construct() {
        $namespace = 'radle/v1';
        $base = 'preview';

        register_rest_route($namespace, '/' . $base, [
            [
                'methods' => 'POST',
                'callback' => [$this, 'preview_post'],
                'permission_callback' => [$this, 'permissions_check'],
                'args' => $this->get_endpoint_args_for_item_schema(true),
            ],
        ]);
    }

    public function permissions_check($request) {
        return current_user_can('edit_posts');
    }

    public function preview_post($request) {

        $title = $request->get_param('title');
        $content = $request->get_param('content');
        $post_id = $request->get_param('post_id');

        $post = get_post($post_id);
        if (!$post || 'post' != $post->post_type) {
            return new WP_Error('invalid_post', 'Invalid post', array('status' => 404));
        }

        $redditAPI = Reddit_API::getInstance();

        // Replace tokens in title and content with actual values
        $title = $redditAPI->replace_tokens($title, $post);
        $content = $redditAPI->replace_tokens($content, $post);

        return rest_ensure_response(['title' => $title, 'content' => $content]);
    }

}
