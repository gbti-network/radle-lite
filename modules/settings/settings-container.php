<?php

namespace Radle\Modules\Settings;

/**
 * Main settings page container for the Radle plugin.
 * 
 * This class manages the plugin's settings interface, including:
 * - Settings page creation and rendering
 * - Tab-based navigation
 * - Settings sections organization
 * - Overview and feature presentation
 * - Pro features promotion
 * 
 * The settings are organized into tabs:
 * - Overview: Plugin introduction and feature matrix
 * - Reddit Connection: API authentication settings
 * - General Settings: Publishing preferences
 * - Comment Management: Comment display and behavior
 * - Monitoring: Usage statistics and rate limits
 */
class Settings_Container {

    /**
     * Settings page slug
     * @var string
     */
    protected $settings_page = 'radle-settings';

    /**
     * Settings page title
     * @var string
     */
    protected $settings_title;

    /**
     * Settings menu label
     * @var string
     */
    protected $settings_menu;

    /**
     * Array of available settings tabs
     * @var array
     */
    protected $tabs = [];

    /**
     * Initialize the settings container and set up WordPress hooks.
     * 
     * Sets up:
     * - Admin menu page
     * - Admin styles and scripts
     * - Settings tabs
     * - AJAX handlers
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'create_settings_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
        add_action('wp_ajax_radle_reset_authorization', [$this, 'reset_authorization']);

        $this->tabs = [
            'overview' => __('Overview','radle-lite'),
            'reddit' => __('Reddit Connection','radle-lite'),
            'publishing' => __('General Settings','radle-lite'),
            'comments' => __('Comment Management','radle-lite'),
            'monitoring' => __('Monitoring','radle-lite')
        ];

        /**
         * Filter Radle settings tabs
         *
         * Allows extensions (like Radle Pro) to add additional settings tabs
         *
         * @since 1.2.0
         * @param array $tabs Array of tabs with slug => label pairs
         */
        $this->tabs = apply_filters('radle_settings_tabs', $this->tabs);

