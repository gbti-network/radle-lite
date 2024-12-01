<?php

namespace Radle\Modules\Settings;

class Settings_Container {

    protected $settings_page = 'radle-settings';
    protected $settings_title;
    protected $settings_menu;
    protected $tabs = [];

    public function __construct() {
        add_action('admin_menu', [$this, 'create_settings_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
        add_action('wp_ajax_radle_reset_authorization', [$this, 'reset_authorization']);

        $this->tabs = [
            'overview' => __('Overview', 'radle'),
            'reddit' => __('Reddit Connection', 'radle'),
            'publishing' => __('General Settings', 'radle'),
            'comments' => __('Comment Management', 'radle'),
            'monitoring' => __('Monitoring', 'radle')
        ];

        $this->settings_title = __('Radle Settings','radle');
        $this->settings_menu = __('Radle Settings','radle');
    }

    public function create_settings_page() {
        add_options_page(
            $this->settings_title,
            $this->settings_menu,
            'manage_options',
            $this->settings_page,
            [$this, 'settings_page_content']
        );
    }

    public function settings_page_content() {
        $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';
        ?>
        <div class="wrap">
            <h1><?php echo $this->settings_title; ?></h1>
            <h2 class="nav-tab-wrapper">
                <?php
                foreach ($this->tabs as $tab_key => $tab_caption) {
                    $active = ($current_tab === $tab_key) ? ' nav-tab-active' : '';
                    echo sprintf(
                        '<a href="?page=%s&tab=%s" class="nav-tab%s" data-tab="%s">%s</a>',
                        $this->settings_page,
                        $tab_key,
                        $active,
                        $tab_key,
                        $tab_caption
                    );
                }
                ?>
            </h2>
            <style>
                .radle-settings-sections {
                    position: relative;
                }
                .radle-settings-section {
                    position: absolute;
                    width: 100%;
                    left: 0;
                    top: 0;
                }
                .radle-settings-section.active {
                    position: relative;
                }
            </style>
            <div class="radle-settings-sections">
            <?php
            // Create all tab sections upfront but hide them
            foreach ($this->tabs as $tab_key => $tab_caption) {
                $style = ($current_tab === $tab_key) ? '' : 'style="display: none;"';
                echo '<div id="radle-settings-' . $tab_key . '" class="radle-settings-section ' . ($current_tab === $tab_key ? 'active' : '') . '" ' . $style . '>';
                
                if ($tab_key === 'overview') {
                    $this->render_overview_tab();
                } else {
                    settings_errors();
                    echo '<form method="post" action="options.php">';
                    do_settings_sections($this->settings_page . '-' . $tab_key);
                    settings_fields('radle_settings');
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

    private function render_overview_tab() {
        ?>
        <div class="radle-overview-container">
            <div class="radle-overview-section">
                <h2><?php _e('Welcome to Radle Demo', 'radle'); ?></h2>
                <p><?php _e('Radle integrates Reddit\'s discussion platform into your WordPress site, creating a vibrant community hub where your content and discussions thrive in both ecosystems.', 'radle'); ?></p>
                
                <div class="radle-feature-matrix">
                    <h3><?php _e('Demo Features', 'radle'); ?></h3>
                    <ul class="radle-feature-list">
                        <li>
                            <span class="dashicons dashicons-yes"></span>
                            <?php _e('Reddit-Powered Comments System', 'radle'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-yes"></span>
                            <?php _e('One-Click Content Publishing', 'radle'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-yes"></span>
                            <?php _e('Real-Time Comment Synchronization', 'radle'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-yes"></span>
                            <?php _e('Essential Comment Sorting (Newest, Most Popular, Oldest)', 'radle'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-yes"></span>
                            <?php _e('Basic Comment Threading (2 levels)', 'radle'); ?>
                        </li>
                    </ul>

                    <h3><?php _e('Pro Features (Members Only)', 'radle'); ?></h3>
                    <ul class="radle-feature-list pro-features">
                        <li>
                            <span class="dashicons dashicons-lock"></span>
                            <?php _e('Custom Thread Depth & Expanded Replies', 'radle'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-lock"></span>
                            <?php _e('Advanced Caching Controls', 'radle'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-lock"></span>
                            <?php _e('Comment Search & Advanced Sorting', 'radle'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-lock"></span>
                            <?php _e('User Badges & Flair Support', 'radle'); ?>
                        </li>
                        <li>
                            <span class="dashicons dashicons-lock"></span>
                            <?php _e('Enhanced Moderation Tools', 'radle'); ?>
                        </li>
                    </ul>
                </div>

                <div class="radle-upgrade-cta">
                    <h3><?php _e('Join the GBTI Network', 'radle'); ?></h3>
                    <p><?php 
                        printf(
                            __('<a href="%s" target="_blank">Radle Pro</a> is available to all GBTI Network members. Sponsor our project on GitHub to become a network member and get access to all our premium plugins and tools.', 'radle'),
                            'https://gbti.network/products/radle/'
                        );
                    ?></p>
                    <a href="https://github.com/sponsors/gbti-network" class="button button-primary radle-sponsor-button" target="_blank">
                        <svg class="radle-heart-icon" viewBox="0 0 16 16" width="16" height="16">
                            <path fill-rule="evenodd" d="M4.25 2.5c-1.336 0-2.75 1.164-2.75 3 0 2.15 1.58 4.144 3.365 5.682A20.565 20.565 0 008 13.393a20.561 20.561 0 003.135-2.211C12.92 9.644 14.5 7.65 14.5 5.5c0-1.836-1.414-3-2.75-3-1.373 0-2.609.986-3.029 2.456a.75.75 0 01-1.442 0C6.859 3.486 5.623 2.5 4.25 2.5z"></path>
                        </svg>
                        <?php _e('Become a Sponsor', 'radle'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    public function enqueue_admin_styles($hook) {
        if (strpos($hook, $this->settings_page) === false) {
            return;
        }

        wp_enqueue_style('dashicons');

        wp_enqueue_script(
            'chart-js',
            RADLE_PLUGIN_URL . 'modules/settings/js/chart.min.js',
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
                'minutes' => esc_html__('Minutes', 'radle'),
                'hours' => esc_html__('Hours', 'radle'),
                'daysOfWeek' => esc_html__('Days of the Week', 'radle'),
                'days' => esc_html__('Days', 'radle'),
                'numberOfCalls' => esc_html__('Number of Calls', 'radle'),
                'apiRateLimitData' => esc_html__('API Rate Limit Data', 'radle'),
                'numberOfApiCalls' => esc_html__('Number of API calls', 'radle'),
                'breachesOf90CallsPerMin' => esc_html__('Breaches of 90 calls/min', 'radle'),
                'failedCallsDueToRateLimits' => esc_html__('Failed calls due to rate limits', 'radle'),
                'confirmDeleteData' => esc_html__('Are you sure you want to delete all collected data? This action cannot be undone.', 'radle'),
                'dataDeleted' => esc_html__('All data has been deleted.', 'radle'),
                'errorDeletingData' => esc_html__('An error occurred while deleting the data. Please try again.', 'radle'),
                'breachesAndFailures' => esc_html__('Breaches/Failures.', 'radle'),
            ]
        ]);

        wp_enqueue_style('radle-admin-styles', RADLE_PLUGIN_URL . 'modules/settings/css/settings.css');
        wp_enqueue_script('radle-admin-scripts', RADLE_PLUGIN_URL . 'modules/settings/js/settings.js', ['jquery', 'wp-api' , 'radle-monitoring', 'radle-debug'], RADLE_VERSION, true);
        wp_localize_script('radle-admin-scripts', 'radleSettings', [
            'i18n' => [
                'noCompany' => __('No company', 'radle'),
                'resetAuthorization' => __('Reset Authorization', 'radle'),
                'latestReleases' => __('Latest Releases', 'radle'),
                'updatesDisabled' => __('Plugin Updates are Currently Disabled', 'radle'),
                'sponsorshipRequired' => __('This plugin requires an active GitHub sponsorship to receive updates.', 'radle'),
                'becomeGitHubSponsor' => __('Become a GitHub Sponsor', 'radle'),
                'sponsorCheckError' => __('Unable to verify sponsor status', 'radle'),
                'sponsorCheckErrorDetail' => __('There was an error checking your GitHub sponsor status. Please try again later.', 'radle'),     
                'AuthorizationReset' => __('Authorization has been reset.', 'radle'),
                'redditFailedApiConnection' => __('Failed to connect to the Reddit API.', 'radle'),
                'githubFailedApiConnection' => __('Connect to GBTI Network through GitHub to enable automatic updates', 'radle'),
                'releasesFetchFail' => __('Failed to fetch releases:', 'radle'),
                'resetWelcomeConfirm' => __('Are you sure you want to reset the welcome process?', 'radle'),
                'resetWelcomeProcess' => __('To reset the Radle welcome process, %s', 'radle'),
                'welcomeResetSuccess' => __('Welcome process has been successfully reset.', 'radle'),
                'welcomeResetFailed' => __('Failed to reset the welcome process.', 'radle'),
                'welcomeResetError' => __('An error occurred while trying to reset the welcome process.', 'radle'),
                'visitUserProfile' => __('Visit User Profile', 'radle'),
                'recentPosts' => __('Recent Posts', 'radle'),
                'manageApps' => __('Manage Apps', 'radle'),
                'selectSubreddit' => __('Select a subreddit', 'radle'),
                'mustConnectSubreddit' => __('You must connect to a subreddit.', 'radle'),
                'loadingEntries' => __('Loading entries...', 'radle'),
                'noEntriesFound' => __('No entries found.', 'radle'),
                'failedToLoadEntries' => __('Failed to load entries.', 'radle'),
                'subredditUpdateFailed' => __('Subreddit selection failed', 'radle'),
                'clickHere' => __('click here', 'radle'),
                'raiseIssues' => __('Raise Issues', 'radle'),
                'requestCustomizations' => __('Request Customizations', 'radle'),
                'myGBTIAccount' => __('My GBTI Account', 'radle'),
            ],
            'redditOAuthUrl' => rest_url('radle/v1/reddit/oauth-callback'),
            'pluginUrl' => RADLE_PLUGIN_URL,
            'gbtiServerUri' => RADLE_GBTI_API_SERVER,
            'repoName' => RADLE_GITHUB_REPO,
            'githubToken' => get_option('radle_github_access_token'),
        ]);
    }

    public function reset_authorization() {
        check_ajax_referer('radle_nonce', '_wpnonce');

        delete_option('radle_reddit_access_token');
        delete_option('radle_raddit_refresh_token');

        wp_send_json_success(['message' => __('Authorization reset successfully.', 'radle')]);
    }
}