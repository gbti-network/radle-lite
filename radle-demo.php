<?php
/**
 * Plugin Name: Radle Demo
 * Description: Radle brings the powers of the Reddit API into WordPress. This is the Demo version of Radle.
 * Version: 1.0.1
 * Author: GBTI
 * Author URI:  https://gbti.network
 * Contributors: Hudson Atwell
 * Text Domain: radle-demo
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Radle_Plugin {
    private $debug_mode;
    private $product_server;
    public $logs;

    public function __construct() {
        $this->set_constants();
        $this->set_variables();
        $this->load_textdomain();
        $this->includes();
        $this->initialize_modules();
        add_action( 'rest_api_init', [ $this, 'register_rest_endpoints' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), [ $this, 'add_plugin_action_links' ] );
    }

    private function set_constants() {
        define( 'RADLE_PLUGIN_FILE', __FILE__ );
        define( 'RADLE_VERSION', '1.0.1' );
        define( 'RADLE_GITHUB_REPO', 'gbti-network/radle-wordpress-plugin' );
        define( 'RADLE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
        define( 'RADLE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
        define( 'RADLE_LANGUAGES_DIR', RADLE_PLUGIN_DIR . 'languages' );
    }

    private function set_variables() {
        $this->debug_mode     = false;
        $this->product_server = 'https://gbti.network/wp-json/github-product-manager/v1';

        if ( defined( 'WP_ENVIRONMENT_TYPE' ) && WP_ENVIRONMENT_TYPE == 'local' ) {
            $this->product_server = 'https://gbti.local/wp-json/github-product-manager/v1';
            $this->debug_mode     = true;
        }

        define( 'RADLE_GBTI_API_SERVER', $this->product_server );
        define( 'RADLE_LOGGING_ENABLED', $this->debug_mode );
    }

    private function load_textdomain() {
        load_plugin_textdomain(
            'radle',
            false,
            dirname( plugin_basename( RADLE_PLUGIN_FILE ) ) . '/languages'
        );
    }

    private function includes() {
        require_once RADLE_PLUGIN_DIR . 'modules/utilities/log.php';
        require_once RADLE_PLUGIN_DIR . 'modules/utilities/markdown-handler.php';

        if ( is_admin() ) {
            require_once RADLE_PLUGIN_DIR . 'modules/welcome/welcome-module.php';
        }

        require_once RADLE_PLUGIN_DIR . 'modules/settings/setting-class.php';
        require_once RADLE_PLUGIN_DIR . 'modules/settings/settings-container.php';
        require_once RADLE_PLUGIN_DIR . 'modules/settings/sections/reddit-api-settings.php';
        require_once RADLE_PLUGIN_DIR . 'modules/settings/sections/publishing-settings.php';
        require_once RADLE_PLUGIN_DIR . 'modules/settings/sections/comments-settings.php';
        require_once RADLE_PLUGIN_DIR . 'modules/settings/sections/monitoring-settings.php';

        require_once RADLE_PLUGIN_DIR . 'modules/publish/publish.php';
        require_once RADLE_PLUGIN_DIR . 'modules/comments/comments.php';
        require_once RADLE_PLUGIN_DIR . 'modules/reddit/user-agent.php';
        require_once RADLE_PLUGIN_DIR . 'modules/reddit/rate-limit-monitor.php';
        require_once RADLE_PLUGIN_DIR . 'modules/reddit/reddit-api.php';
        require_once RADLE_PLUGIN_DIR . 'modules/usage/usage-tracking.php';

        require_once RADLE_PLUGIN_DIR . 'api/v1/reddit/check-auth-endpoint.php';
        require_once RADLE_PLUGIN_DIR . 'api/v1/reddit/comments-endpoint.php';
        require_once RADLE_PLUGIN_DIR . 'api/v1/reddit/delete-token-endpoint.php';
        require_once RADLE_PLUGIN_DIR . 'api/v1/reddit/entries-endpoint.php';
        require_once RADLE_PLUGIN_DIR . 'api/v1/reddit/oauth-callback-endpoint.php';
        require_once RADLE_PLUGIN_DIR . 'api/v1/reddit/publish-endpoint.php';
        require_once RADLE_PLUGIN_DIR . 'api/v1/reddit/refresh-token-endpoint.php';

        require_once RADLE_PLUGIN_DIR . 'api/v1/radle/disassociate-endpoint.php';
        require_once RADLE_PLUGIN_DIR . 'api/v1/radle/hide-comment-endpoint.php';
        require_once RADLE_PLUGIN_DIR . 'api/v1/radle/preview-endpoint.php';
        require_once RADLE_PLUGIN_DIR . 'api/v1/radle/rate-limit-data-endpoint.php';
        require_once RADLE_PLUGIN_DIR . 'api/v1/radle/settings-endpoint.php';
        require_once RADLE_PLUGIN_DIR . 'api/v1/radle/subreddit-endpoint.php';
        require_once RADLE_PLUGIN_DIR . 'api/v1/radle/welcome-endpoints.php';
    }

    private function initialize_modules() {
        $this->logs = new \Radle\Utilities\log();

        new Radle\Modules\Settings\Settings_Container();
        new Radle\Modules\Settings\Reddit_Api_Settings();
        new Radle\Modules\Settings\Publishing_Settings();
        new Radle\Modules\Settings\Comment_Settings();
        new Radle\Modules\Settings\Monitoring_Settings();
        new Radle\Modules\Publish\publish();
        new Radle\Modules\Comments\comments();

        if ( is_admin() ) {
            new Radle\Modules\Welcome\Welcome_Module();
        }
    }

    public function enqueue_scripts($hook) {
        $plugin_version = $this->get_plugin_version();
        $plugin_url = plugin_dir_url(__FILE__);

        // Enqueue debug utility first
        wp_register_script(
            'radle-debug',
            $plugin_url . 'modules/utilities/js/debug.js',
            array('jquery'),
            $plugin_version,
            false  
        );
    }

    public function register_rest_endpoints() {
        new \Radle\API\v1\Reddit\Check_Auth_Endpoint();
        new \Radle\API\v1\Reddit\Comments_Endpoint();
        new \Radle\API\v1\Reddit\Delete_Token_Endpoint();
        new \Radle\API\v1\Reddit\Entries_Endpoint();
        new \Radle\API\v1\Reddit\OAuth_Callback_Endpoint();
        new \Radle\API\v1\Reddit\Publish_Endpoint();
        new \Radle\API\v1\Reddit\Refresh_Token_Endpoint();

        new \Radle\API\v1\Radle\Disassociate_Endpoint();
        new \Radle\API\v1\Radle\Hide_Comment_Endpoint();
        new \Radle\API\v1\Radle\Preview_Endpoint();
        new \Radle\API\v1\Radle\Rate_Limit_Data_Endpoint();
        new \Radle\API\v1\Radle\Settings_Endpoint();
        new \Radle\API\v1\Radle\Subreddit_Endpoint();
        new \Radle\API\v1\Radle\Welcome_Endpoints();
    }

    private function get_plugin_version() {
        return RADLE_VERSION;
    }

    public function add_plugin_action_links( $links ) {
        $settings_link = '<a href="' . admin_url('admin.php?page=radle-settings') . '">' . __('Settings','radle-demo') . '</a>';
        $gbti_link = '<a href="https://gbti.network/" target="_blank">' . __('GBTI Network','radle-demo') . '</a>';
        array_unshift($links, $settings_link, $gbti_link);
        return $links;
    }
}

$Radle = new Radle_Plugin();

global $radleLogs;
$radleLogs = $Radle->logs;