        $this->settings_title = __('Radle','radle-lite');
        $this->settings_menu = __('Radle','radle-lite');
    }

    /**
     * Create the WordPress admin settings page.
     * 
     * Adds a new submenu page under the Settings menu
     * with appropriate user capabilities.
     */
    public function create_settings_page() {
        add_options_page(
            $this->settings_title,
            $this->settings_menu,
            'manage_options',
            $this->settings_page,
            [$this, 'settings_page_content']
        );
    }

    /**
     * Render the settings page content.
     * 
     * Handles:
     * - Tab navigation
     * - Section display
     * - Form rendering
     * - Settings fields organization
     */
    public function settings_page_content() {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'overview';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($this->settings_title); ?></h1>
            <h2 class="nav-tab-wrapper">
                <?php
                foreach ($this->tabs as $tab_key => $tab_caption) {
                    $active = ($current_tab === $tab_key) ? ' nav-tab-active' : '';
                    echo sprintf(
                        '<a href="?page=%s&tab=%s" class="nav-tab%s" data-tab="%s">%s</a>',
                        esc_attr($this->settings_page),
                        esc_attr($tab_key),
                        esc_attr($active),
                        esc_attr($tab_key),
                        esc_html($tab_caption)
                    );
                }
                ?>
            </h2>
            <div class="radle-settings-sections">
            <?php
            // Create all tab sections upfront but hide them
            foreach ($this->tabs as $tab_key => $tab_caption) {
                echo '<div id="radle-settings-' . esc_attr($tab_key) . '" class="radle-settings-section ' . 
                    esc_attr($current_tab === $tab_key ? 'active' : 'hidden') . '">';
                
                if ($tab_key === 'overview') {
                    $this->render_overview_tab();
                } else {
                    settings_errors();
                    echo '<form method="post" action="options.php">';
                    do_settings_sections($this->settings_page . '-' . $tab_key);
                    $settings_group = $tab_key === 'publishing' ? 'radle_publishing_settings' : ($tab_key === 'comments' ? 'radle_comment_settings' : 'radle_settings');
                    settings_fields($settings_group);
                    if ($tab_key !== 'monitoring') {
                        submit_button();
                    }
                    echo '</form>';
                }
                
                echo '</div>';
            }
            ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the overview tab content.
     * 
     * Displays:
     * - Welcome message
     * - Feature matrix
     * - Pro features promotion
     * - Upgrade CTA
     * 
     * @access private
     */
    private function render_overview_tab() {
        ?>
        <div class="radle-overview-container">
            <div class="radle-overview-section">
                <h2><?php esc_html_e('Welcome to Radle','radle-lite'); ?></h2>
                <p><?php esc_html_e('Radle integrates Reddit\'s discussion platform into your WordPress site, creating a vibrant community hub where your content and discussions thrive in both ecosystems.','radle-lite'); ?></p>
                
                <div class="radle-feature-matrix">
                    <h3><?php esc_html_e('Features','radle-lite'); ?></h3>
                    <ul class="radle-feature-list">
                        <li>
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('Reddit-Powered Comments System','radle-lite'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('One-Click Content Publishing','radle-lite'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('Real-Time Comment Synchronization','radle-lite'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('Comment Sorting (Newest, Most Popular, Oldest, Least Popular, Most Engaged, Most Balanced, Q&A)','radle-lite'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('Configurable Comment Threading (up to 10 levels) & Expanded Replies','radle-lite'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('Comment Search','radle-lite'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('Configurable Comment Caching','radle-lite'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('Author Badges (Original Poster, Moderator, Pinned)','radle-lite'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('Per-Post Destination Override (subreddit or user profile)','radle-lite'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('Per-Post Comment System Override','radle-lite'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('SEO Meta Tokens (Yoast SEO & Rank Math)','radle-lite'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('Option to Hide the Legacy Comments Menu','radle-lite'); ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue admin styles and scripts for the settings page.
     * 
     * Loads:
     * - Dashicons
     * - Chart.js for monitoring
     * - Custom monitoring scripts
     * - Localized script data
     * 
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_styles($hook) {
        if (strpos($hook, $this->settings_page) === false) {
            return;
        }

        wp_enqueue_style('dashicons');

        wp_enqueue_script(
            'chart-js',
            RADLE_PLUGIN_URL . 'assets/libraries/chart.min.js',
            [],
            RADLE_VERSION,
            true
        );

        wp_enqueue_script(
            'radle-monitoring',
            RADLE_PLUGIN_URL . 'modules/settings/js/monitoring.js',
            ['jquery', 'chart-js'],
            RADLE_VERSION,
            true
        );

        wp_localize_script('radle-monitoring', 'radleMonitoring', [
            'root' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'timezone' => wp_timezone_string(),
            'i18n' => [
                'minutes' => esc_html__('Minutes','radle-lite'),
                'hours' => esc_html__('Hours','radle-lite'),
                'daysOfWeek' => esc_html__('Days of the Week','radle-lite'),
                'days' => esc_html__('Days','radle-lite'),
                'numberOfCalls' => esc_html__('Number of Calls','radle-lite'),
                'apiRateLimitData' => esc_html__('API Rate Limit Data','radle-lite'),
                'numberOfApiCalls' => esc_html__('Number of API calls','radle-lite'),
                'breachesOf90CallsPerMin' => esc_html__('Breaches of 90 calls/min','radle-lite'),
                'failedCallsDueToRateLimits' => esc_html__('Failed calls due to rate limits','radle-lite'),
                'confirmDeleteData' => esc_html__('Are you sure you want to delete all collected data? This action cannot be undone.','radle-lite'),
                'dataDeleted' => esc_html__('All data has been deleted.','radle-lite'),
                'errorDeletingData' => esc_html__('An error occurred while deleting the data. Please try again.','radle-lite'),
                'breachesAndFailures' => esc_html__('Breaches/Failures.','radle-lite'),
            ]
        ]);

        wp_enqueue_style('radle-admin-styles', RADLE_PLUGIN_URL . 'modules/settings/css/settings.css', [], RADLE_VERSION);
        wp_enqueue_script('radle-admin-scripts', RADLE_PLUGIN_URL . 'modules/settings/js/settings.js', ['jquery', 'wp-api' , 'radle-monitoring', 'radle-debug'], RADLE_VERSION, true);
        wp_localize_script('radle-admin-scripts', 'radleSettings', [
            'i18n' => [
                'noCompany' => __('No company','radle-lite'),
                'resetAuthorization' => __('Reset Authorization','radle-lite'),
                'latestReleases' => __('Latest Releases','radle-lite'),
                'AuthorizationReset' => __('Authorization has been reset.','radle-lite'),
                'redditFailedApiConnection' => __('Failed to connect to the Reddit API.','radle-lite'),
                'releasesFetchFail' => __('Failed to fetch releases:','radle-lite'),
                'resetWelcomeConfirm' => __('Are you sure you want to reset the welcome process?','radle-lite'),
                /* translators: %s: Click here text or link */
                'resetWelcomeProcess' => __('To reset the Radle welcome process, %s','radle-lite'),
                'welcomeResetSuccess' => __('Welcome process has been successfully reset.','radle-lite'),
                'welcomeResetFailed' => __('Failed to reset the welcome process.','radle-lite'),
                'welcomeResetError' => __('An error occurred while trying to reset the welcome process.','radle-lite'),
                'visitUserProfile' => __('Visit User Profile','radle-lite'),
                'recentPosts' => __('Recent Posts','radle-lite'),
                'selectSubreddit' => __('Select a subreddit','radle-lite'),
                'mustConnectSubreddit' => __('You must connect to a subreddit.','radle-lite'),
                'loadingEntries' => __('Loading entries...','radle-lite'),
                'noEntriesFound' => __('No entries found.','radle-lite'),
                'failedToLoadEntries' => __('Failed to load entries.','radle-lite'),
                'subredditUpdateFailed' => __('Subreddit selection failed','radle-lite'),
                'clickHere' => __('click here','radle-lite'),
                'joinGbtiNetwork' => __('Join the GBTI Network','radle-lite'),
                'requestCustomizations' => __('Request Customizations','radle-lite'),
                'enjoyingRadle' => __('Enjoying Radle?','radle-lite'),
                'shortcodeLabel' => __('Shortcode:','radle-lite'),
                'shortcodeInstruction' => __('Use this shortcode in your theme templates or page content to display Reddit comments.','radle-lite'),
            ],
            'redditOAuthUrl' => rest_url('radle/v1/reddit/oauth-callback'),
            'pluginUrl' => RADLE_PLUGIN_URL,
        ]);
    }

    /**
     * Reset Reddit authorization.
     * 
     * AJAX handler for clearing Reddit API credentials
     * and resetting the authorization state.
     */
    public function reset_authorization() {
        check_ajax_referer('radle_nonce', '_wpnonce');

        // A valid nonce proves intent, not authorization. Require the capability too,
        // so a lower-privileged logged-in user cannot disconnect the site's Reddit account.
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to do this.','radle-lite')], 403);
        }

        delete_option('radle_reddit_access_token');
        delete_option('radle_reddit_refresh_token');

        wp_send_json_success(['message' => __('Authorization reset successfully.','radle-lite')]);
    }
}