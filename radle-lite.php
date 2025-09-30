<?php
/**
 * Plugin Name: Radle Lite
 * Description: Radle brings the powers of the Reddit API into WordPress.
 * Version: 1.1.0
 * Author: GBTI
 * Author URI:  https://gbti.network
 * Contributors: GBTI,Hudson Atwell
 * Text Domain: radle-lite
 * Domain Path: /languages
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 5.9.0
 * Requires PHP: 7.4
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main plugin class that initializes all Radle functionality.
 * 
 * This class is responsible for:
 * - Loading required files and dependencies
 * - Initializing all plugin modules
 * - Setting up WordPress hooks and filters
 * - Managing plugin settings and configuration
 * - Handling internationalization
 * - Setting up REST API endpoints
 */
class Radle_Plugin {

    /**
     * Debug mode flag
     * @var bool
     */
    private $debug_mode;

    /**
     * GBTI product server URL
     * @var string
     */
    private $product_server;

    /**
     * Logging utility instance
     * @var object
     */
    public $logs;

    /**
     * Usage tracking instance
     * @var object
     */
    private $usage_tracking;

    /**
     * Initialize the plugin.
     * 
     * Sets up all necessary hooks, loads dependencies,
     * and initializes core functionality.
     */
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

    /**
     * Define plugin constants.
     * 
     * Sets up constants for:
     * - Plugin file paths
     * - Version information
     * - GitHub repository
     * - Directory paths
     * 
     * @access private
     */
    private function set_constants() {
        define( 'RADLE_PLUGIN_FILE', __FILE__ );
        define( 'RADLE_VERSION', '1.1.0' );
        define( 'RADLE_GITHUB_REPO', 'gbti-network/radle-wordpress-plugin' );
        define( 'RADLE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
        define( 'RADLE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
        define( 'RADLE_LANGUAGES_DIR', RADLE_PLUGIN_DIR . 'languages' );
    }

    /**
     * Set plugin variables based on environment.
     * 
     * Configures:
     * - Debug mode
     * - API server URL
     * - Logging settings
     * 
     * @access private
     */
    private function set_variables() {
        $this->debug_mode     = false;
        $this->product_server = 'https://gbti.network/wp-json/github-product-manager/v1';

        if ( defined( 'WP_ENVIRONMENT_TYPE' ) && WP_ENVIRONMENT_TYPE == 'local' ) {
            $this->debug_mode     = true;
        }

        define( 'RADLE_GBTI_API_SERVER', $this->product_server );
        define( 'RADLE_LOGGING_ENABLED', $this->debug_mode );
    }

    /**
     * Load plugin text domain for internationalization.
     * 
     * @access private
     */
    private function load_textdomain() {
        load_plugin_textdomain(
            'radle-lite',
            false,
            dirname( plugin_basename( RADLE_PLUGIN_FILE ) ) . '/languages'
        );
    }

    /**
     * Include required plugin files.
     * 
     * Loads:
     * - Core modules
     * - Settings sections
     * - API endpoints
     * - Utility classes
     * 
     * @access private
     */
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
        require_once RADLE_PLUGIN_DIR . 'modules/reddit/image-upload.php';
        require_once RADLE_PLUGIN_DIR . 'modules/usage/usage-tracking.php';

        require_once RADLE_PLUGIN_DIR . 'api/v1/reddit/associate-endpoint.php';
        require_once RADLE_PLUGIN_DIR . 'api/v1/reddit/check-auth-endpoint.php';
        require_once RADLE_PLUGIN_DIR . 'api/v1/reddit/comments-endpoint.php';
        require_once RADLE_PLUGIN_DIR . 'api/v1/reddit/delete-token-endpoint.php';
        require_once RADLE_PLUGIN_DIR . 'api/v1/reddit/entries-endpoint.php';
        require_once RADLE_PLUGIN_DIR . 'api/v1/reddit/oauth-callback-endpoint.php';
        require_once RADLE_PLUGIN_DIR . 'api/v1/reddit/prepare-images-endpoint.php';
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

    /**
     * Initialize plugin modules.
     * 
     * Sets up:
     * - Settings pages
     * - Reddit API integration
     * - Comments system
     * - Publishing tools
     * - Usage tracking
     * 
     * @access private
     */
    private function initialize_modules() {
        $this->logs = new \Radle\Utilities\log();

        // Initialize usage tracking
        $this->usage_tracking = new \Radle\Modules\Usage\Usage_Tracking(
            'radle',
            $this->product_server,
            RADLE_VERSION
        );
        
        // Register activation and deactivation hooks for usage tracking
        register_activation_hook(RADLE_PLUGIN_FILE, array($this->usage_tracking, 'activate'));
        register_deactivation_hook(RADLE_PLUGIN_FILE, array($this->usage_tracking, 'deactivate'));

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

    /**
     * Register REST API endpoints.
     * 
     * Sets up endpoints for:
     * - Reddit API integration
     * - Comment management
     * - Publishing tools
     * - Settings management
     */
    public function register_rest_endpoints() {
        new \Radle\API\v1\Reddit\Associate_Endpoint();
        new \Radle\API\v1\Reddit\Check_Auth_Endpoint();
        new \Radle\API\v1\Reddit\Comments_Endpoint();
        new \Radle\API\v1\Reddit\Delete_Token_Endpoint();
        new \Radle\API\v1\Reddit\Entries_Endpoint();
        new \Radle\API\v1\Reddit\OAuth_Callback_Endpoint();
        new \Radle\API\v1\Reddit\Prepare_Images_Endpoint();
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

    /**
     * Enqueue plugin scripts and styles.
     * 
     * Loads required assets for:
     * - Comment system
     * - Publishing tools
     * - Admin interface
     */
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

    /**
     * Add plugin action links.
     * 
     * Adds quick access links to:
     * - Settings page
     * - Documentation
     * 
     * @param array $links Existing plugin action links
     * @return array Modified plugin action links
     */
    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=radle-settings') . '">' . __('Settings','radle-lite') . '</a>';
        $gbti_link = '<a href="https://gbti.network/" target="_blank">' . __('GBTI Network','radle-lite') . '</a>';
        array_unshift($links, $settings_link, $gbti_link);
        return $links;
    }

    /**
     * Get the plugin version.
     * 
     * @return string Plugin version
     */
    private function get_plugin_version() {
        return RADLE_VERSION;
    }
}

$Radle = new Radle_Plugin();

global $radleLogs;
$radleLogs = $Radle->logs;
