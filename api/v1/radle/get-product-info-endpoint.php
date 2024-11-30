<?php

namespace Radle\API\v1\Radle;

use WP_REST_Controller;
use WP_Error;
use Radle\Modules\Utilities\Markdown_Handler;

class Get_Product_Info_Endpoint extends WP_REST_Controller {

    public function __construct() {
        $this->namespace = 'radle/v1';
        $this->rest_base = 'get-releases';

        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods'  => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_releases'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    public function get_releases($request) {
        global $radleLogs;

        $radleLogs->log("Attempting to retrieve product releases information", 'api');

        $releases_file = RADLE_PLUGIN_DIR .'/.product/changelog.md';

        if (!file_exists($releases_file)) {
            $radleLogs->log("Releases file not found: $releases_file", 'api');
            return new WP_Error('no_file', 'Releases file not found', ['status' => 404]);
        }

        $releases_content = file_get_contents($releases_file);
        $html_content = wp_kses_post(Markdown_Handler::parse($releases_content));

        $radleLogs->log("Product releases information retrieved successfully", 'api');
        return rest_ensure_response(['releases_html' => $html_content]);
    }
}